@extends('layouts.app')

@push('styles')
<style>
    .workspace { display: grid; grid-template-columns: 380px 1fr; gap: 18px; }
    .panel, .hymn { border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 16px; }
    .stack { display: grid; gap: 12px; }
    .hymn { margin-bottom: 12px; }
    .lyrics { white-space: pre-wrap; line-height: 1.55; }
    @media (max-width: 820px) { .workspace { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
    <h1>Hymns and lyrics</h1>
    <form method="get" action="{{ route('hymns.index') }}" style="margin-bottom: 16px;">
        <input class="input" name="q" value="{{ $search }}" placeholder="Search hymns, numbers, or lyrics">
    </form>
    <div class="workspace">
        <section class="panel">
            <form class="stack" method="post" action="{{ route('hymns.store') }}">
                @csrf
                <input class="input" name="title" placeholder="Hymn title" required>
                <input class="input" name="hymn_number" placeholder="Number">
                <input class="input" name="author" placeholder="Author">
                <textarea name="lyrics" placeholder="Lyrics" required></textarea>
                <button class="btn-primary" type="submit">Add hymn</button>
            </form>
        </section>
        <section>
            @forelse ($hymns as $hymn)
                <article class="hymn">
                    <h2>{{ $hymn->hymn_number ? '#'.$hymn->hymn_number.' ' : '' }}{{ $hymn->title }}</h2>
                    @if ($hymn->author)
                        <div class="muted">{{ $hymn->author }}</div>
                    @endif
                    <p class="lyrics">{{ $hymn->lyrics }}</p>
                    <form method="post" action="{{ route('hymns.destroy', $hymn) }}">
                        @csrf
                        @method('delete')
                        <button class="btn-danger" type="submit">Delete</button>
                    </form>
                </article>
            @empty
                <p class="muted">No hymns yet. Add public-domain or church-owned lyrics here.</p>
            @endforelse
        </section>
    </div>
@endsection
