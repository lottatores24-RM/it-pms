{{--
    Circular progress (0–100) with the percentage in the middle. The arc draws
    itself on load; the number is also exposed to screen readers as a meter.
--}}
@props(['value', 'label' => 'Progress', 'size' => 'h-14 w-14', 'textSize' => 'text-xs'])

@php $value = max(0, min(100, (int) $value)); @endphp

<div {{ $attributes->merge(['class' => "relative shrink-0 {$size}"]) }} role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $value }}" aria-label="{{ $label }}">
    <svg class="h-full w-full -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
        <circle cx="18" cy="18" r="15.5" fill="none" class="stroke-indigo-100" stroke-width="3.5" />
        <circle cx="18" cy="18" r="15.5" fill="none" class="stroke-indigo-600 motion-safe:animate-draw-ring" stroke-width="3.5" stroke-linecap="round"
                pathLength="100" stroke-dasharray="100" stroke-dashoffset="{{ 100 - $value }}" @if ($value === 0) stroke-opacity="0" @endif />
    </svg>
    <span class="absolute inset-0 flex items-center justify-center {{ $textSize }} font-semibold tabular-nums text-gray-900">{{ $value }}%</span>
</div>
