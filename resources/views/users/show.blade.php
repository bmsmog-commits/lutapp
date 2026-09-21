@extends('layouts.app')

@section('content')
    @php
        $displayName = $profile->display_name ?? $profileUser->name;
        $location = collect([$profile->city, $profile->state, $profile->country])->filter()->join(', ');
        $metaDescription = $profile->bio ? \Illuminate\Support\Str::limit(strip_tags($profile->bio), 160) : ($displayName.' on Lutapp');
    @endphp

    @push('styles')
        <meta name="description" content="{{ $metaDescription }}">
        <meta property="og:title" content="{{ $displayName }} (@{{ $profile->username }}) — Lutapp">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:type" content="profile">
        <link rel="canonical" href="{{ route('users.show', $profile->username) }}">
    @endpush

    <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;">
        @if ($profile->profilePhoto)
            <img src="{{ route('files.show', $profile->profilePhoto) }}" alt="" style="width:96px;height:96px;border-radius:50%;object-fit:cover;">
        @else
            <div style="width:96px;height:96px;border-radius:50%;background:var(--surface);display:grid;place-items:center;font-weight:700;font-size:32px;">
                {{ strtoupper(substr($displayName, 0, 1)) }}
            </div>
        @endif

        <div style="flex:1;min-width:200px;">
            <h1 style="margin:0;">{{ $displayName }}</h1>
            <p class="muted" style="margin:2px 0 0;">&#64;{{ $profile->username }}</p>

            @if ($location)
                <p class="muted">{{ $location }}</p>
            @endif

            <p class="muted" style="margin-top:4px;">
                <a href="{{ route('users.followers', $profile->username) }}">{{ $followersCount }} follower{{ $followersCount === 1 ? '' : 's' }}</a>
                &middot;
                <a href="{{ route('users.following', $profile->username) }}">{{ $followingCount }} following</a>
            </p>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
                @if ($canMessage)
                    <form method="post" action="{{ route('messages.start') }}">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $profileUser->id }}">
                        <button class="btn-primary" type="submit">Message</button>
                    </form>
                @elseif (! auth()->check())
                    <a class="btn" href="{{ route('login') }}">Log in to send a message</a>
                @endif

                @unless ($isSelf)
                    @auth
                        @if ($isBlocked)
                            <form method="post" action="{{ route('users.unblock', $profile->username) }}">
                                @csrf @method('DELETE')
                                <button class="btn" type="submit">Unblock</button>
                            </form>
                        @else
                            @if ($isFollowing)
                                <form method="post" action="{{ route('users.unfollow', $profile->username) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn" type="submit">Following</button>
                                </form>
                            @elseif ($canFollow)
                                <form method="post" action="{{ route('users.follow', $profile->username) }}">
                                    @csrf
                                    <button class="btn-primary" type="submit">Follow</button>
                                </form>
                            @endif

                            <form method="post" action="{{ route('users.block', $profile->username) }}" onsubmit="return confirm('Block this user? This removes any existing follow between you.');">
                                @csrf
                                <button class="btn-danger" type="submit">Block</button>
                            </form>
                        @endif
                    @endauth
                @endunless
            </div>

            @unless ($isSelf)
                @include('reports._form', ['reportableType' => \App\Models\Report::TARGET_USER, 'reportableId' => $profileUser->id])
            @endunless
        </div>
    </div>

    @error('follow')<p class="errors">{{ $message }}</p>@enderror
    @error('block')<p class="errors">{{ $message }}</p>@enderror

    @if ($profile->bio)
        <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;margin:16px 0;">{{ $profile->bio }}</div>
    @endif

    @if ($organizations->isNotEmpty())
        <h2 style="margin-top:24px;">Organizations</h2>
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach ($organizations as $organization)
                <a href="{{ route('directory.show', $organization) }}" style="display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:8px;background:#fff;padding:10px;text-decoration:none;color:inherit;">
                    @if ($organization->logo)
                        <img src="{{ route('files.show', $organization->logo) }}" alt="" style="width:32px;height:32px;border-radius:6px;object-fit:cover;">
                    @endif
                    <span>{{ $organization->name }}</span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($jobs->isNotEmpty())
        <h2 style="margin-top:24px;">Services &amp; Jobs</h2>
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach ($jobs as $job)
                <a href="{{ route('jobs.show', $job) }}" style="display:block;border:1px solid var(--line);border-radius:8px;background:#fff;padding:10px;text-decoration:none;color:inherit;">{{ $job->title }}</a>
            @endforeach
        </div>
    @endif

    @if ($resources->isNotEmpty())
        <h2 style="margin-top:24px;">Books &amp; Resources</h2>
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach ($resources as $resource)
                <a href="{{ route('resources.show', $resource) }}" style="display:block;border:1px solid var(--line);border-radius:8px;background:#fff;padding:10px;text-decoration:none;color:inherit;">{{ $resource->title }}</a>
            @endforeach
        </div>
    @endif

    @if ($audio->isNotEmpty())
        <h2 style="margin-top:24px;">Audio</h2>
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach ($audio as $item)
                <a href="{{ route('audio.show', $item) }}" style="display:block;border:1px solid var(--line);border-radius:8px;background:#fff;padding:10px;text-decoration:none;color:inherit;">{{ $item->title }}</a>
            @endforeach
        </div>
    @endif
@endsection
