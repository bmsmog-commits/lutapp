@extends('layouts.app')

@section('content')
    <div style="display:flex;gap:20px;flex-wrap:wrap;">
        <div style="width:200px;">
            @if ($resource->cover)
                <img src="{{ route('files.show', $resource->cover) }}" alt="" style="width:100%;border-radius:8px;">
            @else
                <div style="width:100%;height:260px;background:var(--surface);border-radius:8px;display:grid;place-items:center;color:var(--muted);">No cover</div>
            @endif
        </div>
        <div style="flex:1;min-width:260px;">
            <h1>{{ $resource->title }}</h1>
            <p class="muted">
                {{ $resource->author ?? 'Unknown author' }}
                @if ($resource->publisher) &middot; {{ $resource->publisher }} @endif
                @if ($resource->language) &middot; {{ $resource->language->name }} @endif
                @if ($resource->category) &middot; {{ $resource->category }} @endif
            </p>
            @if ($canManage)
                <p class="muted">Status: {{ ucfirst($resource->status) }} &middot; Visibility: {{ ucfirst($resource->visibility) }}</p>
            @endif

            @if ($resource->description)
                <p>{{ $resource->description }}</p>
            @endif

            <p>
                @if ($resource->file)
                    <a class="btn-primary" href="{{ route('files.show', $resource->file) }}">Download</a>
                @endif
                @auth
                    <form method="post" action="{{ route($isSaved ? 'resources.unsave' : 'resources.save', $resource) }}" style="display:inline;">
                        @csrf
                        @if ($isSaved) @method('delete') @endif
                        <button class="btn" type="submit">{{ $isSaved ? 'Unsave' : 'Save' }}</button>
                    </form>
                @endauth
                @if ($canManage)
                    <a class="btn" href="{{ route('resources.edit', $resource) }}">Edit</a>
                    @if ($resource->status !== 'published')
                        <form method="post" action="{{ route('resources.publish', $resource) }}" style="display:inline;">
                            @csrf
                            <button class="btn" type="submit">Publish</button>
                        </form>
                    @endif
                    @if ($resource->status !== 'archived')
                        <form method="post" action="{{ route('resources.archive', $resource) }}" style="display:inline;">
                            @csrf
                            <button class="btn" type="submit">Archive</button>
                        </form>
                    @endif
                    <form method="post" action="{{ route('resources.destroy', $resource) }}" style="display:inline;">
                        @csrf
                        @method('delete')
                        <button class="btn-danger" type="submit" onclick="return confirm('Delete this resource?')">Delete</button>
                    </form>
                @endif
            </p>

            @if ($canManage)
                <div style="margin-top:16px;display:grid;gap:10px;max-width:360px;">
                    <form method="post" action="{{ route('resources.cover.store', $resource) }}" enctype="multipart/form-data">
                        @csrf
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" required>
                        <button class="btn" type="submit">Upload cover</button>
                    </form>
                    <form method="post" action="{{ route('resources.file.store', $resource) }}" enctype="multipart/form-data">
                        @csrf
                        <input type="file" name="file" accept=".pdf,.epub" required>
                        <button class="btn" type="submit">Upload book file</button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    @include('reports._form', ['reportableType' => \App\Models\Report::TARGET_RESOURCE, 'reportableId' => $resource->id])
@endsection
