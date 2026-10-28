<x-app-layout theme="night">
    <x-slot name="title">Dashboard</x-slot>

    <x-slot name="header">
        <p class="truncate text-[13px] text-night-400">Dashboard <span class="mx-1.5 text-night-600">/</span><span class="text-night-100">Pekerjaan saya</span></p>
    </x-slot>

    @php
    $groups = [
        'overdue' => ['label' => 'Terlambat', 'dot' => 'bg-rose-400'],
        'today' => ['label' => 'Hari ini', 'dot' => 'bg-amber-300'],
        'week' => ['label' => '7 hari ke depan', 'dot' => 'bg-indigo-400'],
        'later' => ['label' => 'Nanti / tanpa tenggat', 'dot' => 'bg-night-400'],
    ];
    $perGroup = 5;
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        @include('dashboard.home.partials.night-header', ['actions' => [
            ['href' => route('projects.index'), 'label' => 'Proyek saya', 'icon' => 'folder', 'primary' => true],
            ['href' => route('notifications.index'), 'label' => 'Notifikasi', 'icon' => 'bell'],
        ]])

        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-night-700 bg-night-700 lg:grid-cols-4 motion-safe:animate-fade-up" style="animation-delay: 60ms">
            <x-night.kpi label="Task aktif" :value="$kpi['openTasks']" hint="Belum Done" />
            <x-night.kpi label="Terlambat" :value="$kpi['overdueTasks']" :tone="$kpi['overdueTasks'] > 0 ? 'bad' : 'good'"
                         :hint="$kpi['overdueTasks'] > 0 ? 'Perlu segera diselesaikan' : 'Tidak ada yang terlambat'" />
            <x-night.kpi label="Selesai 7 hari terakhir" :value="$kpi['doneThisWeek']" :tone="$kpi['doneThisWeek'] > 0 ? 'good' : null"
                         :hint="$kpi['doneThisWeek'] > 0 ? 'Kerja bagus, pertahankan!' : 'Belum ada yang selesai minggu ini'" />
            <x-night.kpi label="Jatuh tempo 7 hari" :value="$kpi['dueSoon']" tone="accent" hint="Termasuk hari ini" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-night.panel title="Fokus kerja" description="Task yang ditugaskan kepada Anda, dikelompokkan menurut tenggat" :count="$kpi['openTasks']" flush
                           class="lg:col-span-2 motion-safe:animate-fade-up" style="animation-delay: 120ms">
                @if ($kpi['openTasks'] === 0)
                    <div class="p-5"><x-night.empty icon="sparkles" title="Semua beres!" message="Belum ada task aktif yang ditugaskan kepada Anda." /></div>
                @else
                    @foreach ($groups as $key => $group)
                        @continue($buckets[$key]->isEmpty())
                        <div class="border-b border-night-700 last:border-b-0">
                            <div class="flex items-center gap-2 bg-white/[0.02] px-5 py-2">
                                <span class="h-1.5 w-1.5 rounded-full {{ $group['dot'] }}" aria-hidden="true"></span>
                                <h3 class="font-mono text-[11px] uppercase tracking-wider text-night-400">{{ $group['label'] }}</h3>
                                <span class="font-mono text-[11px] text-night-400">{{ $buckets[$key]->count() }}</span>
                            </div>
                            <div class="divide-y divide-night-700">
                                @foreach ($buckets[$key]->take($perGroup) as $task)
                                    <x-night.task-row :task="$task" />
                                @endforeach
                            </div>
                            @if ($buckets[$key]->count() > $perGroup)
                                <p class="px-5 pb-3 text-xs text-night-400">dan {{ $buckets[$key]->count() - $perGroup }} task lainnya.</p>
                            @endif
                        </div>
                    @endforeach
                @endif
            </x-night.panel>

            <div class="space-y-6 motion-safe:animate-fade-up" style="animation-delay: 180ms">
                <x-night.panel title="Issue untuk saya" description="Open / In Progress, paling parah di atas" :count="$issues->count()" flush>
                    <ul class="divide-y divide-night-700">
                        @forelse ($issues as $issue)
                            <li>
                                <a href="{{ route('projects.issues.show', [$issue->project, $issue]) }}" class="group flex items-center gap-3 px-5 py-3 transition hover:bg-white/[0.03]">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[13px] font-medium text-night-100 group-hover:text-indigo-200">{{ $issue->title }}</span>
                                        <span class="block truncate text-xs text-night-400"><span class="font-mono">{{ $issue->project->code }}</span> · {{ $issue->status->value }}</span>
                                    </span>
                                    <x-night.tag :tone="$issue->severity->color()" :mono="false">{{ $issue->severity->value }}</x-night.tag>
                                </a>
                            </li>
                        @empty
                            <li class="p-5"><x-night.empty icon="check-circle" title="Tidak ada issue untuk Anda" /></li>
                        @endforelse
                    </ul>
                </x-night.panel>

                <x-night.panel title="Change request saya" description="Status pengajuan perubahan terakhir" flush>
                    <ul class="divide-y divide-night-700">
                        @forelse ($changeRequests as $changeRequest)
                            <li>
                                <a href="{{ route('projects.change-requests.show', [$changeRequest->project, $changeRequest]) }}" class="group flex items-center gap-3 px-5 py-3 transition hover:bg-white/[0.03]">
                                    <x-night.tag tone="violet">CR</x-night.tag>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[13px] font-medium text-night-100 group-hover:text-indigo-200">{{ $changeRequest->title }}</span>
                                        <span class="block truncate text-xs text-night-400"><span class="font-mono">{{ $changeRequest->project->code }}</span> · {{ $changeRequest->created_at->diffForHumans() }}</span>
                                    </span>
                                    <x-night.tag :tone="$changeRequest->status->color()" :mono="false">{{ $changeRequest->status->value }}</x-night.tag>
                                </a>
                            </li>
                        @empty
                            <li class="p-5"><x-night.empty icon="arrows" title="Belum ada pengajuan" message="Ajukan change request dari tab Change Request di proyek." /></li>
                        @endforelse
                    </ul>
                </x-night.panel>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 motion-safe:animate-fade-up" style="animation-delay: 240ms">
                @include('dashboard.home.partials.night-projects', [
                    'title' => 'Proyek saya',
                    'projects' => $projects,
                    'nextMilestones' => $nextMilestones,
                    'showRole' => true,
                    'emptyTitle' => 'Belum ada proyek',
                    'emptyMessage' => 'Anda akan melihat proyek di sini setelah ditambahkan oleh Project Manager.',
                ])
            </div>

            <div class="motion-safe:animate-fade-up" style="animation-delay: 300ms">
                @include('dashboard.home.partials.night-notifications')
            </div>
        </div>
    </div>
</x-app-layout>
