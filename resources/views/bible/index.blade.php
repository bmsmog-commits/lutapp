@extends('layouts.app')

@push('styles')
<style>
    .reader { display: grid; grid-template-columns: 280px 1fr; gap: 18px; }
    .panel, .verse-card { border: 1px solid var(--line); border-radius: 8px; background: #fff; padding: 16px; }
    .book-list { display: grid; gap: 6px; max-height: 68vh; overflow: auto; }
    .book-list a { border-radius: 8px; padding: 8px; text-decoration: none; }
    .book-list a:hover { background: #f1f3f4; }
    .chapters { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0 18px; }
    .chapters a { border: 1px solid var(--line); border-radius: 8px; padding: 7px 10px; text-decoration: none; }
    .verse { display: grid; grid-template-columns: 40px 1fr; gap: 10px; padding: 8px 0; }
    .verse-number { color: var(--muted); }
    @media (max-width: 760px) { .reader { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
    <h1>Bible</h1>
    <div class="reader">
        <aside class="panel">
            <div class="book-list">
                @foreach ($books as $item)
                    <a href="{{ route('bible.index', ['book' => $item->id, 'chapter' => 1]) }}">{{ $item->name }}</a>
                @endforeach
            </div>
        </aside>
        <section class="verse-card">
            @if ($book)
                <h2>{{ $book->name }} {{ $chapter }}</h2>
                <div class="chapters">
                    @for ($i = 1; $i <= $book->chapters_count; $i++)
                        <a href="{{ route('bible.index', ['book' => $book->id, 'chapter' => $i]) }}">{{ $i }}</a>
                    @endfor
                </div>

                @forelse ($verses as $verse)
                    <div class="verse">
                        <span class="verse-number">{{ $verse->verse }}</span>
                        <span>{{ $verse->text }}</span>
                    </div>
                @empty
                    <p class="muted">Bible books are ready, but verse text has not been imported yet.</p>
                    <p class="muted">Use a public-domain translation such as KJV, or a translation your church has permission to store.</p>
                @endforelse
            @else
                <p class="muted">No Bible data has been seeded yet.</p>
            @endif
        </section>
    </div>
@endsection
