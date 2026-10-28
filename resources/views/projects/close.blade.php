<x-app-layout>
    <x-slot name="title">Tutup {{ $project->name }}</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Tutup Proyek</h2>
    </x-slot>

    @php $openTotal = collect($summary)->sum('open'); @endphp

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="bg-white p-6 shadow-sm sm:rounded-lg">
            <p class="font-mono text-xs text-gray-500">{{ $project->code }}</p>
            <h3 class="text-lg font-semibold text-gray-900">{{ $project->name }}</h3>
            <p class="mt-2 text-sm text-gray-600">
                Setelah ditutup, proyek menjadi read-only. Hanya Administrator yang dapat membukanya kembali.
            </p>
            <p class="mt-2 text-sm text-gray-600">
                Saat proyek ditutup, sistem otomatis menyimpan <strong>laporan penutupan</strong> berisi ringkasan jadwal, task, milestone, risiko, issue, dan change request pada saat itu. Laporan dapat dicetak atau disimpan sebagai PDF.
            </p>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg">
            <h3 class="border-b border-gray-100 px-6 py-4 font-semibold text-gray-900">Checklist Penutupan</h3>
            @include('projects.partials.open-items')

            @if ($openTotal > 0)
                <div class="border-t border-orange-100 bg-orange-50 px-6 py-3 text-sm text-orange-800">
                    Masih ada {{ $openTotal }} item yang belum selesai. Anda tetap dapat menutup proyek, tetapi pastikan hal ini dicatat di catatan penutupan.
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('projects.close', $project) }}" class="bg-white p-6 shadow-sm sm:rounded-lg"
              data-confirm="Proyek {{ $project->name }} akan ditutup dan menjadi read-only."
              data-confirm-title="Tutup proyek?"
              data-confirm-button="Tutup Proyek">
            @csrf

            <x-input-label for="closing_notes">Catatan penutupan <span class="text-red-600">*</span></x-input-label>
            <textarea id="closing_notes" name="closing_notes" rows="5" required minlength="10" maxlength="5000"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      placeholder="Ringkasan hasil proyek, pelajaran yang didapat, dan item yang belum selesai…">{{ old('closing_notes') }}</textarea>
            <p class="mt-1 text-xs text-gray-500">Minimal 10 karakter.</p>
            <x-input-error :messages="$errors->get('closing_notes')" class="mt-2" />

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                <x-danger-button>Tutup Proyek</x-danger-button>
            </div>
        </form>
    </div>
</x-app-layout>
