<x-app-layout>
    <x-slot name="title">Risiko Baru · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.risks.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Risiko {{ $project->name }}</a>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <form method="POST" action="{{ route('projects.risks.store', $project) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
            @csrf
            <h2 class="mb-5 text-lg font-semibold text-gray-900">Risiko Baru</h2>

            @include('risks.partials.form')

            <p class="mt-5 text-sm text-gray-500">Risiko baru berstatus <strong>Open</strong>. Skor = probabilitas × dampak (Low=1, Medium=2, High=3).</p>

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('projects.risks.index', $project) }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                <x-primary-button>Simpan</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
