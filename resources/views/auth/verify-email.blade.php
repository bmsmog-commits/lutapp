@extends('layouts.app')

@section('content')
<div style="max-width: 400px; margin: 60px auto; padding: 24px; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <h2 style="margin-top: 0;">Verify Your Email</h2>
    
    <p style="color: #5f6368; line-height: 1.6;">
        We've sent a verification link to your email address. Please check your email and click the link to verify your account.
    </p>

    @if (session('resent'))
        <div style="padding: 12px; background: #e8f5e9; border-radius: 4px; margin-bottom: 20px; color: #2e7d32;">
            A fresh verification link has been sent to your email address.
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" style="width: 100%; padding: 12px; background: #fbbc04; border: none; border-radius: 4px; font-weight: 500; cursor: pointer; margin-bottom: 12px;">
            Resend Verification Email
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" style="display: inline;">
        @csrf
        <button type="submit" style="width: 100%; padding: 12px; background: transparent; border: 1px solid #dadce0; border-radius: 4px; font-weight: 500; cursor: pointer;">
            Logout
        </button>
    </form>
</div>
@endsection
