{{--
    Earned Value Management panel (FR-022). Expects: $project, $evm
    (Project::earnedValue()) and $evmCurve (Project::earnedValueCurve() or null).
--}}
@use('App\Enums\PerformanceIndexStatus')

@php
$number = fn (?float $value): string => $value === null ? '—' : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
$signed = fn (float $value): string => ($value > 0 ? '+' : '').$number($value);
$index = fn (?float $value): string => $value === null ? '—' : number_format($value, 2, ',', '.');
$asOf = Illuminate\Support\Carbon::parse($evm['as_of']);

// Series colours: validated categorical slots 1–3 (PV, EV, AC); the legend,
// the metric keys and the chart all use the same three.
$colors = ['pv' => '#94a3b8', 'ev' => '#f0844f', 'ac' => '#34d399'];

$indices = [
    [
        'name' => 'SPI',
        'title' => 'Schedule Performance Index',
        'value' => $evm['spi'],
        'formula' => 'EV ÷ PV',
        'status' => PerformanceIndexStatus::fromIndex($evm['spi']),
        'label' => fn (PerformanceIndexStatus $status) => $status->scheduleLabel(),
        'variance' => "Schedule variance (EV − PV): {$signed($evm['sv'])} effort point",
        'empty' => 'Belum ada task yang jatuh tempo, sehingga PV masih 0.',
    ],
    [
        'name' => 'CPI',
        'title' => 'Cost Performance Index',
        'value' => $evm['cpi'],
        'formula' => 'EV ÷ AC',
        'status' => PerformanceIndexStatus::fromIndex($evm['cpi']),
        'label' => fn (PerformanceIndexStatus $status) => $status->costLabel(),
        'variance' => "Cost variance (EV − AC): {$signed($evm['cv'])} effort point",
        'empty' => 'Belum ada effort aktual yang dicatat, sehingga AC masih 0.',
    ],
];

$metrics = [
    ['code' => 'BAC', 'name' => 'Budget at Completion', 'value' => $evm['bac'], 'hint' => 'Total effort rencana', 'key' => null],
    ['code' => 'PV', 'name' => 'Planned Value', 'value' => $evm['pv'], 'hint' => 'Seharusnya sudah selesai', 'key' => 'pv'],
    ['code' => 'EV', 'name' => 'Earned Value', 'value' => $evm['ev'], 'hint' => "{$evm['percent_complete']}% dari BAC", 'key' => 'ev'],
    ['code' => 'AC', 'name' => 'Actual Cost', 'value' => $evm['ac'], 'hint' => 'Effort aktual tercatat', 'key' => 'ac'],
    ['code' => 'EAC', 'name' => 'Estimate at Completion', 'value' => $evm['eac'], 'hint' => $evm['eac'] === null ? 'Butuh CPI' : 'Proyeksi BAC ÷ CPI', 'key' => null],
];

$gapMessages = array_filter([
    $evm['gaps']['without_effort'] ? "{$evm['gaps']['without_effort']} task belum memiliki effort points sehingga tidak ikut dihitung." : null,
    $evm['gaps']['without_due_date'] ? "{$evm['gaps']['without_due_date']} task belum memiliki tenggat sehingga tidak masuk PV." : null,
    $evm['gaps']['done_without_actual'] ? "{$evm['gaps']['done_without_actual']} task Done belum dicatat effort aktualnya sehingga AC lebih kecil dari seharusnya." : null,
]);

$chart = $evmCurve ? [
    'theme' => 'night',
    'labels' => $evmCurve['labels'],
    'todayIndex' => ($i = array_search($asOf->translatedFormat('d M Y'), $evmCurve['labels'], true)) === false ? null : $i,
    'series' => [
        ['label' => 'PV · rencana', 'color' => $colors['pv'], 'values' => $evmCurve['pv'], 'dashed' => true, 'fill' => false],
        ['label' => 'EV · hasil', 'color' => $colors['ev'], 'values' => $evmCurve['ev'], 'dashed' => false, 'fill' => true],
        ['label' => 'AC · aktual', 'color' => $colors['ac'], 'values' => $evmCurve['ac'], 'dashed' => false, 'fill' => false],
    ],
] : null;

