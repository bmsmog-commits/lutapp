@extends('layouts.app')

@section('content')
    <div class="auth-shell">
        <section class="auth-card">
            <h1>Create account</h1>
            <p class="muted">Your notes stay attached to your login.</p>

            <form method="post" action="{{ route('register') }}">
                @csrf
                <div class="field">
                    <label for="name">Name</label>
                    <input class="input" id="name" name="name" value="{{ old('name') }}" required autofocus>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input class="input" id="password" name="password" type="password" required>
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirm password</label>
                    <input class="input" id="password_confirmation" name="password_confirmation" type="password" required>
                </div>
                <div class="field">
                    <button class="btn-primary" type="submit">Register</button>
                    <a class="btn" href="{{ route('login') }}">Login instead</a>
                </div>
            </form>
        </section>
    </div>
@endsection
