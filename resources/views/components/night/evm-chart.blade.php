{{--
    EVM S-curve of the dashboard's focus project (FR-022), drawn by charts.js in
    the "night" palette. Expects $focus from DashboardController::focusEarnedValue().
--}}
@props(['focus'])

@php
$project = $focus['project'];
$evm = $focus['evm'];
$curve = $focus['curve'];
$colors = ['pv' => '#94a3b8', 'ev' => '#818cf8', 'ac' => '#34d399'];
$asOf = Carbon\Carbon::parse($evm['as_of']);
$number = fn (?float $value): string => $value === null ? '—' : number_format($value, 2, ',', '.');
$chart = [
    'theme' => 'night',
    'labels' => $curve['labels'],
    'todayIndex' => ($i = array_search($asOf->translatedFormat('d M Y'), $curve['labels'], true)) === false ? null : $i,
    'series' => [
        ['label' => 'PV · rencana', 'color' => $colors['pv'], 'values' => $curve['pv'], 'dashed' => true, 'fill' => false],
        ['label' => 'EV · hasil', 'color' => $colors['ev'], 'values' => $curve['ev'], 'dashed' => false, 'fill' => true],
        ['label' => 'AC · aktual', 'color' => $colors['ac'], 'values' => $curve['ac'], 'dashed' => false, 'fill' => false],
    ],
];
$href = Gate::allows('viewDashboard', $project) ? route('projects.dashboard', $project) : null;
@endphp

<section {{ $attributes->merge(['class' => 'flex flex-col rounded-xl border border-night-700 bg-gradient-to-b from-night-850 to-night-900 p-5']) }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-sm font-semibold text-night-100">Kurva S · <span class="font-mono font-medium">{{ $project->code }}</span></h2>
            <p class="mt-0.5 truncate text-xs text-night-400">{{ $project->name }} · SPI {{ $number($evm['spi']) }} · CPI {{ $number($evm['cpi']) }}</p>
        </div>
        <ul class="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-1 text-[11.5px] text-night-400" aria-label="Legenda">
            <li class="flex items-center gap-1.5"><span class="w-3.5 border-t-2 border-dashed" style="border-color: {{ $colors['pv'] }}"></span>PV</li>
            <li class="flex items-center gap-1.5"><span class="w-3.5 border-t-2" style="border-color: {{ $colors['ev'] }}"></span>EV</li>
            <li class="flex items-center gap-1.5"><span class="w-3.5 border-t-2" style="border-color: {{ $colors['ac'] }}"></span>AC</li>
            @if ($href)
                <li><a href="{{ $href }}" class="font-medium text-indigo-300 hover:text-indigo-200">Detail EVM →</a></li>
            @endif
        </ul>
    </div>
    <div class="relative mt-4 h-60 lg:h-auto lg:min-h-60 lg:flex-1">
        <canvas data-chart="evm-curve" data-chart-data="{{ json_encode($chart) }}" role="img"
                aria-label="Kurva S {{ $project->name }}: per {{ $asOf->translatedFormat('d F Y') }} PV {{ $number($evm['pv']) }}, EV {{ $number($evm['ev']) }}, AC {{ $number($evm['ac']) }} effort point."></canvas>
    </div>
</section>
