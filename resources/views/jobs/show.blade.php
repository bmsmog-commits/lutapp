@extends('layouts.app')

@section('content')
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <h1 style="margin:0;">{{ $job->title }}</h1>
        <span class="muted">{{ ucfirst($job->status) }}</span>
    </div>

    <p class="muted">
        {{ $job->organization?->name ?? $job->user?->name }}
        @if ($job->category) &middot; {{ $job->category }} @endif
        &middot; {{ ucfirst(str_replace('_', ' ', $job->work_mode)) }}
        @if ($job->city) &middot; {{ $job->city }}@if($job->country), {{ $job->country }}@endif @endif
    </p>

    @if ($job->budget_type)
        <p>
            <strong>Budget:</strong>
            @if ($job->budget_type === 'negotiable')
                Negotiable
            @elseif ($job->budget_type === 'fixed')
                {{ $job->currency }} {{ number_format($job->budget_min, 2) }}
            @else
                {{ $job->currency }} {{ number_format($job->budget_min, 2) }} &ndash; {{ number_format($job->budget_max, 2) }}
            @endif
        </p>
    @endif

    @if ($job->application_deadline)
        <p><strong>Application deadline:</strong> {{ $job->application_deadline->format('M j, Y') }}</p>
    @endif

    <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;margin:16px 0;">
        {{ $job->description }}
    </div>

    @if ($job->attachment)
        <p><a href="{{ route('files.show', $job->attachment) }}">📎 {{ $job->attachment->original_name }}</a></p>
    @endif

    @if ($canManage)
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
            <a class="btn" href="{{ route('jobs.edit', $job) }}">Edit</a>
            <a class="btn" href="{{ route('jobs.applications.index', $job) }}">View applications</a>
            @if ($job->status === 'draft')
                <form method="post" action="{{ route('jobs.publish', $job) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn-primary" type="submit">Publish</button></form>
            @endif
            @if ($job->status === 'published')
                <form method="post" action="{{ route('jobs.close', $job) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn" type="submit">Close</button></form>
            @endif
            @if (! in_array($job->status, ['cancelled', 'closed']))
                <form method="post" action="{{ route('jobs.cancel', $job) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn-danger" type="submit">Cancel</button></form>
            @endif
        </div>
    @elseif ($myApplication)
        <p>Your application status: <strong>{{ ucfirst($myApplication->status) }}</strong></p>
        @if ($myApplication->status === 'pending')
            <form method="post" action="{{ route('jobs.applications.withdraw', [$job, $myApplication]) }}">
                @csrf
                <button class="btn" type="submit">Withdraw application</button>
            </form>
        @endif
    @elseif ($canApply)
        <form method="post" action="{{ route('jobs.applications.store', $job) }}">
            @csrf
            <div class="field">
                <label for="message">Message to the job owner (optional)</label>
                <textarea id="message" name="message"></textarea>
            </div>
            <button class="btn-primary" type="submit">Apply</button>
        </form>
    @endif

    @include('reports._form', ['reportableType' => \App\Models\Report::TARGET_JOB, 'reportableId' => $job->id])
@endsection
