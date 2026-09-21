@extends('layouts.app')

@section('content')
    <a class="btn" href="{{ route('users.show', $profile->username) }}">&larr; Back to profile</a>

    <h1 style="margin-top:16px;">{{ $profile->display_name ?? $profile->username }}'s followers</h1>

    <div style="display:flex;flex-direction:column;gap:8px;margin-top:16px;">
        @forelse ($followers as $follower)
            @php $followerProfile = $follower->profile; @endphp
            <a href="{{ $followerProfile ? route('users.show', $followerProfile->username) : '#' }}" style="display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:8px;background:#fff;padding:10px;text-decoration:none;color:inherit;">
                @if ($followerProfile?->profilePhoto)
                    <img src="{{ route('files.show', $followerProfile->profilePhoto) }}" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                @else
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--surface);display:grid;place-items:center;font-weight:700;">
                        {{ strtoupper(substr($followerProfile?->display_name ?? $follower->name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <strong>{{ $followerProfile?->display_name ?? $follower->name }}</strong>
                    @if ($followerProfile?->username)
                        <div class="muted">&#64;{{ $followerProfile->username }}</div>
                    @endif
                </div>
            </a>
        @empty
            <p class="muted">No followers yet.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $followers->links() }}</div>
@endsection
