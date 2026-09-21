@extends('layouts.app')

@section('content')
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <h1 style="margin:0;">{{ $collection->title }}</h1>
        <span class="muted">{{ ucfirst(str_replace('_', ' ', $collection->type)) }} &middot; {{ ucfirst($collection->status) }}</span>
    </div>

    @if ($collection->description)
        <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;margin:12px 0;">{{ $collection->description }}</div>
    @endif

    @if ($canManage)
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
            <a class="btn" href="{{ route('audio.collections.edit', $collection) }}">Edit</a>
            @if ($collection->status === 'draft')
                <form method="post" action="{{ route('audio.collections.publish', $collection) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn-primary" type="submit">Publish</button></form>
            @endif
            @if ($collection->status !== 'archived')
                <form method="post" action="{{ route('audio.collections.archive', $collection) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="btn" type="submit">Archive</button></form>
            @endif
        </div>

        <form method="post" action="{{ route('audio.collections.items.store', $collection) }}" style="display:flex;gap:8px;margin-bottom:16px;">
            @csrf
            <input class="input" type="number" name="audio_resource_id" placeholder="Audio ID to add" required style="max-width:200px;">
            <button class="btn" type="submit">Add to collection</button>
        </form>
    @endif

    <ol style="display:flex;flex-direction:column;gap:8px;padding-left:20px;">
        @forelse ($collection->items as $item)
            <li style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:10px;display:flex;justify-content:space-between;align-items:center;">
                <a href="{{ route('audio.show', $item) }}">{{ $item->title }}</a>
                @if ($canManage)
                    <form method="post" action="{{ route('audio.collections.items.destroy', [$collection, $item]) }}">
                        @csrf @method('DELETE')
                        <button class="btn" type="submit">Remove</button>
                    </form>
                @endif
            </li>
        @empty
            <li class="muted">No audio in this collection yet.</li>
        @endforelse
    </ol>
@endsection
