<x-app-layout theme="night">
    <x-slot name="title">Dashboard</x-slot>

    <x-slot name="header">
        <p class="truncate text-[13px] text-night-400">Dashboard <span class="mx-1.5 text-night-600">/</span><span class="text-night-100">Ringkasan sistem</span></p>
    </x-slot>

    @php
    $actionDots = ['green' => 'bg-emerald-400', 'blue' => 'bg-sky-400', 'red' => 'bg-rose-400', 'yellow' => 'bg-amber-300', 'gray' => 'bg-night-400'];
    $totalUsers = max(1, $usersByRole->sum());
    $groups = [
        ['title' => 'Proyek per status', 'counts' => $projectsByStatus, 'total' => max(1, $kpi['totalProjects'])],
        ['title' => 'User per role', 'counts' => $usersByRole, 'total' => $totalUsers],
    ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        @include('dashboard.home.partials.night-header', ['actions' => [
            ['href' => route('admin.users.create'), 'label' => 'Tambah user', 'icon' => 'user-plus', 'primary' => true],
            ['href' => route('projects.create'), 'label' => 'Buat proyek', 'icon' => 'plus'],
            ['href' => route('admin.activity-logs.index'), 'label' => 'Activity Log', 'icon' => 'list'],
        ]])

        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-night-700 bg-night-700 lg:grid-cols-4 motion-safe:animate-fade-up" style="animation-delay: 60ms">
            <x-night.kpi label="User aktif" :value="$kpi['activeUsers']" :hint="$kpi['inactiveUsers'].' akun non-aktif'" :href="route('admin.users.index')" />
            <x-night.kpi label="Proyek berjalan" :value="$kpi['runningProjects']" :hint="'Dari '.$kpi['totalProjects'].' proyek tercatat'"
                         :href="route('projects.index', ['status' => 'In Progress'])" />
            <x-night.kpi label="Aktivitas hari ini" :value="$kpi['activityToday']" hint="Tercatat di Activity Log"
                         :href="route('admin.activity-logs.index', ['from' => today()->toDateString()])" />
            <x-night.kpi label="Task terlambat" :value="$kpi['overdueTasks']" tone="accent"
                         :hint="$kpi['overdueTasks'] > 0 ? 'Di seluruh proyek aktif' : 'Semua task sesuai tenggat'" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-night.panel title="Ringkasan sistem" description="Komposisi proyek dan akun pengguna saat ini"
                           class="lg:col-span-2 motion-safe:animate-fade-up" style="animation-delay: 120ms">
                <div class="grid gap-8 pt-2 sm:grid-cols-2">
                    @foreach ($groups as $group)
                        <div>
                            <h3 class="font-mono text-[11px] uppercase tracking-wider text-night-400">{{ $group['title'] }}</h3>
                            <dl class="mt-4 space-y-4">
                                @foreach ($group['counts'] as $label => $count)
                                    @php $share = (int) round($count / $group['total'] * 100); @endphp
                                    <div>
                                        <div class="flex items-center justify-between gap-3 text-[13px]">
                                            <dt class="text-night-200">{{ $label }}</dt>
                                            <dd class="font-mono text-night-100">{{ $count }} <span class="text-[11px] text-night-400">{{ $share }}%</span></dd>
                                        </div>
                                        <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-white/[0.08]" aria-hidden="true">
                                            <div class="h-full origin-left rounded-full bg-indigo-400 motion-safe:animate-grow-x" style="width: {{ $share }}%; animation-delay: {{ 200 + $loop->index * 80 }}ms"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endforeach
                </div>
            </x-night.panel>

            <x-night.panel title="Aktivitas terbaru" description="Catatan terakhir di seluruh sistem" :href="route('admin.activity-logs.index')" link-label="Log"
                           class="motion-safe:animate-fade-up" style="animation-delay: 180ms">
                @if ($recentActivity->isEmpty())
                    <x-night.empty icon="list" title="Belum ada aktivitas" />
                @else
                    <div class="max-h-80 overflow-y-auto pl-1.5">
                    <ol class="space-y-4 border-l border-night-700 pl-5">
                        @foreach ($recentActivity as $log)
                            <li class="relative">
                                <span class="absolute -left-[25px] top-1.5 h-2.5 w-2.5 rounded-full border-2 border-night-850 {{ $actionDots[$log->action->color()] ?? 'bg-night-400' }}" aria-hidden="true"></span>
                                <p class="text-[13px] text-night-100"><span class="font-semibold">{{ $log->user_name }}</span> <span class="text-night-200">{{ Str::lcfirst($log->description) }}</span></p>
                                <p class="mt-0.5 truncate text-xs text-night-400">{{ $log->action->label() }} · {{ $log->entityLabel() }}@if ($log->entity_name): {{ $log->entity_name }}@endif</p>
                                <p class="mt-0.5 font-mono text-[11px] text-night-400/70">{{ $log->created_at->diffForHumans() }}</p>
                            </li>
                        @endforeach
                    </ol>
                    </div>
                @endif
            </x-night.panel>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 motion-safe:animate-fade-up" style="animation-delay: 240ms">
                @include('dashboard.home.partials.night-projects', [
                    'title' => 'Proyek aktif · paling banyak task terlambat',
                    'projects' => $projects,
                    'nextMilestones' => $nextMilestones,
                    'emptyTitle' => 'Belum ada proyek aktif',
                    'emptyMessage' => 'Proyek yang dibuat akan tampil di sini beserta progress-nya.',
                ])
            </div>

            <div class="motion-safe:animate-fade-up" style="animation-delay: 300ms">
                @include('dashboard.home.partials.night-notifications')
            </div>
        </div>
    </div>
</x-app-layout>
