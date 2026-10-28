{{--
    Error page layout (NFR-005). Self-contained on purpose: inline CSS and no
    Vite, session or database access, so it still renders when one of those
    is the reason for the error.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · @yield('title') — {{ config('app.name', 'IT-PMS') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: #0b1220;
            color: #e6ecf7;
            color-scheme: dark;
            font-family: Figtree, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        main {
            width: 100%;
            max-width: 28rem;
            padding: 2.5rem 2rem;
            text-align: center;
            background: #101a2e;
            border: 1px solid #1c2842;
            border-radius: 0.75rem;
            box-shadow: 0 24px 48px -24px rgba(0, 0, 0, 0.6);
            animation: rise 0.5s cubic-bezier(0.16, 1, 0.3, 1) backwards;
        }
        .code { margin: 0; font-size: 3.5rem; font-weight: 700; letter-spacing: -0.03em; color: #818cf8; }
        h1 { margin: 0.5rem 0 0; font-size: 1.25rem; font-weight: 600; }
        p { margin: 0.75rem 0 0; color: #8593b0; line-height: 1.6; font-size: 0.9375rem; }
        .actions { margin-top: 2rem; display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center; }
        a {
            display: inline-block;
            padding: 0.55rem 1.1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
        }
        .primary { background: #6366f1; color: #ffffff; }
        .primary:hover { background: #818cf8; }
        .secondary { border: 1px solid #2a3a5c; color: #c9d3e6; }
        .secondary:hover { background: rgba(255, 255, 255, 0.04); }
        footer { margin-top: 2rem; font-size: 0.75rem; color: #6b7a99; }
        @keyframes rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { main { animation: none; } }
    </style>
</head>
<body>
    <main>
        <x-application-logo style="display: block; width: 2.5rem; height: 2.5rem; margin: 0 auto 1.5rem;" />
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>

        <div class="actions">
            <a class="primary" href="{{ url('/dashboard') }}">Ke Dashboard</a>
            <a class="secondary" href="javascript:history.back()">Kembali</a>
        </div>

        <footer>{{ config('app.name', 'IT-PMS') }} · PT Nusantara Digital Solutions</footer>
    </main>
</body>
</html>
