@extends('layouts.app')

@push('styles')
<style>
    .notes-header {
        display: grid;
        grid-template-columns: 1fr minmax(220px, 380px);
        gap: 16px;
        align-items: center;
        margin-bottom: 20px;
    }
    .notes-header h1 {
        margin: 0;
        font-size: 28px;
    }
    .composer {
        border: 1px solid var(--line);
        border-radius: 8px;
        background: #fff;
        padding: 16px;
        margin-bottom: 24px;
        box-shadow: 0 3px 16px rgba(60, 64, 67, .10);
    }
    .composer-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .composer .wide { grid-column: 1 / -1; }
    .tools {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        margin-top: 12px;
    }
    .swatches {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .swatch {
        width: 28px;
        height: 28px;
        border: 1px solid var(--line);
        border-radius: 999px;
        cursor: pointer;
    }
    .swatch input {
        opacity: 0;
        position: absolute;
    }
    .swatch:has(input:checked) {
        outline: 3px solid #202124;
        outline-offset: 2px;
    }
    .notes-grid {
        column-count: 4;
        column-gap: 16px;
    }
    .note {
        display: inline-block;
        width: 100%;
        break-inside: avoid;
        border: 1px solid rgba(60, 64, 67, .24);
        border-radius: 8px;
        margin: 0 0 16px;
        padding: 14px;
        vertical-align: top;
    }
    .note h2 {
        margin: 0 0 10px;
        overflow-wrap: anywhere;
        font-size: 18px;
    }
    .note p {
        margin: 0 0 14px;
        overflow-wrap: anywhere;
        white-space: pre-wrap;
    }
    .note-meta {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
        color: var(--muted);
        font-size: 13px;
    }
    .note-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }
    .note-actions button {
        padding: 7px 9px;
        font-size: 13px;
    }
    .locked {
        display: grid;
        gap: 10px;
        padding: 12px;
        border: 1px dashed rgba(60, 64, 67, .36);
        border-radius: 8px;
        background: rgba(255, 255, 255, .46);
    }
    .edit-box {
        display: grid;
        gap: 10px;
    }
    .empty {
        padding: 50px 18px;
        text-align: center;
        color: var(--muted);
    }
    @media (max-width: 1050px) {
        .notes-grid { column-count: 3; }
    }
    @media (max-width: 780px) {
        .notes-header, .composer-grid { grid-template-columns: 1fr; }
        .notes-grid { column-count: 2; }
    }
    @media (max-width: 520px) {
        .notes-grid { column-count: 1; }
    }
</style>
@endpush

@section('content')
    <div class="notes-header">
        <div>
            <h1>Notes</h1>
            <div class="muted">{{ $notes->count() }} {{ Str::plural('note', $notes->count()) }}</div>
        </div>
        <form method="get" action="{{ route('notes.index') }}">
            <input class="input" name="q" value="{{ $search }}" placeholder="Search notes">
        </form>
    </div>

    <section class="composer">
        <form method="post" action="{{ route('notes.store') }}">
            @csrf
            <div class="composer-grid">
                <input class="input" name="title" value="{{ old('title') }}" placeholder="Title">
                <input class="input" name="passcode" type="password" placeholder="Passcode to lock note">
                <textarea class="wide" name="body" placeholder="Take a note...">{{ old('body') }}</textarea>
            </div>
            <div class="tools">
                <label><input type="checkbox" name="is_pinned" value="1"> Pin</label>
                <div class="swatches" aria-label="Note color">
                    @foreach ($colors as $color)
                        <label class="swatch" style="background: {{ $color }}" title="{{ $color }}">
                            <input type="radio" name="color" value="{{ $color }}" @checked(old('color', $colors[0]) === $color)>
                        </label>
                    @endforeach
                </div>
                <button class="btn-primary" type="submit">Add note</button>
            </div>
        </form>
    </section>

    @if ($notes->isEmpty())
        <div class="empty">No notes yet.</div>
    @else
        <section class="notes-grid">
            @foreach ($notes as $note)
                @php($isUnlocked = ! $note->isLocked() || ($unlockedNotes[$note->id] ?? false))
                <article class="note" style="background: {{ $note->color }}">
                    <div class="note-meta">
                        <span>{{ $note->is_pinned ? 'Pinned' : 'Note' }}</span>
                        <span>{{ $note->isLocked() ? 'Locked' : 'Open' }}</span>
                    </div>

                    @if ($isUnlocked)
                        <form class="edit-box" method="post" action="{{ route('notes.update', $note) }}">
                            @csrf
                            @method('put')
                            <input class="input" name="title" value="{{ $note->title }}" placeholder="Title">
                            <textarea name="body" placeholder="Take a note...">{{ $note->body }}</textarea>
                            <input class="input" name="passcode" type="password" placeholder="{{ $note->isLocked() ? 'New passcode' : 'Passcode to lock' }}">
                            <div class="swatches">
                                @foreach ($colors as $color)
                                    <label class="swatch" style="background: {{ $color }}" title="{{ $color }}">
                                        <input type="radio" name="color" value="{{ $color }}" @checked($note->color === $color)>
                                    </label>
                                @endforeach
                            </div>
                            <label><input type="checkbox" name="is_pinned" value="1" @checked($note->is_pinned)> Pin</label>
                            @if ($note->isLocked())
                                <label><input type="checkbox" name="remove_lock" value="1"> Remove lock</label>
                            @endif
                            <div class="note-actions">
                                <button type="submit">Save</button>
                                @if ($note->isLocked())
                                    <button form="lock-{{ $note->id }}" type="submit">Lock</button>
                                @endif
                            </div>
                        </form>
                    @else
                        <div class="locked">
                            <h2>{{ $note->title ?: 'Locked note' }}</h2>
                            <p class="muted">Enter this note's passcode to view or edit it.</p>
                            <form method="post" action="{{ route('notes.unlock', $note) }}">
                                @csrf
                                <input class="input" name="passcode" type="password" placeholder="Passcode" required>
                                <div class="field">
                                    <button type="submit">Unlock</button>
                                </div>
                            </form>
                            @error("unlock_{$note->id}")
                                <div class="muted">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="note-actions" style="margin-top: 10px;">
                        <form method="post" action="{{ route('notes.destroy', $note) }}" onsubmit="return confirm('Delete this note?')">
                            @csrf
                            @method('delete')
                            <button class="btn-danger" type="submit">Delete</button>
                        </form>
                    </div>

                    @if ($note->isLocked())
                        <form id="lock-{{ $note->id }}" method="post" action="{{ route('notes.lock', $note) }}">
                            @csrf
                        </form>
                    @endif
                </article>
            @endforeach
        </section>
    @endif
@endsection
