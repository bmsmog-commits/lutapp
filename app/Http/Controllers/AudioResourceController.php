<?php

namespace App\Http\Controllers;

use App\Models\AudioResource;
use App\Models\Language;
use App\Models\Organization;
use App\Services\Media\InvalidMediaFileException;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AudioResourceController extends Controller
{
    // Library browsing is open to guests for public audio — scopeVisibleTo()
    // does the actual filtering, and also drives the "My Audio" / "Organization
    // Audio" tabs via the `scope` query param for authenticated users.
    public function index(Request $request): View
    {
        $user = $request->user();
        $scope = $request->query('scope', 'public');

        $query = AudioResource::query()->with(['cover', 'language', 'organization', 'user', 'creatorUser']);

        if ($scope === 'mine' && $user) {
            $query->where('user_id', $user->id);
        } elseif ($scope === 'organization' && $user) {
            $organizationIds = $user->organizations()->wherePivot('status', 'active')->pluck('organizations.id')
                ->merge($user->ownedOrganizations()->pluck('id'));
            $query->whereIn('organization_id', $organizationIds);
        } else {
            $scope = 'public';
            $query->visibleTo($user);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('creator_name', 'like', "%{$search}%")
                    ->orWhereHas('organization', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($languageId = $request->query('language_id')) {
            $query->where('language_id', $languageId);
        }

        if ($organizationId = $request->query('organization_id')) {
            $query->where('organization_id', $organizationId);
        }

        return view('audio.index', [
            'scope' => $scope,
            'resources' => $query->latest()->paginate(12)->withQueryString(),
            'categories' => AudioResource::CATEGORIES,
            'languages' => Language::where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only(['q', 'category', 'language_id', 'organization_id']),
        ]);
    }

    public function create(Request $request, ?Organization $organization = null): View
    {
        $this->authorize('create', [AudioResource::class, $organization]);

        return view('audio.create', [
            'organization' => $organization,
            'categories' => AudioResource::CATEGORIES,
            'languages' => Language::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ?Organization $organization = null): RedirectResponse
    {
        $this->authorize('create', [AudioResource::class, $organization]);

        $data = $this->validateAudio($request);
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['status'] = 'draft';

        if ($organization) {
            $data['organization_id'] = $organization->id;
            $data['visibility'] = $organization->visibility === 'public' ? $data['visibility'] : 'private';
        } else {
            $data['user_id'] = $request->user()->id;
        }

        $audio = AudioResource::create($data);

        return redirect()->route('audio.show', $audio)->with('status', 'Audio saved as a draft.');
    }

    public function show(Request $request, AudioResource $audio, \App\Services\PreferenceService $preferences): View
    {
        $this->authorize('view', $audio);

        return view('audio.show', [
            'audio' => $audio->load(['cover', 'audioMedia', 'language', 'organization', 'user', 'creatorUser', 'collections']),
            'canManage' => $request->user()?->can('update', $audio) ?? false,
            'autoplay' => $request->user() ? $preferences->audio($request->user())['autoplay'] : false,
        ]);
    }

    public function edit(Request $request, AudioResource $audio): View
    {
        $this->authorize('update', $audio);

        return view('audio.edit', [
            'audio' => $audio,
            'categories' => AudioResource::CATEGORIES,
            'languages' => Language::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, AudioResource $audio): RedirectResponse
    {
        $this->authorize('update', $audio);

        $data = $this->validateAudio($request);

        if ($audio->organization_id && $audio->organization->visibility !== 'public') {
            $data['visibility'] = 'private';
        }

        $audio->update($data);
        $audio->syncMediaVisibility();

        return redirect()->route('audio.show', $audio)->with('status', 'Audio updated.');
    }

    public function publish(Request $request, AudioResource $audio): RedirectResponse
    {
        $this->authorize('update', $audio);

        $audio->update(['status' => 'published']);
        $audio->syncMediaVisibility();

        return redirect()->route('audio.show', $audio)->with('status', 'Audio published.');
    }

    public function archive(Request $request, AudioResource $audio): RedirectResponse
    {
        $this->authorize('update', $audio);

        $audio->update(['status' => 'archived']);
        $audio->syncMediaVisibility();

        return redirect()->route('audio.show', $audio)->with('status', 'Audio archived.');
    }

    public function destroy(Request $request, AudioResource $audio, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('delete', $audio);

        foreach (['audioMedia', 'cover'] as $relation) {
            if ($media = $audio->{$relation}) {
                $storage->delete($media);
            }
        }

        $audio->delete();

        return redirect()->route('audio.index')->with('status', 'Audio deleted.');
    }

    public function storeAudioFile(Request $request, AudioResource $audio, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('update', $audio);

        $request->validate([
            'audio' => ['required', 'file', 'mimes:mp3,wav,ogg', 'max:'.config('media.max_size_kb.audio')],
        ]);

        $old = $audio->audioMedia;

        try {
            $owner = $audio->organization_id ? ['organization_id' => $audio->organization_id] : ['user_id' => $audio->user_id];
            $new = $storage->store($request->file('audio'), $owner, $audio->effectiveMediaVisibility());
        } catch (InvalidMediaFileException $e) {
            return back()->withErrors(['audio' => $e->getMessage()]);
        }

        $audio->update(['audio_media_id' => $new->id]);

        if ($old) {
            $storage->delete($old);
        }

        return redirect()->route('audio.show', $audio)->with('status', 'Audio file updated.');
    }

    public function storeCover(Request $request, AudioResource $audio, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('update', $audio);

        $request->validate([
            'cover' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:'.config('media.max_size_kb.image')],
        ]);

        $old = $audio->cover;

        try {
            $owner = $audio->organization_id ? ['organization_id' => $audio->organization_id] : ['user_id' => $audio->user_id];
            $new = $storage->store($request->file('cover'), $owner, $audio->effectiveMediaVisibility());
        } catch (InvalidMediaFileException $e) {
            return back()->withErrors(['cover' => $e->getMessage()]);
        }

        $audio->update(['cover_media_id' => $new->id]);

        if ($old) {
            $storage->delete($old);
        }

        return redirect()->route('audio.show', $audio)->with('status', 'Cover updated.');
    }

    public function destroyCover(Request $request, AudioResource $audio, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('update', $audio);

        if ($audio->cover) {
            $cover = $audio->cover;
            $audio->update(['cover_media_id' => null]);
            $storage->delete($cover);
        }

        return redirect()->route('audio.show', $audio)->with('status', 'Cover removed.');
    }

    private function validateAudio(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'creator_name' => ['nullable', 'string', 'max:255'],
            'creator_user_id' => ['nullable', 'exists:users,id'],
            'category' => ['nullable', Rule::in(AudioResource::CATEGORIES)],
            'language_id' => ['nullable', 'exists:languages,id'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'released_on' => ['nullable', 'date'],
            'visibility' => ['required', Rule::in(AudioResource::VISIBILITIES)],
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'audio';
        $slug = $base;
        $suffix = 1;

        while (AudioResource::where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$suffix);
        }

        return $slug;
    }
}
