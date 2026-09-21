@extends('layouts.app')

@section('content')
    <h1>My giving</h1>

    <div style="display:flex;flex-direction:column;gap:10px;">
        @forelse ($donations as $donation)
            <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;">
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <strong>{{ $donation->campaign->title }}</strong>
                    <span class="muted">{{ ucfirst($donation->status) }}</span>
                </div>
                <div class="muted">{{ $donation->campaign->organization->name }} &middot; {{ $donation->currency }} {{ \App\Support\Money::toDecimalString($donation->amount) }}</div>

                @if ($donation->acceptsNewAttempt())
                    <form method="post" action="{{ route('giving.donations.retry', $donation) }}" style="margin-top:8px;">
                        @csrf
                        <button class="btn" type="submit">Retry payment</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="muted">You haven't given yet.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $donations->links() }}</div>
@endsection
