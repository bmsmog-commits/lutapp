@php
    /**
     * Lutapp global navigation (Phase 27.5).
     *
     * Every destination that used to live in the old horizontal top nav is
     * present here — nothing was removed, only regrouped.
     */
    $navGroups = [
        'Home' => [
            ['route' => 'dashboard', 'label' => __('messages.dashboard'), 'icon' => '⌂', 'active' => 'dashboard', 'accent' => 'var(--brand-strong)', 'bg' => 'var(--brand-soft)'],
            ['route' => 'search.index', 'label' => __('messages.search'), 'icon' => '⌕', 'active' => 'search.*', 'accent' => 'var(--navy)', 'bg' => 'var(--navy-soft)'],
        ],
        'Personal' => [
            ['route' => 'notes.index', 'label' => __('messages.notes'), 'icon' => '✎', 'active' => 'notes.*', 'accent' => 'var(--navy)', 'bg' => 'var(--navy-soft)'],
            ['route' => 'todos.index', 'label' => __('messages.todos'), 'icon' => '✓', 'active' => 'todos.*', 'accent' => 'var(--accent-jobs)', 'bg' => '#e7f7ee'],
            ['route' => 'events.index', 'label' => __('messages.calendar'), 'icon' => '▤', 'active' => 'events.*', 'accent' => 'var(--accent-events)', 'bg' => '#fff0e6'],
            ['route' => 'hymns.index', 'label' => __('messages.hymns'), 'icon' => '♪', 'active' => 'hymns.*', 'accent' => 'var(--accent-audio)', 'bg' => '#eceafe'],
        ],
        'Bible' => [
            ['route' => 'bible.index', 'label' => __('messages.bible'), 'icon' => '✝', 'active' => 'bible.index', 'accent' => 'var(--accent-bible)', 'bg' => '#f2ebfd'],
            ['route' => 'bible.study', 'label' => __('messages.bible_study'), 'icon' => '✎', 'active' => 'bible.study', 'accent' => 'var(--accent-bible)', 'bg' => '#f2ebfd'],
            ['route' => 'bible.bookmarks', 'label' => __('messages.bookmarks'), 'icon' => '★', 'active' => 'bible.bookmarks', 'accent' => 'var(--accent-bible)', 'bg' => '#f2ebfd'],
            ['route' => 'bible.reading-history', 'label' => __('messages.reading_history'), 'icon' => '◷', 'active' => 'bible.reading-history', 'accent' => 'var(--accent-bible)', 'bg' => '#f2ebfd'],
            ['route' => 'bible.search', 'label' => __('messages.search_bible'), 'icon' => '⌕', 'active' => 'bible.search', 'accent' => 'var(--accent-bible)', 'bg' => '#f2ebfd'],
        ],
        'Community' => [
            ['route' => 'organizations.index', 'label' => 'Organizations', 'icon' => '⛪', 'active' => 'organizations.*', 'accent' => 'var(--accent-community)', 'bg' => '#e3f6f4'],
            ['route' => 'directory.index', 'label' => 'Directory', 'icon' => '☰', 'active' => 'directory.*', 'accent' => 'var(--accent-community)', 'bg' => '#e3f6f4'],
            ['route' => 'messages.index', 'label' => 'Messages', 'icon' => '✉', 'active' => 'messages.*', 'accent' => 'var(--accent-messages)', 'bg' => '#e6efff'],
        ],
        'Discover' => [
            ['route' => 'resources.index', 'label' => 'Library', 'icon' => '▣', 'active' => 'resources.*', 'accent' => 'var(--navy)', 'bg' => 'var(--navy-soft)'],
            ['route' => 'jobs.index', 'label' => 'Jobs', 'icon' => '⚒', 'active' => 'jobs.*', 'accent' => 'var(--accent-jobs)', 'bg' => '#e7f7ee'],
            ['route' => 'org-events.discover', 'label' => __('messages.events'), 'icon' => '◆', 'active' => 'org-events.*', 'accent' => 'var(--accent-events)', 'bg' => '#fff0e6'],
            ['route' => 'audio.index', 'label' => 'Audio', 'icon' => '♫', 'active' => 'audio.*', 'accent' => 'var(--accent-audio)', 'bg' => '#eceafe'],
        ],
        'Giving' => [
            ['route' => 'giving.mine', 'label' => 'Giving', 'icon' => '♡', 'active' => 'giving.*', 'accent' => 'var(--accent-giving)', 'bg' => 'var(--brand-soft)'],
        ],
        'Account' => [
            ['route' => 'notifications.index', 'label' => 'Notifications', 'icon' => '◉', 'active' => 'notifications.*', 'accent' => 'var(--accent-alert)', 'bg' => '#ffe8ee'],
            ['route' => 'profile.show', 'label' => __('messages.profile'), 'icon' => '☺', 'active' => 'profile.*', 'accent' => 'var(--navy)', 'bg' => 'var(--navy-soft)'],
            ['route' => 'settings.index', 'label' => __('messages.settings'), 'icon' => '⚙', 'active' => 'settings.*', 'accent' => 'var(--navy)', 'bg' => 'var(--navy-soft)'],
            ['route' => 'language.select', 'label' => __('messages.language'), 'icon' => '⌘', 'active' => 'language.*', 'accent' => 'var(--navy)', 'bg' => 'var(--navy-soft)'],
        ],
    ];

    /**
     * Phase 27.7 — "Connections" is surfaced in the Community group.
     *
     * No new route was invented: the app's existing connections destination is
     * `users.followers`, which requires the viewer's own profile username. It is
     * therefore only shown when the signed-in user actually has a profile, which
     * is the same condition the dashboard's Connections card already uses.
     */
    $navProfileUsername = auth()->user()?->profile?->username;

    if ($navProfileUsername) {
        $navGroups['Community'][] = [
            'route' => 'users.followers',
            'params' => [$navProfileUsername],
            'label' => 'Connections',
            'icon' => '⚭',
            'active' => 'users.followers',
            'accent' => 'var(--accent-community)',
            'bg' => '#e3f6f4',
        ];
    }
@endphp

<div class="nav-scrim" id="nav-scrim" data-open="false" aria-hidden="true"></div>

<nav class="nav-drawer" id="nav-drawer" data-open="false" aria-label="Main navigation" aria-labelledby="nav-drawer-title">
    <div class="sheet-grabber" aria-hidden="true"></div>
    <div class="nav-drawer-head">
        <strong id="nav-drawer-title">Go to</strong>
        <span class="muted" style="font-size:12px;">Esc to close</span>
    </div>

    @foreach ($navGroups as $groupName => $links)
        <div class="nav-group">
            <h3>{{ $groupName }}</h3>
            <div class="nav-links">
                @foreach ($links as $link)
                    @php $isActive = request()->routeIs($link['active']); @endphp
                    <a class="nav-link"
                       href="{{ route($link['route'], $link['params'] ?? []) }}"
                       @if ($isActive) aria-current="page" @endif>
                        <span class="nav-dot" style="background: {{ $link['bg'] }}; color: {{ $link['accent'] }};" aria-hidden="true">{{ $link['icon'] }}</span>
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="nav-group">
        <h3>Session</h3>
        <form action="{{ route('logout') }}" method="post">
            @csrf
            <button type="submit" class="btn nav-logout">{{ __('messages.logout') }}</button>
        </form>
    </div>
</nav>

<button type="button"
        class="nav-fab"
        id="nav-fab"
        aria-label="Open navigation menu"
        aria-expanded="false"
        aria-controls="nav-drawer"
        aria-haspopup="true">
    <svg class="fab-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    <svg class="fab-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
</button>
