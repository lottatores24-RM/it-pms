<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'IT-PMS') }}</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="theme-night flex min-h-screen bg-night-900 text-night-100">
            <!-- Brand panel (large screens) -->
            <aside class="relative isolate hidden w-1/2 max-w-2xl flex-col justify-between overflow-hidden bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-700 p-12 text-white lg:flex">
                <div class="pointer-events-none absolute -right-24 -top-24 -z-10 h-96 w-96 rounded-full bg-white/10 blur-3xl" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-32 -left-16 -z-10 h-80 w-80 rounded-full bg-fuchsia-400/20 blur-3xl" aria-hidden="true"></div>
                <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(rgba(255,255,255,0.12)_1px,transparent_1px)] [background-size:22px_22px] [mask-image:linear-gradient(to_bottom,black,transparent_75%)]" aria-hidden="true"></div>

                <div class="flex items-center gap-3">
                    <x-application-logo class="h-10 w-10 shadow-lg shadow-indigo-900/30 ring-1 ring-white/30 rounded-[10px]" />
                    <span class="text-xl font-semibold tracking-tight">IT-PMS</span>
                </div>

                <div class="max-w-md">
                    <h2 class="text-3xl font-semibold leading-tight tracking-tight">Kelola proyek TI dari perencanaan sampai penutupan.</h2>
                    <p class="mt-4 text-base leading-relaxed text-indigo-100">Task, milestone, risiko, issue, dan change request dalam satu tempat, lengkap dengan dashboard KPI dan EVM.</p>

                    <ul class="mt-8 space-y-3 text-sm text-indigo-50">
                        @foreach (['Kanban board dengan drag-and-drop', 'Register risiko & matriks probabilitas × dampak', 'Dashboard EVM: SPI, CPI, dan kurva S'] as $feature)
                            <li class="flex items-center gap-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/15 ring-1 ring-inset ring-white/25">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </span>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="text-sm text-indigo-200">&copy; {{ now()->year }} PT Nusantara Digital Solutions</p>
            </aside>

            <!-- Form -->
            <main class="page-enter flex flex-1 flex-col items-center justify-center px-4 py-12 sm:px-6">
                <div class="w-full max-w-sm">
                    <a href="{{ url('/') }}" class="mb-8 flex items-center gap-3 lg:hidden">
                        <x-application-logo class="h-10 w-10" />
                        <span class="text-xl font-semibold tracking-tight text-white">IT-PMS</span>
                    </a>

                    @isset($heading)
                        <div class="mb-8">
                            <h1 class="text-2xl font-semibold tracking-tight text-gray-900">{{ $heading }}</h1>
                            @isset($subheading)
                                <p class="mt-2 text-sm text-gray-600">{{ $subheading }}</p>
                            @endisset
                        </div>
                    @endisset

                    <div class="rounded-2xl border border-night-700 bg-night-850 p-6 shadow-xl shadow-black/20 sm:p-8">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
