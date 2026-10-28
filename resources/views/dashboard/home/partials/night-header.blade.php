{{--
    Greeting row of the Midnight Navy dashboards.
    Expects: $greeting, $user, $summary; optional $actions (list of [href, label, icon, primary]).
--}}
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between motion-safe:animate-fade-up">
    <div class="min-w-0">
        <p class="font-mono text-xs text-night-400">{{ now()->translatedFormat('l, d F Y') }}</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-white">{{ $greeting }}, {{ Str::before($user->name, ' ') ?: $user->name }}.</h1>
        <p class="mt-1 text-sm text-night-400">{{ $summary }}</p>
    </div>
    @if (! empty($actions))
        <div class="flex shrink-0 flex-wrap gap-2">
            @foreach ($actions as $action)
                <a href="{{ $action['href'] }}" @class([
                    'inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-[13px] font-medium transition',
                    'bg-indigo-500 text-white shadow-sm shadow-indigo-950/40 hover:bg-indigo-400' => $action['primary'] ?? false,
                    'border border-night-700 text-night-200 hover:bg-night-800 hover:text-white' => ! ($action['primary'] ?? false),
                ])>
                    <x-icon :name="$action['icon']" class="h-4 w-4" />{{ $action['label'] }}
                </a>
            @endforeach
        </div>
    @endif
</div>
