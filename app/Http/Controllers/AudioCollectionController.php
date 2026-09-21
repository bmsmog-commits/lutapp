<?php

namespace App\Http\Controllers;

use App\Models\AudioCollection;
use App\Models\AudioResource;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AudioCollectionController extends Controller
{
    public function index(Request $request): View
    {
        $collections = AudioCollection::query()->visibleTo($request->user())->with(['cover', 'organization', 'user'])
            ->latest()->paginate(12);

        return view('audio.collections.index', ['collections' => $collections, 'types' => AudioCollection::TYPES]);
    }

    public function create(Request $request, ?Organization $organization = null): View
    {
        $this->authorize('create', [AudioCollection::class, $organization]);

        return view('audio.collections.create', ['organization' => $organization, 'types' => AudioCollection::TYPES]);
    }

    public function store(Request $request, ?Organization $organization = null): RedirectResponse
    {
        $this->authorize('create', [AudioCollection::class, $organization]);

        $data = $this->validateCollection($request);
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['status'] = 'draft';

        if ($organization) {
            $data['organization_id'] = $organization->id;
            $data['visibility'] = $organization->visibility === 'public' ? $data['visibility'] : 'private';
        } else {
            $data['user_id'] = $request->user()->id;
        }

        $collection = AudioCollection::create($data);

        return redirect()->route('audio.collections.show', $collection)->with('status', 'Collection saved as a draft.');
    }

    public function show(Request $request, AudioCollection $collection): View
    {
        $this->authorize('view', $collection);

        return view('audio.collections.show', [
            'collection' => $collection->load(['cover', 'organization', 'user', 'items']),
            'canManage' => $request->user()?->can('update', $collection) ?? false,
        ]);
    }

    public function edit(Request $request, AudioCollection $collection): View
    {
        $this->authorize('update', $collection);

        return view('audio.collections.edit', ['collection' => $collection, 'types' => AudioCollection::TYPES]);
    }

    public function update(Request $request, AudioCollection $collection): RedirectResponse
    {
        $this->authorize('update', $collection);

        $data = $this->validateCollection($request);

        if ($collection->organization_id && $collection->organization->visibility !== 'public') {
            $data['visibility'] = 'private';
        }

        $collection->update($data);

        return redirect()->route('audio.collections.show', $collection)->with('status', 'Collection updated.');
    }

    public function publish(Request $request, AudioCollection $collection): RedirectResponse
    {
        $this->authorize('update', $collection);
        $collection->update(['status' => 'published']);

        return redirect()->route('audio.collections.show', $collection)->with('status', 'Collection published.');
    }

    public function archive(Request $request, AudioCollection $collection): RedirectResponse
    {
        $this->authorize('update', $collection);
        $collection->update(['status' => 'archived']);

        return redirect()->route('audio.collections.show', $collection)->with('status', 'Collection archived.');
    }

    public function destroy(Request $request, AudioCollection $collection): RedirectResponse
    {
        $this->authorize('delete', $collection);
        $collection->delete();

        return redirect()->route('audio.collections.index')->with('status', 'Collection deleted.');
    }

    public function addItem(Request $request, AudioCollection $collection): RedirectResponse
    {
        $this->authorize('manageItems', $collection);

        $data = $request->validate(['audio_resource_id' => ['required', 'exists:audio_resources,id']]);
        $audio = AudioResource::findOrFail($data['audio_resource_id']);

        // The item being attached must be manageable by the same actor too —
        // otherwise a collection manager could pull another owner's private
        // audio into their collection just by knowing its ID.
        $this->authorize('update', $audio);

        $collection->addItem($audio);

        return redirect()->route('audio.collections.show', $collection)->with('status', 'Audio added to collection.');
    }

    public function removeItem(Request $request, AudioCollection $collection, AudioResource $audio): RedirectResponse
    {
        $this->authorize('manageItems', $collection);

        $collection->removeItem($audio);

        return redirect()->route('audio.collections.show', $collection)->with('status', 'Audio removed from collection.');
    }

    public function reorder(Request $request, AudioCollection $collection): RedirectResponse
    {
        $this->authorize('manageItems', $collection);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:audio_resources,id'],
        ]);

        // Reject an order list that doesn't exactly match this collection's
        // current membership — a partial/foreign list must not silently drop
        // or smuggle items in via the reorder endpoint.
        $currentIds = $collection->items()->pluck('audio_resources.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $requestedIds = collect($data['order'])->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($currentIds !== $requestedIds) {
            return back()->withErrors(['order' => 'The submitted order does not match this collection\'s current items.']);
        }

        $collection->reorder($data['order']);

        return redirect()->route('audio.collections.show', $collection)->with('status', 'Collection reordered.');
    }

    private function validateCollection(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in(AudioCollection::TYPES)],
            'visibility' => ['required', Rule::in(AudioCollection::VISIBILITIES)],
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'collection';
        $slug = $base;
        $suffix = 1;

        while (AudioCollection::where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$suffix);
        }

        return $slug;
    }
}
