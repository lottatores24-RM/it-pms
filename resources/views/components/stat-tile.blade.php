{{--
    KPI stat tile: label, value and an optional hint line. "alert" marks a
    value that needs attention; it is paired with an icon, never colour alone.
    Whole numbers count up on load; "delay" staggers the entrance in a row.
--}}
@props(['label', 'value', 'hint' => null, 'alert' => false, 'href' => null, 'icon' => null, 'tone' => 'indigo', 'delay' => null])

@php
$tag = $href ? 'a' : 'div';
$tones = [
    'indigo' => 'bg-indigo-50 text-indigo-600 ring-indigo-600/10',
    'sky' => 'bg-sky-50 text-sky-600 ring-sky-600/10',
    'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-600/10',
    'amber' => 'bg-amber-50 text-amber-600 ring-amber-600/10',
    'violet' => 'bg-violet-50 text-violet-600 ring-violet-600/10',
    'rose' => 'bg-rose-50 text-rose-600 ring-rose-600/10',
];
$iconClass = $alert ? $tones['rose'] : ($tones[$tone] ?? $tones['indigo']);
$countable = is_int($value) || (is_string($value) && ctype_digit($value));
$classes = 'group relative block rounded-2xl bg-white p-4 shadow-sm sm:p-5 ring-1 ring-gray-900/5 motion-safe:animate-fade-up';
if ($href) {
    $classes .= ' transition duration-300 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:ring-indigo-500/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600';
}
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => $classes]) }} @if ($delay !== null) style="animation-delay: {{ (int) $delay }}ms" @endif>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-medium text-gray-500 sm:text-sm">{{ $label }}</p>
        @if ($icon || $alert)
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset sm:h-9 sm:w-9 {{ $iconClass }}">
                <x-icon :name="$alert ? 'alert' : $icon" class="h-4 w-4 sm:h-5 sm:w-5" />
            </span>
        @endif
    </div>
    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-900 sm:text-3xl">
        @if ($countable)
            <span x-data="countUp({{ (int) $value }})" x-text="display">{{ $value }}</span>
        @else
            {{ $value }}
        @endif
    </p>
    @if ($hint)
        <p @class(['mt-1 text-xs', 'font-medium text-rose-700' => $alert, 'text-gray-500' => ! $alert])>{{ $hint }}</p>
    @endif
    @if ($href)
        <x-icon name="arrow-right" class="absolute bottom-4 right-4 h-4 w-4 sm:bottom-5 sm:right-5 text-gray-300 transition duration-300 group-hover:translate-x-0.5 group-hover:text-indigo-500" />
    @endif
</{{ $tag }}>
