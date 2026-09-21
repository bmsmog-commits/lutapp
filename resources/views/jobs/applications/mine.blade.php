@extends('layouts.app')

@section('content')
    <h1>My applications</h1>

    <div style="display:flex;flex-direction:column;gap:12px;">
        @forelse ($applications as $application)
            <a href="{{ route('jobs.show', $application->job) }}" style="display:block;border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;text-decoration:none;color:inherit;">
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <strong>{{ $application->job->title }}</strong>
                    <span class="muted">{{ ucfirst($application->status) }}</span>
                </div>
                <div class="muted">{{ $application->job->organization?->name ?? $application->job->user?->name }}</div>
            </a>
        @empty
            <p class="muted">You haven't applied to any jobs yet.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $applications->links() }}</div>
@endsection
