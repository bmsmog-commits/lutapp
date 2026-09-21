@extends('layouts.app')

@section('content')
    <h1>Events</h1>

    <form method="get" action="{{ route('org-events.discover') }}" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <input class="input" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search events..." style="max-width:220px;">
        <select class="input" name="category" style="max-width:180px;">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category }}" @selected(($filters['category'] ?? null) === $category)>{{ $category }}</option>
            @endforeach
        </select>
        <select class="input" name="location_mode" style="max-width:160px;">
            <option value="">Any location mode</option>
            @foreach ($locationModes as $mode)
                <option value="{{ $mode }}" @selected(($filters['location_mode'] ?? null) === $mode)>{{ ucfirst($mode) }}</option>
            @endforeach
        </select>
        <input class="input" type="text" name="city" value="{{ $filters['city'] ?? '' }}" placeholder="City" style="max-width:140px;">
        <input class="input" type="date" name="date" value="{{ $filters['date'] ?? '' }}" style="max-width:160px;">
        <button class="btn-primary" type="submit">Filter</button>
    </form>

    <div style="display:flex;flex-direction:column;gap:12px;">
        @forelse ($events as $event)
            <a href="{{ route('org-events.show', $event) }}" style="display:flex;gap:12px;border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;text-decoration:none;color:inherit;">
                @if ($event->cover)
                    <img src="{{ route('files.show', $event->cover) }}" alt="" style="width:64px;height:64px;border-radius:8px;object-fit:cover;">
                @endif
                <div>
                    <strong>{{ $event->title }}</strong>
                    <div class="muted">
                        {{ $event->organization->name }} &middot; {{ $event->starts_at->format('M j, Y g:i A') }}
                        @if ($event->city) &middot; {{ $event->city }} @endif
                        &middot; {{ ucfirst($event->location_mode) }}
                    </div>
                </div>
            </a>
        @empty
            <p class="muted">No events found.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $events->links() }}</div>
@endsection
