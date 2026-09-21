<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\Organization;
use App\Models\Resource;
use App\Services\Media\InvalidMediaFileException;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResourceController extends Controller
{
    // Library browsing is intentionally open to guests for public resources —
    // scopeVisibleTo() does the actual filtering, mirroring how public
    // organizations/logos are guest-browsable elsewhere in the app.
    public function index(Request $request): View
    {
        $query = Resource::query()->visibleTo($request->user())->with(['cover', 'language', 'organization', 'user']);

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")->orWhere('author', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($languageId = $request->query('language_id')) {
            $query->where('language_id', $languageId);
        }

        return view('resources.index', [
            'resources' => $query->latest()->paginate(12)->withQueryString(),
            'categories' => Resource::CATEGORIES,
            'languages' => Language::where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only(['q', 'category', 'language_id']),
        ]);
    }

    public function saved(Request $request): View
    {
        $resources = $request->user()->savedResources()->with(['cover', 'language', 'organization', 'user'])->latest()->paginate(12);

        return view('resources.saved', ['resources' => $resources]);
    }

    public function create(Request $request, ?Organization $organization = null): View
    {
        $this->authorize('create', Resource::class);

        if ($organization) {
            $this->authorize('manageMembers', $organization);
        }

        return view('resources.create', [
            'organization' => $organization,
            'categories' => Resource::CATEGORIES,
            'languages' => Language::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ?Organization $organization = null): RedirectResponse
    {
        $this->authorize('create', Resource::class);

        if ($organization) {
            $this->authorize('manageMembers', $organization);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'published_on' => ['nullable', 'date'],
            'language_id' => ['nullable', 'exists:languages,id'],
            'category' => ['nullable', Rule::in(Resource::CATEGORIES)],
            'visibility' => ['required', Rule::in(Resource::VISIBILITIES)],
        ]);

        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['type'] = 'book';
        $data['status'] = 'draft';

        if ($organization) {
            $data['organization_id'] = $organization->id;
            // A resource can never be more public than its owning organization —
            // same ceiling rule already established for organization logos.
            $data['visibility'] = $organization->visibility === 'public' ? $data['visibility'] : 'private';
        } else {
            $data['user_id'] = $request->user()->id;
        }

        $resource = Resource::create($data);

        return redirect()->route('resources.show', $resource)->with('status', 'Resource created as a draft.');
    }

    public function show(Request $request, Resource $resource): View
    {
        $this->authorize('view', $resource);

        return view('resources.show', [
            'resource' => $resource->load(['cover', 'file', 'language', 'organization', 'user']),
            'canManage' => $request->user()?->can('update', $resource) ?? false,
            'isSaved' => $request->user()?->savedResources()->where('resource_id', $resource->id)->exists() ?? false,
        ]);
    }

    public function edit(Request $request, Resource $resource): View
    {
        $this->authorize('update', $resource);

        return view('resources.edit', [
            'resource' => $resource,
            'categories' => Resource::CATEGORIES,
            'languages' => Language::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Resource $resource): RedirectResponse
    {
        $this->authorize('update', $resource);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'published_on' => ['nullable', 'date'],
            'language_id' => ['nullable', 'exists:languages,id'],
            'category' => ['nullable', Rule::in(Resource::CATEGORIES)],
            'visibility' => ['required', Rule::in(Resource::VISIBILITIES)],
        ]);

        if ($resource->organization_id && $resource->organization->visibility !== 'public') {
            $data['visibility'] = 'private';
        }

        $resource->update($data);
        $resource->syncMediaVisibility();

        return redirect()->route('resources.show', $resource)->with('status', 'Resource updated.');
    }

    public function publish(Request $request, Resource $resource): RedirectResponse
    {
        $this->authorize('update', $resource);

        $resource->update(['status' => 'published']);
        $resource->syncMediaVisibility();

        return redirect()->route('resources.show', $resource)->with('status', 'Resource published.');
    }

    public function archive(Request $request, Resource $resource): RedirectResponse
    {
        $this->authorize('update', $resource);

        $resource->update(['status' => 'archived']);
        $resource->syncMediaVisibility();

        return redirect()->route('resources.show', $resource)->with('status', 'Resource archived.');
    }

    public function destroy(Request $request, Resource $resource, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('delete', $resource);

        foreach (['cover', 'file'] as $relation) {
            if ($media = $resource->{$relation}) {
                $storage->delete($media);
            }
        }

        $resource->delete();

        return redirect()->route('resources.index')->with('status', 'Resource deleted.');
    }

    public function storeCover(Request $request, Resource $resource, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('update', $resource);

        $request->validate([
            'cover' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:'.config('media.max_size_kb.image')],
        ]);

        $old = $resource->cover;

        try {
            $owner = $resource->organization_id ? ['organization_id' => $resource->organization_id] : ['user_id' => $resource->user_id];
            $new = $storage->store($request->file('cover'), $owner, $resource->effectiveMediaVisibility());
        } catch (InvalidMediaFileException $e) {
            return back()->withErrors(['cover' => $e->getMessage()]);
        }

        $resource->update(['cover_media_id' => $new->id]);

        if ($old) {
            $storage->delete($old);
        }

        return redirect()->route('resources.show', $resource)->with('status', 'Cover updated.');
    }

    public function storeFile(Request $request, Resource $resource, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('update', $resource);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,epub', 'max:'.config('media.max_size_kb.document')],
        ]);

        $old = $resource->file;

        try {
            $owner = $resource->organization_id ? ['organization_id' => $resource->organization_id] : ['user_id' => $resource->user_id];
            $new = $storage->store($request->file('file'), $owner, $resource->effectiveMediaVisibility());
        } catch (InvalidMediaFileException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $resource->update(['file_media_id' => $new->id]);

        if ($old) {
            $storage->delete($old);
        }

        return redirect()->route('resources.show', $resource)->with('status', 'Book file updated.');
    }

    public function save(Request $request, Resource $resource): RedirectResponse
    {
        $this->authorize('view', $resource);

        $request->user()->savedResources()->syncWithoutDetaching([$resource->id]);

        return back()->with('status', 'Resource saved.');
    }

    public function unsave(Request $request, Resource $resource): RedirectResponse
    {
        $request->user()->savedResources()->detach($resource->id);

        return back()->with('status', 'Resource removed from saved list.');
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'resource';
        $slug = $base;
        $suffix = 1;

        while (Resource::where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$suffix);
        }

        return $slug;
    }
}
