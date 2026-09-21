@extends('layouts.app')

@section('content')
    <h1>My organizations</h1>
    <p><a class="btn-primary" href="{{ route('organizations.create') }}">Create organization</a></p>

    @forelse ($organizations as $organization)
        <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;margin-bottom:10px;display:flex;align-items:center;gap:12px;">
            @if ($organization->logo)
                <img src="{{ route('files.show', $organization->logo) }}" alt="" style="width:40px;height:40px;border-radius:6px;object-fit:cover;">
            @endif
            <div>
                <a href="{{ route('organizations.show', $organization) }}"><strong>{{ $organization->name }}</strong></a>
                <div class="muted">{{ ucfirst($organization->type) }} &middot; {{ ucfirst($organization->visibility) }}</div>
            </div>
        </div>
    @empty
        <p class="muted">You don't belong to any organizations yet. Create your first organization.</p>
    @endforelse
@endsection
