<?php

namespace App\Services;

use App\Models\AudioResource;
use App\Models\BibleBookmark;
use App\Models\BibleReadingHistory;
use App\Models\Donation;
use App\Models\EventRsvp;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Notification;
use App\Models\OrganizationEvent;
use App\Models\Resource as LibraryResource;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * The single place that assembles everything the authenticated dashboard
 * shows — DashboardController stays a thin pass-through, and no Blade
 * template runs its own module queries. Every query here reuses each
 * module's own authorization-aware scope (Job::visibleTo(),
 * OrganizationEvent::visibleTo(), etc.) exactly as Phase 19's search
 * providers do — the dashboard is a view over those same rules, never a
 * second copy of them.
 */
class DashboardService
{
    private const WIDGET_LIMIT = 5;

    public function __construct(private readonly PreferenceService $preferences)
    {
    }

    public function forUser(User $user): array
    {
        $enabled = array_flip($this->preferences->dashboardSections($user));

        $builders = [
            'notifications' => fn () => $this->notifications($user),
            'conversations' => fn () => $this->conversations($user),
            'organizations' => fn () => $this->organizations($user),
            'upcomingOrgEvents' => fn () => $this->upcomingEvents($user),
            'jobs' => fn () => $this->jobs($user),
            'resources' => fn () => $this->resources($user),
            'audio' => fn () => $this->audio($user),
            'bible' => fn () => $this->bibleActivity($user),
            'giving' => fn () => $this->giving($user),
            'connections' => fn () => $this->connections($user),
        ];

        $result = [];

        foreach ($builders as $key => $build) {
            // A disabled section is never even queried — this is the actual
            // enforcement of the Phase 21 dashboard-sections preference, not
            // just a display toggle in the view.
            $result[$key] = isset($enabled[$key]) ? $build() : null;
        }

        $result['enabledSections'] = array_keys($enabled);

        return $result;
    }

    private function notifications(User $user): array
    {
        return [
            'unreadCount' => $user->unreadNotificationsCount(),
            'recent' => $user->notifications()->limit(self::WIDGET_LIMIT)->get(),
        ];
    }

    private function connections(User $user): array
    {
        return [
            'followersCount' => $user->followers()->count(),
            'followingCount' => $user->following()->count(),
            // Recent followers only — "following" is the user's own already-
            // known list, nothing new to surface about it on the dashboard.
            'recentFollowers' => $user->followers()->with('profile.profilePhoto')
                ->latest('user_connections.created_at')->limit(3)->get(),
        ];
    }

    private function conversations(User $user): array
    {
        $conversations = $user->conversations()
            ->with(['participants.user.profile', 'latestMessage.sender'])
            ->withAggregate('latestMessage as latest_message_at', 'created_at')
            ->orderByDesc('latest_message_at')
            ->limit(self::WIDGET_LIMIT)
            ->get();

        $conversations->each(function ($conversation) use ($user) {
            $conversation->other_participant = $conversation->otherParticipant($user);
        });

        return $conversations->all();
    }

    private function organizations(User $user): array
    {
        $memberOrganizations = $user->organizations()->wherePivot('status', 'active')->get();
        $ownedOrganizations = $user->ownedOrganizations()->get();

        $organizations = $memberOrganizations->merge($ownedOrganizations)->unique('id')->sortBy('name')->values();

        $organizations->each(function ($organization) use ($user) {
            if ($organization->owner_id === $user->id) {
                $organization->dashboard_role = 'Organization Owner';

                return;
            }

            app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
            $organization->dashboard_role = $user->getRoleNames()->first();
        });

        return $organizations->all();
    }

    private function upcomingEvents(User $user): array
    {
        $events = OrganizationEvent::query()
            ->visibleTo($user)
            ->where('starts_at', '>=', now())
            ->with(['organization', 'cover'])
            ->orderBy('starts_at')
            ->limit(self::WIDGET_LIMIT)
            ->get();

        // One extra query for all RSVP states at once, not one per event.
        $rsvpStatuses = EventRsvp::where('user_id', $user->id)
            ->whereIn('event_id', $events->pluck('id'))
            ->pluck('status', 'event_id');

        $events->each(function ($event) use ($rsvpStatuses) {
            $event->my_rsvp_status = $rsvpStatuses[$event->id] ?? null;
        });

        return $events->all();
    }

    private function jobs(User $user): array
    {
        return [
            'discoverable' => Job::query()->visibleTo($user)->with(['organization', 'user'])
                ->latest()->limit(self::WIDGET_LIMIT)->get(),
            'myApplications' => $user->jobApplications()->with(['job.organization', 'job.user'])
                ->latest()->limit(3)->get(),
        ];
    }

    private function resources(User $user): array
    {
        return LibraryResource::query()->visibleTo($user)->with(['cover', 'language', 'organization', 'user'])
            ->latest()->limit(self::WIDGET_LIMIT)->get()->all();
    }

    private function audio(User $user): array
    {
        return AudioResource::query()->visibleTo($user)->with(['cover', 'language', 'organization', 'creatorUser'])
            ->latest()->limit(self::WIDGET_LIMIT)->get()->all();
    }

    private function bibleActivity(User $user): array
    {
        return [
            'recentReading' => BibleReadingHistory::where('user_id', $user->id)->with(['book', 'translation'])
                ->latest('last_read_at')->limit(3)->get(),
            'bookmarks' => BibleBookmark::where('user_id', $user->id)->with(['book', 'translation'])
                ->latest()->limit(3)->get(),
        ];
    }

    private function giving(User $user): array
    {
        // Only fields already safe for display elsewhere (Phase 15's own
        // giving history view) — no provider metadata, no reference/secrets.
        return Donation::where('user_id', $user->id)->with('campaign.organization')
            ->latest()->limit(3)->get()->all();
    }
}
