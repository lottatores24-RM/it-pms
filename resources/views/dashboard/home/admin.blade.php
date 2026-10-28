<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
    </x-slot>

    @php
    $actionDots = ['green' => 'bg-emerald-500', 'blue' => 'bg-sky-500', 'red' => 'bg-rose-500', 'yellow' => 'bg-amber-400', 'gray' => 'bg-gray-400'];
    $totalUsers = max(1, $usersByRole->sum());
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <x-dashboard.hero :greeting="$greeting" :name="$user->name" :role="$roleName" :summary="$summary">
            <x-slot name="actions">
                <x-dashboard.hero-action :href="route('admin.users.create')" icon="user-plus" primary>Tambah user</x-dashboard.hero-action>
                <x-dashboard.hero-action :href="route('projects.create')" icon="plus">Buat proyek</x-dashboard.hero-action>
                <x-dashboard.hero-action :href="route('admin.activity-logs.index')" icon="list">Activity Log</x-dashboard.hero-action>
            </x-slot>
        </x-dashboard.hero>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-tile label="User aktif" :value="$kpi['activeUsers']" icon="users" :hint="$kpi['inactiveUsers'].' akun non-aktif'" :href="route('admin.users.index')" delay="80" />
            <x-stat-tile label="Proyek berjalan" :value="$kpi['runningProjects']" icon="folder" tone="sky" :hint="'Dari '.$kpi['totalProjects'].' proyek tercatat'" :href="route('projects.index', ['status' => 'In Progress'])" delay="140" />
            <x-stat-tile label="Task terlambat" :value="$kpi['overdueTasks']" icon="check-circle" tone="emerald" :alert="$kpi['overdueTasks'] > 0"
                         :hint="$kpi['overdueTasks'] > 0 ? 'Di seluruh proyek aktif' : 'Semua task sesuai tenggat'" delay="200" />
            <x-stat-tile label="Aktivitas hari ini" :value="$kpi['activityToday']" icon="bolt" tone="violet" hint="Tercatat di Activity Log" :href="route('admin.activity-logs.index', ['from' => today()->toDateString()])" delay="260" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-dashboard.card title="Ringkasan sistem" description="Komposisi proyek dan akun pengguna saat ini" delay="320">
                    <div class="grid gap-8 sm:grid-cols-2">
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Proyek per status</h3>
                            <dl class="mt-4 space-y-4">
                                @foreach ($projectsByStatus as $status => $count)
                                    @include('dashboard.home.partials.bar', ['label' => $status, 'count' => $count, 'total' => $kpi['totalProjects'], 'delay' => 400 + $loop->index * 90])
                                @endforeach
                            </dl>
                        </div>
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">User per role</h3>
                            <dl class="mt-4 space-y-4">
                                @foreach ($usersByRole as $role => $count)
                                    @include('dashboard.home.partials.bar', ['label' => $role, 'count' => $count, 'total' => $totalUsers, 'delay' => 400 + $loop->index * 90])
                                @endforeach
                            </dl>
                        </div>
                    </div>
                </x-dashboard.card>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4 motion-safe:animate-fade-up" style="animation-delay: 380ms">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">Proyek aktif</h2>
                            <p class="text-sm text-gray-500">Diurutkan dari yang paling banyak task terlambat</p>
                        </div>
                        <a href="{{ route('projects.index') }}" class="group inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            Semua proyek <x-icon name="arrow-right" class="h-4 w-4 transition duration-200 group-hover:translate-x-0.5" />
                        </a>
                    </div>
                    @if ($projects->isEmpty())
                        <x-dashboard.empty icon="folder" title="Belum ada proyek aktif" message="Proyek yang dibuat akan tampil di sini beserta progress-nya." class="bg-white" />
                    @else
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            @foreach ($projects as $project)
                                <x-dashboard.project-card :project="$project" :next-milestone="$nextMilestones->get($project->id)" :delay="440 + $loop->index * 80" />
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            <div class="space-y-6">
                <x-dashboard.card title="Aktivitas terbaru" description="Catatan terakhir di seluruh sistem" :href="route('admin.activity-logs.index')" link-label="Log" delay="360" flush>
                    @if ($recentActivity->isEmpty())
                        <div class="p-6"><x-dashboard.empty icon="list" title="Belum ada aktivitas" /></div>
                    @else
                        <ol class="px-6 py-5">
                            @foreach ($recentActivity as $log)
                                <li class="relative flex gap-3 pb-5 last:pb-0">
                                    @unless ($loop->last)
                                        <span class="absolute bottom-0 left-[11px] top-7 w-px bg-gray-200" aria-hidden="true"></span>
                                    @endunless
                                    <span class="relative mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white ring-1 ring-gray-200">
                                        <span class="h-2 w-2 rounded-full {{ $actionDots[$log->action->color()] ?? 'bg-gray-400' }}"></span>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-gray-900"><span class="font-semibold">{{ $log->user_name }}</span> <span class="text-gray-600">{{ Str::lcfirst($log->description) }}</span></p>
                                        <p class="mt-0.5 truncate text-xs text-gray-500">{{ $log->action->label() }} · {{ $log->entityLabel() }}@if ($log->entity_name): {{ $log->entity_name }}@endif</p>
                                        <p class="mt-0.5 text-[11px] text-gray-400">{{ $log->created_at->diffForHumans() }}</p>
                                    </div>
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
