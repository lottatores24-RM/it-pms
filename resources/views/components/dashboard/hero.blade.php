{{--
    Greeting banner at the top of every role dashboard: date, role, a one-line
    summary of what needs attention, and the role's main shortcuts ("actions").
--}}
@props(['greeting', 'name', 'role', 'summary'])

<section class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-indigo-600 to-violet-700 px-6 py-8 text-white shadow-lg shadow-indigo-600/20 sm:px-10 sm:py-10 motion-safe:animate-fade-up">
    <div class="pointer-events-none absolute -right-20 -top-28 -z-10 h-80 w-80 rounded-full bg-white/10 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -bottom-32 left-1/4 -z-10 h-72 w-72 rounded-full bg-fuchsia-400/20 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(rgba(255,255,255,0.12)_1px,transparent_1px)] [background-size:22px_22px] [mask-image:linear-gradient(to_left,black,transparent_70%)]" aria-hidden="true"></div>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-xl">
            <div class="flex flex-wrap items-center gap-2 text-sm text-indigo-100">
                <span>{{ now()->translatedFormat('l, d F Y') }}</span>
                <span aria-hidden="true">&middot;</span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-0.5 text-xs font-medium text-white ring-1 ring-inset ring-white/25 backdrop-blur">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-300" aria-hidden="true"></span>
                    {{ $role }}
                </span>
            </div>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">{{ $greeting }}, {{ $name }}</h1>
            <p class="mt-2 text-sm leading-relaxed text-indigo-100 sm:text-base">{{ $summary }}</p>
        </div>

        @isset($actions)
            <div class="flex flex-wrap gap-2 lg:shrink-0 lg:flex-nowrap">{{ $actions }}</div>
        @endisset
    </div>
</section>
