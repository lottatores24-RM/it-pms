<x-app-layout>
    <x-slot name="title">Permission {{ $role->name }}</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Permission: {{ $role->name }}</h2>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-4">
        @if ($canUpdate)
            <p class="text-sm text-gray-600">
                Perubahan berlaku untuk {{ $role->users_count }} user dengan role global ini
                dan untuk semua anggota proyek dengan role {{ $role->name }} di proyeknya.
            </p>
        @else
            <div class="rounded-md bg-yellow-50 p-4 text-sm text-yellow-800 ring-1 ring-inset ring-yellow-600/20">
                Role {{ $role->name }} selalu memiliki seluruh permission dan tidak dapat dikurangi.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.roles.update', $role) }}"
              data-confirm="Permission role {{ $role->name }} akan diperbarui dan langsung berlaku untuk semua user terkait."
              data-confirm-title="Simpan perubahan permission?"
              data-confirm-button="Simpan"
              data-confirm-variant="primary">
            @csrf
            @method('PUT')

            <x-input-error :messages="$errors->get('permissions')" class="mb-4" />
            <x-input-error :messages="$errors->get('permissions.*')" class="mb-4" />

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <ul class="divide-y divide-gray-100">
                    @foreach ($permissionGroups as $module => $permissions)
                        <li x-data class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-start">
                            <div class="flex items-center justify-between gap-3 sm:w-48 sm:shrink-0 sm:flex-col sm:items-start sm:justify-start">
                                <p class="font-medium text-gray-900">{{ __("permissions.modules.{$module}") }}</p>
                                @if ($canUpdate)
                                    <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800"
                                            @click="const boxes = [...$el.closest('li').querySelectorAll('input[type=checkbox]')];
                                                    const check = boxes.some(box => ! box.checked);
                                                    boxes.forEach(box => box.checked = check);">
                                        Pilih / hapus semua
                                    </button>
                                @endif
                            </div>

                            <div class="grid flex-1 grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($permissions as $permission)
                                    @php $action = str($permission->name)->after('.')->toString(); @endphp
                                    <label class="flex items-start gap-2 text-sm">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                                               class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-60"
                                               @checked(in_array($permission->name, old('permissions', $granted), true))
                                               @disabled(! $canUpdate)>
                                        <span>
                                            <span class="text-gray-900">{{ __("permissions.actions.{$action}") }}</span>
                                            <span class="block font-mono text-xs text-gray-400">{{ $permission->name }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('admin.roles.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Kembali</a>
                @if ($canUpdate)
                    <x-primary-button>Simpan Permission</x-primary-button>
                @endif
            </div>
        </form>
    </div>
</x-app-layout>
