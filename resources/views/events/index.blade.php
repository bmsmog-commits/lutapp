@extends('layouts.app')

@push('styles')
<style>
    .workspace { display: grid; grid-template-columns: 380px 1fr; gap: 18px; }
    .panel, .event { border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 16px; }
    .stack { display: grid; gap: 12px; }
    .event { margin-bottom: 12px; }
    .event-head { display: flex; justify-content: space-between; gap: 12px; }
    @media (max-width: 820px) { .workspace { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
    <h1>Calendar and meetings</h1>
    <div class="workspace">
        <section class="panel">
            <form class="stack" method="post" action="{{ route('events.store') }}">
                @csrf
                <input class="input" name="title" placeholder="Meeting or event title" required>
                <select class="input" name="event_type">
                    <option value="meeting">Meeting</option>
                    <option value="service">Service</option>
                    <option value="rehearsal">Rehearsal</option>
                    <option value="visit">Visit</option>
                    <option value="other">Other</option>
                </select>
                <input class="input" name="location" placeholder="Location">
                <input class="input" name="starts_at" type="datetime-local" required>
                <input class="input" name="ends_at" type="datetime-local">
                <textarea name="description" placeholder="Agenda or notes"></textarea>
                <button class="btn-primary" type="submit">Add schedule</button>
            </form>
        </section>
        <section>
            @forelse ($events as $event)
                <article class="event">
                    <div class="event-head">
                        <div>
                            <strong>{{ $event->title }}</strong>
                            <div class="muted">{{ ucfirst($event->event_type) }}{{ $event->location ? ' - '.$event->location : '' }}</div>
                        </div>
                        <div class="muted">{{ $event->starts_at->format('M j, Y g:i A') }}</div>
                    </div>
                    @if ($event->description)
                        <p>{{ $event->description }}</p>
                    @endif
                    <form method="post" action="{{ route('events.destroy', $event) }}">
                        @csrf
                        @method('delete')
                        <button class="btn-danger" type="submit">Delete</button>
                    </form>
                </article>
            @empty
                <p class="muted">No schedule items yet.</p>
            @endforelse
        </section>
    </div>
@endsection
