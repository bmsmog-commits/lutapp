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
@endsection
