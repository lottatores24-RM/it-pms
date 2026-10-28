{{--
    Project header + tab navigation shared by every page inside a project.
    Tabs appear only once their route exists, so modules show up as they are built.
    Expects: $project.
--}}
@php
$tabs = [
    ['route' => 'projects.show', 'pattern' => 'projects.show', 'label' => 'Ringkasan'],
    ['route' => 'projects.members.index', 'pattern' => 'projects.members.*', 'label' => 'Anggota'],
    ['route' => 'projects.tasks.index', 'pattern' => 'projects.tasks.*', 'label' => 'Task'],
    ['route' => 'projects.kanban', 'pattern' => 'projects.kanban', 'label' => 'Kanban', 'can' => ['viewKanban', $project]],
    ['route' => 'projects.milestones.index', 'pattern' => 'projects.milestones.*', 'label' => 'Milestone'],
    ['route' => 'projects.risks.index', 'pattern' => 'projects.risks.*', 'label' => 'Risiko'],
    ['route' => 'projects.issues.index', 'pattern' => 'projects.issues.*', 'label' => 'Issue'],
    ['route' => 'projects.change-requests.index', 'pattern' => 'projects.change-requests.*', 'label' => 'Change Request'],
    ['route' => 'projects.dashboard', 'pattern' => 'projects.dashboard', 'label' => 'Dashboard', 'can' => ['viewDashboard', $project]],
    ['route' => 'projects.activity', 'pattern' => 'projects.activity', 'label' => 'Aktivitas', 'can' => ['viewProject', [App\Models\ActivityLog::class, $project]]],
];
$tabs = array_filter($tabs, fn (array $tab) => Route::has($tab['route'])
    && (! isset($tab['can']) || Gate::allows(...$tab['can'])));
@endphp

<div class="bg-white shadow-sm sm:rounded-lg">
    <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p class="font-mono text-xs text-gray-500">{{ $project->code }}</p>
            <h1 class="mt-1 text-xl font-semibold text-gray-900 sm:text-2xl">{{ $project->name }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                <x-badge :color="$project->status->color()">{{ $project->status->value }}</x-badge>
                <span>{{ $project->start_date->translatedFormat('d M Y') }} – {{ $project->end_date->translatedFormat('d M Y') }}</span>
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
            @can('update', $project)
                <a href="{{ route('projects.edit', $project) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>
            @endcan
            @can('close', $project)
                <a href="{{ route('projects.close-form', $project) }}" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">Tutup Proyek</a>
            @endcan
            @can('reopen', $project)
                <form method="POST" action="{{ route('projects.reopen', $project) }}"
                      data-confirm="Proyek akan kembali berstatus In Progress dan dapat diubah lagi."
                      data-confirm-title="Buka kembali proyek?"
                      data-confirm-button="Buka kembali"
                      data-confirm-variant="primary">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">Buka Kembali</button>
                </form>
            @endcan
        </div>
    </div>

    @if (count($tabs) > 1)
        <nav class="flex gap-1 overflow-x-auto border-t border-gray-100 px-4" aria-label="Menu proyek">
            @foreach ($tabs as $tab)
                @php $active = request()->routeIs($tab['pattern']); @endphp
                <a href="{{ route($tab['route'], $project) }}"
                   @class([
                       'whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium',
                       'border-indigo-600 text-indigo-600' => $active,
                       'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => ! $active,
                   ])
                   @if ($active) aria-current="page" @endif>
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </nav>
    @endif
</div>

@if ($project->isClosed())
    <div class="flex flex-col gap-3 rounded-md bg-gray-50 p-4 text-sm text-gray-700 ring-1 ring-inset ring-gray-300 sm:flex-row sm:items-start sm:justify-between print:hidden">
        <div>
            <p class="font-semibold">Proyek ditutup pada {{ $project->closed_at?->translatedFormat('d F Y H:i') }} dan bersifat read-only.</p>
            @if ($project->closing_notes)
                <p class="mt-1 whitespace-pre-line">{{ $project->closing_notes }}</p>
            @endif
        </div>
        @can('viewClosingReport', $project)
            <a href="{{ route('projects.closing-report', $project) }}" class="shrink-0 rounded-md border border-gray-300 bg-white px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50">Laporan Penutupan</a>
        @endcan
    </div>
@endif
