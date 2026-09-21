<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Organization;
use App\Models\OrganizationEvent;
use App\Services\Media\InvalidMediaFileException;
use App\Services\Media\MediaStorageService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationEventController extends Controller
{
    // Public cross-organization discovery — guests may browse published
    // public events, mirroring the resource library / job board / directory.
    public function discover(Request $request): View
    {
        $query = OrganizationEvent::query()->visibleTo($request->user())->with(['organization', 'cover']);

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('organization', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($locationMode = $request->query('location_mode')) {
            $query->where('location_mode', $locationMode);
        }

        foreach (['country', 'state', 'city'] as $field) {
            if ($value = $request->query($field)) {
                $query->where($field, $value);
            }
        }

        if ($date = $request->query('date')) {
            $query->whereDate('starts_at', $date);
        }

        return view('org-events.discover', [
            'events' => $query->orderBy('starts_at')->paginate(12)->withQueryString(),
            'categories' => OrganizationEvent::CATEGORIES,
            'locationModes' => OrganizationEvent::LOCATION_MODES,
            'filters' => $request->only(['q', 'category', 'location_mode', 'country', 'state', 'city', 'date']),
        ]);
    }

    // The organization's own management list — every status, not just published.
    public function index(Request $request, Organization $organization): View
    {
        $this->authorize('viewAny', OrganizationEvent::class);

        $events = $organization->events()->with('cover')->withCount(['rsvps as attending_count' => fn ($q) => $q->where('status', 'attending')])
            ->orderByDesc('starts_at')->paginate(12);

        return view('org-events.index', ['organization' => $organization, 'events' => $events]);
    }

    public function create(Request $request, Organization $organization): View
    {
        $this->authorize('create', [OrganizationEvent::class, $organization]);

        return view('org-events.create', [
            'organization' => $organization,
            'categories' => OrganizationEvent::CATEGORIES,
            'locationModes' => OrganizationEvent::LOCATION_MODES,
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('create', [OrganizationEvent::class, $organization]);

        $data = $this->validateEvent($request);
        $data['organization_id'] = $organization->id;
        $data['creator_id'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['status'] = 'draft';

        $event = OrganizationEvent::create($data);

        return redirect()->route('org-events.show', $event)->with('status', 'Event saved as a draft.');
    }

    public function show(Request $request, OrganizationEvent $event): View
    {
        $this->authorize('view', $event);

        $user = $request->user();

        return view('org-events.show', [
            'event' => $event->load(['organization', 'cover']),
            'canManage' => $user?->can('update', $event) ?? false,
            'myRsvp' => $user ? $event->rsvps()->where('user_id', $user->id)->first() : null,
            'attendingCount' => $event->attendingCount(),
            'onlineUrl' => $event->publicOnlineUrl($user),
        ]);
    }

    public function edit(Request $request, OrganizationEvent $event): View
    {
        $this->authorize('update', $event);

        return view('org-events.edit', [
            'event' => $event,
            'categories' => OrganizationEvent::CATEGORIES,
            'locationModes' => OrganizationEvent::LOCATION_MODES,
        ]);
    }

    public function update(Request $request, OrganizationEvent $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->update($this->validateEvent($request));
        $event->syncMediaVisibility();

        return redirect()->route('org-events.show', $event)->with('status', 'Event updated.');
    }

    public function publish(Request $request, OrganizationEvent $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->update(['status' => 'published']);
        $event->syncMediaVisibility();

        return redirect()->route('org-events.show', $event)->with('status', 'Event published.');
    }

    public function cancel(Request $request, OrganizationEvent $event, NotificationService $notifications): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->update(['status' => 'cancelled']);
        $event->syncMediaVisibility();

        $attendees = $event->rsvps()->where('status', 'attending')->with('user')->get();

        foreach ($attendees as $rsvp) {
            $notifications->notify(
                recipient: $rsvp->user,
                type: 'event.cancelled',
                title: '"'.$event->title.'" has been cancelled',
                actor: $request->user(),
                organization: $event->organization,
                relatedType: Notification::RELATED_ORGANIZATION_EVENT,
                relatedId: $event->id,
            );
        }

        return redirect()->route('org-events.show', $event)->with('status', 'Event cancelled.');
    }

    public function complete(Request $request, OrganizationEvent $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->update(['status' => 'completed']);
        $event->syncMediaVisibility();

        return redirect()->route('org-events.show', $event)->with('status', 'Event marked completed.');
    }

    public function destroy(Request $request, OrganizationEvent $event, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('delete', $event);

        if ($event->cover) {
            $storage->delete($event->cover);
        }

        $event->delete();

        return redirect()->route('org-events.index', $event->organization)->with('status', 'Event deleted.');
    }

    public function storeCover(Request $request, OrganizationEvent $event, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('update', $event);

        $request->validate([
            'cover' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:'.config('media.max_size_kb.image')],
        ]);

        $old = $event->cover;

        try {
            $new = $storage->store($request->file('cover'), ['organization_id' => $event->organization_id], $event->effectiveMediaVisibility());
        } catch (InvalidMediaFileException $e) {
            return back()->withErrors(['cover' => $e->getMessage()]);
        }

        $event->update(['cover_media_id' => $new->id]);

        if ($old) {
            $storage->delete($old);
        }

        return redirect()->route('org-events.show', $event)->with('status', 'Cover updated.');
    }

    public function destroyCover(Request $request, OrganizationEvent $event, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('update', $event);

        if ($event->cover) {
            $cover = $event->cover;
            $event->update(['cover_media_id' => null]);
            $storage->delete($cover);
        }

        return redirect()->route('org-events.show', $event)->with('status', 'Cover removed.');
    }

    public function attendance(Request $request, OrganizationEvent $event): View
    {
        $this->authorize('viewAttendance', $event);

        $rsvps = $event->rsvps()->with('user.profile')->where('status', 'attending')->latest()->paginate(30);

        return view('org-events.attendance', ['event' => $event, 'rsvps' => $rsvps]);
    }

    private function validateEvent(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', Rule::in(OrganizationEvent::CATEGORIES)],
            'visibility' => ['required', Rule::in(OrganizationEvent::VISIBILITIES)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['nullable', 'timezone'],
            'location_mode' => ['required', Rule::in(OrganizationEvent::LOCATION_MODES)],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'online_url' => ['nullable', 'url', 'max:255', 'required_if:location_mode,online,hybrid'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($data['location_mode'] === 'online') {
            $data['country'] = $data['state'] = $data['city'] = $data['address'] = null;
            $data['latitude'] = $data['longitude'] = null;
        }

        return $data;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $suffix = 1;

        while (OrganizationEvent::where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$suffix);
        }

        return $slug;
    }
}
