{{--
    Project summary card. Expects the counts loaded by DashboardController
    (tasks, done_tasks, overdue_tasks, critical_issues) and the managers
    relation. Optional: the viewer's role, the project's SPI and its next
    milestone. Health chips state facts (counts), never a made-up score.
--}}
@props(['project', 'role' => null, 'spi' => null, 'nextMilestone' => null, 'delay' => null])

@php
$progress = $project->tasks_count > 0 ? (int) round($project->done_tasks_count / $project->tasks_count * 100) : 0;
$daysLeft = (int) today()->diffInDays($project->end_date, false);
$period = match (true) {
    $daysLeft > 0 => "{$daysLeft} hari lagi",
    $daysLeft === 0 => 'Berakhir hari ini',
    default => 'Lewat '.abs($daysLeft).' hari',
};
$spiStatus = App\Enums\PerformanceIndexStatus::fromIndex($spi);
$href = Gate::allows('viewDashboard', $project) ? route('projects.dashboard', $project) : route('projects.show', $project);
$chip = 'inline-flex items-center gap-1 rounded-full px-2 py-1 font-medium ring-1 ring-inset';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'group flex flex-col gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-900/5 transition duration-300 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:ring-indigo-500/20 motion-safe:animate-fade-up']) }} @if ($delay !== null) style="animation-delay: {{ (int) $delay }}ms" @endif>
    <div class="flex items-start gap-4">
        <x-progress-ring :value="$progress" :label="'Progress '.$project->name" />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-[11px] uppercase tracking-wider text-gray-400">{{ $project->code }}</span>
                <x-badge :color="$project->status->color()">{{ $project->status->value }}</x-badge>
            </div>
            <h3 class="mt-1 truncate font-semibold text-gray-900 transition group-hover:text-indigo-600">{{ $project->name }}</h3>
            <p class="truncate text-xs text-gray-500">
                PM: {{ $project->managers->pluck('name')->join(', ') ?: '—' }}@if ($role) · Anda: {{ $role->value }}@endif
            </p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 text-xs">
        <span @class([$chip, 'bg-gray-50 text-gray-600 ring-gray-200' => $daysLeft >= 0, 'bg-rose-50 text-rose-700 ring-rose-600/20' => $daysLeft < 0])>
            <x-icon name="calendar" class="h-3.5 w-3.5" />{{ $period }}
        </span>
        @if ($project->overdue_tasks_count > 0)
            <span class="{{ $chip }} bg-rose-50 text-rose-700 ring-rose-600/20"><x-icon name="clock" class="h-3.5 w-3.5" />{{ $project->overdue_tasks_count }} task terlambat</span>
        @else
            <span class="{{ $chip }} bg-emerald-50 text-emerald-700 ring-emerald-600/20"><x-icon name="check-circle" class="h-3.5 w-3.5" />Tidak ada task terlambat</span>
        @endif
        @if ($project->critical_issues_count > 0)
            <span class="{{ $chip }} bg-amber-50 text-amber-800 ring-amber-600/20"><x-icon name="warning" class="h-3.5 w-3.5" />{{ $project->critical_issues_count }} issue Critical</span>
        @endif
        @if ($spiStatus)
            <span @class([$chip, 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => $spiStatus !== App\Enums\PerformanceIndexStatus::Below, 'bg-rose-50 text-rose-700 ring-rose-600/20' => $spiStatus === App\Enums\PerformanceIndexStatus::Below])>
                <x-icon name="trend" class="h-3.5 w-3.5" />SPI {{ number_format($spi, 2, ',', '.') }}
            </span>
        @endif
    </div>

    <div class="mt-auto flex items-center justify-between gap-3 border-t border-gray-100 pt-3 text-xs text-gray-500">
        @if ($nextMilestone)
            <span class="flex min-w-0 items-center gap-1.5">
                <x-icon name="flag" class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                <span class="truncate">{{ $nextMilestone->name }} · {{ $nextMilestone->target_date->translatedFormat('d M') }}</span>
            </span>
        @else
            <span>{{ $project->done_tasks_count }} dari {{ $project->tasks_count }} task selesai</span>
        @endif
        <x-icon name="arrow-right" class="h-4 w-4 shrink-0 text-gray-300 transition duration-300 group-hover:translate-x-0.5 group-hover:text-indigo-500" />
    </div>
</a>
