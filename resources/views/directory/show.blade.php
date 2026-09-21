@extends('layouts.app')

@section('content')
    <a class="btn" href="{{ route('directory.index') }}">&larr; Back to directory</a>

    <div style="display:flex;align-items:center;gap:14px;margin:16px 0;">
        @if ($organization->logo)
            <img src="{{ route('files.show', $organization->logo) }}" alt="{{ $organization->name }} logo" style="width:72px;height:72px;border-radius:8px;object-fit:cover;">
        @else
            <div style="width:72px;height:72px;border-radius:8px;background:var(--surface);display:grid;place-items:center;font-weight:700;font-size:24px;">
                {{ strtoupper(substr($organization->name, 0, 1)) }}
            </div>
        @endif
        <div>
            <h1 style="margin:0;">{{ $organization->name }}</h1>
            <span class="muted">{{ ucfirst($organization->type) }}</span>
        </div>
    </div>

    @if ($organization->description)
        <p>{{ $organization->description }}</p>
    @endif

    @if ($organization->city || $organization->state || $organization->country)
        <p>
            <strong>Location:</strong>
            {{ collect([$organization->city, $organization->state, $organization->country])->filter()->join(', ') }}
        </p>
        @if ($organization->hasLocation())
            <div data-lat="{{ $organization->latitude }}" data-lng="{{ $organization->longitude }}" class="muted">
                Map preview coming in a future phase.
            </div>
        @endif
    @endif

    @if ($organization->website)
        <p><strong>Website:</strong> <a href="{{ $organization->website }}" target="_blank" rel="noopener">{{ $organization->website }}</a></p>
    @endif

    @auth
        @if (auth()->id() !== $organization->owner_id)
            <form method="post" action="{{ route('messages.start') }}" style="margin-top:12px;">
                @csrf
                <input type="hidden" name="user_id" value="{{ $organization->owner_id }}">
                <button class="btn-primary" type="submit">Contact organization</button>
            </form>
        @endif

        @can('update', $organization)
            <p style="margin-top:12px;"><a class="btn" href="{{ route('organizations.edit', $organization) }}">Manage organization</a></p>
        @endcan
    @endauth

    @include('reports._form', ['reportableType' => \App\Models\Report::TARGET_ORGANIZATION, 'reportableId' => $organization->id])
@endsection
