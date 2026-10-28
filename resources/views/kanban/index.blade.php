<x-app-layout>
    <x-slot name="title">Kanban · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Semua proyek</a>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <form method="GET" action="{{ route('projects.kanban', $project) }}" class="grid grid-cols-2 gap-3 sm:w-auto sm:grid-cols-2">
                <div>
                    <x-input-label for="assignee" value="Anggota" class="sr-only" />
                    <x-select-input id="assignee" name="assignee" class="w-full" onchange="this.form.submit()">
                        <option value="">Semua anggota</option>
                        <option value="me" @selected($filters['assignee'] === 'me')>Task saya</option>
                        <option value="none" @selected($filters['assignee'] === 'none')>Belum di-assign</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}" @selected($filters['assignee'] === (string) $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label for="priority" value="Prioritas" class="sr-only" />
                    <x-select-input id="priority" name="priority" class="w-full" onchange="this.form.submit()">
                        <option value="">Semua prioritas</option>
                        @foreach (App\Enums\TaskPriority::cases() as $priority)
                            <option value="{{ $priority->value }}" @selected($filters['priority'] === $priority->value)>{{ $priority->value }}</option>
                        @endforeach
                    </x-select-input>
                </div>
            </form>

            <p class="text-xs text-gray-500">
                <span class="hidden md:inline">Seret kartu ke kolom lain untuk mengubah status.</span>
                <span class="md:hidden">Gunakan pilihan status pada kartu untuk memindahkannya.</span>
                Kartu yang tidak dapat Anda pindahkan ditandai ikon gembok.
            </p>
        </div>

        <div data-kanban
             data-move-url="{{ route('projects.kanban.move', [$project, '__TASK__']) }}"
             class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($columns as $status => $tasks)
                @php $statusEnum = App\Enums\TaskStatus::from($status); @endphp
                <section class="flex flex-col rounded-lg bg-gray-200/70" aria-label="Kolom {{ $status }}">
                    <header class="flex items-center justify-between px-3 py-2.5">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                            <x-badge :color="$statusEnum->color()">{{ $status }}</x-badge>
                        </h3>
                        <span data-kanban-count data-status="{{ $status }}" class="rounded-full bg-white px-2 text-xs font-medium text-gray-700">{{ $tasks->count() }}</span>
                    </header>

                    <div data-kanban-column data-status="{{ $status }}" class="flex min-h-24 flex-1 flex-col gap-2 px-2 pb-3">
                        @foreach ($tasks as $task)
                            @php $allowed = $transitions[$task->id] ?? []; @endphp
                            <article data-kanban-card
                                     data-task-id="{{ $task->id }}"
                                     data-title="{{ $task->title }}"
                                     data-status="{{ $task->status->value }}"
                                     data-transitions="{{ json_encode($allowed) }}"
                                     data-draggable="{{ count($allowed) > 0 ? 'true' : 'false' }}"
                                     @class([
                                         'rounded-md bg-white p-3 text-sm shadow-sm ring-1 ring-gray-200',
                                         'cursor-grab active:cursor-grabbing' => count($allowed) > 0,
                                     ])>
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $task->title }}</a>
                                    @if (count($allowed) === 0)
                                        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-label="Tidak dapat dipindahkan">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                        </svg>
                                    @endif
                                </div>

                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                    <x-badge :color="$task->priority->color()">{{ $task->priority->value }}</x-badge>
                                    @if ($task->due_date)
                                        <span @class(['font-medium text-red-600' => $task->isOverdue(), 'text-gray-500' => ! $task->isOverdue()])>
                                            {{ $task->due_date->translatedFormat('d M') }}{{ $task->isOverdue() ? ' · terlambat' : '' }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <span class="flex min-w-0 items-center gap-1.5 text-xs text-gray-600">
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700">
                                            {{ $task->assignee ? mb_strtoupper(mb_substr($task->assignee->name, 0, 1)) : '?' }}
                                        </span>
                                        <span class="truncate">{{ $task->assignee?->name ?? 'Belum di-assign' }}</span>
                                    </span>

                                    <label class="md:hidden">
                                        <span class="sr-only">Pindahkan {{ $task->title }}</span>
                                        <select data-kanban-select class="rounded border-gray-300 py-0.5 pl-1.5 pr-6 text-xs disabled:bg-gray-50" @disabled(count($allowed) === 0)>
                                            <option value="" selected>{{ $task->status->value }}</option>
                                            @foreach ($allowed as $target)
                                                <option value="{{ $target }}">&rarr; {{ $target }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
