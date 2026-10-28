<x-app-layout>
    <x-slot name="title">Proyek Baru</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Proyek Baru</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl">
        <form method="POST" action="{{ route('projects.store') }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
            @csrf

            @include('projects.partials.form', ['project' => null])

            @if ($managers !== null)
                <div class="mt-5 border-t border-gray-100 pt-5">
                    <x-input-label for="manager_id">Project Manager <span class="text-red-600">*</span></x-input-label>
                    <x-select-input id="manager_id" name="manager_id" class="mt-1 block w-full" required>
                        <option value="">— Pilih Project Manager —</option>
                        @foreach ($managers as $manager)
                            <option value="{{ $manager->id }}" @selected((string) old('manager_id') === (string) $manager->id)>{{ $manager->name }} ({{ $manager->email }})</option>
                        @endforeach
                    </x-select-input>
                    <p class="mt-1 text-xs text-gray-500">
                        User aktif dengan role Project Manager. Proyek langsung muncul di dashboard PM ini, lalu PM menambahkan Team Member lewat tab Anggota.
                    </p>
                    @if ($managers->isEmpty())
                        <p class="mt-1 text-sm text-orange-700">Belum ada user aktif dengan role Project Manager. Buat atau aktifkan user di menu User terlebih dahulu.</p>
                    @endif
                    <x-input-error :messages="$errors->get('manager_id')" class="mt-2" />
                </div>

                <p class="mt-5 text-sm text-gray-500">Status awal proyek adalah <strong>Planning</strong>.</p>
            @else
                <p class="mt-5 text-sm text-gray-500">Status awal proyek adalah <strong>Planning</strong> dan Anda otomatis menjadi Project Manager proyek ini.</p>
            @endif

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('projects.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                <x-primary-button>Simpan</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
