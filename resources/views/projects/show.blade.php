<x-app-layout>
    <x-slot name="title">{{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Semua proyek</a>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="font-semibold text-gray-900">Detail Proyek</h3>
                    <p class="mt-3 whitespace-pre-line text-sm text-gray-700">{{ $project->description ?: 'Belum ada deskripsi.' }}</p>

                    <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-gray-100 pt-6 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-gray-500">Sponsor</dt>
                            <dd class="font-medium text-gray-900">{{ $project->sponsor ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Durasi</dt>
                            <dd class="font-medium text-gray-900">{{ (int) $project->start_date->diffInDays($project->end_date) + 1 }} hari</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Dibuat oleh</dt>
                            <dd class="font-medium text-gray-900">{{ $project->creator->name }} · {{ $project->created_at->translatedFormat('d M Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Peran Anda</dt>
                            <dd class="font-medium text-gray-900">{{ $myRole?->value ?? 'Administrator (bukan anggota)' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex items-baseline justify-between">
                        <h3 class="font-semibold text-gray-900">Progress Task</h3>
                        <span class="text-2xl font-semibold text-indigo-600">{{ $progress }}%</span>
                    </div>
                    <div class="mt-3 h-3 rounded-full bg-gray-100">
                        <div class="h-3 rounded-full bg-indigo-600" style="width: {{ $progress }}%"></div>
                    </div>

                    <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($taskCounts as $status => $count)
                            <div class="rounded-md border border-gray-100 p-3">
                                <dt class="text-xs text-gray-500">{{ $status }}</dt>
                                <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $count }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="mt-3 text-xs text-gray-500">Total {{ $totalTasks }} task. Progress = task Done ÷ total task.</p>
                </div>

                @include('collaboration.attachments', ['target' => $project])
                @include('collaboration.comments', ['target' => $project])
            </div>

            <div class="space-y-6">
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <h3 class="border-b border-gray-100 px-6 py-4 font-semibold text-gray-900">Anggota ({{ $project->projectMembers->count() }})</h3>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($project->projectMembers as $member)
                            <li class="flex items-center justify-between gap-3 px-6 py-3 text-sm">
                                <span class="truncate text-gray-900">{{ $member->user->name }}</span>
                                <x-badge :color="$member->role_in_project === App\Enums\ProjectRole::ProjectManager ? 'indigo' : 'gray'" class="shrink-0">
                                    {{ $member->role_in_project->value }}
                                </x-badge>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg">
                    <h3 class="border-b border-gray-100 px-6 py-4 font-semibold text-gray-900">Ringkasan</h3>
                    @include('projects.partials.open-items')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
