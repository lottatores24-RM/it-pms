{{-- Friendly empty state for a dashboard list. --}}
@props(['icon' => 'sparkles', 'title', 'message' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-gray-200 px-6 py-10 text-center']) }}>
    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-50 text-gray-400 ring-1 ring-inset ring-gray-200">
        <x-icon :name="$icon" class="h-5 w-5" />
    </span>
    <p class="text-sm font-medium text-gray-900">{{ $title }}</p>
    @if ($message)
        <p class="max-w-xs text-sm text-gray-500">{{ $message }}</p>
    @endif
</div>
