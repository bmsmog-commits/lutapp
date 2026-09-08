@extends('layouts.app')

@section('content')
<div style="max-width: 400px; margin: 60px auto; padding: 24px; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <h2 style="margin-top: 0;">Forgot Password?</h2>
    
    <p style="color: #5f6368; margin-bottom: 20px;">
        Enter your email address and we'll send you a link to reset your password.
    </p>

    @if ($errors->any())
        <div style="padding: 12px; background: #ffebee; border-radius: 4px; margin-bottom: 20px; color: #c62828;">
            <strong>Error:</strong>
            <ul style="margin: 8px 0 0 20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('status'))
        <div style="padding: 12px; background: #e8f5e9; border-radius: 4px; margin-bottom: 20px; color: #2e7d32;">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        
        <div style="margin-bottom: 20px;">
            <label for="email" style="display: block; margin-bottom: 8px; font-weight: 500;">Email Address</label>
            <input 
                type="email" 
                id="email" 
                name="email" 
                value="{{ old('email') }}"
                required 
                style="width: 100%; padding: 12px; border: 1px solid #dadce0; border-radius: 4px; font-size: 14px;"
                placeholder="your@email.com"
            >
        </div>

        <button type="submit" style="width: 100%; padding: 12px; background: #fbbc04; border: none; border-radius: 4px; font-weight: 500; cursor: pointer; margin-bottom: 12px;">
            Send Password Reset Link
        </button>

        <a href="{{ route('login') }}" style="display: block; text-align: center; color: #fbbc04; text-decoration: none;">
            Back to Login
        </a>
    </form>
</div>
@endsection
