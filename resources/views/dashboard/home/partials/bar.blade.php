{{-- One labelled share bar. Expects: $label, $count, $total, $delay (ms). --}}
@php $share = $total > 0 ? round($count / $total * 100) : 0; @endphp

<div>
    <div class="flex items-center justify-between gap-3 text-sm">
        <dt class="text-gray-700">{{ $label }}</dt>
        <dd class="tabular-nums text-gray-900"><span class="font-semibold">{{ $count }}</span> <span class="text-xs text-gray-400">({{ $share }}%)</span></dd>
    </div>
    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100">
        <div class="h-full origin-left rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 motion-safe:animate-grow-x" style="width: {{ $share }}%; animation-delay: {{ (int) $delay }}ms"></div>
    </div>
</div>
