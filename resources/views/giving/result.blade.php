@extends('layouts.app')

@section('content')
    <h1>Payment result</h1>

    <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:20px;max-width:420px;">
        @if ($transaction->status === 'successful')
            <p style="color:#1a7f37;font-weight:700;">Thank you — your donation was successful.</p>
        @elseif ($transaction->status === 'pending')
            <p class="muted">Your payment is still being confirmed. This page reflects the latest verified status — refresh in a moment.</p>
        @else
            <p style="color:var(--danger);font-weight:700;">This payment was not successful.</p>
        @endif

        <p class="muted">Reference: {{ $transaction->reference }}</p>
        <p class="muted">Amount: {{ $transaction->currency }} {{ \App\Support\Money::toDecimalString($transaction->amount) }}</p>
    </div>
@endsection
