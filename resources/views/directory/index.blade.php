@extends('layouts.app')

@section('content')
    <h1>Organization Directory</h1>

    <form method="get" action="{{ route('directory.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <input class="input" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search organizations..." style="max-width:220px;">
        <select class="input" name="type" style="max-width:160px;">
            <option value="">All types</option>
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(($filters['type'] ?? null) === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>
        <input class="input" type="text" name="country" value="{{ $filters['country'] ?? '' }}" placeholder="Country" style="max-width:140px;">
        <input class="input" type="text" name="state" value="{{ $filters['state'] ?? '' }}" placeholder="State" style="max-width:140px;">
        <input class="input" type="text" name="city" value="{{ $filters['city'] ?? '' }}" placeholder="City" style="max-width:140px;">
        <button class="btn-primary" type="submit">Filter</button>
    </form>

    <div style="display:flex;flex-direction:column;gap:10px;">
        @forelse ($organizations as $organization)
            <a href="{{ route('directory.show', $organization) }}" style="display:flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;text-decoration:none;color:inherit;">
                @if ($organization->logo)
                    <img src="{{ route('files.show', $organization->logo) }}" alt="" style="width:48px;height:48px;border-radius:8px;object-fit:cover;">
                @else
                    <div style="width:48px;height:48px;border-radius:8px;background:var(--surface);display:grid;place-items:center;font-weight:700;">
                        {{ strtoupper(substr($organization->name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <strong>{{ $organization->name }}</strong>
                    <div class="muted">
                        {{ ucfirst($organization->type) }}
                        @if ($organization->city || $organization->state)
                            &middot; {{ trim(($organization->city ?? '').(($organization->city && $organization->state) ? ', ' : '').($organization->state ?? '')) }}
                        @endif
                        @if (isset($organization->distance_km))
                            &middot; {{ round($organization->distance_km, 1) }} km away
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <p class="muted">No organizations found.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $organizations->links() }}</div>
@endsection
