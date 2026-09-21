@extends('layouts.app')

@section('content')
    @if ($event->cover)
        <img src="{{ route('files.show', $event->cover) }}" alt="" style="width:100%;max-height:280px;object-fit:cover;border-radius:8px;margin-bottom:12px;">
    @endif

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <h1 style="margin:0;">{{ $event->title }}</h1>
        <span class="muted">{{ ucfirst($event->status) }}</span>
    </div>

    <p class="muted">
        {{ $event->organization->name }}
        @if ($event->category) &middot; {{ $event->category }} @endif
        &middot; {{ ucfirst($event->location_mode) }}
    </p>

    <p><strong>Starts:</strong> {{ $event->starts_at->format('M j, Y g:i A') }} @if ($event->timezone) ({{ $event->timezone }}) @endif</p>
    @if ($event->ends_at)
        <p><strong>Ends:</strong> {{ $event->ends_at->format('M j, Y g:i A') }}</p>
    @endif

    @if (in_array($event->location_mode, ['physical', 'hybrid']) && ($event->city || $event->country))
        <p><strong>Location:</strong> {{ collect([$event->address, $event->city, $event->state, $event->country])->filter()->join(', ') }}</p>
    @endif

    @if ($onlineUrl)
        <p><strong>Join online:</strong> <a href="{{ $onlineUrl }}" target="_blank" rel="noopener">{{ $onlineUrl }}</a></p>
    @elseif (in_array($event->location_mode, ['online', 'hybrid']))
        <p class="muted">The online link is available to confirmed attendees.</p>
    @endif

    @if ($event->description)
        <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;margin:12px 0;">{{ $event->description }}</div>
    @endif

    <p>
        <strong>Attending:</strong> {{ $attendingCount }}
        @if ($event->capacity) / {{ $event->capacity }} @endif
    </p>

    @if ($canManage)
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
            <a class="btn" href="{{ route('org-events.edit', $event) }}">Edit</a>
            <a class="btn" href="{{ route('org-events.attendance', $event) }}">Attendance</a>
            @if ($event->status === 'draft')
                <form method="post" action="{{ route('org-events.publish', $event) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn-primary" type="submit">Publish</button></form>
            @endif
            @if ($event->status === 'published')
                <form method="post" action="{{ route('org-events.complete', $event) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn" type="submit">Mark completed</button></form>
            @endif
            @if (! in_array($event->status, ['cancelled', 'completed']))
                <form method="post" action="{{ route('org-events.cancel', $event) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn-danger" type="submit">Cancel</button></form>
            @endif
        </div>
    @endif

    @auth
        @if ($event->acceptsRsvps())
            @if ($myRsvp && $myRsvp->status === 'attending')
                <p>You are attending this event.</p>
                <form method="post" action="{{ route('org-events.rsvp.destroy', $event) }}">
                    @csrf @method('DELETE')
                    <button class="btn" type="submit">Cancel RSVP</button>
                </form>
            @elseif ($event->isFull())
                <p class="muted">This event is full.</p>
            @else
                <form method="post" action="{{ route('org-events.rsvp.store', $event) }}">
                    @csrf
                    <button class="btn-primary" type="submit">RSVP</button>
                </form>
            @endif
        @endif
    @endauth

    @include('reports._form', ['reportableType' => \App\Models\Report::TARGET_ORGANIZATION_EVENT, 'reportableId' => $event->id])
@endsection
