@extends('layouts.app')

@section('content')
    <h1>Attendance — {{ $event->title }}</h1>

    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="text-align:left;border-bottom:1px solid var(--line);">
                <th style="padding:8px;">Attendee</th>
                <th style="padding:8px;">RSVP'd</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rsvps as $rsvp)
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:8px;">{{ $rsvp->user->profile?->display_name ?? $rsvp->user->name }}</td>
                    <td style="padding:8px;">{{ $rsvp->created_at->format('M j, Y g:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="muted" style="padding:8px;">No RSVPs yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;">{{ $rsvps->links() }}</div>
@endsection
