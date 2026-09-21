@extends('layouts.app')

@push('styles')
<style>
    .settings-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:16px; }
    @media (max-width: 850px) { .settings-grid { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
    <h1>Settings</h1>
    <p class="muted">Manage your account, profile, and Lutapp preferences.</p>

    <div class="settings-grid">

        {{-- ACCOUNT --}}
        <div class="panel">
            <h2>Account</h2>
            <form method="post" action="{{ route('settings.account.update') }}">
                @csrf @method('PUT')
                <div class="field">
                    <label for="name">Name</label>
                    <input class="input" type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                    <p class="muted" style="font-size:12px;margin-top:4px;">
                        {{ $user->email_verified_at ? 'Verified' : 'Not verified' }} — changing your email will require re-verification.
                    </p>
                </div>
                <button class="btn-primary" type="submit" style="margin-top:12px;">Save account</button>
            </form>
        </div>

        {{-- PROFILE --}}
        <div class="panel">
            <h2>Profile</h2>
            <p class="muted">Display name, username, bio, photo, and other profile details are managed on your profile page.</p>
            <a class="btn" href="{{ route('profile.edit') }}">Edit profile &rarr;</a>
        </div>

        {{-- LANGUAGE --}}
        <div class="panel">
            <h2>Language</h2>
            <p class="muted">Current: {{ $preference?->language ?? config('app.locale') }}</p>
            <a class="btn" href="{{ route('language.select') }}">Change language &rarr;</a>
        </div>

        {{-- APPEARANCE --}}
        <div class="panel">
            <h2>Appearance</h2>
            <form method="post" action="{{ route('settings.appearance.update') }}">
                @csrf @method('PUT')
                <div class="field">
                    <label for="theme">Theme</label>
                    <select class="input" id="theme" name="theme">
                        <option value="system" @selected($theme === 'system')>System</option>
                        <option value="light" @selected($theme === 'light')>Light</option>
                        <option value="dark" @selected($theme === 'dark')>Dark</option>
                    </select>
                </div>
                <button class="btn-primary" type="submit" style="margin-top:12px;">Save appearance</button>
            </form>
        </div>

        {{-- NOTIFICATIONS --}}
        <div class="panel">
            <h2>Notifications</h2>
            <form method="post" action="{{ route('settings.notifications.update') }}">
                @csrf @method('PUT')
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="notifications_enabled" value="1" @checked($preference?->notifications_enabled ?? true)>
                    Enable in-app notifications
                </label>
                <p class="muted" style="margin-top:8px;">Categories:</p>
                @foreach ($notificationCategories as $category)
                    <label style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                        <input type="checkbox" name="categories[]" value="{{ $category }}"
                            @checked(($preference?->notification_preferences[$category] ?? true))>
                        {{ ucfirst($category) }}
                    </label>
                @endforeach
                <button class="btn-primary" type="submit" style="margin-top:12px;">Save notification preferences</button>
            </form>
        </div>

        {{-- PRIVACY --}}
        <div class="panel">
            <h2>Privacy</h2>
            <form method="post" action="{{ route('settings.privacy.update') }}">
                @csrf @method('PUT')
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="discoverable" value="1" @checked($isDiscoverable)>
                    Appear in People search and message search
                </label>
                <button class="btn-primary" type="submit" style="margin-top:12px;">Save privacy preferences</button>
            </form>
        </div>

        {{-- BIBLE --}}
        <div class="panel">
            <h2>Bible</h2>
            <form method="post" action="{{ route('settings.bible.update') }}">
                @csrf @method('PUT')
                <div class="field">
                    <label for="translation_id">Preferred translation</label>
                    <select class="input" id="translation_id" name="translation_id">
                        <option value="">No preference</option>
                        @foreach ($translations as $translation)
                            <option value="{{ $translation->id }}" @selected($bible['translation_id'] == $translation->id)>{{ $translation->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="font_size">Text size</label>
                    <select class="input" id="font_size" name="font_size">
                        <option value="small" @selected($bible['font_size'] === 'small')>Small</option>
                        <option value="medium" @selected($bible['font_size'] === 'medium')>Medium</option>
                        <option value="large" @selected($bible['font_size'] === 'large')>Large</option>
                    </select>
                </div>
                <button class="btn-primary" type="submit" style="margin-top:12px;">Save Bible preferences</button>
            </form>
        </div>

        {{-- AUDIO --}}
        <div class="panel">
            <h2>Audio</h2>
            <form method="post" action="{{ route('settings.audio.update') }}">
                @csrf @method('PUT')
                <label style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="autoplay" value="1" @checked($audio['autoplay'])>
                    Autoplay audio when opening a track
                </label>
                <button class="btn-primary" type="submit" style="margin-top:12px;">Save audio preferences</button>
            </form>
        </div>

        {{-- DASHBOARD --}}
        <div class="panel">
            <h2>Dashboard</h2>
            <form method="post" action="{{ route('settings.dashboard.update') }}">
                @csrf @method('PUT')
                <p class="muted">Choose which sections appear on your dashboard.</p>
                @foreach ($allDashboardSections as $section)
                    <label style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                        <input type="checkbox" name="sections[]" value="{{ $section }}" @checked(in_array($section, $dashboardSections))>
                        {{ ucfirst(preg_replace('/(?<!^)[A-Z]/', ' $0', $section)) }}
                    </label>
                @endforeach
                <button class="btn-primary" type="submit" style="margin-top:12px;">Save dashboard preferences</button>
            </form>
        </div>

        {{-- SECURITY --}}
        <div class="panel">
            <h2>Security</h2>

            <form method="post" action="{{ route('settings.password.update') }}" style="margin-bottom:20px;">
                @csrf @method('PUT')
                <div class="field">
                    <label for="current_password">Current password</label>
                    <input class="input" type="password" id="current_password" name="current_password" required>
                </div>
                <div class="field">
                    <label for="password">New password</label>
                    <input class="input" type="password" id="password" name="password" required>
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirm new password</label>
                    <input class="input" type="password" id="password_confirmation" name="password_confirmation" required>
                </div>
                <button class="btn-primary" type="submit" style="margin-top:12px;">Change password</button>
            </form>

            <form method="post" action="{{ route('settings.security-question.update') }}">
                @csrf @method('PUT')
                <div class="field">
                    <label for="security_question">Security question</label>
                    <input class="input" type="text" id="security_question" name="security_question" value="{{ old('security_question', $securityQuestion?->question) }}" required>
                </div>
                <div class="field">
                    <label for="security_answer">Answer</label>
                    <input class="input" type="text" id="security_answer" name="security_answer" required placeholder="Enter a new answer">
                </div>
                <button class="btn-primary" type="submit" style="margin-top:12px;">Update security question</button>
            </form>
        </div>

    </div>
@endsection
