<x-app-layout>
    <x-slot name="title">Edit Task · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali ke task</a>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <form method="POST" action="{{ route('projects.tasks.update', [$project, $task]) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
            @csrf
            @method('PUT')
            <h2 class="mb-5 text-lg font-semibold text-gray-900">Edit Task</h2>

            @include('tasks.partials.form')

            <p class="mt-5 text-sm text-gray-500">Status diubah melalui tombol alur status di halaman task.</p>

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('projects.tasks.show', [$project, $task]) }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                <x-primary-button>Simpan Perubahan</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
