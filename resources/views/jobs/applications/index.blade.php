@extends('layouts.app')

@section('content')
    <h1>Applications for {{ $job->title }}</h1>

    <div style="display:flex;flex-direction:column;gap:12px;">
        @forelse ($applications as $application)
            <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;">
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <strong>{{ $application->applicant->profile?->display_name ?? $application->applicant->name }}</strong>
                    <span class="muted">{{ ucfirst($application->status) }}</span>
                </div>
                @if ($application->message)
                    <p>{{ $application->message }}</p>
                @endif

                @if ($application->status === 'pending')
                    <div style="display:flex;gap:8px;">
                        <form method="post" action="{{ route('jobs.applications.accept', [$job, $application]) }}">
                            @csrf
                            <button class="btn-primary" type="submit">Accept</button>
                        </form>
                        <form method="post" action="{{ route('jobs.applications.reject', [$job, $application]) }}">
                            @csrf
                            <button class="btn" type="submit">Reject</button>
                        </form>
                    </div>
                @elseif ($application->status === 'accepted' && $application->conversation_id)
                    <a class="btn" href="{{ route('messages.show', $application->conversation_id) }}">Message applicant</a>
                @endif
            </div>
        @empty
            <p class="muted">No applications yet.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $applications->links() }}</div>
@endsection
