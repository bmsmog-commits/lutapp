@extends('layouts.app')

@section('content')
    <h1>{{ $organization->name }} — Departments</h1>

    @if ($canManage)
        <form method="post" action="{{ route('organizations.departments.store', $organization) }}" style="display:flex;gap:8px;margin-bottom:16px;">
            @csrf
            <input class="input" name="name" placeholder="Department name" required>
            <button class="btn-primary" type="submit">Add department</button>
        </form>
    @endif

    @forelse ($departments as $department)
        <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:12px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <strong>{{ $department->name }}</strong>
                <div class="muted">{{ $department->members_count }} member(s)</div>
            </div>
            @if ($canManage)
                <form method="post" action="{{ route('organizations.departments.destroy', [$organization, $department]) }}">
                    @csrf
                    @method('delete')
                    <button class="btn-danger" type="submit" onclick="return confirm('Delete this department?')">Delete</button>
                </form>
            @endif
        </div>
    @empty
        <p class="muted">No departments have been created yet.</p>
    @endforelse

    {{ $departments->links() }}
@endsection
