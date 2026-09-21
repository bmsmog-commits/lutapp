@extends('layouts.app')

@section('content')
    <div style="display:flex;gap:16px;flex-wrap:wrap;">
        @if ($audio->cover)
            <img src="{{ route('files.show', $audio->cover) }}" alt="" style="width:160px;height:160px;border-radius:8px;object-fit:cover;">
        @endif

        <div>
            <h1 style="margin:0;">{{ $audio->title }}</h1>
            <p class="muted">
                {{ $audio->creatorDisplayName() }}
                @if ($audio->category) &middot; {{ $audio->category }} @endif
                @if ($audio->language) &middot; {{ $audio->language->name }} @endif
                &middot; {{ ucfirst($audio->status) }}
            </p>

            @if ($audio->organization)
                <p class="muted">{{ $audio->organization->name }}</p>
            @endif
        </div>
    </div>

    @if ($audio->description)
        <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;margin:12px 0;">{{ $audio->description }}</div>
    @endif

    @if ($audio->audioMedia)
        <audio controls preload="none" @if ($autoplay) autoplay @endif style="width:100%;margin:16px 0;" src="{{ route('files.show', $audio->audioMedia) }}">
            Your browser does not support the audio element.
        </audio>
    @else
        <p class="muted">No audio file uploaded yet.</p>
    @endif

    @if ($audio->collections->isNotEmpty())
        <p class="muted">Part of:
            @foreach ($audio->collections as $collection)
                <a href="{{ route('audio.collections.show', $collection) }}">{{ $collection->title }}</a>@if (! $loop->last), @endif
            @endforeach
        </p>
    @endif

    @if ($canManage)
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;">
            <a class="btn" href="{{ route('audio.edit', $audio) }}">Edit</a>
            @if ($audio->status === 'draft')
                <form method="post" action="{{ route('audio.publish', $audio) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn-primary" type="submit">Publish</button></form>
            @endif
            @if ($audio->status !== 'archived')
                <form method="post" action="{{ route('audio.archive', $audio) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn" type="submit">Archive</button></form>
            @endif
            <form method="post" action="{{ route('audio.destroy', $audio) }}" onsubmit="return confirm('Delete this audio?');">
                @csrf @method('DELETE')
                <button class="btn-danger" type="submit">Delete</button>
            </form>
        </div>
    @endif

    @include('reports._form', ['reportableType' => \App\Models\Report::TARGET_AUDIO, 'reportableId' => $audio->id])
@endsection
