@extends('layouts.app')

@section('content')
<div class="auth-shell">
    <div class="auth-card">
        <h1>Verify your phone</h1>

        @if ($providerConfigured)
            <p class="muted">We sent a 6-digit code to {{ $phone }}. Enter it below.</p>
        @else
            {{-- Honest state: nothing was sent, and we say so plainly rather
                 than showing a "code sent" message that would be untrue. --}}
            <div class="errors" role="status">
                No SMS provider is configured for this installation, so no verification
                code was sent. Phone verification cannot be completed here yet.
            </div>
        @endif

        <form method="POST" action="{{ route('verification.phone.verify') }}">
            @csrf
            <div class="field">
                <label for="phone-code">Verification code</label>
                <input class="input" id="phone-code" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
            </div>
            <button class="btn btn-primary" type="submit" style="margin-top:14px;">Verify phone</button>
        </form>
    </div>
</div>
@endsection
