@extends('layouts.app')

@section('content')
    <div class="auth-shell">
        <section class="auth-card">
            <h1>Create account</h1>
            <p class="muted">Your notes stay attached to your login.</p>

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

                <div style="padding: 16px; background: #f0f7ff; border-radius: 4px; margin: 24px 0; border-left: 4px solid #fbbc04;">
                    <p style="margin-top: 0; color: #5f6368; font-size: 14px;">
                        <strong>🔒 Security Question</strong><br>
                        We'll use this question to help you recover your account if you forget your password.
                    </p>
                </div>

                <div class="field">
                    <label for="security_question">Security Question</label>
                    <select class="input" id="security_question" name="security_question" required style="width: 100%; padding: 12px; border: 1px solid #dadce0; border-radius: 4px;">
                        <option value="">Select a security question...</option>
                        <option value="What is your uncle's name?">What is your uncle's name?</option>
                        <option value="Who was your childhood best friend?">Who was your childhood best friend?</option>
                        <option value="What is your father's name?">What is your father's name?</option>
                        <option value="What is your childhood nickname?">What is your childhood nickname?</option>
                        <option value="What was the name of your first pet?">What was the name of your first pet?</option>
                        <option value="What city were you born in?">What city were you born in?</option>
                        <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                        <option value="What was the name of your first school?">What was the name of your first school?</option>
                    </select>
                </div>

                <div class="field">
                    <label for="security_answer">Your Answer</label>
                    <input class="input" id="security_answer" name="security_answer" type="text" placeholder="Answer to your security question" required>
                    <small style="color: #5f6368; display: block; margin-top: 8px;">
                        Keep this answer safe and remember it. You'll need it to recover your account.
                    </small>
                </div>

                <div class="field">
                    <button class="btn-primary" type="submit">Register</button>
                    <a class="btn" href="{{ route('login') }}">Login instead</a>
                </div>
            </form>
        </section>
    </div>
@endsection

