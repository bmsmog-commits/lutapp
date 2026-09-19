@extends('layouts.app')

@section('content')
    <h1>{{ $organization->name }} — Members</h1>

    @if ($canManage)
        <p><a class="btn-primary" href="{{ route('organizations.members.create', $organization) }}">Add Member</a></p>
    @endif

    @forelse ($members as $member)
        <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:12px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div>
                <strong>{{ $member->user->profile?->display_name ?? $member->user->name }}</strong>
                <div class="muted">{{ $member->role_names->implode(', ') ?: 'Member' }} &middot; {{ ucfirst($member->status) }}</div>
            </div>
            @if ($canManage)
                <div style="display:flex;gap:8px;">
                    <form method="post" action="{{ route('organizations.members.update', [$organization, $member]) }}">
                        @csrf
                        @method('put')
                        <select name="role" onchange="this.form.submit()">
                            <option value="Organization Admin" @selected($member->role_names->first() === 'Organization Admin')>Admin</option>
                            <option value="Member" @selected($member->role_names->first() !== 'Organization Admin')>Member</option>
                        </select>
                        <input type="hidden" name="status" value="{{ $member->status }}">
                        <input type="hidden" name="department_id" value="{{ $member->department_id }}">
                    </form>
                    @if ($member->user_id !== $organization->owner_id)
                        <form method="post" action="{{ route('organizations.members.destroy', [$organization, $member]) }}">
                            @csrf
                            @method('delete')
                            <button class="btn-danger" type="submit" onclick="return confirm('Remove this member?')">Remove</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    @empty
        <p class="muted">No organization members yet.</p>
    @endforelse

    {{ $members->links() }}
@endsection
