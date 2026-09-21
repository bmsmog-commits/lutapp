@extends('layouts.app')

@section('content')
    <div style="display:flex;align-items:center;justify-content:space-between;">
        <h1 style="margin:0;">Events — {{ $organization->name }}</h1>
        @can('create', [App\Models\OrganizationEvent::class, $organization])
            <a class="btn-primary" href="{{ route('org-events.create', $organization) }}">New event</a>
        @endcan
    </div>

    <div style="display:flex;flex-direction:column;gap:12px;margin-top:16px;">
        @forelse ($events as $event)
            <a href="{{ route('org-events.show', $event) }}" style="display:block;border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;text-decoration:none;color:inherit;">
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <strong>{{ $event->title }}</strong>
                    <span class="muted">{{ ucfirst($event->status) }}</span>
                </div>
                <div class="muted">{{ $event->starts_at->format('M j, Y g:i A') }} &middot; {{ $event->attending_count }} attending</div>
            </a>
        @empty
            <p class="muted">No events yet.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $events->links() }}</div>
@endsection
