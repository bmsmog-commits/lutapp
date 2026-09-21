@extends('layouts.app')

@section('content')
    <h1>Resource Library</h1>

    <form method="get" action="{{ route('resources.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <input class="input" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search title or author" style="max-width:240px;">
        <select class="input" name="category" style="max-width:200px;">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category }}" @selected(($filters['category'] ?? null) === $category)>{{ $category }}</option>
            @endforeach
        </select>
        <select class="input" name="language_id" style="max-width:200px;">
            <option value="">All languages</option>
            @foreach ($languages as $language)
                <option value="{{ $language->id }}" @selected((string) ($filters['language_id'] ?? '') === (string) $language->id)>{{ $language->name }}</option>
            @endforeach
        </select>
        <button class="btn-primary" type="submit">Filter</button>
    </form>

    @auth
        <p>
            <a class="btn" href="{{ route('resources.create') }}">Add personal resource</a>
            <a class="btn" href="{{ route('resources.saved') }}">Saved resources</a>
        </p>
    @endauth

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;">
        @forelse ($resources as $resource)
            <a href="{{ route('resources.show', $resource) }}" style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:12px;text-decoration:none;color:inherit;">
                @if ($resource->cover)
                    <img src="{{ route('files.show', $resource->cover) }}" alt="" style="width:100%;height:140px;object-fit:cover;border-radius:6px;margin-bottom:8px;">
                @else
                    <div style="width:100%;height:140px;background:var(--surface);border-radius:6px;margin-bottom:8px;display:grid;place-items:center;color:var(--muted);">No cover</div>
                @endif
                <strong>{{ $resource->title }}</strong>
                <div class="muted">{{ $resource->author ?? 'Unknown author' }}</div>
            </a>
        @empty
            <p class="muted">No resources found.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $resources->links() }}</div>
@endsection
