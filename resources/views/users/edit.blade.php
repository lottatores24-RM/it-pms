<x-app-layout>
    <x-slot name="title">Edit User</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Edit User: {{ $user->name }}</h2>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
            @csrf
            @method('PUT')

            @include('users.partials.form', ['canAssignRole' => Auth::user()->can('assignRole', $user)])

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('admin.users.show', $user) }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                <x-primary-button>Simpan Perubahan</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
