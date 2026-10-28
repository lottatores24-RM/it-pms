<x-app-layout>
    <x-slot name="title">Edit Milestone · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.milestones.show', [$project, $milestone]) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali ke milestone</a>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <form method="POST" action="{{ route('projects.milestones.update', [$project, $milestone]) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
            @csrf
            @method('PUT')
            <h2 class="mb-5 text-lg font-semibold text-gray-900">Edit Milestone</h2>

            @include('milestones.partials.form')

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('projects.milestones.show', [$project, $milestone]) }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                <x-primary-button>Simpan Perubahan</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
