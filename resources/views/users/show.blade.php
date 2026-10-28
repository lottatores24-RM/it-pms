<x-app-layout>
    <x-slot name="title">{{ $user->name }}</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Detail User</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="bg-white p-6 shadow-sm sm:rounded-lg">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-center gap-4">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xl font-semibold text-indigo-700">
                        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-lg font-semibold text-gray-900">{{ $user->name }}</p>
                        <p class="truncate text-sm text-gray-600">{{ $user->email }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <x-badge color="indigo">{{ $user->roles->first()?->name ?? 'Tanpa role' }}</x-badge>
                            @if ($user->is_active)
                                <x-badge color="green">Aktif</x-badge>
                            @else
                                <x-badge color="red">Non-aktif</x-badge>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4 text-sm">
                    @can('update', $user)
                        <a href="{{ route('admin.users.edit', $user) }}" class="rounded-md border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                    @endcan
                    @can('toggleActive', $user)
                        @include('users.partials.toggle-active-form', ['user' => $user])
                    @endcan
                </div>
            </div>

            <dl class="mt-6 grid grid-cols-1 gap-4 border-t border-gray-100 pt-6 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-gray-500">Terdaftar sejak</dt>
                    <dd class="font-medium text-gray-900">{{ $user->created_at->translatedFormat('d F Y') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Jumlah proyek</dt>
                    <dd class="font-medium text-gray-900">{{ $user->projects->count() }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Task aktif</dt>
                    <dd class="font-medium text-gray-900">{{ $activeTaskCount }}</dd>
                </div>
            </dl>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <h3 class="border-b border-gray-100 px-6 py-4 font-semibold text-gray-900">Keanggotaan Proyek</h3>
                <ul class="divide-y divide-gray-100">
                    @forelse ($user->projects as $project)
                        <li class="flex items-center justify-between gap-3 px-6 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-gray-900">{{ $project->name }}</p>
                                <p class="text-xs text-gray-500">{{ $project->code }}</p>
                            </div>
                            <x-badge>{{ $project->pivot->role_in_project->value }}</x-badge>
                        </li>
                    @empty
                        <li class="px-6 py-6 text-sm text-gray-500">Belum menjadi anggota proyek mana pun.</li>
                    @endforelse
                </ul>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg">
                <h3 class="border-b border-gray-100 px-6 py-4 font-semibold text-gray-900">Aktivitas Terbaru</h3>
                <ul class="divide-y divide-gray-100">
                    @forelse ($recentActivity as $log)
                        <li class="px-6 py-3 text-sm">
                            <p class="text-gray-900">{{ $log->description ?? $log->action->value }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $log->entity_type }}{{ $log->entity_name ? ': '.$log->entity_name : '' }}
                                · {{ $log->created_at->translatedFormat('d M Y H:i') }}
                            </p>
                        </li>
                    @empty
                        <li class="px-6 py-6 text-sm text-gray-500">Belum ada aktivitas.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <a href="{{ route('admin.users.index') }}" class="inline-block text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke daftar user</a>
    </div>
</x-app-layout>
