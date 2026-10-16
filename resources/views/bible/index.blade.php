@extends('layouts.app')

@section('page-context', __('messages.bible'))

@push('styles')
<style>
    .bible-shell {
        display: grid;
        grid-template-columns: 300px minmax(0, 1fr);
        gap: 22px;
        align-items: start;
    }
    @media (max-width: 1023px) { .bible-shell { grid-template-columns: 1fr; } }

    .bible-side { position: sticky; top: 76px; }
    @media (max-width: 1023px) { .bible-side { position: static; } }

    .bible-side h2 {
        margin: 0 0 10px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: var(--muted);
    }
    .translation-option {
        display: flex;
        align-items: center;
        gap: 9px;
        margin-bottom: 4px;
        border: 1px solid transparent;
        border-radius: var(--radius-sm);
        padding: 8px 9px;
        cursor: pointer;
    }
    .translation-option:hover { background: color-mix(in srgb, #f4ecff 70%, var(--card)); }
    /* Phase 27.7 — selection is a tinted surface with a violet edge, not a
       saturated fill; the accent stays on the border and the state badge. */
    .translation-option.is-selected {
        border-color: color-mix(in srgb, var(--accent-bible) 55%, transparent);
        background: color-mix(in srgb, #f4ecff 75%, var(--card));
    }
    .translation-option input[type="radio"] { flex: 0 0 auto; accent-color: var(--accent-bible); margin: 0; }
    .translation-option .t-name { flex: 1; min-width: 0; font-size: 13.5px; font-weight: 600; }
    .translation-option .t-lang { font-size: 11px; color: var(--muted); display: block; font-weight: 400; }
    .translation-option .badge { flex: 0 0 auto; max-width: 46%; }
    .translation-option .t-state {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .02em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .book-list {
        max-height: 420px;
        overflow-y: auto;
        overscroll-behavior: contain;
        margin-top: 6px;
        padding-right: 4px;
        scrollbar-width: thin;
        scrollbar-color: var(--line) transparent;
    }
    .book-list::-webkit-scrollbar { width: 8px; }
    .book-list::-webkit-scrollbar-track { background: transparent; }
    .book-list::-webkit-scrollbar-thumb {
        border: 2px solid transparent;
        border-radius: 999px;
        background: var(--line);
        background-clip: content-box;
    }
    .book-list::-webkit-scrollbar-thumb:hover { background: var(--muted); background-clip: content-box; }
    .book-link {
        display: block;
        border-radius: 8px;
        padding: 7px 10px;
        font-size: 13.5px;
        text-decoration: none;
        transition: background .14s ease, box-shadow .14s ease;
    }
    .book-link:hover { background: color-mix(in srgb, #f4ecff 70%, var(--card)); }
    /* The current book is marked with a tinted surface and an inset violet
       rule rather than a full-bleed violet block. */
    .book-link.is-current {
        background: color-mix(in srgb, #f4ecff 80%, var(--card));
        box-shadow: inset 3px 0 0 var(--accent-bible);
        color: var(--accent-bible);
        font-weight: 700;
    }
    /* Continue-reading entries reuse .book-link but carry a second meta line,
       which previously fell outside the scoped .translation-option rule and so
       rendered unstyled. */
    .book-link .t-lang {
        display: block;
        margin-top: 1px;
        color: var(--muted);
        font-size: 11px;
        font-weight: 400;
    }

    /* Comfortable reading column — deliberately not full-bleed. */
    .reader { max-width: 70ch; }
    .verse-row {
        display: flex;
        gap: 14px;
        border-radius: var(--radius-sm);
        padding: 8px 10px;
        margin: 0 -10px;
    }
    .verse-row:hover { background: #faf7ff; }
    .verse-row:hover .verse-tools { opacity: 1; }
    .verse-num {
        flex: 0 0 auto;
        width: 26px;
        padding-top: 3px;
        text-align: right;
        color: var(--accent-bible);
        font-size: 12px;
        font-weight: 700;
    }
    .verse-text {
        flex: 1;
        min-width: 0;
        font-family: 'Iowan Old Style', 'Palatino Linotype', 'Book Antiqua', Georgia, serif;
        line-height: 1.78;
    }
    .verse-tools {
        flex: 0 0 auto;
        display: flex;
        gap: 2px;
        opacity: .35;
        transition: opacity .15s ease;
    }
    .verse-tools:focus-within { opacity: 1; }
    .verse-tools button {
        border: none;
        background: none;
        padding: 3px 5px;
        font-size: 14px;
        line-height: 1;
        border-radius: 6px;
    }
    .verse-tools button:hover { background: var(--brand-soft); }
    .is-bookmarked { color: var(--brand-strong); }

    .hl-yellow { background: #fef7d3; box-shadow: inset 3px 0 0 #eab308; }
    .hl-red    { background: #fde8e8; box-shadow: inset 3px 0 0 #dc2626; }
    .hl-blue   { background: #e5eeff; box-shadow: inset 3px 0 0 #2563eb; }
    .hl-green  { background: #e3f7ec; box-shadow: inset 3px 0 0 #059669; }
    .hl-orange { background: #ffeede; box-shadow: inset 3px 0 0 #ea580c; }
    .hl-purple { background: #f1e9fd; box-shadow: inset 3px 0 0 #7c3aed; }

    .hl-menu { position: relative; }
    .hl-menu-items {
        position: absolute;
        right: 0;
        top: 100%;
        z-index: 20;
        display: none;
        width: 150px;
        border: 1px solid var(--line);
        border-radius: var(--radius-sm);
        background: var(--card);
        box-shadow: var(--shadow-lg);
        padding: 5px;
    }
    .hl-menu:hover .hl-menu-items,
    .hl-menu:focus-within .hl-menu-items { display: block; }
    .hl-menu-items button {
        width: 100%;
        text-align: left;
        font-size: 13px;
        padding: 6px 8px;
    }

    .chapter-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--line);
        padding-bottom: 14px;
        margin-bottom: 20px;
    }
</style>
@endpush

@section('content')
<x-page-header
    :title="__('messages.bible')"
    :subtitle="$currentBook ? $currentBook->name.' '.$currentChapter.' — '.$selectedTranslation->name : __('messages.select_book_to_start')"
    accent="var(--accent-bible)">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('bible.study', array_filter(['book' => $currentBook?->id, 'chapter' => $currentChapter, 'translation_id' => $selectedTranslation->id])) }}">✎ {{ __('messages.bible_study') }}</a>
        <a class="btn" href="{{ route('bible.bookmarks') }}">★ {{ __('messages.bookmarks') }}</a>
        <a class="btn" href="{{ route('bible.reading-history') }}">◷ {{ __('messages.reading_history') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="bible-shell">
    {{-- Sidebar: translations + books --}}
    <aside class="panel bible-side">
        <h2>{{ __('messages.translation') }}</h2>
        <form method="GET">
            @foreach($translations as $translation)
                @php
                    $info = $availability[$translation->id] ?? ['has_text' => false, 'state' => 'not_imported'];
                    $isSelected = $selectedTranslation->id === $translation->id;
                @endphp
                <label class="translation-option {{ $isSelected ? 'is-selected' : '' }}">
                    <input type="radio" name="translation_id" value="{{ $translation->id }}"
                        {{ $isSelected ? 'checked' : '' }}
                        onchange="this.form.submit()">
                    <span class="t-name">
                        {{ $translation->name }}
                        <span class="t-lang">{{ $translation->language }}</span>
                    </span>
                    {{-- Phase 27.6 — the richer state label. "Available" is still
                         only ever produced by real verse rows existing locally. --}}
                    @php $readable = $info['has_text'] || ($info['remote'] ?? false); @endphp
                    <span class="badge {{ $readable ? 'badge-ok' : 'badge-muted' }}"
                          data-state="{{ $info['state'] }}"
                          title="{{ __('messages.translation_state_'.$info['state']) }}">
                        {{ $readable ? '✓' : '—' }}
                        <span class="t-state">{{ __('messages.translation_state_'.$info['state']) }}</span>
                    </span>
                </label>
            @endforeach
        </form>
        <p class="muted" style="font-size:11.5px;margin:8px 2px 0;">
            <span class="badge badge-ok">✓</span> {{ __('messages.translation_available') }} &middot;
            <span class="badge badge-muted">—</span> {{ __('messages.translation_not_available') }}
        </p>

        @if (isset($continueReading) && $continueReading->isNotEmpty())
            <hr style="border:none;border-top:1px solid var(--line);margin:16px 0 10px;">
            <h2>{{ __('messages.continue_reading') }}</h2>
            <div data-testid="continue-reading">
                @foreach ($continueReading as $entry)
                    <a class="book-link"
                       href="{{ route('bible.index', ['book' => $entry->bible_book_id, 'chapter' => $entry->chapter, 'translation_id' => $entry->translation_id]) }}">
                        {{ $entry->book?->name }} {{ $entry->chapter }}
                        <span class="t-lang">{{ $entry->translation?->code }} &middot; {{ $entry->last_read_at?->diffForHumans() }}</span>
                    </a>
                @endforeach
            </div>
            <p class="muted" style="font-size:11.5px;margin:8px 2px 0;">{{ __('messages.continue_reading_window') }}</p>
        @endif

        <hr style="border:none;border-top:1px solid var(--line);margin:16px 0 10px;">

        <h2>{{ __('messages.bible_books') }}</h2>
        <div class="book-list">
            @foreach($books as $book)
                <a href="{{ route('bible.index', ['book' => $book->id, 'chapter' => 1, 'translation_id' => $selectedTranslation->id]) }}"
                   class="book-link {{ $currentBook?->id === $book->id ? 'is-current' : '' }}"
                   @if($currentBook?->id === $book->id) aria-current="page" @endif>
                    {{ $book->name }}
                </a>
            @endforeach
        </div>
    </aside>

    {{-- Reader --}}
    <div>
        @if($currentBook)
            <div class="panel">
                <div class="chapter-bar">
                    <div style="min-width:0;">
                        <h2 style="margin:0;font-size:22px;">{{ $currentBook->name }} {{ $currentChapter }}</h2>
                        <p class="muted" style="margin:2px 0 0;font-size:13px;">
                            {{ $selectedTranslation->name }} ({{ $selectedTranslation->code }}) &middot; {{ $selectedTranslation->language }}
                        </p>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        @if($currentChapter > 1)
                            <a class="btn" href="{{ route('bible.index', ['book' => $currentBook->id, 'chapter' => $currentChapter - 1, 'translation_id' => $selectedTranslation->id]) }}">← {{ __('messages.previous') }}</a>
                        @endif
                        <label class="muted" for="chapter-jump" style="font-size:12px;">{{ __('messages.chapter') }}</label>
                        <select id="chapter-jump" style="width:auto;padding:8px 10px;"
                                onchange="window.location = '{{ route('bible.index', ['book' => $currentBook->id, 'translation_id' => $selectedTranslation->id]) }}&chapter=' + this.value">
                            @for($ch = 1; $ch <= $currentBook->chapters_count; $ch++)
                                <option value="{{ $ch }}" {{ $ch == $currentChapter ? 'selected' : '' }}>{{ $ch }}</option>
                            @endfor
                        </select>
                        @if($currentChapter < $currentBook->chapters_count)
                            <a class="btn" href="{{ route('bible.index', ['book' => $currentBook->id, 'chapter' => $currentChapter + 1, 'translation_id' => $selectedTranslation->id]) }}">{{ __('messages.next') }} →</a>
                        @endif
                    </div>
                </div>

                @if($verses->isNotEmpty())
                    <div class="reader" style="font-size: {{ ['small' => '15px', 'large' => '20px'][$bibleFontSize ?? 'medium'] ?? '17px' }};">
                        @foreach($verses as $verse)
                            @php
                                $isBookmarked = $userBookmarks->firstWhere('verse', $verse->verse);
                                $highlight = $userHighlights->firstWhere('verse', $verse->verse);
                                $note = $highlight?->note ?: $isBookmarked?->note;
                            @endphp
                            <div class="verse-row {{ $highlight ? 'hl-'.$highlight->color : '' }}">
                                <span class="verse-num">{{ $verse->verse }}</span>
                                <div class="verse-text">
                                    <p style="margin:0;">{{ $verse->text }}</p>
                                    @if($note)
                                        <p class="muted" style="margin:6px 0 0;font-size:13px;font-style:italic;font-family:inherit;">📝 {{ $note }}</p>
                                    @endif
                                </div>
                                <div class="verse-tools">
                                    <form action="{{ route('bible.bookmark') }}" method="POST" class="bookmark-form" style="display:inline;">
                                        @csrf
                                        <input type="hidden" name="bible_book_id" value="{{ $currentBook->id }}">
                                        <input type="hidden" name="chapter" value="{{ $currentChapter }}">
                                        <input type="hidden" name="verse" value="{{ $verse->verse }}">
                                        <input type="hidden" name="translation_id" value="{{ $selectedTranslation->id }}">
                                        <button type="submit" class="{{ $isBookmarked ? 'is-bookmarked' : 'muted' }}"
                                                aria-label="{{ $isBookmarked ? 'Remove bookmark from verse '.$verse->verse : 'Bookmark verse '.$verse->verse }}">★</button>
                                    </form>
                                    <div class="hl-menu">
                                        <button type="button" class="muted" aria-label="{{ __('messages.highlight') }} verse {{ $verse->verse }}" aria-haspopup="true">🎨</button>
                                        <div class="hl-menu-items">
                                            @foreach(['yellow', 'red', 'blue', 'green', 'orange', 'purple'] as $color)
                                                <form action="{{ route('bible.highlight') }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="bible_book_id" value="{{ $currentBook->id }}">
                                                    <input type="hidden" name="chapter" value="{{ $currentChapter }}">
                                                    <input type="hidden" name="verse" value="{{ $verse->verse }}">
                                                    <input type="hidden" name="translation_id" value="{{ $selectedTranslation->id }}">
                                                    <input type="hidden" name="color" value="{{ $color }}">
                                                    <button type="submit" style="text-transform:capitalize;">● {{ $color }}</button>
                                                </form>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @include('bible.partials.remote-attribution')
                    </div>
                @else
                    {{--
                        Phase 27.5 empty state.

                        The chapter reference is valid and the translation is known;
                        its text simply has not been imported into this installation.
                        No verse text is ever invented here.
                    --}}
                    <x-empty-state
                        data-testid="bible-translation-unavailable"
                        icon="📖"
                        accent="var(--accent-bible)"
                        tint="#f2ebfd"
                        :data-remote-error="$remoteError"
                        :title="$remoteError ? __('messages.api_bible_error_title') : __('messages.translation_text_not_imported_title')">
                        @if ($remoteError)
                            <p>{{ __('messages.api_bible_error_'.$remoteError) }}</p>
                        @else
                            <p>
                                {{ __('messages.translation_text_not_imported_body', [
                                    'reference' => $currentBook->name.' '.$currentChapter,
                                    'translation' => $selectedTranslation->name.' ('.$selectedTranslation->code.')',
                                ]) }}
                            </p>
                            @unless ($selectedTranslation->isLegallyRedistributable())
                                <p style="margin-top:8px;">{{ __('messages.translation_text_licensed_note') }}</p>
                            @endunless
                        @endif
                        <x-slot:actions>
                            @if ($fallbackTranslation)
                                <a class="btn btn-primary"
                                   href="{{ route('bible.index', ['book' => $currentBook->id, 'chapter' => $currentChapter, 'translation_id' => $fallbackTranslation->id]) }}">
                                    {{ __('messages.translation_read_in', ['translation' => $fallbackTranslation->code]) }}
                                </a>
                            @endif
                            <a class="btn" href="{{ route('bible.index') }}">{{ __('messages.choose_another_translation') }}</a>
                        </x-slot:actions>
                    </x-empty-state>
                @endif
            </div>
        @else
            <x-empty-state
                icon="📖"
                accent="var(--accent-bible)"
                tint="#f2ebfd"
                :title="__('messages.welcome_to_bible')">
                <p>{{ __('messages.select_book_to_start') }}</p>
                <p style="font-size:13px;">{{ __('messages.choose_translation_and_book') }}</p>
            </x-empty-state>
        @endif

        <form action="{{ route('bible.search') }}" method="GET" class="panel" style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap;">
            <input type="hidden" name="translation_id" value="{{ $selectedTranslation->id }}">
            <label class="sr-only" for="bible-q" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);">{{ __('messages.search_verses') }}</label>
            <input id="bible-q" class="input" style="flex:1;min-width:200px;" type="text" name="q"
                   placeholder="{{ __('messages.search_verses') }}" minlength="3">
            <button type="submit" class="btn btn-primary">{{ __('messages.search') }}</button>
        </form>
    </div>
</div>

<script>
    // Toggle the bookmark form to the removal endpoint when already bookmarked.
    document.querySelectorAll('.bookmark-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            const submitBtn = this.querySelector('button');

            if (submitBtn.classList.contains('is-bookmarked')) {
                e.preventDefault();
                this.action = '{{ route('bible.removeBookmark') }}';
                this.submit();
            }
        });
    });
</script>
@endsection
