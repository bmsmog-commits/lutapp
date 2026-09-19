<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    // Public files are servable to guests by design; anything else requires
    // authentication and passes through MediaFilePolicy — the physical disk
    // path is never exposed, only this route.
    public function show(Request $request, MediaFile $media): StreamedResponse
    {
        if (! $media->isPublic()) {
            abort_unless($request->user(), 404);
            $this->authorize('view', $media);
        }

        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->response($media->path, $media->original_name);
    }

    public function destroy(Request $request, MediaFile $media, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('delete', $media);

        $storage->delete($media);

        return back()->with('status', 'File deleted.');
    }
}
