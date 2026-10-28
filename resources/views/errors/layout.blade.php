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
            background: #f3f4f6;
            color: #111827;
            font-family: Figtree, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        main {
            width: 100%;
            max-width: 28rem;
            padding: 2.5rem 2rem;
            text-align: center;
            background: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        .code { margin: 0; font-size: 3.5rem; font-weight: 700; letter-spacing: -0.03em; color: #4f46e5; }
        h1 { margin: 0.5rem 0 0; font-size: 1.25rem; font-weight: 600; }
        p { margin: 0.75rem 0 0; color: #4b5563; line-height: 1.6; font-size: 0.9375rem; }
        .actions { margin-top: 2rem; display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center; }
        a {
            display: inline-block;
            padding: 0.55rem 1.1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
        }
        .primary { background: #4f46e5; color: #ffffff; }
        .primary:hover { background: #4338ca; }
        .secondary { border: 1px solid #d1d5db; color: #374151; }
        .secondary:hover { background: #f9fafb; }
        footer { margin-top: 2rem; font-size: 0.75rem; color: #9ca3af; }
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
