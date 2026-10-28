<x-app-layout>
    <x-slot name="title">{{ $milestone->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.milestones.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Milestone {{ $project->name }}</a>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        @if ($suggestCompletion)
            @can('update', $milestone)
                <div class="flex flex-col gap-3 rounded-md bg-green-50 p-4 ring-1 ring-inset ring-green-600/20 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-green-800">Semua task di milestone ini sudah <strong>Done</strong>. Tandai milestone sebagai Completed?</p>
                    <form method="POST" action="{{ route('projects.milestones.update', [$project, $milestone]) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="name" value="{{ $milestone->name }}">
                        <input type="hidden" name="description" value="{{ $milestone->description }}">
                        <input type="hidden" name="target_date" value="{{ $milestone->target_date->format('Y-m-d') }}">
                        <input type="hidden" name="status" value="{{ App\Enums\MilestoneStatus::Completed->value }}">
                        <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-500">Tandai Completed</button>
                    </form>
                </div>
            @endcan
        @endif

        @if ($milestone->isOverdue())
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 ring-1 ring-inset ring-red-600/20">
                Tanggal target sudah terlewati dan milestone belum Completed. Milestone akan otomatis ditandai <strong>Missed</strong> oleh sistem.
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <x-badge :color="$milestone->status->color()">{{ $milestone->status->value }}</x-badge>
                            <h2 class="mt-2 text-xl font-semibold text-gray-900">{{ $milestone->name }}</h2>
                            <p @class(['mt-1 text-sm', 'text-red-600' => $milestone->isOverdue(), 'text-gray-600' => ! $milestone->isOverdue()])>
                                Target: {{ $milestone->target_date->translatedFormat('d F Y') }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3 text-sm">
                            @can('update', $milestone)
                                <a href="{{ route('projects.milestones.edit', [$project, $milestone]) }}" class="rounded-md border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                            @endcan
                            @can('delete', $milestone)
                                <form method="POST" action="{{ route('projects.milestones.destroy', [$project, $milestone]) }}"
                                      data-confirm="Milestone &quot;{{ $milestone->name }}&quot; akan dihapus. {{ $tasks->count() }} task terkait akan dilepas dari milestone ini."
                                      data-confirm-title="Hapus milestone?"
                                      data-confirm-button="Hapus">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">Hapus</button>
                                </form>
                            @endcan
                        </div>
                    </div>
                    <p class="mt-4 whitespace-pre-line text-sm text-gray-700">{{ $milestone->description ?: 'Tidak ada deskripsi.' }}</p>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <h3 class="border-b border-gray-100 px-6 py-4 font-semibold text-gray-900">Task Terkait ({{ $tasks->count() }})</h3>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($tasks as $task)
                            <li class="flex items-center justify-between gap-3 px-6 py-3 text-sm">
                                <div class="min-w-0">
                                    <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $task->title }}</a>
                                    <p class="text-xs text-gray-500">{{ $task->assignee?->name ?? 'Belum di-assign' }}</p>
                                </div>
                                <x-badge :color="$task->status->color()" class="shrink-0">{{ $task->status->value }}</x-badge>
                            </li>
                        @empty
                            <li class="px-6 py-6 text-sm text-gray-500">Belum ada task yang terhubung ke milestone ini.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg lg:self-start">
                <div class="flex items-baseline justify-between">
                    <h3 class="font-semibold text-gray-900">Ringkasan Task</h3>
                    <span class="text-2xl font-semibold text-indigo-600">{{ $progress }}%</span>
                </div>
                <div class="mt-3 h-2 rounded-full bg-gray-100">
                    <div class="h-2 rounded-full bg-indigo-600" style="width: {{ $progress }}%"></div>
                </div>
                <dl class="mt-5 space-y-2 text-sm">
                    @foreach ($taskCounts as $status => $count)
                        <div class="flex justify-between">
                            <dt class="text-gray-600">{{ $status }}</dt>
                            <dd class="font-medium text-gray-900">{{ $count }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
