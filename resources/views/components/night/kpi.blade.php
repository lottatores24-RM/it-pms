{{--
    One cell of the KPI strip. "tone" colours the value (warn, bad, good) or,
    as "accent", fills the whole cell to mark what needs the user first.
    "bar" (0–100) draws a thin progress line under the value.
--}}
@props(['label', 'value', 'hint' => null, 'tone' => null, 'bar' => null, 'href' => null])

@php
$tag = $href ? 'a' : 'div';
$accent = $tone === 'accent';
$valueColor = match ($tone) {
    'warn' => 'text-amber-300',
    'bad' => 'text-rose-300',
    'good' => 'text-emerald-300',
    default => '',
};
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'group block p-5 transition '.($accent ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'bg-night-850 hover:bg-night-800')]) }}>
    <p @class(['text-xs', 'text-indigo-100' => $accent, 'text-night-400' => ! $accent])>{{ $label }}</p>
    <p class="mt-2 font-mono text-[28px] font-medium leading-none tracking-tight {{ $valueColor }}">{{ $value }}</p>
    @if ($bar !== null)
        <div class="mt-3 h-1 overflow-hidden rounded-full bg-white/[0.08]" aria-hidden="true">
            <div class="h-full origin-left rounded-full bg-indigo-400 motion-safe:animate-grow-x" style="width: {{ max(0, min(100, (int) $bar)) }}%"></div>
        </div>
    @endif
    @if ($hint)
        <p @class(['mt-2 text-xs', 'text-indigo-100' => $accent, 'text-night-400' => ! $accent])>{{ $hint }}</p>
    @endif
</{{ $tag }}>
