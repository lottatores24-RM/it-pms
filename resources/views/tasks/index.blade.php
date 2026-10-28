<x-app-layout>
    <x-slot name="title">Task · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Semua proyek</a>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <form method="GET" action="{{ route('projects.tasks.index', $project) }}" class="grid flex-1 grid-cols-2 gap-3 md:grid-cols-5">
                <div class="col-span-2 md:col-span-1">
                    <x-input-label for="search" value="Cari" class="sr-only" />
                    <x-text-input id="search" name="search" type="search" class="w-full" placeholder="Cari judul…" :value="$filters['search']" />
                </div>
                <div>
                    <x-input-label for="status" value="Status" class="sr-only" />
                    <x-select-input id="status" name="status" class="w-full" onchange="this.form.submit()">
                        <option value="">Semua status</option>
                        @foreach (App\Enums\TaskStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->value }}</option>
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
                <div>
                    <x-input-label for="assignee" value="Penanggung jawab" class="sr-only" />
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
                    <x-input-label for="due" value="Tenggat" class="sr-only" />
                    <x-select-input id="due" name="due" class="w-full" onchange="this.form.submit()">
                        <option value="">Semua tenggat</option>
                        <option value="overdue" @selected($filters['due'] === 'overdue')>Terlambat</option>
                        <option value="week" @selected($filters['due'] === 'week')>7 hari ke depan</option>
                        <option value="none" @selected($filters['due'] === 'none')>Tanpa tenggat</option>
                    </x-select-input>
                </div>
            </form>

            @can('create', [App\Models\Task::class, $project])
                <a href="{{ route('projects.tasks.create', $project) }}" class="inline-flex shrink-0 items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    + Task Baru
                </a>
            @endcan
        </div>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">Task</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="hidden px-4 py-3 sm:table-cell">Prioritas</th>
                            <th scope="col" class="hidden px-4 py-3 md:table-cell">Penanggung jawab</th>
                            <th scope="col" class="px-4 py-3">Tenggat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tasks as $task)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $task->title }}</a>
                                    @if ($task->milestone)
                                        <p class="text-xs text-gray-500">Milestone: {{ $task->milestone->name }}</p>
                                    @endif
                                    <p class="text-xs text-gray-500 md:hidden">{{ $task->assignee?->name ?? 'Belum di-assign' }}</p>
                                </td>
                                <td class="px-4 py-3"><x-badge :color="$task->status->color()">{{ $task->status->value }}</x-badge></td>
                                <td class="hidden px-4 py-3 sm:table-cell"><x-badge :color="$task->priority->color()">{{ $task->priority->value }}</x-badge></td>
                                <td class="hidden px-4 py-3 text-gray-700 md:table-cell">
                                    {{ $task->assignee?->name ?? '—' }}
                                    @if ($task->assigned_to === Auth::id())
                                        <span class="text-xs text-gray-500">(Anda)</span>
                                    @endif
                                </td>
                                <td @class(['px-4 py-3 whitespace-nowrap', 'font-medium text-red-600' => $task->isOverdue(), 'text-gray-700' => ! $task->isOverdue()])>
                                    {{ $task->due_date?->translatedFormat('d M Y') ?? '—' }}
                                    @if ($task->isOverdue())
                                        <span class="block text-xs">Terlambat</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-gray-500">Belum ada task yang cocok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $tasks->links() }}
    </div>
</x-app-layout>
