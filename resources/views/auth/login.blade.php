@extends('layouts.app')

@section('content')
    <div class="auth-shell">
        <section class="auth-card">
            <h1>Welcome back</h1>
            <p class="muted">Sign in to open your notes.</p>

            <form method="post" action="{{ route('login') }}">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input class="input" id="password" name="password" type="password" required>
                </div>
                <div class="field">
                    <label>
                        <input type="checkbox" name="remember" value="1">
                        Remember me
                    </label>
                </div>
                <div class="field">
                    <button class="btn-primary" type="submit">Login</button>
                    <a class="btn" href="{{ route('register') }}">Create account</a>
                </div>
            </form>
        </section>
    </div>
@endsection
