<x-app-layout>
    <x-slot name="title">Laporkan Issue · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.issues.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Issue {{ $project->name }}</a>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <form method="POST" action="{{ route('projects.issues.store', $project) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
            @csrf
            <h2 class="mb-5 text-lg font-semibold text-gray-900">Laporkan Issue</h2>

            @include('issues.partials.form')

            <p class="mt-5 text-sm text-gray-500">Issue baru berstatus <strong>Open</strong>, dengan Anda sebagai pelapor.</p>

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('projects.issues.index', $project) }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                <x-primary-button>Laporkan</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
