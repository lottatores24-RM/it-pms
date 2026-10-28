<x-app-layout theme="night">
    <x-slot name="title">Dashboard</x-slot>

    <x-slot name="header">
        <p class="truncate text-[13px] text-night-400">Dashboard <span class="mx-1.5 text-night-600">/</span><span class="text-night-100">Ringkasan proyek</span></p>
    </x-slot>

    @php
    $types = [
        'requests' => ['label' => 'CR', 'filter' => 'CR', 'tone' => 'violet'],
        'tasks' => ['label' => 'Late', 'filter' => 'Terlambat', 'tone' => 'rose'],
        'issues' => ['label' => 'Issue', 'filter' => 'Issue', 'tone' => 'emerald'],
        'milestones' => ['label' => 'Due', 'filter' => 'Milestone', 'tone' => 'amber'],
    ];
    $inbox = collect();
    foreach ($actions['requests']['items'] as $item) {
        $inbox->push(['type' => 'requests', 'title' => $item->title, 'href' => route('projects.change-requests.show', [$item->project, $item]),
            'meta' => "{$item->project->code} · {$item->requester->name} · {$item->status->value}", 'aside' => 'menunggu '.$item->created_at->diffForHumans(null, true)]);
    }
    foreach ($actions['tasks']['items'] as $item) {
        $inbox->push(['type' => 'tasks', 'title' => $item->title, 'href' => route('projects.tasks.show', [$item->project, $item]),
            'meta' => "{$item->project->code} · {$item->status->value}", 'aside' => abs((int) today()->diffInDays($item->due_date, false)).' hari', 'alert' => true]);
    }
    foreach ($actions['issues']['items'] as $item) {
        $inbox->push(['type' => 'issues', 'title' => $item->title, 'href' => route('projects.issues.show', [$item->project, $item]),
            'meta' => "{$item->project->code} · resolved oleh ".($item->assignee?->name ?? 'tim'), 'aside' => 'siap ditutup']);
    }
    foreach ($actions['milestones']['items'] as $item) {
        $daysLeft = (int) today()->diffInDays($item->target_date, false);
        $inbox->push(['type' => 'milestones', 'title' => $item->name, 'href' => route('projects.milestones.show', [$item->project, $item]),
            'meta' => "{$item->project->code} · target ".$item->target_date->format('d·m·Y'), 'aside' => $daysLeft === 0 ? 'hari ini' : "{$daysLeft} hari lagi"]);
    }
    $byStatus = $managed->countBy(fn ($project) => $project->status->value);
    $waitingParts = collect([
        $kpi['pendingRequests'] ? "{$kpi['pendingRequests']} CR" : null,
        $kpi['overdueTasks'] ? "{$kpi['overdueTasks']} terlambat" : null,
        $actions['issues']['count'] ? "{$actions['issues']['count']} issue" : null,
        $actions['milestones']['count'] ? "{$actions['milestones']['count']} milestone" : null,
    ])->filter();
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        @include('dashboard.home.partials.night-header', ['actions' => [
            ['href' => route('projects.create'), 'label' => 'Proyek baru', 'icon' => 'plus', 'primary' => true],
            ['href' => route('projects.index'), 'label' => 'Semua proyek', 'icon' => 'folder'],
        ]])

        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-night-700 bg-night-700 lg:grid-cols-4 motion-safe:animate-fade-up" style="animation-delay: 60ms">
            <x-night.kpi label="Proyek dikelola" :value="$kpi['projects']" :href="route('projects.index')"
                         :hint="$byStatus->map(fn ($count, $status) => $count.' '.$status)->join(' · ') ?: 'Belum ada proyek aktif'" />
            <x-night.kpi label="Task terlambat" :value="$kpi['overdueTasks']" :tone="$kpi['overdueTasks'] > 0 ? 'bad' : 'good'"
                         :hint="$kpi['overdueTasks'] > 0 ? 'Di proyek yang Anda kelola' : 'Semua sesuai tenggat'" />
            <x-night.kpi label="Risiko High / Critical" :value="$kpi['openRisks']" :tone="$kpi['openRisks'] > 0 ? 'warn' : 'good'"
                         :hint="$kpi['openRisks'] > 0 ? 'Masih terbuka' : 'Tidak ada risiko besar'" />
            <x-night.kpi label="Menunggu Anda" :value="$kpi['waiting']" tone="accent"
                         :hint="$waitingParts->join(' · ') ?: 'Tidak ada yang mendesak'" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 motion-safe:animate-fade-up" style="animation-delay: 120ms">
                @if ($focus)
                    <x-night.evm-chart :focus="$focus" class="h-full" />
                @else
                    <x-night.panel title="Kurva S" description="Muncul setelah task di proyek Anda punya effort points." class="h-full">
                        <x-night.empty icon="chart" title="Belum ada data EVM" message="Isi effort points pada task agar PV, EV, dan AC bisa dihitung." />
                    </x-night.panel>
                @endif
            </div>

            <x-night.panel title="Inbox" description="Keputusan dan tindak lanjut Anda" :count="$kpi['waiting']" flush
                           class="motion-safe:animate-fade-up" style="animation-delay: 180ms" x-data="{ filter: 'all' }">
                @if ($inbox->isEmpty())
                    <div class="p-5"><x-night.empty icon="check-circle" title="Inbox kosong" message="Tidak ada CR, task terlambat, atau issue yang menunggu Anda." /></div>
                @else
                    <div class="flex flex-wrap gap-1 border-b border-night-700 px-5 py-2.5" role="group" aria-label="Saring inbox">
                        <button type="button" @click="filter = 'all'" :aria-pressed="(filter === 'all').toString()"
                                class="rounded px-2 py-0.5 text-xs transition" :class="filter === 'all' ? 'bg-white/10 text-white' : 'text-night-400 hover:text-night-100'">Semua</button>
                        @foreach ($types as $key => $type)
                            @if ($actions[$key]['count'] > 0)
                                <button type="button" @click="filter = '{{ $key }}'" :aria-pressed="(filter === '{{ $key }}').toString()"
                                        class="rounded px-2 py-0.5 text-xs transition" :class="filter === '{{ $key }}' ? 'bg-white/10 text-white' : 'text-night-400 hover:text-night-100'">
                                    {{ $type['filter'] }} <span class="font-mono">{{ $actions[$key]['count'] }}</span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                    <ul class="max-h-80 divide-y divide-night-700 overflow-y-auto">
                        @foreach ($inbox as $entry)
                            <li x-show="filter === 'all' || filter === '{{ $entry['type'] }}'">
                                <a href="{{ $entry['href'] }}" class="group block px-5 py-3 transition hover:bg-white/[0.03]">
                                    <span class="flex items-center gap-2">
                                        <x-night.tag :tone="$types[$entry['type']]['tone']">{{ $types[$entry['type']]['label'] }}</x-night.tag>
                                        <span class="truncate text-[13px] font-medium text-night-100 group-hover:text-indigo-200">{{ $entry['title'] }}</span>
                                    </span>
                                    <span class="mt-1 flex items-center justify-between gap-3 text-xs text-night-400">
                                        <span class="truncate">{{ $entry['meta'] }}</span>
                                        <span @class(['shrink-0 font-mono text-[11px]', 'text-rose-300' => $entry['alert'] ?? false])>{{ $entry['aside'] }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-night.panel>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2 motion-safe:animate-fade-up" style="animation-delay: 240ms">
                @include('dashboard.home.partials.night-projects', [
                    'title' => 'Portofolio proyek saya',
                    'projects' => $portfolio,
                    'spi' => $spi,
                    'emptyTitle' => 'Belum ada proyek yang Anda kelola',
                    'emptyMessage' => 'Buat proyek baru; Anda otomatis menjadi Project Manager-nya.',
                ])

                @if ($otherProjects->isNotEmpty())
                    @include('dashboard.home.partials.night-projects', [
                        'title' => 'Proyek lain yang saya ikuti',
                        'projects' => $otherProjects,
                        'spi' => null,
                        'showRole' => true,
                    ])
                @endif
            </div>

            <div class="space-y-6 motion-safe:animate-fade-up" style="animation-delay: 300ms">
                <x-night.panel title="Task saya" description="Ditugaskan langsung kepada Anda" :count="$myTasks->count()" flush>
                    <div class="divide-y divide-night-700">
                        @forelse ($myTasks as $task)
                            <x-night.task-row :task="$task" />
                        @empty
                            <div class="p-5"><x-night.empty icon="check-circle" title="Tidak ada task pribadi" /></div>
                        @endforelse
                    </div>
                </x-night.panel>

                @include('dashboard.home.partials.night-notifications')
            </div>
        </div>
    </div>
</x-app-layout>
