{{--
    Section panel on the Midnight Navy dashboards: title, optional description,
    counter and "see all" link. "flush" drops the body padding for edge-to-edge lists.
--}}
@props(['title', 'description' => null, 'count' => null, 'href' => null, 'linkLabel' => 'Lihat semua', 'flush' => false])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-night-700 bg-night-850']) }}>
    <header @class(['flex items-start justify-between gap-4 px-5 pt-4', 'border-b border-night-700 pb-3.5' => $flush])>
        <div class="min-w-0">
            <h2 class="text-sm font-semibold text-night-100">{{ $title }}</h2>
            @if ($description)
                <p class="mt-0.5 text-xs text-night-400">{{ $description }}</p>
            @endif
        </div>
        <div class="flex shrink-0 items-center gap-3">
            @if ($count !== null)
                <span class="font-mono text-xs text-night-400">{{ $count }}</span>
            @endif
            @if ($href)
                <a href="{{ $href }}" class="group inline-flex items-center gap-1 text-xs font-medium text-indigo-300 hover:text-indigo-200">
                    {{ $linkLabel }}
                    <x-icon name="arrow-right" class="h-3.5 w-3.5 transition duration-200 group-hover:translate-x-0.5" />
                </a>
            @endif
        </div>
    </header>
    <div @class(['px-5 pb-5 pt-3' => ! $flush])>
        {{ $slot }}
    </div>
</section>
