@extends('layouts.app')

@section('content')
    <a class="btn" href="{{ route('users.show', $profile->username) }}">&larr; Back to profile</a>

    <h1 style="margin-top:16px;">{{ $profile->display_name ?? $profile->username }} is following</h1>

    <div style="display:flex;flex-direction:column;gap:8px;margin-top:16px;">
        @forelse ($following as $user)
            @php $userProfile = $user->profile; @endphp
            <a href="{{ $userProfile ? route('users.show', $userProfile->username) : '#' }}" style="display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:8px;background:#fff;padding:10px;text-decoration:none;color:inherit;">
                @if ($userProfile?->profilePhoto)
                    <img src="{{ route('files.show', $userProfile->profilePhoto) }}" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                @else
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--surface);display:grid;place-items:center;font-weight:700;">
                        {{ strtoupper(substr($userProfile?->display_name ?? $user->name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <strong>{{ $userProfile?->display_name ?? $user->name }}</strong>
                    @if ($userProfile?->username)
                        <div class="muted">&#64;{{ $userProfile->username }}</div>
                    @endif
                </div>
            </a>
        @empty
            <p class="muted">Not following anyone yet.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $following->links() }}</div>
@endsection
