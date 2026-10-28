<x-app-layout theme="night">
    <x-slot name="title">Dashboard</x-slot>

    <x-slot name="header">
        <p class="truncate text-[13px] text-night-400">Dashboard <span class="mx-1.5 text-night-600">/</span><span class="text-night-100">Pemantauan proyek</span></p>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('dashboard.home.partials.night-header', ['actions' => [
            ['href' => route('projects.index'), 'label' => 'Lihat proyek', 'icon' => 'eye', 'primary' => true],
        ]])

        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-night-700 bg-night-700 lg:grid-cols-4 motion-safe:animate-fade-up" style="animation-delay: 60ms">
            <x-night.kpi label="Proyek dipantau" :value="$kpi['projects']" hint="Belum ditutup" :href="route('projects.index')" />
            <x-night.kpi label="Milestone 30 hari" :value="$kpi['milestonesSoon']" :tone="$kpi['milestonesSoon'] > 0 ? 'warn' : null" hint="Target dalam 30 hari ke depan" />
            <x-night.kpi label="Issue Critical terbuka" :value="$kpi['criticalIssues']" :tone="$kpi['criticalIssues'] > 0 ? 'bad' : 'good'"
                         :hint="$kpi['criticalIssues'] > 0 ? 'Sedang ditangani tim' : 'Tidak ada issue Critical'" />
            <x-night.kpi label="Rata-rata progress" :value="$kpi['averageProgress'].'%'" tone="accent" :bar="$kpi['averageProgress']" hint="Task Done dibanding total" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 motion-safe:animate-fade-up" style="animation-delay: 120ms">
                @if ($focus)
                    <x-night.evm-chart :focus="$focus" class="h-full" />
                @else
                    <x-night.panel title="Kurva S" description="Muncul setelah task di proyek punya effort points." class="h-full">
                        <x-night.empty icon="chart" title="Belum ada data EVM" message="Grafik rencana dan realisasi tampil di sini saat tim mulai mencatat effort." />
                    </x-night.panel>
                @endif
            </div>

            <x-night.panel title="Milestone mendatang" description="Target dalam 30 hari ke depan" :count="$upcomingMilestones->count()"
                           class="motion-safe:animate-fade-up" style="animation-delay: 180ms">
                @if ($upcomingMilestones->isEmpty())
                    <x-night.empty icon="flag" title="Tidak ada milestone dalam 30 hari" />
                @else
                    <ol class="relative space-y-5 border-l border-night-700 pl-5">
                        @foreach ($upcomingMilestones as $milestone)
                            @php $daysLeft = (int) today()->diffInDays($milestone->target_date, false); @endphp
                            <li class="relative">
                                <span class="absolute -left-[25px] top-1 h-2.5 w-2.5 rounded-full border-2 border-night-850 {{ $loop->first ? 'bg-indigo-400' : 'bg-night-600' }}" aria-hidden="true"></span>
                                <p class="font-mono text-[11px] text-night-400">{{ $milestone->target_date->format('d·m·Y') }} · {{ $daysLeft === 0 ? 'hari ini' : $daysLeft.' hari lagi' }}</p>
                                <a href="{{ route('projects.milestones.show', [$milestone->project, $milestone]) }}" class="mt-0.5 block truncate text-[13px] font-medium text-night-100 hover:text-indigo-200">{{ $milestone->name }}</a>
                                <p class="mt-0.5 flex items-center gap-2 text-xs text-night-400"><span class="font-mono">{{ $milestone->project->code }}</span><x-night.tag :tone="$milestone->status->color()" :mono="false">{{ $milestone->status->value }}</x-night.tag></p>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-night.panel>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2 motion-safe:animate-fade-up" style="animation-delay: 240ms">
                @include('dashboard.home.partials.night-projects', [
                    'title' => 'Proyek yang Anda pantau',
                    'projects' => $projects,
                    'nextMilestones' => $nextMilestones,
                    'emptyTitle' => 'Belum ada proyek',
                    'emptyMessage' => 'Minta Project Manager menambahkan Anda sebagai Viewer di proyeknya.',
                ])

                <x-night.panel title="Pencapaian terbaru" description="Task yang selesai dalam 14 hari terakhir" :count="$recentlyDone->count()" flush>
                    @if ($recentlyDone->isEmpty())
                        <div class="p-5"><x-night.empty icon="sparkles" title="Belum ada task selesai dalam 14 hari terakhir" /></div>
                    @else
                        <ul class="divide-y divide-night-700">
                            @foreach ($recentlyDone as $task)
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-300"><x-icon name="check-circle" class="h-3.5 w-3.5" /></span>
                                    <span class="min-w-0 flex-1">
                                        <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" class="block truncate text-[13px] font-medium text-night-100 hover:text-indigo-200">{{ $task->title }}</a>
                                        <span class="block truncate text-xs text-night-400"><span class="font-mono">{{ $task->project->code }}</span> · {{ $task->assignee?->name ?? 'Tim proyek' }}</span>
                                    </span>
                                    <span class="shrink-0 font-mono text-[11px] text-night-400">{{ $task->completed_at->diffForHumans() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-night.panel>
            </div>

            <div class="motion-safe:animate-fade-up" style="animation-delay: 300ms">
                @include('dashboard.home.partials.night-notifications')
            </div>
        </div>
    </div>
</x-app-layout>
