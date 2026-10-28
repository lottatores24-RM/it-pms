@props(['active' => false, 'icon' => null])

@php
$classes = $active
    ? 'flex items-center gap-3 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white'
    : 'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($active) aria-current="page" @endif>
    @if ($icon)
        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
        </svg>
    @endif
    <span class="truncate">{{ $slot }}</span>
</a>
