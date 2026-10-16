@php
    // Phase 27.6 — rotating quotes. Source of truth is resources/data/wealth-quotes.php;
    // nothing here is generated at runtime.
    $lutQuotes = collect(config('quotes.wealth', []))
        ->filter(fn ($q) => filled($q['text'] ?? null))
        ->values();
    // Deterministic server-side pick: the same 10-minute window shows the same
    // quote to everyone, and the client JS below continues from that index.
    $lutQuoteIndex = $lutQuotes->isEmpty() ? 0 : intdiv(time(), 600) % $lutQuotes->count();
@endphp

<footer class="site-footer">
    <div class="footer-inner">
        @if ($lutQuotes->isNotEmpty())
            <section class="footer-quote" aria-label="Rotating reflection">
                <blockquote id="lut-quote" data-index="{{ $lutQuoteIndex }}" aria-live="polite">
                    <p class="footer-quote-text">{{ $lutQuotes[$lutQuoteIndex]['text'] }}</p>
                    <cite class="footer-quote-author">— {{ $lutQuotes[$lutQuoteIndex]['author'] ?? 'Lutapp' }}</cite>
                </blockquote>
            </section>
            <script type="application/json" id="lut-quote-data">@json($lutQuotes)</script>
        @endif

        {{-- Phase 27.7 — the app's own credit lines. These are plain footer
             text, deliberately not part of the rotating quote block and never
             attributed to anyone. --}}
        <div class="footer-meta">
            <span class="footer-credit">Created by BMS — Beyond Microsoft</span>
            <span class="footer-sep" aria-hidden="true">&middot;</span>
            <span>© 2026 Lutapp. All Rights Reserved.</span>
            <span class="footer-sep" aria-hidden="true">&middot;</span>
            <span class="footer-note">Reflections are for encouragement only and are not financial advice.</span>
        </div>
    </div>
</footer>
