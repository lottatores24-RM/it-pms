{{--
    Dashboard section card: title, optional description and "see all" link.
    "flush" drops the body padding for edge-to-edge lists.
--}}
@props(['title', 'description' => null, 'href' => null, 'linkLabel' => 'Lihat semua', 'delay' => null, 'flush' => false])

<section {{ $attributes->merge(['class' => 'rounded-2xl bg-white shadow-sm ring-1 ring-gray-900/5 motion-safe:animate-fade-up']) }} @if ($delay !== null) style="animation-delay: {{ (int) $delay }}ms" @endif>
    <header @class(['flex items-start justify-between gap-4 px-6 pt-5', 'border-b border-gray-100 pb-4' => $flush])>
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
            @if ($description)
                <p class="mt-0.5 text-sm text-gray-500">{{ $description }}</p>
            @endif
        </div>
        @if ($href)
            <a href="{{ $href }}" class="group inline-flex shrink-0 items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                {{ $linkLabel }}
                <x-icon name="arrow-right" class="h-4 w-4 transition duration-200 group-hover:translate-x-0.5" />
            </a>
        @endif
    </header>
    <div @class(['px-6 pb-6 pt-4' => ! $flush])>
        {{ $slot }}
    </div>
</section>
