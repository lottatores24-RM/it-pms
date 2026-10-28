<x-app-layout>
    <x-slot name="title">{{ $task->title }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.tasks.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Task {{ $project->name }}</a>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :color="$task->status->color()">{{ $task->status->value }}</x-badge>
                                <x-badge :color="$task->priority->color()">{{ $task->priority->value }}</x-badge>
                                @if ($task->isOverdue())
                                    <x-badge color="red">Terlambat</x-badge>
                                @endif
                            </div>
                            <h2 class="mt-2 text-xl font-semibold text-gray-900">{{ $task->title }}</h2>
                        </div>

                        <div class="flex shrink-0 items-center gap-3 text-sm">
                            @can('update', $task)
                                <a href="{{ route('projects.tasks.edit', [$project, $task]) }}" class="rounded-md border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                            @endcan
                            @can('delete', $task)
                                <form method="POST" action="{{ route('projects.tasks.destroy', [$project, $task]) }}"
                                      data-confirm="Task &quot;{{ $task->title }}&quot; akan dihapus."
                                      data-confirm-title="Hapus task?"
                                      data-confirm-button="Hapus">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">Hapus</button>
                                </form>
                            @endcan
                        </div>
                    </div>

                    <p class="mt-4 whitespace-pre-line text-sm text-gray-700">{{ $task->description ?: 'Tidak ada deskripsi.' }}</p>

                    @if (count($transitions) > 0)
                        <div class="mt-6 border-t border-gray-100 pt-5">
                            <p class="mb-2 text-sm font-medium text-gray-700">Ubah status</p>
                            @include('tasks.partials.status-actions')
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>
                    @elseif ($task->status === App\Enums\TaskStatus::Done && ! $project->isClosed())
                        <p class="mt-6 border-t border-gray-100 pt-5 text-sm text-gray-500">Task sudah Done. Hanya Project Manager yang dapat membukanya kembali.</p>
                    @endif
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                        <h3 class="font-semibold text-gray-900">Issue Terkait ({{ $task->issues->count() }})</h3>
                        @can('create', [App\Models\Issue::class, $project])
                            <a href="{{ route('projects.issues.create', [$project, 'task' => $task->id]) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">+ Laporkan issue</a>
                        @endcan
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($task->issues as $issue)
                            <li class="flex items-center justify-between gap-3 px-6 py-3 text-sm">
                                <a href="{{ route('projects.issues.show', [$project, $issue]) }}" class="text-gray-900 hover:text-indigo-600">{{ $issue->title }}</a>
                                <x-badge :color="$issue->status->color()" class="shrink-0">{{ $issue->status->value }}</x-badge>
                            </li>
                        @empty
                            <li class="px-6 py-4 text-sm text-gray-500">Tidak ada issue terkait.</li>
                        @endforelse
                    </ul>
                </div>

                @include('collaboration.attachments', ['target' => $task])
                @include('collaboration.comments', ['target' => $task])

                @include('activity-logs.partials.history')
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg lg:self-start">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Penanggung jawab</dt>
                        <dd class="font-medium text-gray-900">{{ $task->assignee?->name ?? 'Belum di-assign' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Tenggat</dt>
                        <dd @class(['font-medium', 'text-red-600' => $task->isOverdue(), 'text-gray-900' => ! $task->isOverdue()])>{{ $task->due_date?->translatedFormat('d F Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Milestone</dt>
                        <dd class="font-medium text-gray-900">
                            @if ($task->milestone)
                                <a href="{{ route('projects.milestones.show', [$project, $task->milestone]) }}" class="text-indigo-600 hover:text-indigo-800">{{ $task->milestone->name }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Effort points</dt>
                        <dd class="font-medium text-gray-900">{{ $task->effort_points ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Effort aktual</dt>
                        <dd class="font-medium text-gray-900">{{ $task->actual_effort_points ?? '—' }}</dd>
                        @can('recordEffort', $task)
                            <form method="POST" action="{{ route('projects.tasks.recordEffort', [$project, $task]) }}" class="mt-2 flex items-start gap-2">
                                @csrf
                                @method('PATCH')
                                <div class="flex-1">
                                    <x-input-label for="actual_effort_points" value="Catat effort aktual" class="sr-only" />
                                    <x-text-input id="actual_effort_points" name="actual_effort_points" type="number" step="0.01" min="0" class="block w-full text-sm"
                                                  placeholder="Effort terpakai" :value="old('actual_effort_points', $task->actual_effort_points)" />
                                </div>
                                <button type="submit" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Simpan</button>
                            </form>
                            <x-input-error :messages="$errors->actualEffort->get('actual_effort_points')" class="mt-1" />
                            @if ($task->status === App\Enums\TaskStatus::Done && $task->actual_effort_points === null)
                                <p class="mt-1 text-xs text-orange-700">Task sudah Done; isi effort aktual agar perhitungan EVM lengkap.</p>
                            @endif
                        @endcan
                    </div>
                    @if ($task->completed_at)
                        <div>
                            <dt class="text-gray-500">Selesai pada</dt>
                            <dd class="font-medium text-gray-900">{{ $task->completed_at->translatedFormat('d F Y H:i') }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-gray-500">Dibuat</dt>
                        <dd class="font-medium text-gray-900">{{ $task->created_at->translatedFormat('d F Y H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
