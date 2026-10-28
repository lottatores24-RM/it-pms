<x-app-layout>
    <x-slot name="title">Edit Change Request · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.change-requests.show', [$project, $changeRequest]) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali ke change request</a>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <form method="POST" action="{{ route('projects.change-requests.update', [$project, $changeRequest]) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
            @csrf
            @method('PUT')
            <h2 class="mb-5 text-lg font-semibold text-gray-900">Edit Change Request</h2>

            @include('change-requests.partials.form')

            <p class="mt-5 text-sm text-gray-500">Status diubah melalui form review di halaman change request.</p>

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('projects.change-requests.show', [$project, $changeRequest]) }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                <x-primary-button>Simpan Perubahan</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
