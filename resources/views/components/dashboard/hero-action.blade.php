{{-- Shortcut button inside the dashboard hero; "primary" is the solid white one. --}}
@props(['href', 'icon', 'primary' => false])

<a href="{{ $href }}" @class([
    'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition duration-200 ease-out hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white',
    'bg-white text-indigo-700 shadow-sm hover:bg-indigo-50 hover:shadow-md' => $primary,
    'bg-white/10 text-white ring-1 ring-inset ring-white/25 backdrop-blur hover:bg-white/20' => ! $primary,
])>
    <x-icon :name="$icon" class="h-4 w-4" />
    {{ $slot }}
</a>
