@extends('layouts.app')

@push('styles')
<style>
    .hero {
        display: grid;
        gap: 8px;
        margin-bottom: 22px;
    }
    .hero h1 { margin: 0; font-size: 30px; }
    .tiles {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 24px;
    }
    .tile, .panel {
        border: 1px solid var(--line);
        border-radius: 8px;
        background: #fff;
        padding: 16px;
    }
    .tile {
        text-decoration: none;
    }
    .tile strong {
        display: block;
        margin-top: 12px;
        font-size: 28px;
    }
    .workspace-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .panel h2 { margin: 0 0 12px; font-size: 20px; }
    .event-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        border-top: 1px solid var(--line);
        padding: 12px 0;
    }
    @media (max-width: 850px) {
        .tiles, .workspace-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 560px) {
        .tiles, .workspace-grid { grid-template-columns: 1fr; }
    }
    .lutapp-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-top: 24px;
    }
    .lutapp-grid .panel h2 { display: flex; justify-content: space-between; align-items: center; }
    .lutapp-grid .panel h2 a { font-size: 13px; font-weight: 400; }
    .row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        border-top: 1px solid var(--line);
        padding: 10px 0;
        text-decoration: none;
        color: inherit;
    }
    .row:first-of-type { border-top: none; }
    @media (max-width: 850px) {
        .lutapp-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
    <section class="hero">
        <h1>Church workspace</h1>
        <p class="muted">Notes, tasks, meetings, hymns, and Bible reading in one installable app.</p>
    </section>

    <section class="tiles">
        <a class="tile" href="{{ route('notes.index') }}">Notes <strong>{{ $notesCount }}</strong></a>
        <a class="tile" href="{{ route('todos.index') }}">Open tasks <strong>{{ $openTasksCount }}</strong></a>
        <a class="tile" href="{{ route('events.index') }}">Upcoming <strong>{{ $upcomingEvents->count() }}</strong></a>
        <a class="tile" href="{{ route('hymns.index') }}">Hymns <strong>{{ $hymnsCount }}</strong></a>
    </section>

    <section class="workspace-grid">
        <div class="panel">
            <h2>Upcoming schedule</h2>
            @forelse ($upcomingEvents as $event)
                <div class="event-row">
                    <div>
                        <strong>{{ $event->title }}</strong>
                        <div class="muted">{{ ucfirst($event->event_type) }}{{ $event->location ? ' at '.$event->location : '' }}</div>
                    </div>
                    <div class="muted">{{ $event->starts_at->format('M j, g:i A') }}</div>
                </div>
            @empty
                <p class="muted">No upcoming meetings yet.</p>
            @endforelse
        </div>
        <div class="panel">
            <h2>Fast actions</h2>
            <p><a class="btn" href="{{ route('notes.index') }}">Write note</a></p>
            <p><a class="btn" href="{{ route('events.index') }}">Schedule meeting</a></p>
            <p><a class="btn" href="{{ route('hymns.index') }}">Add hymn lyrics</a></p>
            <p><a class="btn" href="{{ route('bible.index') }}">Open Bible</a></p>
        </div>
    </section>

    <section style="margin-top:32px;">
        <h1 style="font-size:24px;margin-bottom:4px;">Welcome, {{ auth()->user()->profile?->display_name ?? auth()->user()->name }}</h1>
        <form method="get" action="{{ route('search.index') }}" style="margin:12px 0 24px;">
            <input class="input" type="text" name="q" placeholder="Search Lutapp..." style="max-width:400px;">
        </form>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
            <a class="btn" href="{{ route('bible.index') }}">Read Bible</a>
            <a class="btn" href="{{ route('search.index') }}">Search Lutapp</a>
            <a class="btn" href="{{ route('messages.index') }}">View Messages</a>
            <a class="btn" href="{{ route('directory.index') }}">Find Organizations</a>
            <a class="btn" href="{{ route('jobs.index') }}">Find Jobs</a>
            <a class="btn" href="{{ route('org-events.discover') }}">Explore Events</a>
            <a class="btn" href="{{ route('resources.index') }}">Browse Resources</a>
            <a class="btn" href="{{ route('audio.index') }}">Browse Audio</a>
            <a class="btn" href="{{ route('giving.mine') }}">Give</a>
            <a class="btn" href="{{ route('profile.show') }}">Manage Profile</a>
        </div>

        <div class="lutapp-grid">
            @if (in_array('notifications', $enabledSections))
            <div class="panel">
                <h2>Notifications <a href="{{ route('notifications.index') }}">View all &rarr;</a></h2>
                @if ($notifications['unreadCount'] > 0)
                    <p class="muted">{{ $notifications['unreadCount'] }} unread</p>
                @endif
                @forelse ($notifications['recent'] as $notification)
                    <div class="row">
                        <span>{{ $notification->title }}</span>
                        <span class="muted">{{ $notification->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="muted">No notifications yet.</p>
                @endforelse
            </div>
            @endif

            @if (in_array('conversations', $enabledSections))
            <div class="panel">
                <h2>Messages <a href="{{ route('messages.index') }}">View messages &rarr;</a></h2>
                @forelse ($conversations as $conversation)
                    @php $other = $conversation->other_participant?->user; @endphp
                    <a class="row" href="{{ route('messages.show', $conversation) }}">
                        <span>{{ $other?->name ?? 'Unknown user' }}</span>
                        <span class="muted">{{ $conversation->latestMessage?->created_at?->diffForHumans() }}</span>
                    </a>
                @empty
                    <p class="muted">No messages yet. <a href="{{ route('messages.search') }}">Start a conversation &rarr;</a></p>
                @endforelse
            </div>
            @endif

            @if (in_array('organizations', $enabledSections))
            <div class="panel">
                <h2>Your Organizations <a href="{{ route('organizations.index') }}">View all &rarr;</a></h2>
                @forelse ($organizations as $organization)
                    <a class="row" href="{{ route('organizations.show', $organization) }}">
                        <span>{{ $organization->name }}</span>
                        <span class="muted">{{ $organization->dashboard_role ?? ucfirst($organization->type) }}</span>
                    </a>
                @empty
                    <p class="muted">No organizations yet. <a href="{{ route('directory.index') }}">Discover an organization &rarr;</a></p>
                @endforelse
            </div>
            @endif

            @if (in_array('upcomingOrgEvents', $enabledSections))
            <div class="panel">
                <h2>Upcoming Events <a href="{{ route('org-events.discover') }}">View events &rarr;</a></h2>
                @forelse ($upcomingOrgEvents as $event)
                    <a class="row" href="{{ route('org-events.show', $event) }}">
                        <span>{{ $event->title }}<br><span class="muted">{{ $event->organization->name }}</span></span>
                        <span class="muted">
                            {{ $event->starts_at->format('M j, g:i A') }}
                            @if ($event->my_rsvp_status === 'attending')<br>Attending @endif
                        </span>
                    </a>
                @empty
                    <p class="muted">No upcoming events. <a href="{{ route('org-events.discover') }}">Explore events &rarr;</a></p>
                @endforelse
            </div>
            @endif

            @if (in_array('jobs', $enabledSections))
            <div class="panel">
                <h2>Jobs <a href="{{ route('jobs.index') }}">Browse jobs &rarr;</a></h2>
                @forelse ($jobs['discoverable'] as $job)
                    <a class="row" href="{{ route('jobs.show', $job) }}">
                        <span>{{ $job->title }}</span>
                        <span class="muted">{{ $job->organization?->name ?? $job->user?->name }}</span>
                    </a>
                @empty
                    <p class="muted">No jobs found. <a href="{{ route('jobs.index') }}">Find jobs &rarr;</a></p>
                @endforelse
                @if ($jobs['myApplications']->isNotEmpty())
                    <p class="muted" style="margin-top:8px;"><a href="{{ route('jobs.applications.mine') }}">View your {{ $jobs['myApplications']->count() }} recent application(s) &rarr;</a></p>
                @endif
            </div>
            @endif

            @if (in_array('resources', $enabledSections))
            <div class="panel">
                <h2>Books &amp; Resources <a href="{{ route('resources.index') }}">Browse &rarr;</a></h2>
                @forelse ($resources as $resource)
                    <a class="row" href="{{ route('resources.show', $resource) }}">
                        <span>{{ $resource->title }}</span>
                        <span class="muted">{{ $resource->language?->name }}</span>
                    </a>
                @empty
                    <p class="muted">No recent resources.</p>
                @endforelse
            </div>
            @endif

            @if (in_array('audio', $enabledSections))
            <div class="panel">
                <h2>Audio <a href="{{ route('audio.index') }}">Browse &rarr;</a></h2>
                @forelse ($audio as $item)
                    <a class="row" href="{{ route('audio.show', $item) }}">
                        <span>{{ $item->title }}</span>
                        <span class="muted">{{ $item->creatorDisplayName() }}</span>
                    </a>
                @empty
                    <p class="muted">No recent audio.</p>
                @endforelse
            </div>
            @endif

            @if (in_array('bible', $enabledSections))
            <div class="panel">
                <h2>Bible Activity <a href="{{ route('bible.index') }}">Open Bible &rarr;</a></h2>
                @forelse ($bible['recentReading'] as $history)
                    <div class="row">
                        <span>{{ $history->book->name }} {{ $history->chapter }}</span>
                        <span class="muted">{{ $history->translation->code ?? $history->translation->name }}</span>
                    </div>
                @empty
                    <p class="muted">No recent Bible reading yet.</p>
                @endforelse
                @if ($bible['bookmarks']->isNotEmpty())
                    <p class="muted" style="margin-top:8px;"><a href="{{ route('bible.bookmarks') }}">View your bookmarks &rarr;</a></p>
                @endif
            </div>
            @endif

            @if (in_array('giving', $enabledSections))
            <div class="panel">
                <h2>Giving <a href="{{ route('giving.mine') }}">View history &rarr;</a></h2>
                @forelse ($giving as $donation)
                    <div class="row">
                        <span>{{ $donation->campaign->title }}</span>
                        <span class="muted">{{ ucfirst($donation->status) }}</span>
                    </div>
                @empty
                    <p class="muted">No giving history yet.</p>
                @endforelse
            </div>
            @endif

            @if (in_array('connections', $enabledSections))
            <div class="panel">
                <h2>Connections</h2>
                <p class="muted">{{ $connections['followersCount'] }} followers &middot; {{ $connections['followingCount'] }} following</p>
                @forelse ($connections['recentFollowers'] as $follower)
                    @php $followerProfile = $follower->profile; @endphp
                    <div class="row">
                        <span>{{ $followerProfile?->display_name ?? $follower->name }}</span>
                        <span class="muted">started following you</span>
                    </div>
                @empty
                    <p class="muted">No followers yet.</p>
                @endforelse
                @if (auth()->user()->profile)
                    <p class="muted" style="margin-top:8px;"><a href="{{ route('users.show', auth()->user()->profile->username) }}">View your profile &rarr;</a></p>
                @endif
            </div>
            @endif
        </div>
    </section>
@endsection
