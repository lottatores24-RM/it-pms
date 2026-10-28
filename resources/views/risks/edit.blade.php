<x-app-layout>
    <x-slot name="title">Edit Risiko · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.risks.show', [$project, $risk]) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali ke risiko</a>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <form method="POST" action="{{ route('projects.risks.update', [$project, $risk]) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
            @csrf
            @method('PUT')
            <h2 class="mb-5 text-lg font-semibold text-gray-900">Edit Risiko</h2>

            @include('risks.partials.form')

            <p class="mt-5 text-sm text-gray-500">Skor dan level dihitung ulang otomatis. Status diubah melalui tombol di halaman risiko.</p>

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('projects.risks.show', [$project, $risk]) }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                <x-primary-button>Simpan Perubahan</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
