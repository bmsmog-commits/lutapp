@extends('layouts.app')

@section('content')
<div style="max-width: 400px; margin: 60px auto; padding: 24px; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <h2 style="margin-top: 0;">Reset Your Password</h2>

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

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div style="margin-bottom: 20px;">
            <label for="email" style="display: block; margin-bottom: 8px; font-weight: 500;">Email Address</label>
            <input 
                type="email" 
                id="email" 
                name="email" 
                value="{{ old('email', $request->email) }}"
                required 
                readonly
                style="width: 100%; padding: 12px; border: 1px solid #dadce0; border-radius: 4px; font-size: 14px; background: #f8f9fa;"
            >
        </div>

        <div style="margin-bottom: 20px;">
            <label for="password" style="display: block; margin-bottom: 8px; font-weight: 500;">New Password</label>
            <input 
                type="password" 
                id="password" 
                name="password" 
                required 
                style="width: 100%; padding: 12px; border: 1px solid #dadce0; border-radius: 4px; font-size: 14px;"
                placeholder="At least 8 characters"
            >
        </div>

        <div style="margin-bottom: 20px;">
            <label for="password_confirmation" style="display: block; margin-bottom: 8px; font-weight: 500;">Confirm Password</label>
            <input 
                type="password" 
                id="password_confirmation" 
                name="password_confirmation" 
                required 
                style="width: 100%; padding: 12px; border: 1px solid #dadce0; border-radius: 4px; font-size: 14px;"
                placeholder="Repeat your password"
            >
        </div>

        <button type="submit" style="width: 100%; padding: 12px; background: #fbbc04; border: none; border-radius: 4px; font-weight: 500; cursor: pointer;">
            Reset Password
        </button>
    </form>
</div>
@endsection
