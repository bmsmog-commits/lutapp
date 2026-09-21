@extends('layouts.app')

@section('content')
    <h1>My profile</h1>

    <div class="panel" style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:16px;max-width:520px;">
        @if ($profile?->profilePhoto)
            <img src="{{ route('files.show', $profile->profilePhoto) }}" alt="Profile photo" style="width:96px;height:96px;border-radius:50%;object-fit:cover;margin-bottom:12px;">
        @else
            <div style="width:96px;height:96px;border-radius:50%;background:var(--surface);border:1px solid var(--line);display:grid;place-items:center;color:var(--muted);margin-bottom:12px;">No photo</div>
        @endif

        <form method="post" action="{{ route('profile.photo.store') }}" enctype="multipart/form-data" style="margin-bottom:6px;">
            @csrf
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
            <button class="btn" type="submit">Upload photo</button>
        </form>
        @if ($profile?->profilePhoto)
            <form method="post" action="{{ route('profile.photo.destroy') }}" style="margin-bottom:12px;">
                @csrf
                @method('delete')
                <button class="btn-danger" type="submit" onclick="return confirm('Remove your profile photo?')">Remove photo</button>
            </form>
        @endif

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
