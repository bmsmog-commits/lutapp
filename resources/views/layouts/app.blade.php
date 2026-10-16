<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ auth()->check() ? app(\App\Services\PreferenceService::class)->theme(auth()->user()) : 'system' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fbbc04">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Lutapp Notes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /*
         * Lutapp design tokens (Phase 27.5).
         * Mirrors resources/css/app.css @theme so hand-written classes below
         * and Tailwind utilities used in views resolve to the same palette.
         */
        :root {
            --ink: #1b1d23;
            --muted: #63676f;
            --line: #e6e2da;
            --surface: #faf8f4;
            --card: #ffffff;
            --brand: #fbbc04;
            --brand-strong: #c98a00;
            --brand-soft: #fffbeb;
            --navy: #1d2f5c;
            --navy-deep: #121c38;
            --navy-soft: #eef2ff;
            --danger: #b3261e;

            --accent-bible: #6d28d9;
            --accent-community: #0d9488;
            --accent-jobs: #059669;
            --accent-events: #ea580c;
            --accent-audio: #4f46e5;
            --accent-giving: #b45309;
            --accent-alert: #e11d48;
            --accent-messages: #2563eb;

            --radius: 14px;
            --radius-sm: 10px;
            --shadow-sm: 0 1px 2px rgba(18, 28, 56, .06), 0 1px 3px rgba(18, 28, 56, .05);
            --shadow-lg: 0 18px 48px rgba(18, 28, 56, .18);
            --header-h: 60px;

            /* Phase 27.7 — one source of truth for the floating-control stack.
               Everything that must clear the corner (AI pill, AI panel, nav
               panel, footer padding) is derived from these, so the two
               controls can never overlap each other or the page content. */
            --fab-size: 60px;
            --fab-gap: 14px;
            --fab-inset-bottom: max(20px, env(safe-area-inset-bottom));
            --fab-inset-right: max(20px, env(safe-area-inset-right));
            --ai-fab-h: 40px;
            /* bottom edge of the secondary control */
            --ai-fab-bottom: calc(var(--fab-inset-bottom) + var(--fab-size) + var(--fab-gap));
            /* total height the floating stack occupies */
            --fab-stack-h: calc(var(--fab-size) + var(--fab-gap) + var(--ai-fab-h));
        }

        /* Phase 21 appearance preference — same variables the whole app
           already uses, just redefined for dark. 'system' (the default,
           unchanged for every existing user) follows the OS preference;
           an explicit 'dark' choice always applies regardless of OS. */
        html[data-theme="dark"] {
            --ink: #e8eaed;
            --muted: #9aa0a6;
            --line: #33363d;
            --surface: #15171c;
            --card: #1e2027;
            --brand-soft: #2a2415;
            --navy-soft: #1b2133;
            --danger: #f28b82;
        }
        html[data-theme="dark"] body { background: var(--surface); }
        html[data-theme="dark"] .panel,
        html[data-theme="dark"] .tile,
        html[data-theme="dark"] .auth-card,
        html[data-theme="dark"] .card,
        html[data-theme="dark"] .btn,
        html[data-theme="dark"] .input,
        html[data-theme="dark"] textarea,
        html[data-theme="dark"] .nav-drawer,
        html[data-theme="dark"] .topbar { background: var(--card); color: var(--ink); }

        @media (prefers-color-scheme: dark) {
            html[data-theme="system"] {
                --ink: #e8eaed;
                --muted: #9aa0a6;
                --line: #33363d;
                --surface: #15171c;
                --card: #1e2027;
                --brand-soft: #2a2415;
                --navy-soft: #1b2133;
                --danger: #f28b82;
            }
            html[data-theme="system"] body { background: var(--surface); }
            html[data-theme="system"] .panel,
            html[data-theme="system"] .tile,
            html[data-theme="system"] .auth-card,
            html[data-theme="system"] .card,
            html[data-theme="system"] .btn,
            html[data-theme="system"] .input,
            html[data-theme="system"] textarea,
            html[data-theme="system"] .nav-drawer,
            html[data-theme="system"] .topbar { background: var(--card); color: var(--ink); }
        }

        * { box-sizing: border-box; }
        html, body { max-width: 100%; overflow-x: hidden; }
        body {
            margin: 0;
            color: var(--ink);
            background: var(--surface);
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            font-size: 15px;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; }
        h1, h2, h3 { letter-spacing: -0.015em; }

        /* ---------- Simplified header ---------- */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 40;
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: var(--header-h);
            padding: 8px 16px;
            background: color-mix(in srgb, var(--card) 92%, transparent);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(12px);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: max-content;
            font-size: 18px;
            font-weight: 700;
            text-decoration: none;
        }
        .brand-mark {
            display: grid;
            width: 32px;
            height: 32px;
            place-items: center;
            border-radius: 10px;
            background: linear-gradient(140deg, var(--brand) 0%, var(--brand-strong) 100%);
            color: #1b1d23;
            font-weight: 800;
            font-size: 13px;
            box-shadow: var(--shadow-sm);
        }
        .page-context {
            min-width: 0;
            padding-left: 12px;
            margin-left: 2px;
            border-left: 1px solid var(--line);
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .topbar-spacer { flex: 1; }
        .icon-btn {
            position: relative;
            display: inline-grid;
            place-items: center;
            width: 38px;
            height: 38px;
            flex: 0 0 auto;
            border: 1px solid transparent;
            border-radius: 999px;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            padding: 0;
            text-decoration: none;
        }
        .icon-btn:hover { background: var(--brand-soft); color: var(--ink); }
        .icon-btn svg { width: 19px; height: 19px; }
        .icon-dot {
            position: absolute;
            top: 5px;
            right: 4px;
            min-width: 16px;
            height: 16px;
            display: grid;
            place-items: center;
            padding: 0 4px;
            border-radius: 999px;
            background: var(--accent-alert);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
        }
        .avatar-btn {
            display: inline-grid;
            place-items: center;
            width: 34px;
            height: 34px;
            flex: 0 0 auto;
            border-radius: 999px;
            background: var(--navy);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        /* ---------- Page shell ---------- */
        .page {
            width: min(1180px, calc(100% - 32px));
            margin: 24px auto 120px;
        }
        .page-narrow { width: min(760px, calc(100% - 32px)); }

        /* ---------- Reusable surfaces ---------- */
        .card, .panel, .tile {
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--card);
            padding: 18px;
            box-shadow: var(--shadow-sm);
        }
        .tile { text-decoration: none; display: block; }
        .section-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            margin: 28px 0 12px;
        }
        .section-head h2 {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .section-head a { font-size: 13px; color: var(--muted); text-decoration: none; }
        .section-head a:hover { color: var(--ink); }

        .page-header { margin-bottom: 18px; }
        .page-header h1 { margin: 0 0 4px; font-size: 26px; }
        .page-header p { margin: 0; color: var(--muted); }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 999px;
            padding: 2px 9px;
            font-size: 11.5px;
            font-weight: 700;
            line-height: 1.7;
            border: 1px solid transparent;
        }
        .badge-ok { background: #e7f7ee; color: #056a3c; border-color: #b7e3cb; }
        .badge-muted { background: #f1f0ec; color: #63676f; border-color: #e0ddd5; }
        .badge-brand { background: var(--brand-soft); color: var(--brand-strong); border-color: #fde68a; }

        .empty-state {
            border: 1px dashed var(--line);
            border-radius: var(--radius);
            background: var(--card);
            padding: 40px 24px;
            text-align: center;
        }
        .empty-state h3 { margin: 14px 0 6px; font-size: 19px; }
        .empty-state p { margin: 0 auto 6px; max-width: 46ch; color: var(--muted); }
        .empty-icon {
            display: inline-grid;
            place-items: center;
            width: 54px;
            height: 54px;
            border-radius: 16px;
            font-size: 24px;
        }

        .btn, button {
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            background: var(--card);
            color: var(--ink);
            cursor: pointer;
            font: inherit;
            font-weight: 600;
            padding: 9px 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover { border-color: var(--brand-strong); }
        .btn-primary {
            border-color: var(--brand-strong);
            background: var(--brand);
            color: #1b1d23;
            font-weight: 700;
        }
        .btn-navy { border-color: var(--navy-deep); background: var(--navy); color: #fff; }
        .btn-danger { color: var(--danger); }
        .input, textarea, select {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            background: var(--card);
            color: var(--ink);
            font: inherit;
            padding: 10px 12px;
        }
        textarea { min-height: 130px; resize: vertical; }
        .muted { color: var(--muted); }
        :focus-visible {
            outline: 3px solid var(--brand);
            outline-offset: 2px;
            border-radius: 6px;
        }

        .errors, .status, .offline-banner {
            margin-bottom: 18px;
            border-radius: var(--radius-sm);
            padding: 12px 14px;
        }
        .errors { border: 1px solid #f1b8b8; background: #fff1f1; color: var(--danger); }
        .status { border: 1px solid #b7dfbf; background: #eef9f0; color: #14532d; }
        .offline-banner { display: none; border: 1px solid #e0c05a; background: #fff8dd; color: #6b4c00; }

        .auth-shell { display: grid; min-height: calc(100vh - 110px); place-items: center; }
        .auth-card {
            width: min(430px, 100%);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--card);
            padding: 28px;
            box-shadow: var(--shadow-lg);
        }
        .auth-card h1 { margin: 0 0 8px; font-size: 26px; }
        .field { margin-top: 16px; }
        .field label { display: block; margin-bottom: 7px; color: var(--muted); font-size: 14px; }

        /* ---------- Floating navigation button (PRIMARY) ----------
           Phase 27.7: the primary control always sits closest to the thumb, in
           the bottom-right corner. The secondary AI control is stacked above
           it with a real gap and a different shape, so they never collide and
           never read as two identical circles. */
        .nav-fab {
            position: fixed;
            right: var(--fab-inset-right);
            bottom: var(--fab-inset-bottom);
            z-index: 60;
            display: grid;
            place-items: center;
            width: var(--fab-size);
            height: var(--fab-size);
            border: none;
            border-radius: 999px;
            background: linear-gradient(140deg, var(--brand) 0%, var(--brand-strong) 100%);
            color: #1b1d23;
            box-shadow: 0 10px 28px rgba(201, 138, 0, .34), 0 2px 6px rgba(18, 28, 56, .18);
            cursor: pointer;
            padding: 0;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .nav-fab:hover { transform: translateY(-2px) scale(1.04); }
        .nav-fab:active { transform: scale(.96); }
        .nav-fab svg { width: 24px; height: 24px; }
        .nav-fab .fab-close { display: none; }
        .nav-fab[aria-expanded="true"] .fab-open { display: none; }
        .nav-fab[aria-expanded="true"] .fab-close { display: block; }

        /* ---------- Navigation panel ---------- */
        .nav-scrim {
            position: fixed;
            inset: 0;
            z-index: 50;
            background: rgba(18, 28, 56, .42);
            backdrop-filter: blur(2px);
            opacity: 0;
            visibility: hidden;
            transition: opacity .22s ease, visibility .22s ease;
        }
        .nav-scrim[data-open="true"] { opacity: 1; visibility: visible; }

        .nav-drawer {
            position: fixed;
            z-index: 55;
            background: var(--card);
            border: 1px solid var(--line);
            box-shadow: var(--shadow-lg);
            overflow-y: auto;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            scrollbar-color: var(--line) transparent;
            transition: transform .24s cubic-bezier(.22,.8,.3,1), opacity .2s ease, visibility .24s;
            opacity: 0;
            visibility: hidden;
        }
        .nav-drawer[data-open="true"] { opacity: 1; visibility: visible; }
        .nav-drawer::-webkit-scrollbar { width: 8px; }
        .nav-drawer::-webkit-scrollbar-track { background: transparent; }
        .nav-drawer::-webkit-scrollbar-thumb {
            border: 2px solid transparent;
            border-radius: 999px;
            background: var(--line);
            background-clip: content-box;
        }
        .nav-drawer::-webkit-scrollbar-thumb:hover { background: var(--muted); background-clip: content-box; }

        /* Desktop: anchored panel near the floating button */
        @media (min-width: 768px) {
            .nav-drawer {
                right: var(--fab-inset-right);
                bottom: calc(var(--fab-inset-bottom) + var(--fab-size) + 16px);
                width: min(440px, calc(100vw - 36px));
                max-height: min(74vh, 640px);
                border-radius: 20px;
                padding: 18px 18px 20px;
                transform: translateY(14px) scale(.98);
                transform-origin: bottom right;
            }
            .nav-drawer[data-open="true"] { transform: translateY(0) scale(1); }
        }

        /* Mobile: bottom sheet */
        @media (max-width: 767px) {
            .nav-drawer {
                left: 0;
                right: 0;
                bottom: 0;
                width: 100%;
                max-height: 86vh;
                border-radius: 22px 22px 0 0;
                border-bottom: none;
                /* Phase 27.7 — the primary FAB stays on top of the open sheet
                   (it is the close affordance), so the sheet reserves its full
                   height at the bottom; otherwise Logout sat underneath it. */
                padding: 8px 16px calc(24px + var(--fab-size) + env(safe-area-inset-bottom));
                transform: translateY(100%);
            }
            .nav-drawer[data-open="true"] { transform: translateY(0); }
            .sheet-grabber {
                width: 42px;
                height: 4px;
                margin: 8px auto 12px;
                border-radius: 999px;
                background: var(--line);
            }
        }
        @media (min-width: 768px) { .sheet-grabber { display: none; } }

        .nav-drawer-head {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 2px 0 12px;
            margin-bottom: 2px;
            background: var(--card);
            border-bottom: 1px solid var(--line);
        }
        .nav-drawer-head strong { font-size: 15px; letter-spacing: -.01em; }
        .nav-group { margin-top: 18px; }
        .nav-group:first-of-type { margin-top: 12px; }
        .nav-group > h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 9px 2px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--muted);
        }
        /* A hairline rule trailing each section label, so the groups read as
           sections rather than as one undifferentiated list of links. */
        .nav-group > h3::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--line);
        }
        .nav-links {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 4px 8px;
        }
        @media (max-width: 359px) { .nav-links { grid-template-columns: 1fr; } }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            border: 1px solid transparent;
            border-radius: var(--radius-sm);
            padding: 8px 10px;
            color: var(--ink);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background .14s ease, border-color .14s ease;
        }
        .nav-link span:last-child {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .nav-link:hover { background: color-mix(in srgb, var(--brand-soft) 70%, transparent); }
        .nav-link:hover .nav-dot { transform: scale(1.06); }
        .nav-link[aria-current="page"] {
            background: var(--brand-soft);
            border-color: color-mix(in srgb, var(--brand) 55%, transparent);
            color: var(--ink);
            font-weight: 700;
        }
        /* Active marker: a short gold bar rather than a saturated block. */
        .nav-link[aria-current="page"] .nav-dot {
            box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--brand-strong) 45%, transparent);
        }
        .nav-dot {
            flex: 0 0 auto;
            width: 26px;
            height: 26px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: var(--navy-soft);
            color: var(--navy);
            font-size: 13px;
            transition: transform .14s ease;
        }
        .nav-logout {
            width: 100%;
            margin-top: 2px;
            border-color: var(--line);
            color: var(--danger);
            font-weight: 600;
            text-align: left;
        }
        .nav-logout:hover { border-color: var(--danger); background: color-mix(in srgb, var(--danger) 8%, var(--card)); }

        /* ---------- AI entry point (SECONDARY) ----------
           Phase 27.7: a labelled pill, not a second circle. Different shape,
           smaller, unsaturated surface, navy sparkle mark — so the corner reads
           as "main nav" (gold circle) vs "assistant" (quiet pill). It is always
           stacked directly above the nav button, at every viewport width. */
        .ai-fab {
            position: fixed;
            right: var(--fab-inset-right);
            bottom: var(--ai-fab-bottom);
            z-index: 59;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            height: var(--ai-fab-h);
            max-width: calc(100vw - 32px);
            padding: 0 14px 0 11px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: color-mix(in srgb, var(--card) 94%, var(--navy-soft));
            color: var(--navy);
            font: inherit;
            font-size: 12.5px;
            font-weight: 700;
            letter-spacing: .01em;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: transform .18s ease, border-color .18s ease, opacity .18s ease, visibility .18s;
        }
        .ai-fab:hover { transform: translateY(-2px); border-color: var(--navy); }
        .ai-fab .ai-fab-mark { font-size: 13px; line-height: 1; color: var(--brand-strong); }
        .ai-fab .ai-fab-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* While the navigation panel is open the secondary control steps out of
           the way entirely — it can never sit on top of the open drawer. */
        body.nav-open .ai-fab,
        body.nav-open .ai-panel { opacity: 0; visibility: hidden; pointer-events: none; }

        .ai-panel {
            position: fixed;
            right: var(--fab-inset-right);
            bottom: calc(var(--ai-fab-bottom) + var(--ai-fab-h) + 12px);
            z-index: 58;
            width: min(340px, calc(100vw - 32px));
            max-height: min(56vh, 420px);
            overflow-y: auto;
            overscroll-behavior: contain;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--card);
            box-shadow: var(--shadow-lg);
            padding: 14px 16px 16px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(8px);
            transition: opacity .18s ease, transform .18s ease, visibility .18s;
        }
        .ai-panel[data-open="true"] { opacity: 1; visibility: visible; transform: translateY(0); }
        .ai-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom: 1px solid var(--line);
        }
        .ai-panel-close {
            border: none;
            background: transparent;
            color: var(--muted);
            font-size: 22px;
            line-height: 1;
            padding: 0 4px;
        }
        .ai-panel-head strong { display: flex; align-items: center; gap: 7px; font-size: 14.5px; }
        .ai-panel-head .ai-fab-mark { color: var(--brand-strong); }
        .ai-status {
            border: 1px dashed color-mix(in srgb, var(--navy) 22%, var(--line));
            border-radius: var(--radius-sm);
            background: color-mix(in srgb, var(--navy-soft) 55%, var(--card));
            padding: 12px 13px;
        }
        .ai-status-title { margin: 0 0 6px; font-weight: 700; font-size: 13.5px; }
        .ai-status p:last-child { margin-bottom: 0; }
        .ai-status p, .ai-result { font-size: 13.5px; }
        .ai-result { margin: 10px 0 0; }
        .sr-only {
            position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
        }

        /* ---------- Phase 27.6: footer ---------- */
        .site-footer {
            margin-top: 40px;
            border-top: 1px solid var(--line);
            background: color-mix(in srgb, var(--card) 70%, transparent);
        }
        .footer-inner {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
            /* Phase 27.7 — reserve the exact height of the floating stack plus
               breathing room, so neither control ever sits on footer text. */
            padding: 26px 0 calc(var(--fab-inset-bottom) + var(--fab-stack-h) + 28px);
        }
        .footer-quote blockquote { margin: 0; }
        .footer-quote-text {
            margin: 0 0 6px;
            max-width: 62ch;
            font-family: var(--font-serif, Georgia, serif);
            font-size: 16px;
            line-height: 1.6;
        }
        .footer-quote-author { color: var(--muted); font-size: 13px; font-style: normal; }
        #lut-quote { transition: opacity .4s ease; }
        .footer-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px 14px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 12.5px;
        }
        .footer-credit { font-weight: 600; color: var(--ink); }
        .footer-sep { color: var(--line); }
        @media (max-width: 560px) {
            .footer-meta { flex-direction: column; align-items: flex-start; gap: 5px; }
            .footer-sep { display: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            #lut-quote, .ai-panel, .ai-fab { transition: none; }
        }

        @media (max-width: 700px) {
            .topbar { padding: 8px 12px; }
            .page-context { display: none; }
            .page, .page-narrow { width: min(100% - 24px, 1180px); }
        }
        @media (prefers-reduced-motion: reduce) {
            .nav-drawer, .nav-scrim, .nav-fab { transition: none; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
            <span class="brand-mark">LT</span>
            <span>Lutapp</span>
        </a>
        @hasSection('page-context')
            <span class="page-context">@yield('page-context')</span>
        @endif
        <div class="topbar-spacer"></div>
        @auth
            @php $unreadNotifications = auth()->user()->unreadNotificationsCount(); @endphp
            <a class="icon-btn" href="{{ route('search.index') }}" aria-label="Search Lutapp" title="Search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
            </a>
            <a class="icon-btn" href="{{ route('notifications.index') }}" aria-label="Notifications{{ $unreadNotifications > 0 ? ' ('.$unreadNotifications.' unread)' : '' }}" title="Notifications">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                @if ($unreadNotifications > 0)
                    <span class="icon-dot">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                @endif
            </a>
            <a class="avatar-btn" href="{{ route('profile.show') }}" aria-label="Your profile" title="Profile">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</a>
        @endauth
    </header>

    <main class="page @yield('page-width')">
        <div class="offline-banner" id="offline-banner">You are offline. Previously opened pages may still be available.</div>

        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @yield('content')
    </main>

    @auth
        @include('partials.nav-drawer')
        @include('partials.ai-assistant')
    @endauth

    @include('partials.footer')

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js');
            });
        }

        const banner = document.getElementById('offline-banner');
        const updateOnlineState = () => {
            banner.style.display = navigator.onLine ? 'none' : 'block';
        };

        window.addEventListener('online', updateOnlineState);
        window.addEventListener('offline', updateOnlineState);
        updateOnlineState();

        /* Floating navigation button + panel (vanilla JS — no framework added). */
        (function () {
            const fab = document.getElementById('nav-fab');
            const drawer = document.getElementById('nav-drawer');
            const scrim = document.getElementById('nav-scrim');
            if (!fab || !drawer || !scrim) return;

            let lastFocused = null;

            const isOpen = () => drawer.dataset.open === 'true';

            function open() {
                lastFocused = document.activeElement;
                drawer.dataset.open = 'true';
                scrim.dataset.open = 'true';
                fab.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
                /* Phase 27.7 — retire the secondary AI control while the
                   navigation panel is open so the two can never overlap. */
                document.body.classList.add('nav-open');
                const aiPanel = document.getElementById('ai-panel');
                if (aiPanel && aiPanel.dataset.open === 'true') {
                    aiPanel.dataset.open = 'false';
                    document.getElementById('ai-fab')?.setAttribute('aria-expanded', 'false');
                }
                const first = drawer.querySelector('a, button');
                if (first) first.focus();
            }

            function close(restoreFocus) {
                drawer.dataset.open = 'false';
                scrim.dataset.open = 'false';
                fab.setAttribute('aria-expanded', 'false');
                document.body.style.overflow = '';
                document.body.classList.remove('nav-open');
                if (restoreFocus !== false) (lastFocused || fab).focus();
            }

            fab.addEventListener('click', () => (isOpen() ? close() : open()));
            scrim.addEventListener('click', () => close());

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && isOpen()) {
                    e.preventDefault();
                    close();
                }
                /* Simple focus trap while the panel is open. */
                if (e.key === 'Tab' && isOpen()) {
                    const items = drawer.querySelectorAll('a[href], button:not([disabled]), input, select, textarea');
                    if (!items.length) return;
                    const first = items[0];
                    const last = items[items.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            });
        })();

        /* Phase 27.6 — AI panel. Opens the UI slot only; when no provider is
           configured the panel shows a status message and the ask endpoint is
           never called. Nothing here invents an answer. */
        (function () {
            const aiFab = document.getElementById('ai-fab');
            const aiPanel = document.getElementById('ai-panel');
            if (!aiFab || !aiPanel) return;

            const closeBtn = document.getElementById('ai-panel-close');
            const setOpen = (open) => {
                aiPanel.dataset.open = open ? 'true' : 'false';
                aiFab.setAttribute('aria-expanded', open ? 'true' : 'false');
            };

            aiFab.addEventListener('click', () => setOpen(aiPanel.dataset.open !== 'true'));
            if (closeBtn) closeBtn.addEventListener('click', () => { setOpen(false); aiFab.focus(); });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && aiPanel.dataset.open === 'true') { setOpen(false); aiFab.focus(); }
            });

            const send = document.getElementById('ai-send');
            const prompt = document.getElementById('ai-prompt');
            const result = document.getElementById('ai-result');
            if (!send || !prompt || !result) return;

            send.addEventListener('click', async () => {
                const value = prompt.value.trim();
                if (!value) return;
                send.disabled = true;
                try {
                    const res = await fetch(@json(route('ai.ask')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({ prompt: value }),
                    });
                    const data = await res.json();
                    result.textContent = data.message || '';
                } catch (e) {
                    result.textContent = 'The assistant could not be reached.';
                }
                result.hidden = false;
                send.disabled = false;
            });
        })();

        /* Phase 27.6 — footer quote rotation, every 10 minutes.
           Index is derived from the clock, not random, so it is deterministic
           and testable, and it matches what the server rendered. */
        (function () {
            const holder = document.getElementById('lut-quote');
            const data = document.getElementById('lut-quote-data');
            if (!holder || !data) return;

            let quotes = [];
            try { quotes = JSON.parse(data.textContent) || []; } catch (e) { return; }
            if (quotes.length < 2) return;

            const INTERVAL = 600000; /* 10 minutes */
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const textEl = holder.querySelector('.footer-quote-text');
            const authorEl = holder.querySelector('.footer-quote-author');

            const indexForNow = () => Math.floor(Date.now() / INTERVAL) % quotes.length;

            function render() {
                const next = indexForNow();
                if (String(next) === holder.dataset.index) return;
                const apply = () => {
                    textEl.textContent = quotes[next].text;
                    authorEl.textContent = '— ' + (quotes[next].author || 'Lutapp');
                    holder.dataset.index = String(next);
                    holder.style.opacity = '1';
                };
                if (reduceMotion) { apply(); return; }
                holder.style.opacity = '0';
                setTimeout(apply, 400);
            }

            /* Align the first tick to the next 10-minute boundary, then hold it. */
            setTimeout(() => { render(); setInterval(render, INTERVAL); },
                INTERVAL - (Date.now() % INTERVAL));
        })();
    </script>
</body>
</html>
