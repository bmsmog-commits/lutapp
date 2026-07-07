<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fbbc04">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Lutapp Notes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>{{ config('app.name') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <style>
        :root {
            --ink: #202124;
            --muted: #5f6368;
            --line: #dadce0;
            --surface: #f8fafd;
            --brand: #fbbc04;
            --danger: #b3261e;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            background: var(--surface);
            font-family: Arial, Helvetica, sans-serif;
        }
        a { color: inherit; }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, .94);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(12px);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: max-content;
            font-size: 21px;
            font-weight: 700;
            text-decoration: none;
        }
        .brand-mark {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 8px;
            background: var(--brand);
            font-weight: 800;
        }
        .topbar-spacer { flex: 1; }
        .nav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .nav a {
            border-radius: 8px;
            color: var(--muted);
            padding: 8px 10px;
            text-decoration: none;
        }
        .nav a:hover {
            background: #f1f3f4;
            color: var(--ink);
        }
        .page {
            width: min(1180px, calc(100% - 32px));
            margin: 26px auto 56px;
        }
        .btn, button {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--ink);
            cursor: pointer;
            font: inherit;
            padding: 10px 14px;
            text-decoration: none;
        }
        .btn-primary {
            border-color: #e0a800;
            background: var(--brand);
            font-weight: 700;
        }
        .btn-danger { color: var(--danger); }
        .input, textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            padding: 11px 12px;
        }
        textarea {
            min-height: 130px;
            resize: vertical;
        }
        .muted { color: var(--muted); }
        .errors, .status, .offline-banner {
            margin-bottom: 18px;
            border-radius: 8px;
            padding: 12px 14px;
        }
        .errors {
            border: 1px solid #f1b8b8;
            background: #fff1f1;
            color: var(--danger);
        }
        .status {
            border: 1px solid #b7dfbf;
            background: #eef9f0;
        }
        .offline-banner {
            display: none;
            border: 1px solid #e0c05a;
            background: #fff8dd;
        }
        .auth-shell {
            display: grid;
            min-height: calc(100vh - 82px);
            place-items: center;
        }
        .auth-card {
            width: min(430px, 100%);
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 28px;
            box-shadow: 0 8px 28px rgba(60, 64, 67, .10);
        }
        .auth-card h1 {
            margin: 0 0 8px;
            font-size: 26px;
        }
        .field { margin-top: 16px; }
        .field label {
            display: block;
            margin-bottom: 7px;
            color: var(--muted);
            font-size: 14px;
        }
        @media (max-width: 700px) {
            .topbar {
                align-items: stretch;
                flex-wrap: wrap;
                padding: 12px 16px;
            }
            .brand { width: 100%; }
            .page { width: min(100% - 24px, 1180px); }
        }
    </style>
    @stack('styles')
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ auth()->check() ? route('notes.index') : route('login') }}">
            <span class="brand-mark">L</span>
            <span>Lutapp Notes</span>
        </a>
        <div class="topbar-spacer"></div>
        @auth
            <nav class="nav" aria-label="Main navigation">
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a href="{{ route('notes.index') }}">Notes</a>
                <a href="{{ route('todos.index') }}">To-do</a>
                <a href="{{ route('events.index') }}">Calendar</a>
                <a href="{{ route('hymns.index') }}">Hymns</a>
                <a href="{{ route('bible.index') }}">Bible</a>
            </nav>
            <span class="muted">{{ auth()->user()->name }}</span>
            <form action="{{ route('logout') }}" method="post">
                @csrf
                <button type="submit">Logout</button>
            </form>
        @endauth
    </header>

    <main class="page">
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
    </script>
</body>
</html>
