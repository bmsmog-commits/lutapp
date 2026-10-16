@extends('layouts.app')

@section('page-context', __('messages.bible_study'))

@push('styles')
<style>
    /* Two panes on desktop/tablet; a tab switch on mobile. No horizontal
       overflow at any width: both tracks are minmax(0, …). */
    .study-shell {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
        gap: 20px;
        align-items: start;
    }
    .study-pane { min-width: 0; }
    .study-notes { position: sticky; top: 76px; }

    .study-tabs { display: none; }

    @media (max-width: 899px) {
        .study-shell { grid-template-columns: minmax(0, 1fr); }
        .study-notes { position: static; }
        .study-tabs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 4px;
            width: 100%;
            max-width: 100%;
            margin-bottom: 14px;
            padding: 4px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--card);
            box-shadow: var(--shadow-sm);
        }
        .study-tab {
            min-width: 0;
            border: none;
            border-radius: 999px;
            background: transparent;
            color: var(--muted);
            padding: 9px 10px;
            font-weight: 700;
            font-size: 13px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: background .15s ease, color .15s ease;
        }
        .study-tab[aria-selected="true"] {
            background: var(--brand-soft);
            color: var(--brand-strong);
            box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--brand) 45%, transparent);
        }
        .study-shell[data-active="bible"] .study-pane-notes { display: none; }
        .study-shell[data-active="notes"] .study-pane-bible { display: none; }
    }

    .study-reader { max-width: none; }
    .study-verse {
        display: flex;
        gap: 12px;
        border-radius: var(--radius-sm);
        padding: 6px 8px;
        margin: 0 -8px;
    }
    .study-verse:hover { background: #faf7ff; }
    .study-verse .v-num {
        flex: 0 0 auto;
        width: 24px;
        text-align: right;
        padding-top: 3px;
        color: var(--accent-bible);
        font-size: 12px;
        font-weight: 700;
    }
    .study-verse .v-text {
        flex: 1;
        min-width: 0;
        font-family: 'Iowan Old Style', 'Palatino Linotype', 'Book Antiqua', Georgia, serif;
        line-height: 1.75;
    }
    /* Phase 27.7 — the cite control now reads as a labelled affordance rather
       than a bare "+": same action, same reference-only payload. */
    .cite-verse {
        flex: 0 0 auto;
        align-self: flex-start;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid var(--line);
        border-radius: 999px;
        background: var(--card);
        color: var(--muted);
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        padding: 3px 9px;
        opacity: .45;
        transition: opacity .15s ease, border-color .15s ease, color .15s ease;
    }
    .study-verse:hover .cite-verse,
    .study-verse:focus-within .cite-verse { opacity: 1; }
    .cite-verse:focus-visible { opacity: 1; }
    .cite-verse:hover {
        border-color: color-mix(in srgb, var(--accent-bible) 55%, transparent);
        color: var(--accent-bible);
        background: color-mix(in srgb, #f4ecff 70%, var(--card));
    }

    .study-chapter-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--line);
        padding-bottom: 12px;
        margin-bottom: 16px;
    }
    .ref-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid var(--accent-bible);
        border-radius: 999px;
        background: #f4ecff;
        color: var(--accent-bible);
        font-size: 12px;
        font-weight: 700;
        padding: 3px 10px;
    }
    .note-card {
        border: 1px solid var(--line);
        border-radius: var(--radius-sm);
        padding: 10px 12px;
        margin-top: 8px;
    }
    .note-card h4 { margin: 0 0 4px; font-size: 13.5px; }
    .note-card p { margin: 0; font-size: 13px; color: var(--muted); }
    .unsaved-warning { color: var(--danger); font-size: 12.5px; }
</style>
@endpush

