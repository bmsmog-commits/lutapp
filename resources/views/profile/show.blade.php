@extends('layouts.app')

@section('content')
    <h1>My profile</h1>

    <div class="panel" style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:16px;max-width:520px;">
        <p><strong>Name:</strong> {{ auth()->user()->name }}</p>
        <p><strong>Username:</strong> {{ $profile?->username ?? '—' }}</p>
        <p><strong>Display name:</strong> {{ $profile?->display_name ?? '—' }}</p>
        <p><strong>Bio:</strong> {{ $profile?->bio ?? '—' }}</p>
        <p><strong>Phone:</strong> {{ $profile?->phone ?? '—' }}</p>
        <p><strong>Location:</strong> {{ implode(', ', array_filter([$profile?->city, $profile?->state, $profile?->country])) ?: '—' }}</p>
        <p><strong>Preferred language:</strong> {{ $preference?->language ?? 'en' }}</p>
        <a class="btn" href="{{ route('profile.edit') }}">Edit profile</a>
    </div>
@endsection
