<x-app-layout>
    <x-slot name="title">Milestone · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Semua proyek</a>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        @can('create', [App\Models\Milestone::class, $project])
            <div class="flex justify-end">
                <a href="{{ route('projects.milestones.create', $project) }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    + Milestone Baru
                </a>
            </div>
        @endcan

        @if ($milestones->isEmpty())
            <div class="bg-white px-6 py-12 text-center text-sm text-gray-500 shadow-sm sm:rounded-lg">Belum ada milestone.</div>
        @else
            <ol class="space-y-3">
                @foreach ($milestones as $milestone)
                    @php $progress = $milestone->tasks_count > 0 ? (int) round($milestone->done_tasks_count / $milestone->tasks_count * 100) : 0; @endphp
                    <li>
                        <a href="{{ route('projects.milestones.show', [$project, $milestone]) }}"
                           @class([
                               'flex flex-col gap-3 bg-white p-5 shadow-sm transition hover:shadow-md sm:flex-row sm:items-center sm:rounded-lg',
                               'ring-2 ring-red-200' => $milestone->isOverdue(),
                           ])>
                            <div class="sm:w-40 sm:shrink-0">
                                <p @class(['text-sm font-semibold', 'text-red-600' => $milestone->isOverdue(), 'text-gray-900' => ! $milestone->isOverdue()])>
                                    {{ $milestone->target_date->translatedFormat('d M Y') }}
                                </p>
                                @if ($milestone->isOverdue())
                                    <p class="text-xs font-medium text-red-600">Terlambat</p>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate font-semibold text-gray-900">{{ $milestone->name }}</h3>
                                    <x-badge :color="$milestone->status->color()">{{ $milestone->status->value }}</x-badge>
                                </div>
                                <div class="mt-2 flex items-center gap-3">
                                    <div class="h-2 flex-1 rounded-full bg-gray-100">
                                        <div class="h-2 rounded-full bg-indigo-600" style="width: {{ $progress }}%"></div>
                                    </div>
                                    <span class="shrink-0 text-xs text-gray-600">{{ $milestone->done_tasks_count }}/{{ $milestone->tasks_count }} task Done</span>
                                </div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</x-app-layout>
