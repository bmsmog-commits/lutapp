@extends('layouts.app')

@section('content')
    <h1>{{ $organization->name }}</h1>
    <p class="muted">{{ ucfirst($organization->type) }} &middot; {{ ucfirst($organization->visibility) }} &middot; {{ ucfirst($organization->status) }}</p>

    @if ($organization->description)
        <p>{{ $organization->description }}</p>
    @endif

    <div style="display:flex;gap:24px;margin:16px 0;">
        <div><strong>{{ $memberCount }}</strong><div class="muted">Members</div></div>
        <div><strong>{{ $departmentCount }}</strong><div class="muted">Departments</div></div>
        <div><strong>{{ $currentUserRole ?? '—' }}</strong><div class="muted">Your role</div></div>
    </div>

    <p>
        <a class="btn" href="{{ route('organizations.members.index', $organization) }}">Members</a>
        <a class="btn" href="{{ route('organizations.departments.index', $organization) }}">Departments</a>
        @if ($canManage)
            <a class="btn" href="{{ route('organizations.edit', $organization) }}">Edit</a>
        @endif
        @can('delete', $organization)
            <form method="post" action="{{ route('organizations.destroy', $organization) }}" style="display:inline;">
                @csrf
                @method('delete')
                <button class="btn-danger" type="submit" onclick="return confirm('Delete this organization?')">Delete</button>
            </form>
        @endcan
    </p>
@endsection
