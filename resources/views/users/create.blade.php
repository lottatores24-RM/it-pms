<x-app-layout>
    <x-slot name="title">Tambah User</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Tambah User</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <form method="POST" action="{{ route('admin.users.store') }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
            @csrf

            @include('users.partials.form', ['canAssignRole' => true])

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                <x-primary-button>Simpan</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
