@extends('layouts.app')

@section('content')
    <div style="display:flex;align-items:center;justify-content:space-between;">
        <h1 style="margin:0;">Giving — {{ $organization->name }}</h1>
        @can('create', [App\Models\GivingCampaign::class, $organization])
            <a class="btn-primary" href="{{ route('giving.create', $organization) }}">New campaign</a>
        @endcan
    </div>

    <div style="display:flex;flex-direction:column;gap:12px;margin-top:16px;">
        @forelse ($campaigns as $campaign)
            <a href="{{ route('giving.show', [$organization, $campaign]) }}" style="display:block;border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;text-decoration:none;color:inherit;">
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <strong>{{ $campaign->title }}</strong>
                    <span class="muted">{{ ucfirst($campaign->status) }}</span>
                </div>
                @if ($campaign->target_amount)
                    <div class="muted">Target: {{ $campaign->currency }} {{ $campaign->targetAmountDisplay() }}</div>
                @endif
            </a>
        @empty
            <p class="muted">No campaigns yet.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $campaigns->links() }}</div>
@endsection