// Gauge scale for SPI/CPI: 0.5 … 1.5 with the target 1.0 in the middle.
$gauge = fn (float $value): float => max(0, min(100, ($value - 0.5) * 100));
$chip = 'inline-flex items-center gap-1.5 rounded-full bg-gray-50 px-2.5 py-1 font-medium text-gray-600 ring-1 ring-inset ring-gray-200';
@endphp

<section class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-900/5 motion-safe:animate-fade-up" style="animation-delay: 300ms" aria-labelledby="evm-title">
    <header class="flex flex-col gap-4 border-b border-gray-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-md shadow-indigo-500/25">
                <x-icon name="trend" class="h-5 w-5" />
            </span>
            <div>
                <h3 id="evm-title" class="text-base font-semibold text-gray-900">Earned Value Management</h3>
                <p class="text-sm text-gray-500">Kinerja jadwal dan effort proyek, dihitung dari effort point task</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 text-xs">
            <span class="{{ $chip }}"><x-icon name="calendar" class="h-3.5 w-3.5" />Per {{ $asOf->translatedFormat('d M Y') }}</span>
            <span class="{{ $chip }}"><x-icon name="check-circle" class="h-3.5 w-3.5" />EV aturan 0/100</span>
            <span class="{{ $chip }}"><x-icon name="chart" class="h-3.5 w-3.5" />Satuan effort point</span>
        </div>
    </header>

    <div class="space-y-6 p-6">
        @if ($evm['bac'] <= 0)
            <div class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-gray-200 px-6 py-10 text-center">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-50 text-gray-400 ring-1 ring-inset ring-gray-200">
                    <x-icon name="chart" class="h-5 w-5" />
                </span>
                <p class="text-sm font-semibold text-gray-900">Data belum lengkap</p>
                <p class="max-w-md text-sm text-gray-500">
                    {{ $evm['task_count'] === 0 ? 'Proyek belum memiliki task.' : 'Belum ada task yang memiliki effort points.' }}
                    EVM dihitung dari effort points setiap task, jadi isi effort points (dan tenggat) di
                    <a href="{{ route('projects.tasks.index', $project) }}" class="font-medium text-indigo-600 hover:text-indigo-500">daftar task</a>
                    terlebih dahulu.
                </p>
            </div>
        @else
            @if ($gapMessages !== [])
                <div class="flex gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20">
                    <x-icon name="warning" class="h-5 w-5 shrink-0 text-amber-600" />
                    <div>
                        <p class="font-semibold">Data belum lengkap, hasil EVM belum sepenuhnya akurat</p>
                        <ul class="mt-1.5 list-disc space-y-0.5 pl-4 text-amber-800">
                            @foreach ($gapMessages as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- SPI / CPI with a bullet gauge around the 1.0 target --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($indices as $item)
                    @php $below = $item['status'] === PerformanceIndexStatus::Below; @endphp
                    <div class="rounded-2xl bg-gradient-to-b from-gray-50/80 to-white p-5 ring-1 ring-inset ring-gray-200">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $item['name'] }} <span class="font-normal text-gray-500">· {{ $item['title'] }}</span></p>
                                <p class="text-xs text-gray-500">{{ $item['formula'] }}</p>
                            </div>
                            @if ($item['status'])
                                <span @class([
                                    'inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset',
                                    'bg-rose-50 text-rose-700 ring-rose-600/20' => $below,
                                    'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => ! $below,
                                ])>
                                    @if ($below)
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" /></svg>
                                    @elseif ($item['status'] === PerformanceIndexStatus::Above)
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" /></svg>
                                    @else
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                    @endif
                                    {{ ($item['label'])($item['status']) }}
                                </span>
                            @endif
                        </div>

                        <p class="mt-3 text-5xl font-semibold tracking-tight text-gray-900">{{ $index($item['value']) }}</p>

                        @if ($item['status'])
                            @php
                                $position = $gauge($item['value']);
                                $from = min($position, 50);
                                $width = abs($position - 50);
                            @endphp
                            <div class="mt-5" role="img" aria-label="{{ $item['name'] }} {{ $index($item['value']) }} terhadap target 1,00">
                                <div class="relative h-2.5 rounded-full bg-gray-100">
                                    <div class="absolute inset-y-0 left-0 w-1/2 rounded-l-full bg-rose-50"></div>
                                    <div class="absolute inset-y-0 right-0 w-1/2 rounded-r-full bg-emerald-50"></div>
                                    <div @class([
                                        'absolute inset-y-0 motion-safe:animate-grow-x',
                                        'origin-right rounded-l-full bg-gradient-to-l from-rose-300 to-rose-500' => $below,
                                        'origin-left rounded-r-full bg-gradient-to-r from-emerald-300 to-emerald-500' => ! $below,
                                    ]) style="left: {{ $from }}%; width: {{ $width }}%; animation-delay: 450ms"></div>
                                    <div class="absolute -inset-y-1.5 left-1/2 w-0.5 -translate-x-1/2 rounded-full bg-gray-800/70" aria-hidden="true"></div>
                                    <div class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2" style="left: {{ $position }}%">
                                        <span @class([
                                            'block h-4 w-4 rounded-full shadow-md ring-[3px] ring-white motion-safe:animate-pop',
                                            'bg-rose-600' => $below,
                                            'bg-emerald-600' => ! $below,
                                        ]) style="animation-delay: 900ms"></span>
                                    </div>
                                </div>
                                <div class="mt-2.5 flex justify-between text-[11px] tabular-nums text-gray-400">
                                    <span>0,50</span>
                                    <span class="font-medium text-gray-600">Target 1,00</span>
                                    <span>1,50</span>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-gray-500">{{ $item['variance'] }}</p>
                        @else
                            <p class="mt-3 text-sm text-gray-500">{{ $item['empty'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Base figures; PV / EV / AC carry the chart's line keys --}}
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($metrics as $metric)
                    <div @class(['rounded-xl bg-gray-50/80 p-4 ring-1 ring-inset ring-gray-100', 'col-span-2 sm:col-span-1' => $loop->last])>
                        <dt class="flex items-center gap-2 text-xs font-medium text-gray-500">
                            @if ($metric['key'])
                                <span class="w-4 border-t-2 {{ $metric['key'] === 'pv' ? 'border-dashed' : '' }}" style="border-color: {{ $colors[$metric['key']] }}" aria-hidden="true"></span>
                            @endif
                            <abbr title="{{ $metric['name'] }}" class="font-semibold text-gray-700 no-underline">{{ $metric['code'] }}</abbr>
                            <span class="truncate">{{ $metric['name'] }}</span>
                        </dt>
                        <dd class="mt-1.5 text-2xl font-semibold tracking-tight text-gray-900">{{ $number($metric['value']) }}</dd>
                        <dd class="mt-0.5 text-xs text-gray-500">{{ $metric['hint'] }}</dd>
                        @if ($metric['code'] === 'EV')
                            <dd class="mt-2 h-1.5 overflow-hidden rounded-full bg-orange-100" aria-hidden="true">
                                <span class="block h-full origin-left rounded-full bg-[#eb6834] motion-safe:animate-grow-x" style="width: {{ min(100, $evm['percent_complete']) }}%; animation-delay: 500ms"></span>
                            </dd>
                        @endif
                    </div>
                @endforeach
            </dl>

            @if ($chart)
                <div class="rounded-2xl p-5 ring-1 ring-inset ring-gray-200">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Kurva S kumulatif</h4>
                            <p class="text-xs text-gray-500">PV mengikuti tenggat task; EV dan AC bertambah pada tanggal task selesai, sampai hari ini.</p>
                        </div>
                        <ul class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-gray-600" aria-label="Legenda">
                            <li class="flex items-center gap-2"><span class="w-5 border-t-2 border-dashed" style="border-color: {{ $colors['pv'] }}"></span>PV · rencana</li>
                            <li class="flex items-center gap-2"><span class="w-5 border-t-2" style="border-color: {{ $colors['ev'] }}"></span>EV · hasil</li>
                            <li class="flex items-center gap-2"><span class="w-5 border-t-2" style="border-color: {{ $colors['ac'] }}"></span>AC · aktual</li>
                        </ul>
                    </div>
                    <div class="relative mt-4 h-80">
                        <canvas data-chart="evm-curve" data-chart-data="{{ json_encode($chart) }}" role="img"
                                aria-label="Kurva S: per {{ $asOf->translatedFormat('d F Y') }} PV {{ $number($evm['pv']) }}, EV {{ $number($evm['ev']) }}, AC {{ $number($evm['ac']) }} effort point. Data lengkap ada di tabel di bawah grafik."></canvas>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                @if ($chart)
                    <details class="group rounded-xl ring-1 ring-inset ring-gray-200 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex cursor-pointer items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                            <span class="flex items-center gap-2"><x-icon name="list" class="h-4 w-4 text-gray-400" />Lihat data kurva</span>
                            <svg class="h-4 w-4 text-gray-400 transition duration-200 group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </summary>
                        <div class="max-h-72 overflow-auto px-4 pb-3">
                            <table class="min-w-full text-sm tabular-nums">
                                <thead class="sticky top-0 bg-white text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    <tr class="border-b border-gray-100">
                                        <th scope="col" class="py-2 pr-4">Tanggal</th>
                                        <th scope="col" class="px-2 py-2 text-right">PV</th>
                                        <th scope="col" class="px-2 py-2 text-right">EV</th>
                                        <th scope="col" class="py-2 pl-2 text-right">AC</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach ($evmCurve['labels'] as $i => $label)
                                        <tr @class(['bg-indigo-50/50 font-medium' => $i === $chart['todayIndex']])>
                                            <td class="py-1.5 pr-4 text-gray-900">{{ $label }}@if ($i === $chart['todayIndex']) <span class="text-xs text-indigo-600">(hari ini)</span>@endif</td>
                                            <td class="px-2 py-1.5 text-right text-gray-700">{{ $number($evmCurve['pv'][$i]) }}</td>
                                            <td class="px-2 py-1.5 text-right text-gray-700">{{ $number($evmCurve['ev'][$i]) }}</td>
                                            <td class="py-1.5 pl-2 text-right text-gray-700">{{ $number($evmCurve['ac'][$i]) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif

                <details class="group rounded-xl ring-1 ring-inset ring-gray-200 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex cursor-pointer items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        <span class="flex items-center gap-2"><x-icon name="sparkles" class="h-4 w-4 text-gray-400" />Cara membaca EVM</span>
                        <svg class="h-4 w-4 text-gray-400 transition duration-200 group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </summary>
                    <ul class="list-disc space-y-1.5 px-4 pb-4 pl-8 text-sm text-gray-600">
                        <li><strong class="text-gray-900">SPI &gt; 1</strong> berarti pekerjaan lebih cepat dari jadwal, <strong class="text-gray-900">SPI &lt; 1</strong> berarti terlambat.</li>
                        <li><strong class="text-gray-900">CPI &gt; 1</strong> berarti effort yang terpakai lebih hemat dari rencana, <strong class="text-gray-900">CPI &lt; 1</strong> berarti boros.</li>
                        <li>Pada gauge, garis tengah adalah target 1,00; titik menunjukkan posisi indeks saat ini.</li>
                        <li>EAC memperkirakan total effort di akhir proyek jika efisiensi saat ini (CPI) berlanjut.</li>
                    </ul>
                </details>
            </div>
        @endif
    </div>
</section>
