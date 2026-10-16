@extends('layouts.app')

@section('page-context', 'Dashboard')

@push('styles')
<style>
    /* ---------------------------------------------------------------
       Phase 27.7 — dashboard visual hierarchy.

       Same data, same $enabledSections guards, same queries. What changed
       is weight: a hero, then one prominent "Continue reading" surface,
       then compact stats, then progressively lighter section treatments.
       --------------------------------------------------------------- */

    /* 1. Hero ------------------------------------------------------- */
    .hero {
        position: relative;
        overflow: hidden;
        border: 1px solid var(--line);
        border-radius: var(--radius);
        /* Toned down from the previous 3-stop gradient + gold glow to a
           quiet navy field with a single soft highlight. */
        background: linear-gradient(160deg, var(--navy-deep) 0%, var(--navy) 100%);
        color: #fff;
        padding: 30px 26px 26px;
        box-shadow: var(--shadow-sm);
    }
    .hero::after {
        content: '';
        position: absolute;
        top: -120px;
        right: -80px;
        width: 300px;
        height: 300px;
        border-radius: 999px;
        background: radial-gradient(circle, rgba(251, 188, 4, .16), transparent 70%);
        pointer-events: none;
    }
    .hero-eyebrow {
        margin: 0 0 6px;
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, .58);
    }
    .hero h1 { margin: 0; font-size: 30px; line-height: 1.18; }
    .hero-sub { margin: 8px 0 0; max-width: 54ch; color: rgba(255, 255, 255, .74); font-size: 14px; }

    .hero-actions {
        position: relative;
        z-index: 1;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 20px;
    }
    .hero-actions .btn {
        border-color: rgba(255, 255, 255, .22);
        background: rgba(255, 255, 255, .10);
        color: #fff;
        font-size: 13.5px;
        padding: 9px 14px;
    }
    .hero-actions .btn:hover { border-color: rgba(255, 255, 255, .46); background: rgba(255, 255, 255, .16); }
    .hero-actions .btn-hero-primary {
        border-color: var(--brand);
        background: var(--brand);
        color: #1b1d23;
        font-weight: 700;
    }
    .hero-actions .btn-hero-primary:hover { background: #ffc726; border-color: #ffc726; }

    .hero-search { position: relative; z-index: 1; margin-top: 16px; max-width: 460px; }
    .hero-search input {
        border-color: rgba(255, 255, 255, .24);
        background: rgba(255, 255, 255, .10);
        color: #fff;
    }
    .hero-search input::placeholder { color: rgba(255, 255, 255, .6); }

    /* 2. Continue reading — the single most prominent surface below the hero */
    .continue-card {
        border: 1px solid var(--line);
        border-top: 3px solid var(--accent-bible);
        border-radius: var(--radius);
        background: var(--card);
        padding: 20px;
        box-shadow: var(--shadow-sm);
    }
    .continue-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }
    .continue-head h2 { margin: 0; font-size: 17px; }
    .continue-head .muted { font-size: 12.5px; }
    .continue-list {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }
    .continue-item {
        display: flex;
        align-items: center;
        gap: 11px;
        min-width: 0;
        border: 1px solid var(--line);
        border-radius: var(--radius-sm);
        padding: 11px 12px;
        text-decoration: none;
        color: inherit;
        transition: border-color .15s ease, transform .15s ease;
    }
    .continue-item:hover { border-color: var(--accent-bible); transform: translateY(-1px); }
    .continue-item .c-mark {
        flex: 0 0 auto;
        display: grid;
        place-items: center;
        width: 30px;
        height: 30px;
        border-radius: 9px;
        background: #f2ebfd;
        color: var(--accent-bible);
        font-size: 14px;
    }
    .continue-item .c-body { min-width: 0; }
    .continue-item strong { display: block; font-size: 14px; }
    .continue-item .c-meta {
        display: block;
        color: var(--muted);
        font-size: 11.5px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .continue-empty {
        border: 1px dashed var(--line);
        border-radius: var(--radius-sm);
        padding: 18px;
        color: var(--muted);
        font-size: 14px;
    }
    .continue-foot {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 16px;
        margin-top: 14px;
        color: var(--muted);
        font-size: 12px;
    }
    .continue-foot a { color: var(--accent-bible); font-weight: 600; text-decoration: none; }

    /* 3. Compact stat strip ---------------------------------------- */
    .tiles {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }
    .tile {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        color: inherit;
        transition: transform .15s ease, border-color .15s ease;
    }
    .tile:hover { transform: translateY(-2px); border-color: color-mix(in srgb, var(--brand) 50%, var(--line)); }
    .tile .t-icon {
        flex: 0 0 auto;
        display: inline-grid;
        place-items: center;
        width: 34px;
        height: 34px;
        border-radius: 10px;
        font-size: 15px;
    }
    .tile .t-body { min-width: 0; display: flex; flex-direction: column; }
    .tile .t-label { color: var(--muted); font-size: 12px; font-weight: 600; }
    .tile strong { font-size: 22px; line-height: 1.15; }

    /* 4. Section rhythm -------------------------------------------- */
    .grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }

    .row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        border-top: 1px solid var(--line);
        padding: 9px 0;
        text-decoration: none;
        color: inherit;
        font-size: 14px;
    }
    .row:first-of-type { border-top: none; }
    .row a { text-decoration: none; }

    /* 5. Demoted action list — the remaining destinations, quiet by design */
    .more-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .more-actions .btn {
        border-radius: 999px;
        background: transparent;
        color: var(--muted);
        font-size: 13px;
        font-weight: 600;
        padding: 7px 13px;
    }
    .more-actions .btn:hover { background: var(--card); color: var(--ink); border-color: var(--brand-strong); }

    @media (max-width: 900px) {
        .tiles, .grid-2, .grid-3, .continue-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 560px) {
        .tiles, .grid-2, .grid-3, .continue-list { grid-template-columns: minmax(0, 1fr); }
        .hero { padding: 22px 17px; }
        .hero h1 { font-size: 24px; }
        .continue-card { padding: 16px; }
    }
