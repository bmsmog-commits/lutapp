@extends('layouts.app')

@section('page-context', __('messages.reading_history'))

@push('styles')
<style>
    /* Phase 27.7 — brought onto the Lutapp design system. The previous markup
       was hand-written Tailwind (blue-600 / gray-200) from before Tailwind was
       actually loaded, so it read as a different product. Same data, same
       pagination, same routes. */
    .history-list { display: grid; gap: 12px; }
    .history-item {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        border: 1px solid var(--line);
        border-left: 3px solid var(--accent-bible);
        border-radius: var(--radius);
        background: var(--card);
        padding: 16px 18px;
        box-shadow: var(--shadow-sm);
        transition: transform .15s ease, border-color .15s ease;
    }
    .history-item:hover { transform: translateY(-1px); }
    .history-body { min-width: 0; flex: 1 1 260px; }
    .history-body h3 { margin: 0 0 3px; font-size: 16px; }
    .history-body h3 a { color: var(--accent-bible); text-decoration: none; }
    .history-body h3 a:hover { text-decoration: underline; }
    .history-meta { margin: 0; color: var(--muted); font-size: 13px; }
    .history-progress { width: 100%; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--line); }
    .history-progress p { margin: 0 0 6px; color: var(--muted); font-size: 11.5px; }
    .progress-track {
        height: 6px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--accent-bible) 12%, var(--line));
        overflow: hidden;
    }
    .progress-fill { height: 6px; border-radius: 999px; background: var(--accent-bible); }

    .history-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 24px; }
    .history-stat { text-align: center; padding: 18px 14px; }
    .history-stat strong { display: block; font-size: 26px; line-height: 1.15; }
    .history-stat span { color: var(--muted); font-size: 12.5px; font-weight: 600; }
    .history-pagination { margin-top: 26px; }

    @media (max-width: 560px) {
        .history-stats { grid-template-columns: minmax(0, 1fr); }
    }
</style>
@endpush

@section('content')
<x-page-header
    :title="__('messages.reading_history')"
    :subtitle="$history->total().' '.__('messages.chapters_read')"
    accent="var(--accent-bible)">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('bible.index') }}">{{ __('messages.bible') }}</a>
        <a class="btn" href="{{ route('bible.bookmarks') }}">★ {{ __('messages.bookmarks') }}</a>
    </x-slot:actions>
</x-page-header>

<p class="muted" style="margin:-6px 0 18px;font-size:12.5px;">{{ __('messages.continue_reading_window') }}</p>

@if($history->count() > 0)
    <div class="history-list">
        @foreach($history as $record)
            <article class="history-item">
                <div class="history-body">
                    <h3>
                        <a href="{{ route('bible.index', ['book' => $record->bible_book_id, 'chapter' => $record->chapter, 'translation_id' => $record->translation_id]) }}">
                            {{ $record->book->name }} {{ $record->chapter }}
                        </a>
                    </h3>
                    <p class="history-meta">{{ $record->translation->name }} ({{ $record->translation->code }})</p>
                    <p class="history-meta">
                        {{ __('messages.last_read') }}:
                        <strong>{{ $record->last_read_at->format(__('messages.datetime_format')) }}</strong>
                    </p>
                </div>
                <span class="badge badge-brand">{{ __('messages.chapter') }} {{ $record->chapter }}</span>

                @if($record->book->chapters_count > 1)
                    <div class="history-progress">
                        <p>{{ __('messages.progress') }}: {{ $record->chapter }} / {{ $record->book->chapters_count }}</p>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: {{ ($record->chapter / $record->book->chapters_count * 100) }}%"></div>
                        </div>
                    </div>
                @endif
            </article>
        @endforeach
    </div>

    <div class="history-pagination">
        {{ $history->links() }}
    </div>

    <div class="history-stats">
        <div class="panel history-stat">
            <strong style="color:var(--accent-bible);">{{ $history->total() }}</strong>
            <span>{{ __('messages.chapters_read') }}</span>
        </div>
        <div class="panel history-stat">
            <strong style="color:var(--brand-strong);">{{ auth()->user()->bibleBookmarks()->count() }}</strong>
            <span>{{ __('messages.bookmarks') }}</span>
        </div>
        <div class="panel history-stat">
            <strong style="color:var(--accent-audio);">{{ auth()->user()->bibleHighlights()->count() }}</strong>
            <span>{{ __('messages.highlights') }}</span>
        </div>
    </div>
@else
    <x-empty-state
        icon="◷"
        accent="var(--accent-bible)"
        tint="#f2ebfd"
        :title="__('messages.no_reading_history')">
        <p>{{ __('messages.start_reading_to_track') }}</p>
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('bible.index') }}">{{ __('messages.start_reading') }}</a>
        </x-slot:actions>
    </x-empty-state>
@endif
@endsection
