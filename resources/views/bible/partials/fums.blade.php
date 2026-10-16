{{--
    Phase 27.9 — API.Bible Fair Use Management System (FUMS v3) view report.

    Contract (docs.api.bible/guides/fair-use, checked 2026-10-16):
      - request text with `fums-version=3`; the response's `meta.fumsToken`
        is the only token ever reported here — none is synthesised;
      - load https://pkg.api.bible/fumsV3.min.js and call
        fums("trackView", token) once the Scripture is shown. The script
        manages the anonymous device/session IDs itself.
    A cache hit re-reports the token API.Bible issued for the original fetch
    (data-fums-source="cache"). The docs do not address cached tokens; see
    docs/bible-translation-licensing.md.

    Reporting is best-effort: if the script is blocked or throws, the reader
    is unaffected.

    @param string|null $fumsToken
    @param bool        $fumsFromCache
--}}
@if (! empty($fumsToken))
    @once
        <script src="https://pkg.api.bible/fumsV3.min.js" async data-testid="fums-script"></script>
    @endonce
    <script data-testid="fums-report" data-fums-source="{{ ($fumsFromCache ?? false) ? 'cache' : 'live' }}">
        (function (token) {
            var attempts = 0;
            function report() {
                try {
                    if (typeof window.fums === 'function') {
                        window.fums('trackView', token);
                        return;
                    }
                } catch (e) {
                    return; // never let usage reporting break the reader
                }
                if (++attempts < 20) {
                    setTimeout(report, 500);
                }
            }
            if (document.readyState === 'complete') {
                report();
            } else {
                window.addEventListener('load', report);
            }
        })(@json($fumsToken));
    </script>
@endif
