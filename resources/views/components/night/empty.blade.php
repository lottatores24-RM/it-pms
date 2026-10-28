{{-- Quiet empty state for a list on the Midnight Navy dashboards. --}}
@props(['icon' => 'sparkles', 'title', 'message' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-night-700 px-6 py-8 text-center']) }}>
    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/[0.04] text-night-400 ring-1 ring-inset ring-night-700">
        <x-icon :name="$icon" class="h-4 w-4" />
    </span>
    <p class="text-sm font-medium text-night-100">{{ $title }}</p>
    @if ($message)
        <p class="max-w-xs text-xs text-night-400">{{ $message }}</p>
    @endif
</div>
