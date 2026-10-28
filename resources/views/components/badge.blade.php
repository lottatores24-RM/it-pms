@props(['color' => 'gray'])

@php
$colors = [
    'gray' => 'bg-gray-100 text-gray-700 ring-gray-500/20',
    'green' => 'bg-green-50 text-green-700 ring-green-600/20',
    'red' => 'bg-red-50 text-red-700 ring-red-600/20',
    'yellow' => 'bg-yellow-50 text-yellow-800 ring-yellow-600/20',
    'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
    'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    'orange' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($colors[$color] ?? $colors['gray'])]) }}>
    {{ $slot }}
</span>
