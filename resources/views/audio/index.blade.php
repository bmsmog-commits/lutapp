@extends('layouts.app')

@section('content')
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <h1 style="margin:0;">Audio Library</h1>
        @auth
            <a class="btn-primary" href="{{ route('audio.create') }}">Upload audio</a>
        @endauth
    </div>

    @auth
        <div style="display:flex;gap:8px;margin:12px 0;">
            <a class="btn{{ $scope === 'public' ? '-primary' : '' }}" href="{{ route('audio.index', ['scope' => 'public']) }}">Public Audio</a>
            <a class="btn{{ $scope === 'mine' ? '-primary' : '' }}" href="{{ route('audio.index', ['scope' => 'mine']) }}">My Audio</a>
            <a class="btn{{ $scope === 'organization' ? '-primary' : '' }}" href="{{ route('audio.index', ['scope' => 'organization']) }}">Organization Audio</a>
        </div>
    @endauth

    <form method="get" action="{{ route('audio.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <input type="hidden" name="scope" value="{{ $scope }}">
        <input class="input" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search title or creator" style="max-width:220px;">
        <select class="input" name="category" style="max-width:180px;">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category }}" @selected(($filters['category'] ?? null) === $category)>{{ $category }}</option>
            @endforeach
        </select>
        <select class="input" name="language_id" style="max-width:180px;">
            <option value="">All languages</option>
            @foreach ($languages as $language)
                <option value="{{ $language->id }}" @selected((string) ($filters['language_id'] ?? '') === (string) $language->id)>{{ $language->name }}</option>
            @endforeach
        </select>
        <button class="btn-primary" type="submit">Filter</button>
    </form>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;">
        @forelse ($resources as $audio)
            <a href="{{ route('audio.show', $audio) }}" style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:12px;text-decoration:none;color:inherit;">
                @if ($audio->cover)
                    <img src="{{ route('files.show', $audio->cover) }}" alt="" style="width:100%;height:140px;object-fit:cover;border-radius:6px;margin-bottom:8px;">
                @else
                    <div style="width:100%;height:140px;background:var(--surface);border-radius:6px;margin-bottom:8px;display:grid;place-items:center;color:var(--muted);">No cover</div>
                @endif
                <strong>{{ $audio->title }}</strong>
                <div class="muted">{{ $audio->creatorDisplayName() }}</div>
                <div class="muted" style="font-size:12px;">
                    @if ($audio->category) {{ $audio->category }} @endif
                    @if ($scope !== 'public') &middot; {{ ucfirst($audio->status) }} @endif
                </div>
            </a>
        @empty
            <p class="muted">No audio found.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $resources->links() }}</div>
@endsection
