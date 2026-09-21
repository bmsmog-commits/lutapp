@extends('layouts.app')

@section('content')
    <h1>Application for {{ $job->title }}</h1>

    <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;">
        <strong>{{ $application->applicant->profile?->display_name ?? $application->applicant->name }}</strong>
        <p class="muted">Status: {{ ucfirst($application->status) }}</p>
        @if ($application->message)
            <p>{{ $application->message }}</p>
        @endif
    </div>
@endsection
