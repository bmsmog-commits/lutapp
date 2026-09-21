@extends('layouts.app')

@section('content')
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
        <h1 style="margin:0;">Jobs & Services</h1>
        @auth
            <div style="display:flex;gap:8px;">
                <a class="btn" href="{{ route('jobs.mine') }}">My postings</a>
                <a class="btn" href="{{ route('jobs.applications.mine') }}">My applications</a>
                <a class="btn-primary" href="{{ route('jobs.create') }}">Post a job</a>
            </div>
        @endauth
    </div>

    <form method="get" action="{{ route('jobs.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <input class="input" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search title or description" style="max-width:240px;">
        <select class="input" name="category" style="max-width:200px;">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category }}" @selected(($filters['category'] ?? null) === $category)>{{ $category }}</option>
            @endforeach
        </select>
        <select class="input" name="work_mode" style="max-width:160px;">
            <option value="">Any work mode</option>
            @foreach ($workModes as $mode)
                <option value="{{ $mode }}" @selected(($filters['work_mode'] ?? null) === $mode)>{{ ucfirst(str_replace('_', ' ', $mode)) }}</option>
            @endforeach
        </select>
        <input class="input" type="text" name="city" value="{{ $filters['city'] ?? '' }}" placeholder="City" style="max-width:160px;">
        <button class="btn-primary" type="submit">Filter</button>
    </form>

    <div style="display:flex;flex-direction:column;gap:12px;">
        @forelse ($jobs as $job)
            <a href="{{ route('jobs.show', $job) }}" style="display:block;border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;text-decoration:none;color:inherit;">
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <strong>{{ $job->title }}</strong>
                    <span class="muted">{{ ucfirst(str_replace('_', ' ', $job->work_mode)) }}</span>
                </div>
                <div class="muted">
                    {{ $job->organization?->name ?? $job->user?->name }}
                    @if ($job->city) &middot; {{ $job->city }} @endif
                    @if ($job->category) &middot; {{ $job->category }} @endif
                </div>
            </a>
        @empty
            <p class="muted">No jobs found.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $jobs->links() }}</div>
@endsection
