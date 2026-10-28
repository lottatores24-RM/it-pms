<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        <x-dashboard.hero :greeting="$greeting" :name="$user->name" :role="$roleName" :summary="$summary">
            <x-slot name="actions">
                <x-dashboard.hero-action :href="route('projects.index')" icon="eye" primary>Lihat proyek</x-dashboard.hero-action>
            </x-slot>
        </x-dashboard.hero>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-tile label="Proyek dipantau" :value="$kpi['projects']" icon="eye" hint="Belum ditutup" :href="route('projects.index')" delay="80" />
            <x-stat-tile label="Rata-rata progress" :value="$kpi['averageProgress'].'%'" icon="chart" tone="sky" hint="Task Done dibanding total" delay="140" />
            <x-stat-tile label="Milestone 30 hari" :value="$kpi['milestonesSoon']" icon="flag" tone="amber" hint="Target dalam 30 hari ke depan" delay="200" />
            <x-stat-tile label="Issue Critical terbuka" :value="$kpi['criticalIssues']" icon="check-circle" tone="emerald" :alert="$kpi['criticalIssues'] > 0"
                         :hint="$kpi['criticalIssues'] > 0 ? 'Sedang ditangani tim' : 'Tidak ada issue Critical'" delay="260" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="space-y-4">
                    <div class="motion-safe:animate-fade-up" style="animation-delay: 320ms">
                        <h2 class="text-base font-semibold text-gray-900">Proyek yang Anda pantau</h2>
                        <p class="text-sm text-gray-500">Progress, jadwal, dan milestone berikutnya</p>
                    </div>
                    @if ($projects->isEmpty())
                        <x-dashboard.empty icon="eye" title="Belum ada proyek" message="Minta Project Manager menambahkan Anda sebagai Viewer di proyeknya." class="bg-white" />
                    @else
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            @foreach ($projects as $project)
                                <x-dashboard.project-card :project="$project" :role="$project->pivot->role_in_project" :next-milestone="$nextMilestones->get($project->id)" :delay="380 + $loop->index * 80" />
                            @endforeach
                        </div>
                    @endif
                </section>

                <x-dashboard.card title="Pencapaian terbaru" description="Task yang selesai dalam 14 hari terakhir" delay="440" flush>
                    @if ($recentlyDone->isEmpty())
                        <div class="p-6"><x-dashboard.empty icon="sparkles" title="Belum ada task selesai dalam 14 hari terakhir" /></div>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($recentlyDone as $task)
                                <li class="flex items-center gap-3 px-6 py-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-600/10"><x-icon name="check-circle" class="h-4 w-4" /></span>
                                    <span class="min-w-0 flex-1">
                                        <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" class="block truncate text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $task->title }}</a>
                                        <span class="block truncate text-xs text-gray-500">{{ $task->project->code }} · {{ $task->assignee?->name ?? 'Tim proyek' }}</span>
                                    </span>
                                    <span class="shrink-0 text-xs text-gray-500">{{ $task->completed_at->diffForHumans() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-dashboard.card>
            </div>

            <div class="space-y-6">
                <x-dashboard.card title="Milestone mendatang" description="Target dalam 30 hari ke depan" delay="360">
                    @if ($upcomingMilestones->isEmpty())
                        <x-dashboard.empty icon="flag" title="Tidak ada milestone dalam 30 hari" />
                    @else
                        <ol class="space-y-4">
                            @foreach ($upcomingMilestones as $milestone)
                                @php $daysLeft = (int) today()->diffInDays($milestone->target_date, false); @endphp
                                <li class="flex items-start gap-3">
                                    <span class="flex w-12 shrink-0 flex-col items-center rounded-xl bg-indigo-50 py-1.5 text-indigo-700 ring-1 ring-inset ring-indigo-600/10">
                                        <span class="text-base font-semibold leading-none tabular-nums">{{ $milestone->target_date->format('d') }}</span>
                                        <span class="mt-0.5 text-[10px] font-medium uppercase tracking-wider">{{ $milestone->target_date->translatedFormat('M') }}</span>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <a href="{{ route('projects.milestones.show', [$milestone->project, $milestone]) }}" class="block truncate text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $milestone->name }}</a>
                                        <span class="block truncate text-xs text-gray-500">{{ $milestone->project->code }} · {{ $daysLeft === 0 ? 'hari ini' : $daysLeft.' hari lagi' }}</span>
                                    </span>
                                    <x-badge :color="$milestone->status->color()" class="shrink-0">{{ $milestone->status->value }}</x-badge>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-dashboard.card>

                @include('dashboard.home.partials.notifications', ['delay' => 420])
            </div>
        </div>
    </div>
</x-app-layout>
