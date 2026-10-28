{{--
    One task in a Midnight Navy dashboard list: title, project and status, priority
    and a due label ("Terlambat 3 hari", "Hari ini", "Besok", a date).
    Expects the task's project relation to be loaded.
--}}
@props(['task'])

@php
$due = $task->due_date;
$daysLeft = $due ? (int) today()->diffInDays($due, false) : null;
$overdue = $task->isOverdue();
$dueLabel = match (true) {
    $due === null => 'Tanpa tenggat',
    $overdue => 'Terlambat '.abs($daysLeft).' hari',
    $daysLeft === 0 => 'Hari ini',
    $daysLeft === 1 => 'Besok',
    default => $due->format('d·m·Y'),
};
@endphp

<a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" {{ $attributes->merge(['class' => 'group flex items-center gap-3 px-5 py-3 transition hover:bg-white/[0.03]']) }}>
    <span @class(['h-8 w-1 shrink-0 rounded-full', 'bg-rose-400' => $overdue, 'bg-indigo-400' => ! $overdue]) aria-hidden="true"></span>
    <span class="min-w-0 flex-1">
        <span class="block truncate text-[13px] font-medium text-night-100 group-hover:text-indigo-200">{{ $task->title }}</span>
        <span class="block truncate text-xs text-night-400"><span class="font-mono">{{ $task->project->code }}</span> · {{ $task->status->value }}</span>
    </span>
    <span class="flex shrink-0 flex-col items-end gap-1">
        <x-night.tag :tone="$task->priority->color()" :mono="false">{{ $task->priority->value }}</x-night.tag>
        <span @class(['font-mono text-[11px]', 'text-rose-300' => $overdue, 'text-night-400' => ! $overdue])>{{ $dueLabel }}</span>
    </span>
</a>
