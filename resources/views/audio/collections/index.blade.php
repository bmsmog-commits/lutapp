@extends('layouts.app')

@section('content')
    <div style="display:flex;align-items:center;justify-content:space-between;">
        <h1 style="margin:0;">Collections</h1>
        @auth
            <a class="btn-primary" href="{{ route('audio.collections.create') }}">New collection</a>
        @endauth
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-top:16px;">
        @forelse ($collections as $collection)
            <a href="{{ route('audio.collections.show', $collection) }}" style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:12px;text-decoration:none;color:inherit;">
                @if ($collection->cover)
                    <img src="{{ route('files.show', $collection->cover) }}" alt="" style="width:100%;height:140px;object-fit:cover;border-radius:6px;margin-bottom:8px;">
                @endif
                <strong>{{ $collection->title }}</strong>
                <div class="muted">{{ ucfirst(str_replace('_', ' ', $collection->type)) }}</div>
            </a>
        @empty
            <p class="muted">No collections found.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $collections->links() }}</div>
@endsection