</style>
@endpush

@section('content')
    @php
        // Real greeting from the real clock and the real signed-in user.
        // Nothing here is fabricated or sampled.
        $lutUser = auth()->user();
        $lutName = $lutUser->profile?->display_name ?? $lutUser->name;
        $lutHour = (int) now()->format('G');
        $lutGreeting = $lutHour < 12 ? 'Good morning' : ($lutHour < 17 ? 'Good afternoon' : 'Good evening');

        // Deep-link the hero's primary action at the most recent chapter the
        // user actually read inside the 24-hour window, when there is one.
        $lutResume = (in_array('bible', $enabledSections) && $bible['recentReading']->isNotEmpty())
            ? $bible['recentReading']->first()
            : null;
    @endphp

    {{-- ---------- Hero ---------- --}}
    <section class="hero">
        <p class="hero-eyebrow">{{ now()->format('l, j F') }}</p>
        <h1>{{ $lutGreeting }}, {{ $lutName }}</h1>
        <p class="hero-sub">Notes, tasks, meetings, hymns, and Bible reading in one installable app.</p>

        <div class="hero-actions">
            @if ($lutResume)
                <a class="btn btn-hero-primary"
                   href="{{ route('bible.index', ['book' => $lutResume->bible_book_id, 'chapter' => $lutResume->chapter, 'translation_id' => $lutResume->translation_id]) }}">
                    {{ __('messages.continue_reading') }} &middot; {{ $lutResume->book?->name }} {{ $lutResume->chapter }}
                </a>
            @else
                <a class="btn btn-hero-primary" href="{{ route('bible.index') }}">Read Bible</a>
            @endif
            <a class="btn" href="{{ route('notes.index') }}">Write note</a>
            <a class="btn" href="{{ route('bible.study') }}">{{ __('messages.bible_study') }}</a>
            <button type="button" class="btn" data-open-ai>&#10022; Ask Lutapp AI</button>
        </div>

        <form class="hero-search" method="get" action="{{ route('search.index') }}">
            <label class="sr-only" for="dash-search" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);">Search Lutapp</label>
            <input id="dash-search" class="input" type="text" name="q" placeholder="Search Lutapp...">
        </form>
    </section>

    {{-- ---------- Continue reading (spiritual life, most prominent) ---------- --}}
    @if (in_array('bible', $enabledSections))
        <div class="section-head">
            <h2>Spiritual life</h2>
            <a href="{{ route('bible.index') }}">Open Bible &rarr;</a>
        </div>
        <section class="continue-card">
            <div class="continue-head">
                <h2>Bible Activity</h2>
                <span class="muted">{{ __('messages.continue_reading') }}</span>
            </div>

            @if ($bible['recentReading']->isNotEmpty())
                <div class="continue-list">
                    @foreach ($bible['recentReading'] as $history)
                        <a class="continue-item"
                           href="{{ route('bible.index', ['book' => $history->bible_book_id, 'chapter' => $history->chapter, 'translation_id' => $history->translation_id]) }}">
                            <span class="c-mark" aria-hidden="true">✝</span>
                            <span class="c-body">
                                <strong>{{ $history->book->name }} {{ $history->chapter }}</strong>
                                <span class="c-meta">
                                    {{ $history->translation->code ?? $history->translation->name }}
                                    @if ($history->last_read_at) &middot; {{ $history->last_read_at->diffForHumans() }} @endif
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="continue-empty">{{ __('messages.nothing_read_recently') }}</p>
            @endif

            <div class="continue-foot">
                <span>{{ __('messages.continue_reading_window') }}</span>
                @if ($bible['bookmarks']->isNotEmpty())
                    <a href="{{ route('bible.bookmarks') }}">View your bookmarks &rarr;</a>
                @endif
            </div>
        </section>
    @endif

    {{-- ---------- Compact stat strip ---------- --}}
    <div class="section-head"><h2>At a glance</h2></div>
    <section class="tiles">
        <a class="tile" href="{{ route('notes.index') }}">
            <span class="t-icon" style="background:var(--navy-soft);color:var(--navy);" aria-hidden="true">✎</span>
            <span class="t-body">
                <span class="t-label">Notes</span>
                <strong>{{ $notesCount }}</strong>
            </span>
        </a>
        <a class="tile" href="{{ route('todos.index') }}">
            <span class="t-icon" style="background:#e7f7ee;color:var(--accent-jobs);" aria-hidden="true">✓</span>
            <span class="t-body">
                <span class="t-label">Open tasks</span>
                <strong>{{ $openTasksCount }}</strong>
            </span>
        </a>
        <a class="tile" href="{{ route('events.index') }}">
            <span class="t-icon" style="background:#fff0e6;color:var(--accent-events);" aria-hidden="true">▤</span>
            <span class="t-body">
                <span class="t-label">Upcoming</span>
                <strong>{{ $upcomingEvents->count() }}</strong>
            </span>
        </a>
        <a class="tile" href="{{ route('hymns.index') }}">
            <span class="t-icon" style="background:#eceafe;color:var(--accent-audio);" aria-hidden="true">♪</span>
            <span class="t-body">
                <span class="t-label">Hymns</span>
                <strong>{{ $hymnsCount }}</strong>
            </span>
        </a>
    </section>

    {{-- ---------- Personal productivity ---------- --}}
    <div class="section-head"><h2>Personal productivity</h2></div>
    <section class="grid-2">
        <x-card title="Upcoming schedule" accent="var(--accent-events)" :link="route('events.index')" link-label="Calendar">
            @forelse ($upcomingEvents as $event)
                <div class="row">
                    <div>
                        <strong>{{ $event->title }}</strong>
                        <div class="muted">{{ ucfirst($event->event_type) }}{{ $event->location ? ' at '.$event->location : '' }}</div>
                    </div>
                    <div class="muted">{{ $event->starts_at->format('M j, g:i A') }}</div>
                </div>
            @empty
                <p class="muted">No upcoming meetings yet.</p>
            @endforelse
        </x-card>

        @if (in_array('notifications', $enabledSections))
            <x-card title="Notifications" accent="var(--accent-alert)" :link="route('notifications.index')">
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
            </x-card>
        @endif
    </section>

    {{-- ---------- Community ---------- --}}
    <div class="section-head"><h2>Community</h2></div>
    <section class="grid-3">
        @if (in_array('conversations', $enabledSections))
            <x-card title="Messages" accent="var(--accent-messages)" :link="route('messages.index')" link-label="View messages">
                @forelse ($conversations as $conversation)
                    @php $other = $conversation->other_participant?->user; @endphp
                    <a class="row" href="{{ route('messages.show', $conversation) }}">
                        <span>{{ $other?->name ?? 'Unknown user' }}</span>
                        <span class="muted">{{ $conversation->latestMessage?->created_at?->diffForHumans() }}</span>
                    </a>
                @empty
                    <p class="muted">No messages yet. <a href="{{ route('messages.search') }}">Start a conversation &rarr;</a></p>
                @endforelse
            </x-card>
        @endif

        @if (in_array('organizations', $enabledSections))
            <x-card title="Your Organizations" accent="var(--accent-community)" :link="route('organizations.index')">
                @forelse ($organizations as $organization)
                    <a class="row" href="{{ route('organizations.show', $organization) }}">
                        <span>{{ $organization->name }}</span>
                        <span class="muted">{{ $organization->dashboard_role ?? ucfirst($organization->type) }}</span>
                    </a>
                @empty
                    <p class="muted">No organizations yet. <a href="{{ route('directory.index') }}">Discover an organization &rarr;</a></p>
                @endforelse
            </x-card>
        @endif

        @if (in_array('connections', $enabledSections))
            <x-card title="Connections" accent="var(--accent-community)">
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
            </x-card>
        @endif
    </section>

    {{-- ---------- Discover ---------- --}}
    <div class="section-head"><h2>Discover</h2></div>
    <section class="grid-3">
        @if (in_array('upcomingOrgEvents', $enabledSections))
            <x-card title="Upcoming Events" accent="var(--accent-events)" :link="route('org-events.discover')" link-label="View events">
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
            </x-card>
        @endif

        @if (in_array('jobs', $enabledSections))
            <x-card title="Jobs" accent="var(--accent-jobs)" :link="route('jobs.index')" link-label="Browse jobs">
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
            </x-card>
        @endif

        @if (in_array('resources', $enabledSections))
            <x-card title="Books &amp; Resources" accent="var(--navy)" :link="route('resources.index')" link-label="Browse">
                @forelse ($resources as $resource)
                    <a class="row" href="{{ route('resources.show', $resource) }}">
                        <span>{{ $resource->title }}</span>
                        <span class="muted">{{ $resource->language?->name }}</span>
                    </a>
                @empty
                    <p class="muted">No recent resources.</p>
                @endforelse
            </x-card>
        @endif

        @if (in_array('audio', $enabledSections))
            <x-card title="Audio" accent="var(--accent-audio)" :link="route('audio.index')" link-label="Browse">
                @forelse ($audio as $item)
                    <a class="row" href="{{ route('audio.show', $item) }}">
                        <span>{{ $item->title }}</span>
                        <span class="muted">{{ $item->creatorDisplayName() }}</span>
                    </a>
                @empty
                    <p class="muted">No recent audio.</p>
                @endforelse
            </x-card>
        @endif

        @if (in_array('giving', $enabledSections))
            <x-card title="Giving" accent="var(--accent-giving)" :link="route('giving.mine')" link-label="View history">
                @forelse ($giving as $donation)
                    <div class="row">
                        <span>{{ $donation->campaign->title }}</span>
                        <span class="muted">{{ ucfirst($donation->status) }}</span>
                    </div>
                @empty
                    <p class="muted">No giving history yet.</p>
                @endforelse
            </x-card>
        @endif
    </section>

    {{-- ---------- Everything else, deliberately demoted ----------
         Every destination from the old flat "Quick actions" row is still
         here; the four the user actually starts from were promoted into the
         hero, and the rest read as quiet pills rather than 13 equal buttons. --}}
    <div class="section-head"><h2>More in Lutapp</h2></div>
    <section class="more-actions">
        <a class="btn" href="{{ route('events.index') }}">Schedule meeting</a>
        <a class="btn" href="{{ route('hymns.index') }}">Add hymn lyrics</a>
        <a class="btn" href="{{ route('search.index') }}">Search Lutapp</a>
        <a class="btn" href="{{ route('messages.index') }}">View Messages</a>
        <a class="btn" href="{{ route('directory.index') }}">Find Organizations</a>
        <a class="btn" href="{{ route('jobs.index') }}">Find Jobs</a>
        <a class="btn" href="{{ route('org-events.discover') }}">Explore Events</a>
        <a class="btn" href="{{ route('resources.index') }}">Browse Resources</a>
        <a class="btn" href="{{ route('audio.index') }}">Browse Audio</a>
        <a class="btn" href="{{ route('giving.mine') }}">Give</a>
        <a class="btn" href="{{ route('profile.show') }}">Manage Profile</a>
    </section>

    <script>
        /* The hero's "Ask Lutapp AI" action simply opens the existing assistant
           panel — it does not call the assistant and never fabricates a reply. */
        document.querySelectorAll('[data-open-ai]').forEach((el) => {
            el.addEventListener('click', () => document.getElementById('ai-fab')?.click());
        });
    </script>
@endsection
