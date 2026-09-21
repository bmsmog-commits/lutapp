@extends('layouts.app')

@section('content')
    <h1>Saved resources</h1>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;">
        @forelse ($resources as $resource)
            <a href="{{ route('resources.show', $resource) }}" style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:12px;text-decoration:none;color:inherit;">
                @if ($resource->cover)
                    <img src="{{ route('files.show', $resource->cover) }}" alt="" style="width:100%;height:140px;object-fit:cover;border-radius:6px;margin-bottom:8px;">
                @endif
                <strong>{{ $resource->title }}</strong>
                <div class="muted">{{ $resource->author ?? 'Unknown author' }}</div>
            </a>
        @empty
            <p class="muted">You haven't saved any resources yet.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $resources->links() }}</div>
@endsection
