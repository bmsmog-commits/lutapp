@extends('layouts.app')

@section('content')
    <h1>Add member to {{ $organization->name }}</h1>

    <form method="get" action="{{ route('organizations.members.create', $organization) }}" style="max-width:420px;display:flex;gap:8px;margin-bottom:16px;">
        <input class="input" type="text" name="q" value="{{ $query }}" placeholder="Search by username, name, or email" minlength="2" maxlength="100">
        <button class="btn-primary" type="submit">Search</button>
    </form>

    @if ($query !== '' && mb_strlen($query) < 2)
        <p class="muted">Enter at least 2 characters to search.</p>
    @elseif ($query !== '' && $results->isEmpty())
        <p class="muted">No matching users found.</p>
    @endif

    @foreach ($results as $user)
        <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:12px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div>
                <strong>{{ $user->profile?->display_name ?? $user->name }}</strong>
                @if ($user->profile?->username)
                    <div class="muted">{{ '@'.$user->profile->username }}</div>
                @endif
            </div>
            <form method="post" action="{{ route('organizations.members.store', $organization) }}">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <button class="btn-primary" type="submit">Add</button>
            </form>
        </div>
    @endforeach

    <p><a class="btn" href="{{ route('organizations.members.index', $organization) }}">Back to members</a></p>
@endsection
