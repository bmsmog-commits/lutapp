@extends('layouts.app')

@section('content')
    <h1>New conversation</h1>

    <form method="get" action="{{ route('messages.search') }}" style="margin-bottom:16px;">
        <input class="input" type="text" name="q" value="{{ $query }}" placeholder="Search by name, username, or exact email" autofocus>
    </form>

    <div style="border:1px solid var(--line);border-radius:8px;background:#fff;overflow:hidden;">
        @forelse ($results as $user)
            <form method="post" action="{{ route('messages.start') }}" style="display:flex;align-items:center;gap:12px;padding:14px;border-bottom:1px solid var(--line);">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <div style="flex:1;">
                    <strong>{{ $user->profile?->display_name ?? $user->name }}</strong>
                    @if ($user->profile?->username)
                        <div class="muted">&#64;{{ $user->profile->username }}</div>
                    @endif
                </div>
                <button class="btn-primary" type="submit">Message</button>
            </form>
        @empty
            @if ($query !== '')
                <p class="muted" style="padding:14px;">No users found.</p>
            @else
                <p class="muted" style="padding:14px;">Type at least 2 characters to search.</p>
            @endif
        @endforelse
    </div>
@endsection
