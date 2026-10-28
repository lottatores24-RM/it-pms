<x-app-layout>
    <x-slot name="title">Proyek</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Proyek</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <form method="GET" action="{{ route('projects.index') }}" class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-input-label for="search" value="Cari" class="sr-only" />
                    <x-text-input id="search" name="search" type="search" class="w-full" placeholder="Cari nama atau kode proyek…" :value="$filters['search']" />
                </div>
                <div>
                    <x-input-label for="status" value="Status" class="sr-only" />
                    <x-select-input id="status" name="status" class="w-full" onchange="this.form.submit()">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->value }}</option>
                        @endforeach
                    </x-select-input>
                </div>
            </form>

            @can('create', App\Models\Project::class)
                <a href="{{ route('projects.create') }}" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    + Proyek Baru
                </a>
            @endcan
        </div>

        @if ($projects->isEmpty())
            <div class="bg-white px-6 py-12 text-center text-sm text-gray-500 shadow-sm sm:rounded-lg">
                @if ($filters['search'] || $filters['status'])
                    Tidak ada proyek yang cocok dengan filter.
                @else
                    Anda belum menjadi anggota proyek mana pun.
                @endif
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($projects as $project)
                    @php
                        $progress = $project->tasks_count > 0 ? (int) round($project->done_tasks_count / $project->tasks_count * 100) : 0;
                        $myRole = $project->projectMembers->first()?->role_in_project;
                    @endphp
                    <a href="{{ route('projects.show', $project) }}" class="flex flex-col bg-white p-5 shadow-sm transition hover:shadow-md sm:rounded-lg">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-mono text-xs text-gray-500">{{ $project->code }}</p>
                                <h3 class="mt-0.5 truncate font-semibold text-gray-900">{{ $project->name }}</h3>
                            </div>
                            <x-badge :color="$project->status->color()" class="shrink-0">{{ $project->status->value }}</x-badge>
                        </div>

                        <p class="mt-2 text-sm text-gray-500">
                            {{ $project->start_date->translatedFormat('d M Y') }} – {{ $project->end_date->translatedFormat('d M Y') }}
                        </p>

                        <div class="mt-4">
                            <div class="flex justify-between text-xs text-gray-600">
                                <span>Progress</span>
                                <span>{{ $progress }}% ({{ $project->done_tasks_count }}/{{ $project->tasks_count }} task)</span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-gray-100">
                                <div class="h-2 rounded-full bg-indigo-600" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between text-xs text-gray-500">
                            <span>{{ $project->members_count }} anggota</span>
                            @if ($myRole)
                                <span>Peran Anda: <span class="font-medium text-gray-700">{{ $myRole->value }}</span></span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        {{ $projects->links() }}
    </div>
</x-app-layout>
