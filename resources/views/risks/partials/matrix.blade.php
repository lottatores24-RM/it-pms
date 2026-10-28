{{--
    Probability × impact matrix of unclosed risks (FR-013/FR-014).
    Each cell links to the register filtered on that combination.
    Expects: $project, $matrix (["Prob|Impact" => count]), $filters.
--}}
@php
$ratings = App\Enums\RiskRating::cases();
@endphp

<div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 motion-safe:animate-fade-up" style="animation-delay: 150ms">
    <h3 class="text-base font-semibold text-gray-900">Matriks risiko</h3>
    <p class="mt-0.5 text-sm text-gray-500">Risiko yang belum ditutup. Klik sel untuk memfilter.</p>

    <div class="mt-4 flex gap-2">
        <div class="flex w-6 items-center justify-center">
            <span class="-rotate-90 whitespace-nowrap text-xs font-medium text-gray-500">Dampak</span>
        </div>
        <div class="flex-1">
            <div class="grid grid-cols-[3.5rem_repeat(3,minmax(0,1fr))] gap-1 text-center text-xs">
                @foreach (array_reverse($ratings) as $impact)
                    <div class="flex items-center justify-end pr-1 font-medium text-gray-600">{{ $impact->value }}</div>
                    @foreach ($ratings as $probability)
                        @php
                            $score = $probability->weight() * $impact->weight();
                            $count = $matrix["{$probability->value}|{$impact->value}"] ?? 0;
                            $selected = $filters['probability'] === $probability->value && $filters['impact'] === $impact->value;
                        @endphp
                        <a href="{{ route('projects.risks.index', [$project, 'probability' => $probability->value, 'impact' => $impact->value]) }}"
                           title="Probabilitas {{ $probability->value }} × Dampak {{ $impact->value }} = {{ $score }}"
                           @class([
                               'flex h-14 flex-col items-center justify-center rounded-lg transition duration-200 hover:-translate-y-0.5 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600',
                               App\Enums\RiskLevel::fromScore($score)->cellClass(),
                               'opacity-40 saturate-50' => $count === 0 && ! $selected,
                               'shadow-sm' => $count > 0,
                               'ring-2 ring-indigo-600 ring-offset-2' => $selected,
                           ])>
                            <span class="text-lg font-semibold">{{ $count }}</span>
                            <span class="text-[10px] opacity-70">skor {{ $score }}</span>
                        </a>
                    @endforeach
                @endforeach

                <div></div>
                @foreach ($ratings as $probability)
                    <div class="pt-1 font-medium text-gray-600">{{ $probability->value }}</div>
                @endforeach
            </div>
            <p class="mt-1 text-center text-xs font-medium text-gray-500">Probabilitas</p>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-2 text-xs">
        @foreach (App\Enums\RiskLevel::cases() as $level)
            <span class="inline-flex items-center gap-1">
                <span class="h-3 w-3 rounded-sm {{ $level->cellClass() }}"></span>{{ $level->value }}
            </span>
        @endforeach
    </div>
</div>
