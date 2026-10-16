{{--
    Phase 27.8/27.9 — API.Bible obligations for remotely served text:
    the publisher's copyright notice is shown with the chapter, and the view is
    reported to API.Bible's Fair Use Management System (FUMS v3).
--}}
@if ($remoteChapter)
    <footer class="muted" data-testid="api-bible-attribution"
            style="margin-top:18px;padding-top:12px;border-top:1px solid var(--line);font-size:12px;line-height:1.55;">
        @if ($remoteChapter->copyright !== '')
            <p style="margin:0 0 4px;">{{ $remoteChapter->copyright }}</p>
        @endif
        <p style="margin:0;">{{ __('messages.api_bible_source') }}</p>
    </footer>

    @include('bible.partials.fums', [
        'fumsToken' => $remoteChapter->fumsToken,
        'fumsFromCache' => $remoteChapter->fromCache,
    ])
@endif
