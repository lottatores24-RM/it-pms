{{-- Small monospace label (CR, LATE, ISSUE …) or status chip on the Midnight Navy dashboards. --}}
@props(['tone' => 'gray', 'mono' => true])

@php
$tones = [
    'gray' => 'bg-white/[0.06] text-night-200',
    'indigo' => 'bg-indigo-500/15 text-indigo-200',
    'violet' => 'bg-violet-500/15 text-violet-200',
    'rose' => 'bg-rose-500/15 text-rose-300',
    'amber' => 'bg-amber-500/15 text-amber-200',
    'emerald' => 'bg-emerald-500/15 text-emerald-300',
    'sky' => 'bg-sky-500/15 text-sky-200',
    // Badge colour names used by the status enums.
    'red' => 'bg-rose-500/15 text-rose-300',
    'yellow' => 'bg-amber-500/15 text-amber-200',
    'green' => 'bg-emerald-500/15 text-emerald-300',
    'blue' => 'bg-sky-500/15 text-sky-200',
    'orange' => 'bg-orange-500/15 text-orange-200',
];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center rounded px-1.5 py-px text-[11px] font-medium '.($mono ? 'font-mono uppercase ' : '').($tones[$tone] ?? $tones['gray'])]) }}>{{ $slot }}</span>
