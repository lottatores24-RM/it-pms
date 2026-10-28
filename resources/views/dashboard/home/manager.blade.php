<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
    </x-slot>

    @php
    $tabs = [
        'requests' => ['label' => 'CR perlu review', 'icon' => 'arrows', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-600/10'],
        'issues' => ['label' => 'Issue siap ditutup', 'icon' => 'check-circle', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-600/10'],
        'tasks' => ['label' => 'Task terlambat', 'icon' => 'clock', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-600/10'],
        'milestones' => ['label' => 'Milestone 7 hari', 'icon' => 'flag', 'tone' => 'bg-amber-50 text-amber-600 ring-amber-600/10'],
    ];
    $activeTab = collect(array_keys($tabs))->first(fn (string $key) => $actions[$key]['count'] > 0) ?? 'requests';
    $more = fn (string $key) => $actions[$key]['count'] - $actions[$key]['items']->count();
    $row = 'group flex items-center gap-3 rounded-xl px-3 py-2.5 transition duration-200 hover:bg-gray-50';
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <x-dashboard.hero :greeting="$greeting" :name="$user->name" :role="$roleName" :summary="$summary">
            <x-slot name="actions">
                <x-dashboard.hero-action :href="route('projects.create')" icon="plus" primary>Buat proyek</x-dashboard.hero-action>
                <x-dashboard.hero-action :href="route('projects.index')" icon="folder">Semua proyek</x-dashboard.hero-action>
            </x-slot>
        </x-dashboard.hero>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-tile label="Proyek yang saya kelola" :value="$kpi['projects']" icon="folder" hint="Belum ditutup" :href="route('projects.index')" delay="80" />
            <x-stat-tile label="Change request menunggu" :value="$kpi['pendingRequests']" icon="arrows" tone="violet" hint="Submitted / Under Review" delay="140" />
            <x-stat-tile label="Task terlambat" :value="$kpi['overdueTasks']" icon="check-circle" tone="emerald" :alert="$kpi['overdueTasks'] > 0"
                         :hint="$kpi['overdueTasks'] > 0 ? 'Di proyek yang saya kelola' : 'Semua task sesuai tenggat'" delay="200" />
            <x-stat-tile label="Risiko High / Critical" :value="$kpi['openRisks']" icon="shield" tone="amber" :alert="$kpi['openRisks'] > 0"
                         :hint="$kpi['openRisks'] > 0 ? 'Masih terbuka, perlu dipantau' : 'Tidak ada risiko besar terbuka'" delay="260" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-dashboard.card title="Perlu tindakan Anda" description="Keputusan dan tindak lanjut di proyek yang Anda kelola" delay="320" flush>
                    <div x-data="{ tab: '{{ $activeTab }}' }">
                        <div class="grid grid-cols-2 gap-2 border-b border-gray-100 p-3 sm:grid-cols-4" role="tablist" aria-label="Kategori tindakan">
                            @foreach ($tabs as $key => $tab)
                                <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                                        :aria-selected="(tab === '{{ $key }}').toString()" @click="tab = '{{ $key }}'"
                                        class="flex items-center gap-3 rounded-xl p-2.5 text-left ring-1 ring-inset transition duration-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                        :class="tab === '{{ $key }}' ? 'bg-indigo-50/70 ring-indigo-300 shadow-sm' : 'ring-gray-200 hover:bg-gray-50 hover:ring-gray-300'">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset {{ $tab['tone'] }}">
                                        <x-icon :name="$tab['icon']" class="h-4 w-4" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-lg font-semibold leading-tight tabular-nums text-gray-900">{{ $actions[$key]['count'] }}</span>
                                        <span class="block text-xs leading-snug transition duration-200" :class="tab === '{{ $key }}' ? 'font-medium text-indigo-700' : 'text-gray-500'">{{ $tab['label'] }}</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        @foreach (array_keys($tabs) as $key)
                            <div id="panel-{{ $key }}" role="tabpanel" aria-labelledby="tab-{{ $key }}" class="p-3"
                                 x-show="tab === '{{ $key }}'" @if ($key !== $activeTab) x-cloak @endif
                                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                @if ($actions[$key]['items']->isEmpty())
                                    <x-dashboard.empty icon="check-circle" title="Tidak ada yang menunggu" message="Bagian ini akan terisi saat ada hal yang perlu Anda tindak lanjuti." class="m-3" />
                                @else
                                    <ul class="space-y-1">
                                        @foreach ($actions[$key]['items'] as $item)
                                            <li>
                                                @switch($key)
                                                    @case('requests')
                                                        <a href="{{ route('projects.change-requests.show', [$item->project, $item]) }}" class="{{ $row }}">
                                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 ring-1 ring-inset ring-violet-600/10"><x-icon name="arrows" class="h-4 w-4" /></span>
                                                            <span class="min-w-0 flex-1">
                                                                <span class="block truncate text-sm font-medium text-gray-900 group-hover:text-indigo-600">{{ $item->title }}</span>
                                                                <span class="block truncate text-xs text-gray-500">{{ $item->project->code }} · diajukan {{ $item->requester->name }} · {{ $item->created_at->diffForHumans() }}</span>
                                                            </span>
                                                            <span class="flex shrink-0 flex-col items-end gap-1">
                                                                <x-badge :color="$item->status->color()">{{ $item->status->value }}</x-badge>
                                                                <span class="text-xs text-gray-500">Prioritas {{ $item->priority->value }}</span>
                                                            </span>
                                                        </a>
                                                        @break
                                                    @case('issues')
                                                        <a href="{{ route('projects.issues.show', [$item->project, $item]) }}" class="{{ $row }}">
                                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-600/10"><x-icon name="check-circle" class="h-4 w-4" /></span>
                                                            <span class="min-w-0 flex-1">
                                                                <span class="block truncate text-sm font-medium text-gray-900 group-hover:text-indigo-600">{{ $item->title }}</span>
                                                                <span class="block truncate text-xs text-gray-500">{{ $item->project->code }} · resolved oleh {{ $item->assignee?->name ?? 'tim' }} · {{ $item->updated_at->diffForHumans() }}</span>
                                                            </span>
                                                            <x-badge :color="$item->severity->color()" class="shrink-0">{{ $item->severity->value }}</x-badge>
                                                        </a>
                                                        @break
                                                    @case('tasks')
                                                        <x-dashboard.task-row :task="$item" />
                                                        @break
                                                    @case('milestones')
                                                        @php $daysLeft = (int) today()->diffInDays($item->target_date, false); @endphp
                                                        <a href="{{ route('projects.milestones.show', [$item->project, $item]) }}" class="{{ $row }}">
                                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 ring-1 ring-inset ring-amber-600/10"><x-icon name="flag" class="h-4 w-4" /></span>
                                                            <span class="min-w-0 flex-1">
                                                                <span class="block truncate text-sm font-medium text-gray-900 group-hover:text-indigo-600">{{ $item->name }}</span>
                                                                <span class="block truncate text-xs text-gray-500">{{ $item->project->code }} · target {{ $item->target_date->translatedFormat('d M Y') }}</span>
                                                            </span>
                                                            <span class="shrink-0 text-xs font-medium text-amber-700">{{ $daysLeft === 0 ? 'Hari ini' : $daysLeft.' hari lagi' }}</span>
                                                        </a>
                                                        @break
                                                @endswitch
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if ($more($key) > 0)
                                        <p class="px-3 pt-2 text-xs text-gray-500">dan {{ $more($key) }} lainnya di halaman proyek.</p>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-dashboard.card>

                <section class="space-y-4">
                    <div class="flex items-end justify-between gap-4 motion-safe:animate-fade-up" style="animation-delay: 380ms">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">Portofolio proyek saya</h2>
                            <p class="text-sm text-gray-500">Progress, kesehatan jadwal, dan SPI dari EVM</p>
                        </div>
                        @if ($managed->count() > $portfolio->count())
                            <a href="{{ route('projects.index') }}" class="group inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                Semua <x-icon name="arrow-right" class="h-4 w-4 transition duration-200 group-hover:translate-x-0.5" />
                            </a>
                        @endif
                    </div>
                    @if ($portfolio->isEmpty())
                        <x-dashboard.empty icon="folder" title="Belum ada proyek yang Anda kelola" message="Buat proyek baru; Anda otomatis menjadi Project Manager-nya." class="bg-white" />
                    @else
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            @foreach ($portfolio as $project)
                                <x-dashboard.project-card :project="$project" :spi="$spi->get($project->id)" :delay="440 + $loop->index * 80" />
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            <div class="space-y-6">
                <x-dashboard.card title="Task saya" description="Task yang ditugaskan langsung kepada Anda" delay="360" flush>
                    <div class="p-3">
                        @forelse ($myTasks as $task)
                            <x-dashboard.task-row :task="$task" />
                        @empty
                            <x-dashboard.empty icon="check-circle" title="Tidak ada task pribadi" class="m-3" />
                        @endforelse
                    </div>
                </x-dashboard.card>

                @include('dashboard.home.partials.notifications', ['delay' => 420])

                @if ($otherProjects->isNotEmpty())
                    <x-dashboard.card title="Proyek lain yang saya ikuti" description="Sebagai Team Member atau Viewer" delay="480" flush>
                        <ul class="divide-y divide-gray-100">
                            @foreach ($otherProjects as $project)
                                @php $progress = $project->tasks_count > 0 ? (int) round($project->done_tasks_count / $project->tasks_count * 100) : 0; @endphp
                                <li>
                                    <a href="{{ route('projects.show', $project) }}" class="group flex items-center gap-3 px-6 py-3 transition duration-200 hover:bg-gray-50">
                                        <x-progress-ring :value="$progress" :label="'Progress '.$project->name" size="h-10 w-10" text-size="text-[10px]" />
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-gray-900 group-hover:text-indigo-600">{{ $project->name }}</span>
                                            <span class="block text-xs text-gray-500">{{ $project->code }} · Anda: {{ $project->pivot->role_in_project->value }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </x-dashboard.card>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
