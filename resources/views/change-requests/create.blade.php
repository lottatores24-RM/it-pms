<x-app-layout>
    <x-slot name="title">Ajukan Change Request · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.change-requests.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Change Request {{ $project->name }}</a>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <form method="POST" action="{{ route('projects.change-requests.store', $project) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
            @csrf
            <h2 class="mb-5 text-lg font-semibold text-gray-900">Ajukan Change Request</h2>

            @include('change-requests.partials.form')

            <p class="mt-5 text-sm text-gray-500">Change request baru berstatus <strong>Submitted</strong> dan akan direview oleh Project Manager.</p>

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('projects.change-requests.index', $project) }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                <x-primary-button>Ajukan</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
