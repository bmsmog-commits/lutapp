@extends('layouts.app')

@section('content')
    <h1>My postings</h1>

    <div style="display:flex;flex-direction:column;gap:12px;">
        @forelse ($jobs as $job)
            <a href="{{ route('jobs.show', $job) }}" style="display:block;border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;text-decoration:none;color:inherit;">
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <strong>{{ $job->title }}</strong>
                    <span class="muted">{{ ucfirst($job->status) }}</span>
                </div>
                <div class="muted">{{ $job->applications_count }} application(s)</div>
            </a>
        @empty
            <p class="muted">You haven't posted any jobs yet.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $jobs->links() }}</div>
@endsection
