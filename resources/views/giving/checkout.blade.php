@extends('layouts.app')

@section('content')
    <h1>Checkout</h1>

    <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:20px;max-width:420px;">
        <p class="muted">You are paying</p>
        <p style="font-size:28px;font-weight:700;margin:0 0 16px;">{{ $transaction->currency }} {{ \App\Support\Money::toDecimalString($transaction->amount) }}</p>

        <p class="muted">This is a simulated checkout page for the test payment provider. No real payment is processed.</p>

        <form method="post" action="{{ route('giving.checkout.simulate', $transaction->reference) }}" style="display:flex;gap:8px;margin-top:16px;">
            @csrf
            <input type="hidden" name="outcome" value="success">
            <button class="btn-primary" type="submit">Simulate successful payment</button>
        </form>
        <form method="post" action="{{ route('giving.checkout.simulate', $transaction->reference) }}" style="margin-top:8px;">
            @csrf
            <input type="hidden" name="outcome" value="failed">
            <button class="btn" type="submit">Simulate failed payment</button>
        </form>
    </div>
@endsection
