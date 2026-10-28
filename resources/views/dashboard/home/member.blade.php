<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
    </x-slot>

    @php
    $groups = [
        'overdue' => ['label' => 'Terlambat', 'dot' => 'bg-rose-500'],
        'today' => ['label' => 'Hari ini', 'dot' => 'bg-amber-400'],
        'week' => ['label' => '7 hari ke depan', 'dot' => 'bg-indigo-500'],
        'later' => ['label' => 'Nanti / tanpa tenggat', 'dot' => 'bg-gray-300'],
    ];
    $perGroup = 5;
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <x-dashboard.hero :greeting="$greeting" :name="$user->name" :role="$roleName" :summary="$summary">
            <x-slot name="actions">
                <x-dashboard.hero-action :href="route('projects.index')" icon="folder" primary>Proyek saya</x-dashboard.hero-action>
                <x-dashboard.hero-action :href="route('notifications.index')" icon="bell">Notifikasi</x-dashboard.hero-action>
            </x-slot>
        </x-dashboard.hero>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-tile label="Task aktif" :value="$kpi['openTasks']" icon="list" hint="Belum Done" delay="80" />
            <x-stat-tile label="Terlambat" :value="$kpi['overdueTasks']" icon="check-circle" tone="emerald" :alert="$kpi['overdueTasks'] > 0"
                         :hint="$kpi['overdueTasks'] > 0 ? 'Perlu segera diselesaikan' : 'Tidak ada yang terlambat'" delay="140" />
            <x-stat-tile label="Jatuh tempo 7 hari" :value="$kpi['dueSoon']" icon="calendar" tone="amber" hint="Termasuk hari ini" delay="200" />
            <x-stat-tile label="Selesai 7 hari terakhir" :value="$kpi['doneThisWeek']" icon="sparkles" tone="violet"
                         :hint="$kpi['doneThisWeek'] > 0 ? 'Kerja bagus, pertahankan!' : 'Belum ada task selesai minggu ini'" delay="260" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-dashboard.card title="Fokus kerja Anda" description="Task yang ditugaskan kepada Anda, dikelompokkan menurut tenggat" delay="320">
                    @if ($kpi['openTasks'] === 0)
                        <x-dashboard.empty icon="sparkles" title="Semua beres!" message="Belum ada task aktif yang ditugaskan kepada Anda." />
                    @else
                        <div class="space-y-5">
                            @foreach ($groups as $key => $group)
                                @continue($buckets[$key]->isEmpty())
                                <div class="motion-safe:animate-fade-up" style="animation-delay: {{ 380 + $loop->index * 80 }}ms">
                                    <div class="flex items-center gap-2 px-3 pb-1">
                                        <span class="h-2 w-2 rounded-full {{ $group['dot'] }}" aria-hidden="true"></span>
                                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $group['label'] }}</h3>
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium tabular-nums text-gray-600">{{ $buckets[$key]->count() }}</span>
                                    </div>
                                    <div>
                                        @foreach ($buckets[$key]->take($perGroup) as $task)
                                            <x-dashboard.task-row :task="$task" />
                                        @endforeach
                                    </div>
                                    @if ($buckets[$key]->count() > $perGroup)
                                        <p class="px-3 pt-1 text-xs text-gray-500">dan {{ $buckets[$key]->count() - $perGroup }} task lainnya.</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-dashboard.card>

                <section class="space-y-4">
                    <div class="motion-safe:animate-fade-up" style="animation-delay: 380ms">
                        <h2 class="text-base font-semibold text-gray-900">Proyek saya</h2>
                        <p class="text-sm text-gray-500">Proyek yang sedang Anda ikuti</p>
                    </div>
                    @if ($projects->isEmpty())
                        <x-dashboard.empty icon="folder" title="Belum ada proyek" message="Anda akan melihat proyek di sini setelah ditambahkan oleh Project Manager." class="bg-white" />
                    @else
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            @foreach ($projects as $project)
                                <x-dashboard.project-card :project="$project" :role="$project->pivot->role_in_project" :next-milestone="$nextMilestones->get($project->id)" :delay="440 + $loop->index * 80" />
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            <div class="space-y-6">
                <x-dashboard.card title="Issue untuk saya" description="Open / In Progress, paling parah di atas" delay="360" flush>
                    <div class="p-3">
                        @forelse ($issues as $issue)
                            <a href="{{ route('projects.issues.show', [$issue->project, $issue]) }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 transition duration-200 hover:bg-gray-50">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 ring-1 ring-inset ring-amber-600/10"><x-icon name="alert" class="h-4 w-4" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-gray-900 group-hover:text-indigo-600">{{ $issue->title }}</span>
                                    <span class="block truncate text-xs text-gray-500">{{ $issue->project->code }} · {{ $issue->status->value }}</span>
                                </span>
                                <x-badge :color="$issue->severity->color()" class="shrink-0">{{ $issue->severity->value }}</x-badge>
                            </a>
                        @empty
                            <x-dashboard.empty icon="check-circle" title="Tidak ada issue untuk Anda" class="m-3" />
                        @endforelse
                    </div>
                </x-dashboard.card>

                <x-dashboard.card title="Change request saya" description="Status pengajuan perubahan terakhir" delay="420" flush>
                    <div class="p-3">
                        @forelse ($changeRequests as $changeRequest)
                            <a href="{{ route('projects.change-requests.show', [$changeRequest->project, $changeRequest]) }}" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 transition duration-200 hover:bg-gray-50">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 ring-1 ring-inset ring-violet-600/10"><x-icon name="arrows" class="h-4 w-4" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-gray-900 group-hover:text-indigo-600">{{ $changeRequest->title }}</span>
                                    <span class="block truncate text-xs text-gray-500">{{ $changeRequest->project->code }} · {{ $changeRequest->created_at->diffForHumans() }}</span>
                                </span>
                                <x-badge :color="$changeRequest->status->color()" class="shrink-0">{{ $changeRequest->status->value }}</x-badge>
                            </a>
                        @empty
                            <x-dashboard.empty icon="arrows" title="Belum ada pengajuan" message="Ajukan change request dari tab Change Request di proyek." class="m-3" />
                        @endforelse
                    </div>
                </x-dashboard.card>

                @include('dashboard.home.partials.notifications', ['delay' => 480])
            </div>
        </div>
    </div>
</x-app-layout>
