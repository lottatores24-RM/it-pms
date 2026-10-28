<x-app-layout>
    <x-slot name="title">Role & Permission</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Role & Permission</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-4">
        <p class="text-sm text-gray-600">
            Permission role Project Manager, Team Member, dan Viewer juga berlaku di dalam proyek,
            sesuai role yang dimiliki user di proyek tersebut.
        </p>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach ($roles as $role)
                <div class="flex flex-col bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ $role->name }}</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ $role->permissions->count() }} dari {{ $totalPermissions }} permission · {{ $role->users_count }} user
                            </p>
                        </div>
                        @if ($role->name === App\Models\User::ADMIN_ROLE)
                            <x-badge color="yellow">Terkunci</x-badge>
                        @endif
                    </div>

                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach ($role->permissions->groupBy(fn ($permission) => str($permission->name)->before('.')->toString()) as $module => $permissions)
                            <x-badge>{{ __("permissions.modules.{$module}") }} ({{ $permissions->count() }})</x-badge>
                        @endforeach
                    </div>

                    <div class="mt-auto pt-5">
                        <a href="{{ route('admin.roles.edit', $role) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                            @can('update', $role) Kelola permission @else Lihat permission @endcan &rarr;
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
