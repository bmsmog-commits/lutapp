@extends('layouts.app')

@section('content')
    <h1>Edit profile</h1>

    <form class="stack" method="post" action="{{ route('profile.update') }}" style="max-width:520px;display:grid;gap:12px;">
        @csrf
        @method('put')

        <div class="field">
            <label for="username">Username</label>
            <input class="input" id="username" name="username" value="{{ old('username', $profile?->username) }}" required>
        </div>

        <div class="field">
            <label for="display_name">Display name</label>
            <input class="input" id="display_name" name="display_name" value="{{ old('display_name', $profile?->display_name) }}">
        </div>

        <div class="field">
            <label for="bio">Bio</label>
            <textarea id="bio" name="bio">{{ old('bio', $profile?->bio) }}</textarea>
        </div>

        <div class="field">
            <label for="phone">Phone</label>
            <input class="input" id="phone" name="phone" value="{{ old('phone', $profile?->phone) }}">
        </div>

        <div class="field">
            <label for="country">Country</label>
            <input class="input" id="country" name="country" value="{{ old('country', $profile?->country) }}">
        </div>

        <div class="field">
            <label for="state">State/Province</label>
            <input class="input" id="state" name="state" value="{{ old('state', $profile?->state) }}">
        </div>

        <div class="field">
            <label for="city">City</label>
            <input class="input" id="city" name="city" value="{{ old('city', $profile?->city) }}">
        </div>

        <div class="field">
            <label for="address">Address</label>
            <input class="input" id="address" name="address" value="{{ old('address', $profile?->address) }}">
        </div>

        <div class="field">
            <label for="postal_code">Postal code</label>
            <input class="input" id="postal_code" name="postal_code" value="{{ old('postal_code', $profile?->postal_code) }}">
        </div>

        <div class="field">
            <label for="language">Preferred language</label>
            <select class="input" id="language" name="language">
                <option value="">— Keep current —</option>
                @foreach ($languages as $language)
                    <option value="{{ $language->code }}" @selected(old('language', $preference?->language) === $language->code)>
                        {{ $language->name }} ({{ $language->native_name }})
                    </option>
                @endforeach
            </select>
        </div>

        <button class="btn-primary" type="submit">Save profile</button>
    </form>
@endsection