@section('content')
<x-page-header
    :title="__('messages.bible_study')"
    :subtitle="__('messages.bible_study_intro')"
    accent="var(--accent-bible)">
    <x-slot:actions>
        <a class="btn" href="{{ route('bible.index', array_filter(['book' => $currentBook?->id, 'chapter' => $currentChapter, 'translation_id' => $selectedTranslation->id])) }}">{{ __('messages.bible') }}</a>
        <a class="btn" href="{{ route('notes.index') }}">{{ __('messages.tab_notes') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="study-tabs" role="tablist" aria-label="{{ __('messages.bible_study') }}">
    <button type="button" class="study-tab" id="tab-bible" role="tab" aria-selected="true" aria-controls="pane-bible">{{ __('messages.tab_bible') }}</button>
    <button type="button" class="study-tab" id="tab-notes" role="tab" aria-selected="false" aria-controls="pane-notes">{{ __('messages.tab_notes') }}</button>
</div>

<div class="study-shell" id="study-shell" data-active="bible">
    {{-- ---------------- Bible pane ---------------- --}}
    <section class="panel study-pane study-pane-bible" id="pane-bible" role="tabpanel" aria-labelledby="tab-bible">
        <div class="study-chapter-bar">
            <form method="GET" action="{{ route('bible.study') }}" id="study-nav" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;flex:1;min-width:0;">
                <label class="sr-only" for="study-book">{{ __('messages.bible_books') }}</label>
                <select id="study-book" name="book" style="width:auto;max-width:190px;">
                    <option value="">{{ __('messages.bible_books') }}</option>
                    @foreach ($books as $book)
                        <option value="{{ $book->id }}" {{ $currentBook?->id === $book->id ? 'selected' : '' }}>{{ $book->name }}</option>
                    @endforeach
                </select>

                @if ($currentBook)
                    <label class="sr-only" for="study-chapter">{{ __('messages.chapter') }}</label>
                    <select id="study-chapter" name="chapter" style="width:auto;">
                        @for ($ch = 1; $ch <= $currentBook->chapters_count; $ch++)
                            <option value="{{ $ch }}" {{ $ch == $currentChapter ? 'selected' : '' }}>{{ $ch }}</option>
                        @endfor
                    </select>
                @endif

                <label class="sr-only" for="study-translation">{{ __('messages.translation') }}</label>
                <select id="study-translation" name="translation_id" style="width:auto;max-width:200px;">
                    @foreach ($translations as $translation)
                        @php $info = $availability[$translation->id] ?? ['has_text' => false, 'state' => 'not_imported']; @endphp
                        <option value="{{ $translation->id }}" {{ $selectedTranslation->id === $translation->id ? 'selected' : '' }}>
                            {{ $translation->code }} — {{ __('messages.translation_state_'.$info['state']) }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="btn">{{ __('messages.open_chapter') }}</button>
            </form>
        </div>

        @if (! $currentBook)
            <p class="muted">{{ __('messages.no_chapter_selected') }}</p>
        @elseif ($verses->isNotEmpty())
            <p class="ref-chip" id="current-reference"
               data-reference="{{ $currentBook->name }} {{ $currentChapter }} ({{ $selectedTranslation->code }})">
                {{ $currentBook->name }} {{ $currentChapter }} ({{ $selectedTranslation->code }})
            </p>
            <div class="study-reader" style="font-size: {{ ['small' => '15px', 'large' => '19px'][$bibleFontSize ?? 'medium'] ?? '16px' }};">
                @foreach ($verses as $verse)
                    <div class="study-verse">
                        <span class="v-num">{{ $verse->verse }}</span>
                        <p class="v-text" style="margin:0;">{{ $verse->text }}</p>
                        {{-- Inserts the REFERENCE only — never the verse text. --}}
                        <button type="button" class="cite-verse"
                                data-reference="{{ $currentBook->name }} {{ $currentChapter }}:{{ $verse->verse }} ({{ $selectedTranslation->code }})"
                                aria-label="{{ __('messages.insert_reference') }} {{ $currentBook->name }} {{ $currentChapter }}:{{ $verse->verse }}"><span aria-hidden="true">+</span> Cite</button>
                    </div>
                @endforeach
                @include('bible.partials.remote-attribution')
            </div>
        @else
            {{-- Same honest empty state as the reader. No verse text is invented. --}}
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
                           href="{{ route('bible.study', ['book' => $currentBook->id, 'chapter' => $currentChapter, 'translation_id' => $fallbackTranslation->id]) }}">
                            {{ __('messages.translation_read_in', ['translation' => $fallbackTranslation->code]) }}
                        </a>
                    @endif
                </x-slot:actions>
            </x-empty-state>
        @endif
    </section>

    {{-- ---------------- Notes pane ----------------
         Posts to the EXISTING notes.store route with the existing validation
         and payload shape. This is a second surface onto the mature Notes
         system, not a parallel one. --}}
    <section class="panel study-pane study-notes study-pane-notes" id="pane-notes" role="tabpanel" aria-labelledby="tab-notes">
        <h2 style="margin:0 0 12px;font-size:16px;">{{ __('messages.tab_notes') }}</h2>

        <form method="POST" action="{{ route('notes.store') }}" id="study-note-form">
            @csrf
            <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
            <input type="hidden" name="color" value="{{ $noteColors[0] }}">
            <input type="hidden" name="bible_reference" id="note-bible-reference"
                   value="{{ $currentBook ? $currentBook->name.' '.$currentChapter.' ('.$selectedTranslation->code.')' : '' }}">
            <input type="hidden" name="bible_book_id" value="{{ $currentBook?->id }}">
            <input type="hidden" name="bible_chapter" value="{{ $currentBook ? $currentChapter : '' }}">
            <input type="hidden" name="bible_translation_id" value="{{ $selectedTranslation->id }}">

            <div class="field">
                <label for="study-note-title">{{ __('messages.note_title') }}</label>
                <input class="input" type="text" id="study-note-title" name="title" maxlength="120">
            </div>

            <div class="field">
                <label for="study-note-body">{{ __('messages.note_body') }}</label>
                <textarea class="input" id="study-note-body" name="body" maxlength="10000" rows="10"></textarea>
            </div>

            @if ($currentBook)
                <p class="muted" style="font-size:12.5px;margin:8px 0 0;">
                    {{ __('messages.linked_reference') }}:
                    <strong id="linked-reference-label">{{ $currentBook->name }} {{ $currentChapter }} ({{ $selectedTranslation->code }})</strong>
                </p>
                <button type="button" class="btn" id="insert-chapter-reference" style="margin-top:8px;">
                    {{ __('messages.insert_reference') }}
                </button>
            @endif

            <p class="unsaved-warning" id="unsaved-warning" hidden></p>

            <button type="submit" class="btn btn-primary" style="margin-top:12px;">{{ __('messages.save_note') }}</button>
        </form>

        <h3 style="margin:22px 0 4px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);">
            {{ __('messages.recent_notes') }}
        </h3>
        @forelse ($studyNotes as $note)
            <article class="note-card">
                <h4>{{ $note->title ?: __('messages.note_body') }}</h4>
                @if ($note->hasBibleReference())
                    <span class="ref-chip">{{ $note->bible_reference }}</span>
                @endif
                <p>{{ \Illuminate\Support\Str::limit($note->body, 140) }}</p>
            </article>
        @empty
            <p class="muted">{{ __('messages.no_notes_yet') }}</p>
        @endforelse
    </section>
</div>

<script>
/* Phase 27.6 — Bible Study workspace behaviour. Vanilla JS, no framework. */
(function () {
    const shell = document.getElementById('study-shell');
    const body = document.getElementById('study-note-body');
    const form = document.getElementById('study-note-form');
    const warning = document.getElementById('unsaved-warning');
    const DRAFT_KEY = 'lutapp.study-note-draft';

    /* --- mobile tab switch --- */
    const tabs = { bible: document.getElementById('tab-bible'), notes: document.getElementById('tab-notes') };
    Object.entries(tabs).forEach(([name, btn]) => {
        if (!btn) return;
        btn.addEventListener('click', () => {
            shell.dataset.active = name;
            Object.entries(tabs).forEach(([n, b]) => b && b.setAttribute('aria-selected', String(n === name)));
        });
    });

    /* --- insert a reference (never verse text) at the caret --- */
    function insertReference(reference) {
        if (!body) return;
        const start = body.selectionStart ?? body.value.length;
        const end = body.selectionEnd ?? body.value.length;
        const prefix = body.value.slice(0, start);
        const needsBreak = prefix.length > 0 && !prefix.endsWith('\n');
        const snippet = (needsBreak ? '\n' : '') + reference + ' ';
        body.value = prefix + snippet + body.value.slice(end);
        body.focus();
        body.selectionStart = body.selectionEnd = start + snippet.length;
        saveDraft();
    }

    document.querySelectorAll('.cite-verse').forEach((btn) => {
        btn.addEventListener('click', () => insertReference(btn.dataset.reference));
    });

    const chapterBtn = document.getElementById('insert-chapter-reference');
    const refChip = document.getElementById('current-reference');
    if (chapterBtn) {
        chapterBtn.addEventListener('click', () => {
            const ref = refChip?.dataset.reference
                || document.getElementById('note-bible-reference')?.value;
            if (ref) insertReference(ref);
        });
    }

    /* --- unsaved content survives a translation/chapter switch ---
       The reader navigation is a full page load, so the draft is persisted to
       sessionStorage on input and restored on load. Every access is guarded:
       storage can be unavailable or throw in a private window. */
    function saveDraft() {
        if (!body) return;
        try {
            sessionStorage.setItem(DRAFT_KEY, JSON.stringify({
                title: document.getElementById('study-note-title')?.value || '',
                body: body.value,
            }));
        } catch (e) { /* storage unavailable — the form still works */ }
    }

    function restoreDraft() {
        try {
            const raw = sessionStorage.getItem(DRAFT_KEY);
            if (!raw) return;
            const draft = JSON.parse(raw);
            const title = document.getElementById('study-note-title');
            if (title && !title.value) title.value = draft.title || '';
            if (body && !body.value) body.value = draft.body || '';
            if (body && body.value && warning) {
                warning.hidden = false;
                warning.textContent = '';
            }
        } catch (e) { /* ignore malformed or blocked storage */ }
    }

    if (body) {
        body.addEventListener('input', saveDraft);
        document.getElementById('study-note-title')?.addEventListener('input', saveDraft);
        restoreDraft();
    }

    if (form) {
        form.addEventListener('submit', () => {
            try { sessionStorage.removeItem(DRAFT_KEY); } catch (e) {}
        });
    }
})();
</script>
@endsection
