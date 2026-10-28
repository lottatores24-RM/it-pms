<x-app-layout>
    <x-slot name="title">Manajemen User</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Manajemen User</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <form method="GET" action="{{ route('admin.users.index') }}" class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-4">
                <div class="sm:col-span-2">
                    <x-input-label for="search" value="Cari" class="sr-only" />
                    <x-text-input id="search" name="search" type="search" class="w-full" placeholder="Cari nama atau email…" :value="$filters['search']" />
                </div>
                <div>
                    <x-input-label for="role" value="Role" class="sr-only" />
                    <x-select-input id="role" name="role" class="w-full" onchange="this.form.submit()">
                        <option value="">Semua role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected($filters['role'] === $role)>{{ $role }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label for="status" value="Status" class="sr-only" />
                    <x-select-input id="status" name="status" class="w-full" onchange="this.form.submit()">
                        <option value="">Semua status</option>
                        <option value="active" @selected($filters['status'] === 'active')>Aktif</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Non-aktif</option>
                    </x-select-input>
                </div>
            </form>

            @can('create', App\Models\User::class)
                <a href="{{ route('admin.users.create') }}" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    + Tambah User
                </a>
            @endcan
        </div>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">Nama</th>
                            <th scope="col" class="hidden px-4 py-3 md:table-cell">Email</th>
                            <th scope="col" class="px-4 py-3">Role</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($users as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.users.show', $user) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $user->name }}</a>
                                    <div class="text-xs text-gray-500 md:hidden">{{ $user->email }}</div>
                                </td>
                                <td class="hidden px-4 py-3 text-gray-600 md:table-cell">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    <x-badge color="indigo">{{ $user->roles->first()?->name ?? 'Tanpa role' }}</x-badge>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($user->is_active)
                                        <x-badge color="green">Aktif</x-badge>
                                    @else
                                        <x-badge color="red">Non-aktif</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        @can('update', $user)
                                            <a href="{{ route('admin.users.edit', $user) }}" class="text-indigo-600 hover:text-indigo-800">Edit</a>
                                        @endcan
                                        @can('toggleActive', $user)
                                            @include('users.partials.toggle-active-form', ['user' => $user])
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-gray-500">Tidak ada user yang cocok dengan filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $users->links() }}
    </div>
</x-app-layout>
