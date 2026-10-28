{{--
    One task in a dashboard list: title, project and status, priority and a
    human due label ("Terlambat 3 hari", "Hari ini", "Besok", a date).
    Expects the task's project relation to be loaded.
--}}
@props(['task'])

@php
$due = $task->due_date;
$daysLeft = $due ? (int) today()->diffInDays($due, false) : null;
$dueLabel = match (true) {
    $due === null => 'Tanpa tenggat',
    $task->isOverdue() => 'Terlambat '.abs($daysLeft).' hari',
    $daysLeft === 0 => 'Hari ini',
    $daysLeft === 1 => 'Besok',
    default => $due->translatedFormat('d M Y'),
};
@endphp

<a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" {{ $attributes->merge(['class' => 'group flex items-center gap-3 rounded-xl px-3 py-2.5 transition duration-200 hover:bg-gray-50']) }}>
    <span @class([
        'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset',
        'bg-rose-50 text-rose-600 ring-rose-600/10' => $task->isOverdue(),
        'bg-indigo-50 text-indigo-600 ring-indigo-600/10' => ! $task->isOverdue(),
    ])>
        <x-icon :name="$task->isOverdue() ? 'clock' : 'check-circle'" class="h-4 w-4" />
    </span>
    <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-medium text-gray-900 group-hover:text-indigo-600">{{ $task->title }}</span>
        <span class="block truncate text-xs text-gray-500">{{ $task->project->code }} · {{ $task->status->value }}</span>
    </span>
    <span class="flex shrink-0 flex-col items-end gap-1">
        <x-badge :color="$task->priority->color()">{{ $task->priority->value }}</x-badge>
        <span @class(['text-xs', 'font-medium text-rose-700' => $task->isOverdue(), 'text-gray-500' => ! $task->isOverdue()])>{{ $dueLabel }}</span>
    </span>
</a>
