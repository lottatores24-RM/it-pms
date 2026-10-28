{{--
    Project table on the Midnight Navy dashboards. Expects $projects with the
    card counts from DashboardController; optional $spi (id => SPI), $nextMilestones
    (id => Milestone), $showRole (adds the user's role in each project) and $title.
--}}
@php
$spi ??= null;
$nextMilestones ??= collect();
$showRole ??= false;
$statusDots = ['blue' => 'bg-sky-400', 'green' => 'bg-emerald-400', 'yellow' => 'bg-amber-400', 'gray' => 'bg-night-400'];
$number = fn (?float $value): string => $value === null ? '—' : number_format($value, 2, ',', '.');
@endphp

<x-night.panel :title="$title ?? 'Proyek'" :count="$projects->count()" :href="route('projects.index')" link-label="Semua proyek" flush>
    @if ($projects->isEmpty())
        <div class="p-5"><x-night.empty icon="folder" :title="$emptyTitle ?? 'Belum ada proyek aktif'" :message="$emptyMessage ?? null" /></div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-[13px]">
                <thead class="text-left text-[11.5px] text-night-400">
                    <tr class="border-b border-night-700">
                        <th scope="col" class="w-full px-5 py-2.5 font-normal">Proyek</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-2.5 font-normal">Status</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-2.5 font-normal">Progress</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-2.5 font-normal">{{ $spi ? 'SPI' : 'Milestone berikut' }}</th>
                        <th scope="col" class="whitespace-nowrap px-3 py-2.5 font-normal">Terlambat</th>
                        <th scope="col" class="whitespace-nowrap px-5 py-2.5 text-right font-normal">Deadline</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-night-700">
                    @foreach ($projects as $project)
                        @php
                        $progress = $project->tasks_count > 0 ? (int) round($project->done_tasks_count / $project->tasks_count * 100) : 0;
                        $projectSpi = $spi?->get($project->id);
                        $milestone = $nextMilestones->get($project->id);
                        $href = Gate::allows('viewDashboard', $project) ? route('projects.dashboard', $project) : route('projects.show', $project);
                        @endphp
                        <tr class="group transition hover:bg-white/[0.03]">
                            <td class="w-full max-w-0 px-5 py-3">
                                <a href="{{ $href }}" class="block truncate font-medium text-night-100 group-hover:text-indigo-200">{{ $project->name }}</a>
                                <span class="block truncate font-mono text-[11.5px] text-night-400">{{ $project->code }}@if ($showRole && $project->pivot) · {{ $project->pivot->role_in_project->value }}@endif</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                <span class="flex items-center gap-1.5 text-xs text-night-200"><span class="h-1.5 w-1.5 rounded-full {{ $statusDots[$project->status->color()] ?? 'bg-night-400' }}"></span>{{ $project->status->value }}</span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-1 w-14 overflow-hidden rounded-full bg-white/[0.08]" aria-hidden="true"><div class="h-full rounded-full bg-indigo-400" style="width: {{ $progress }}%"></div></div>
                                    <span class="font-mono text-xs text-night-200">{{ $progress }}%</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                @if ($spi)
                                    <span @class(['font-mono text-xs', 'text-night-400' => $projectSpi === null, 'text-amber-300' => $projectSpi !== null && $projectSpi < 1, 'text-emerald-300' => $projectSpi !== null && $projectSpi >= 1])>{{ $number($projectSpi) }}</span>
                                @elseif ($milestone)
                                    <span class="block max-w-[10rem] truncate text-xs text-night-200">{{ $milestone->name }}</span><span class="font-mono text-[11px] text-night-400">{{ $milestone->target_date->format('d·m·Y') }}</span>
                                @else
                                    <span class="text-xs text-night-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3">
                                @if ($project->overdue_tasks_count > 0)
                                    <x-night.tag tone="rose" :mono="false" class="font-mono">{{ $project->overdue_tasks_count }} task</x-night.tag>
                                @else
                                    <span class="font-mono text-xs text-night-400">0</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-mono text-xs text-night-400">{{ $project->end_date->format('d·m·Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-night.panel>
