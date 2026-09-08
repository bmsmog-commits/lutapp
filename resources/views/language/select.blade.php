@extends('layouts.app')

@push('styles')
<style>
    .language-shell {
        display: grid;
        min-height: calc(100vh - 160px);
        place-items: center;
    }
    .language-card {
        width: min(720px, 100%);
        border: 1px solid var(--line);
        border-radius: 8px;
        background: #fff;
        padding: clamp(24px, 5vw, 48px);
        box-shadow: 0 12px 36px rgba(60, 64, 67, .12);
    }
    .language-card h1 { margin: 0 0 10px; font-size: clamp(28px, 5vw, 42px); }
    .language-card p { margin: 0 0 28px; }
    .language-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 24px;
    }
    .language-option {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 54px;
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 12px 14px;
        cursor: pointer;
    }
    .language-option:has(input:checked) {
        border-color: #d79b00;
        background: #fff8dd;
        box-shadow: 0 0 0 2px rgba(251, 188, 4, .2);
    }
    .language-option input { accent-color: var(--brand); }
    @media (max-width: 560px) {
        .language-options { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
    <div class="language-shell">
        <section class="language-card">
            <div class="brand" style="margin-bottom: 28px;">
                <span class="brand-mark">LT</span>
                <span>Lutapp</span>
            </div>
            <h1>{{ __('messages.choose_language') }}</h1>
            <p class="muted">{{ __('messages.choose_language_description') }}</p>

            <form method="post" action="{{ route('language.update') }}">
                @csrf
                <div class="language-options">
                    @foreach ($locales as $code => $name)
                        <label class="language-option">
                            <input type="radio" name="locale" value="{{ $code }}" @checked($selectedLocale === $code) required>
                            <span>{{ $name }}</span>
                        </label>
                    @endforeach
                </div>
                <button class="btn btn-primary" type="submit">{{ __('messages.continue') }}</button>
            </form>
        </section>
    </div>
@endsection
