@extends('layouts.app')

@section('content')
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <h1 style="margin:0;">{{ $campaign->title }}</h1>
        <span class="muted">{{ ucfirst($campaign->status) }}</span>
    </div>

    <p class="muted">{{ $organization->name }}</p>

    @if ($campaign->description)
        <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;margin:12px 0;">{{ $campaign->description }}</div>
    @endif

    <p>
        <strong>Raised:</strong> {{ $campaign->currency }} {{ \App\Support\Money::toDecimalString($raisedAmount) }}
        @if ($campaign->target_amount)
            of {{ $campaign->currency }} {{ $campaign->targetAmountDisplay() }}
        @endif
    </p>

    @if ($canManage)
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
            <a class="btn" href="{{ route('giving.edit', [$organization, $campaign]) }}">Edit</a>
            <a class="btn" href="{{ route('giving.history', [$organization, $campaign]) }}">Giving history</a>
            @if ($campaign->status === 'draft')
                <form method="post" action="{{ route('giving.publish', [$organization, $campaign]) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn-primary" type="submit">Publish</button></form>
            @endif
            @if ($campaign->status === 'published')
                <form method="post" action="{{ route('giving.close', [$organization, $campaign]) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn" type="submit">Close</button></form>
            @endif
            @if (! in_array($campaign->status, ['cancelled', 'closed']))
                <form method="post" action="{{ route('giving.cancel', [$organization, $campaign]) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn-danger" type="submit">Cancel</button></form>
            @endif
        </div>
    @endif

    @if ($campaign->acceptsDonations())
        <form method="post" action="{{ route('giving.donate', [$organization, $campaign]) }}" style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;">
            @csrf
            <div class="field">
                <label for="amount">Amount ({{ $campaign->currency }})</label>
                <input class="input" type="number" step="0.01" min="1" id="amount" name="amount" required>
            </div>

            @guest
                <div class="field">
                    <label for="donor_name">Your name</label>
                    <input class="input" type="text" id="donor_name" name="donor_name" required>
                </div>
                <div class="field">
                    <label for="donor_email">Your email</label>
                    <input class="input" type="email" id="donor_email" name="donor_email" required>
                </div>
            @endguest

            <button class="btn-primary" type="submit" style="margin-top:12px;">Give now</button>
        </form>
    @endif
@endsection
