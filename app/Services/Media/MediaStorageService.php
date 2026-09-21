<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Phase 8 storage foundation. All uploads and deletions for personal or
 * organization-owned files go through here so validation, safe naming, and
 * disk selection stay consistent regardless of which future feature calls it.
 */
class MediaStorageService
{
    /**
     * @param  array{user_id?: int, organization_id?: int}  $owner  exactly one key must be set
     */
    public function store(UploadedFile $file, array $owner, string $visibility = 'private'): MediaFile
    {
        $this->assertSingleOwner($owner);
        $this->assertSafeExtension($file);
        $category = $this->resolveCategory($file);
        $this->assertWithinSizeLimit($file, $category);

        $disk = $visibility === 'public' ? 'public' : 'local';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $storedName = Str::uuid()->toString().'.'.$extension;
        $directory = $this->buildDirectory($owner, $category);
        $checksum = hash_file('sha256', $file->getRealPath());

        $path = $file->storeAs($directory, $storedName, $disk);

        if ($path === false) {
            throw new InvalidMediaFileException('Failed to store the uploaded file.');
        }

        try {
            return MediaFile::create(array_merge($owner, [
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'extension' => $extension,
                'size' => $file->getSize(),
                'visibility' => $visibility,
                'category' => $category,
                'checksum' => $checksum,
            ]));
        } catch (Throwable $e) {
            // The DB row and the physical file are not one atomic transaction —
            // if metadata creation fails after the file is already written, the
            // orphaned physical file is cleaned up immediately rather than left behind.
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }

    /**
     * Moves a media file between the public and private disks to match a new
     * visibility, so a visibility change actually revokes/grants access rather
     * than leaving bytes reachable at their old, now-incorrect disk/URL (Phase 10:
     * flipping only the `visibility` column would be meaningless for a file
     * still sitting under the web-reachable `public` disk).
     */
    public function changeVisibility(MediaFile $media, string $visibility): MediaFile
    {
        if ($media->visibility === $visibility) {
            return $media;
        }

        $newDisk = $visibility === 'public' ? 'public' : 'local';
        $oldDisk = $media->disk;
        $path = $media->path;

        if ($newDisk === $oldDisk) {
            $media->update(['visibility' => $visibility]);

            return $media;
        }

        if (! Storage::disk($oldDisk)->exists($path)) {
            // Nothing to move — the physical file is already gone. Update the
            // metadata so it at least stops being reported as public, and log
            // the inconsistency rather than silently pretending nothing is wrong.
            Log::warning("MediaStorageService: cannot move missing physical file for media #{$media->id} during visibility change.");
            $media->update(['visibility' => $visibility]);

            return $media;
        }

        Storage::disk($newDisk)->put($path, Storage::disk($oldDisk)->get($path));
        Storage::disk($oldDisk)->delete($path);

        $media->update(['disk' => $newDisk, 'visibility' => $visibility]);

        return $media;
    }

    public function delete(MediaFile $media): void
    {
        try {
            if (Storage::disk($media->disk)->exists($media->path)) {
                Storage::disk($media->disk)->delete($media->path);
            }
        } catch (Throwable $e) {
            // A failed physical delete must not be reported as success — log it and
            // still remove the metadata row so the resource no longer appears active,
            // rather than silently pretending the file is gone.
            Log::warning("MediaStorageService: failed to delete physical file for media #{$media->id}: {$e->getMessage()}");
        }

        $media->delete();
    }

    private function resolveCategory(UploadedFile $file): string
    {
        // getMimeType() uses PHP's fileinfo extension against the actual file
        // contents — it is never the client-supplied Content-Type header.
        $mime = $file->getMimeType();

        foreach (config('media.allowed_mimes') as $category => $mimes) {
            if (in_array($mime, $mimes, true)) {
                return $category;
            }
        }

        throw new InvalidMediaFileException("File type not allowed: {$mime}");
    }

    private function assertSafeExtension(UploadedFile $file): void
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (in_array($extension, config('media.blocked_extensions'), true)) {
            throw new InvalidMediaFileException("File extension not allowed: .{$extension}");
        }
    }

    private function assertWithinSizeLimit(UploadedFile $file, string $category): void
    {
        $maxKb = config("media.max_size_kb.{$category}", config('media.max_size_kb.other'));

        if ($file->getSize() > $maxKb * 1024) {
            throw new InvalidMediaFileException('File exceeds the maximum allowed size for this category.');
        }
    }

    private function assertSingleOwner(array $owner): void
    {
        $hasUser = ! empty($owner['user_id'] ?? null);
        $hasOrganization = ! empty($owner['organization_id'] ?? null);

        if ($hasUser === $hasOrganization) {
            throw new InvalidArgumentException('A media file must belong to exactly one of user_id or organization_id.');
        }
    }

    private function buildDirectory(array $owner, string $category): string
    {
        if (! empty($owner['user_id'])) {
            return "users/{$owner['user_id']}/{$category}";
        }

        return "organizations/{$owner['organization_id']}/{$category}";
    }
}
