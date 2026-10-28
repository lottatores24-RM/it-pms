<x-app-layout>
    <x-slot name="title">Dashboard · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Semua proyek</a>
    </x-slot>

    @php
    $done = $taskCounts['Done'];
    $statusColors = collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->chartColor()]);
    $share = fn (int $count, int $total): int => $total > 0 ? (int) round($count / $total * 100) : 0;

    $totalDays = max(1, (int) $project->start_date->diffInDays($project->end_date));
    $elapsed = max(0, min(100, (int) round($project->start_date->diffInDays(today(), false) / $totalDays * 100)));

    $people = $memberStats->where('name', '!=', 'Belum di-assign');
    $maxLoad = max(1, $memberStats->max('total') ?? 0);
    $completedMilestones = $milestones->where('status', App\Enums\MilestoneStatus::Completed)->count();
    $lateMilestones = $milestones->filter->isOverdue()->count();
    $milestoneIcons = ['Completed' => ['check-circle', 'bg-emerald-50 text-emerald-600 ring-emerald-600/10'], 'Missed' => ['alert', 'bg-rose-50 text-rose-600 ring-rose-600/10'], 'In Progress' => ['refresh', 'bg-indigo-50 text-indigo-600 ring-indigo-600/10'], 'Planned' => ['flag', 'bg-gray-50 text-gray-500 ring-gray-500/10']];
    $riskDots = ['Open' => 'bg-amber-400', 'Mitigated' => 'bg-sky-500', 'Closed' => 'bg-gray-300'];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        {{-- Headline: progress + KPI tiles --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <section class="flex flex-col rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 motion-safe:animate-fade-up sm:col-span-2 lg:col-span-1 lg:row-span-2">
                <p class="text-sm font-medium text-gray-500">Progress proyek</p>
                <div class="mt-4 flex items-center gap-4">
                    <x-progress-ring :value="$progress" label="Progress proyek" size="h-24 w-24" text-size="text-xl" />
                    <div class="min-w-0">
                        <p class="text-2xl font-semibold tracking-tight text-gray-900">{{ $done }}<span class="text-base font-medium text-gray-400"> / {{ $totalTasks }}</span></p>
                        <p class="text-sm text-gray-600">task selesai</p>
                        <p class="mt-1 text-xs text-gray-400">Done ÷ total × 100</p>
                    </div>
                </div>

                <dl class="mt-6 space-y-4 border-t border-gray-100 pt-5 text-sm">
                    <div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">Waktu berjalan</dt>
                            <dd class="font-semibold text-gray-900">{{ $elapsed }}%</dd>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                            <div class="h-full origin-left rounded-full bg-gray-400 motion-safe:animate-grow-x" style="width: {{ $elapsed }}%; animation-delay: 200ms"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-600">Pekerjaan selesai</dt>
                            <dd class="font-semibold text-gray-900">{{ $progress }}%</dd>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-indigo-100" aria-hidden="true">
                            <div class="h-full origin-left rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 motion-safe:animate-grow-x" style="width: {{ $progress }}%; animation-delay: 320ms"></div>
                        </div>
                    </div>
                </dl>
                <p class="mt-auto pt-4 text-xs text-gray-500">
                    {{ $project->start_date->translatedFormat('d M Y') }} – {{ $project->end_date->translatedFormat('d M Y') }}
                    @if ($progress < $elapsed && $totalTasks > 0)
                        · <span class="font-medium text-rose-700">pekerjaan tertinggal dari waktu</span>
                    @endif
                </p>
            </section>

            <x-stat-tile label="Total task" :value="$totalTasks" icon="list" :hint="($totalTasks - $done).' masih berjalan'" :href="route('projects.tasks.index', $project)" delay="60" />
            <x-stat-tile label="Task terlambat" :value="$overdueTasks->count()" icon="check-circle" tone="emerald" :alert="$overdueTasks->isNotEmpty()"
                         :hint="$overdueTasks->isNotEmpty() ? 'Melewati tenggat, belum Done' : 'Tidak ada yang terlambat'"
                         :href="route('projects.tasks.index', [$project, 'due' => 'overdue'])" delay="120" />
            <x-stat-tile label="Risiko High / Critical terbuka" :value="$severeRisks" icon="shield" tone="amber" :alert="$severeRisks > 0"
                         :hint="$riskCounts['Open'].' risiko Open'" :href="route('projects.risks.index', $project)" delay="180" />
            <x-stat-tile label="Issue Critical belum selesai" :value="$criticalIssues" icon="check-circle" tone="emerald" :alert="$criticalIssues > 0"
                         :hint="($issueCounts['Open'] + $issueCounts['In Progress']).' issue terbuka'" :href="route('projects.issues.index', [$project, 'severity' => 'Critical'])" delay="240" />
            <x-stat-tile label="Milestone selesai" :value="$completedMilestones.' / '.$milestones->count()" icon="flag" tone="sky" :alert="$lateMilestones > 0"
                         :hint="$lateMilestones > 0 ? $lateMilestones.' milestone terlambat' : 'Tidak ada yang terlambat'"
                         :href="route('projects.milestones.index', $project)" delay="300" />
            <x-stat-tile label="Anggota aktif mengerjakan task" :value="$people->where('open', '>', 0)->count()" icon="users" tone="violet"
                         :hint="$people->count().' PM / Team Member'" :href="route('projects.members.index', $project)" delay="360" />
        </div>

        @include('dashboard.evm')

        {{-- Workflow & workload --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-dashboard.card title="Alur kerja task" description="Sebaran task di setiap tahap" delay="360">
                @if ($totalTasks > 0)
                    <div class="flex h-3 w-full gap-0.5 overflow-hidden rounded-full" role="img"
                         aria-label="Distribusi task: {{ collect($taskCounts)->map(fn ($count, $status) => "$status $count")->join(', ') }}">
                        @foreach ($taskCounts as $status => $count)
                            @continue($count === 0)
                            <div class="h-full origin-left transition-opacity hover:opacity-80 motion-safe:animate-grow-x"
                                 style="width: {{ $count / $totalTasks * 100 }}%; background-color: {{ $statusColors[$status] }}; animation-delay: {{ 420 + $loop->index * 90 }}ms"
                                 title="{{ $status }}: {{ $count }} task ({{ $share($count, $totalTasks) }}%)"></div>
                        @endforeach
                    </div>
                    <dl class="mt-5 grid grid-cols-2 gap-3">
                        @foreach ($taskCounts as $status => $count)
                            <div class="rounded-xl p-3 ring-1 ring-inset ring-gray-100 transition hover:bg-gray-50">
                                <dt class="flex items-center gap-2 text-xs font-medium text-gray-500">
                                    <span class="h-2.5 w-2.5 rounded-sm" style="background-color: {{ $statusColors[$status] }}" aria-hidden="true"></span>{{ $status }}
                                </dt>
                                <dd class="mt-1 flex items-baseline gap-1.5">
                                    <span class="text-xl font-semibold text-gray-900">{{ $count }}</span>
                                    <span class="text-xs text-gray-400">{{ $share($count, $totalTasks) }}%</span>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <x-dashboard.empty icon="list" title="Belum ada task." message="Distribusi muncul setelah task pertama dibuat." />
                @endif
            </x-dashboard.card>

            <x-dashboard.card title="Beban kerja anggota" description="Task milik setiap PM dan Team Member, dirinci per status" delay="420" class="lg:col-span-2">
                <ul class="mb-4 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-gray-600" aria-label="Legenda status">
                    @foreach ($statusColors as $status => $color)
                        <li class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background-color: {{ $color }}"></span>{{ $status }}</li>
                    @endforeach
                </ul>

                @if ($memberStats->sum('total') > 0)
                    <ul class="space-y-3.5">
                        @foreach ($memberStats as $row)
                            <li class="grid grid-cols-[minmax(0,9rem)_minmax(0,1fr)_auto] items-center gap-3 sm:grid-cols-[minmax(0,12rem)_minmax(0,1fr)_auto]">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <span @class([
                                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                        'bg-gradient-to-br from-indigo-100 to-violet-100 text-indigo-700' => $row['name'] !== 'Belum di-assign',
                                        'bg-gray-100 text-gray-500' => $row['name'] === 'Belum di-assign',
                                    ])>{{ $row['name'] === 'Belum di-assign' ? '?' : mb_strtoupper(mb_substr($row['name'], 0, 1)) }}</span>
                                    <span class="truncate text-sm text-gray-900">{{ $row['name'] }}</span>
                                </div>
                                <div class="h-2.5 rounded-full bg-gray-50">
                                    <div class="flex h-full origin-left gap-0.5 overflow-hidden rounded-full motion-safe:animate-grow-x"
                                         style="width: {{ $row['total'] / $maxLoad * 100 }}%; animation-delay: {{ 480 + $loop->index * 70 }}ms">
                                        @foreach ($statusColors as $status => $color)
                                            @continue($row['counts'][$status] === 0)
                                            <div class="h-full" style="width: {{ $row['counts'][$status] / max(1, $row['total']) * 100 }}%; background-color: {{ $color }}"
                                                 title="{{ $row['name'] }} · {{ $status }}: {{ $row['counts'][$status] }} task"></div>
                                        @endforeach
                                    </div>
                                </div>
                                <span class="text-right text-xs text-gray-500"><span class="text-sm font-semibold text-gray-900">{{ $row['total'] }}</span> task · {{ $row['open'] }} aktif</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-dashboard.empty icon="users" title="Belum ada task yang ditugaskan" />
                @endif

                <details class="group mt-5 rounded-xl ring-1 ring-inset ring-gray-200 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex cursor-pointer items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                        <span class="flex items-center gap-2"><x-icon name="list" class="h-4 w-4 text-gray-400" />Lihat tabel per anggota</span>
                        <svg class="h-4 w-4 text-gray-400 transition duration-200 group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </summary>
                    <div class="overflow-x-auto px-4 pb-3">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <tr class="border-b border-gray-100">
                                    <th scope="col" class="py-2 pr-4">Anggota</th>
                                    @foreach ($statuses as $status)
                                        <th scope="col" class="px-2 py-2 text-right">{{ $status->value }}</th>
                                    @endforeach
                                    <th scope="col" class="py-2 pl-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 tabular-nums">
                                @forelse ($memberStats as $row)
                                    <tr>
                                        <td class="py-2 pr-4 text-gray-900">{{ $row['name'] }}</td>
                                        @foreach ($statuses as $status)
                                            <td class="px-2 py-2 text-right text-gray-700">{{ $row['counts'][$status->value] }}</td>
                                        @endforeach
                                        <td class="py-2 pl-2 text-right font-medium text-gray-900">{{ $row['total'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="py-4 text-gray-500">Belum ada anggota PM / Team Member.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </details>
            </x-dashboard.card>
        </div>

        {{-- Milestones & risks --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-dashboard.card title="Status milestone" :description="$completedMilestones.' dari '.$milestones->count().' milestone selesai'" :href="route('projects.milestones.index', $project)" delay="480" flush class="lg:col-span-2">
                <ul class="divide-y divide-gray-100">
                    @forelse ($milestones as $milestone)
                        @php
                            $milestoneProgress = $milestone->tasks_count > 0 ? (int) round($milestone->done_tasks_count / $milestone->tasks_count * 100) : 0;
                            [$icon, $iconClass] = $milestoneIcons[$milestone->status->value];
                        @endphp
                        <li @class(['flex flex-col gap-3 px-6 py-4 text-sm transition hover:bg-gray-50/70 sm:flex-row sm:items-center sm:gap-4', 'bg-rose-50/40' => $milestone->isOverdue()])>
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset {{ $iconClass }}">
                                    <x-icon :name="$icon" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0">
                                    <a href="{{ route('projects.milestones.show', [$project, $milestone]) }}" class="block truncate font-medium text-gray-900 hover:text-indigo-600">{{ $milestone->name }}</a>
                                    <p @class(['text-xs', 'font-medium text-rose-700' => $milestone->isOverdue(), 'text-gray-500' => ! $milestone->isOverdue()])>
                                        Target {{ $milestone->target_date->translatedFormat('d M Y') }}{{ $milestone->isOverdue() ? ' · terlambat' : '' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 sm:w-72">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-indigo-100">
                                    <div class="h-full origin-left rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 motion-safe:animate-grow-x" style="width: {{ $milestoneProgress }}%; animation-delay: {{ 540 + $loop->index * 70 }}ms"></div>
                                </div>
                                <span class="w-12 text-right text-xs tabular-nums text-gray-600">{{ $milestone->done_tasks_count }}/{{ $milestone->tasks_count }}</span>
                                <x-badge :color="$milestone->status->color()" class="w-24 justify-center">{{ $milestone->status->value }}</x-badge>
                            </div>
                        </li>
                    @empty
                        <li class="p-6"><x-dashboard.empty icon="flag" title="Belum ada milestone." /></li>
                    @endforelse
                </ul>
            </x-dashboard.card>

            <div class="space-y-6">
                <x-dashboard.card title="Ringkasan risiko" :description="$severeRisks.' risiko High / Critical masih terbuka'" :href="route('projects.risks.index', $project)" delay="540">
                    <dl class="grid grid-cols-3 gap-3">
                        @foreach ($riskCounts as $status => $count)
                            <div class="rounded-xl p-3 ring-1 ring-inset ring-gray-100">
                                <dt class="flex items-center gap-1.5 text-xs font-medium text-gray-500"><span class="h-2 w-2 rounded-full {{ $riskDots[$status] ?? 'bg-gray-300' }}"></span>{{ $status }}</dt>
                                <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $count }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-dashboard.card>

                @include('risks.partials.matrix', ['matrix' => $riskMatrix, 'filters' => ['probability' => '', 'impact' => '']])
            </div>
        </div>

        {{-- Overdue tasks --}}
        @if ($overdueTasks->isNotEmpty())
            <x-dashboard.card :title="'Task terlambat ('.$overdueTasks->count().')'" description="Melewati tenggat dan belum Done, paling lama di atas" :href="route('projects.tasks.index', [$project, 'due' => 'overdue'])" delay="600" flush>
                <ul class="divide-y divide-gray-100">
                    @foreach ($overdueTasks as $task)
                        <li>
                            <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="group flex items-center gap-3 px-6 py-3 transition hover:bg-gray-50">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600 ring-1 ring-inset ring-rose-600/10"><x-icon name="clock" class="h-4 w-4" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-gray-900 group-hover:text-indigo-600">{{ $task->title }}</span>
                                    <span class="block truncate text-xs text-gray-500">{{ $task->assignee?->name ?? 'Belum di-assign' }} · {{ $task->status->value }}</span>
                                </span>
                                <span class="shrink-0 text-right">
                                    <span class="block text-xs font-semibold text-rose-700">Terlambat {{ (int) $task->due_date->diffInDays(today()) }} hari</span>
                                    <span class="block text-xs text-gray-500">{{ $task->due_date->translatedFormat('d M Y') }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-dashboard.card>
        @endif
    </div>
</x-app-layout>
