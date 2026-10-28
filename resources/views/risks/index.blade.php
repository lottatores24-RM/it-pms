<x-app-layout>
    <x-slot name="title">Risiko · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Semua proyek</a>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:order-2">
                @include('risks.partials.matrix')
            </div>

            <div class="space-y-4 lg:order-1 lg:col-span-2">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <form method="GET" action="{{ route('projects.risks.index', $project) }}" class="grid flex-1 grid-cols-2 gap-3 md:grid-cols-4">
                        <input type="hidden" name="probability" value="{{ $filters['probability'] }}">
                        <input type="hidden" name="impact" value="{{ $filters['impact'] }}">
                        <div class="col-span-2 md:col-span-1">
                            <x-input-label for="search" value="Cari" class="sr-only" />
                            <x-text-input id="search" name="search" type="search" class="w-full" placeholder="Cari judul…" :value="$filters['search']" />
                        </div>
                        <div>
                            <x-input-label for="status" value="Status" class="sr-only" />
                            <x-select-input id="status" name="status" class="w-full" onchange="this.form.submit()">
                                <option value="">Semua status</option>
                                @foreach (App\Enums\RiskStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->value }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label for="level" value="Level" class="sr-only" />
                            <x-select-input id="level" name="level" class="w-full" onchange="this.form.submit()">
                                <option value="">Semua level</option>
                                @foreach (App\Enums\RiskLevel::cases() as $level)
                                    <option value="{{ $level->value }}" @selected($filters['level'] === $level->value)>{{ $level->value }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label for="category" value="Kategori" class="sr-only" />
                            <x-select-input id="category" name="category" class="w-full" onchange="this.form.submit()">
                                <option value="">Semua kategori</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                    </form>

                    @can('create', [App\Models\Risk::class, $project])
                        <a href="{{ route('projects.risks.create', $project) }}" class="inline-flex shrink-0 items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                            + Risiko Baru
                        </a>
                    @endcan
                </div>

                @if ($filters['probability'] || $filters['impact'])
                    <p class="text-sm text-gray-600">
                        Difilter: probabilitas {{ $filters['probability'] ?: 'semua' }} × dampak {{ $filters['impact'] ?: 'semua' }}.
                        <a href="{{ route('projects.risks.index', $project) }}" class="text-indigo-600 hover:text-indigo-800">Hapus filter</a>
                    </p>
                @endif

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <tr>
                                    <th scope="col" class="px-4 py-3">Risiko</th>
                                    <th scope="col" class="px-4 py-3 text-center">Skor</th>
                                    <th scope="col" class="px-4 py-3">Status</th>
                                    <th scope="col" class="hidden px-4 py-3 md:table-cell">Pemilik</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($risks as $risk)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <a href="{{ route('projects.risks.show', [$project, $risk]) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $risk->title }}</a>
                                            <p class="text-xs text-gray-500">
                                                {{ $risk->category ?? 'Tanpa kategori' }} · {{ $risk->response_plan->value }}
                                            </p>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="block text-base font-semibold text-gray-900">{{ $risk->risk_score }}</span>
                                            <x-badge :color="$risk->risk_level->color()">{{ $risk->risk_level->value }}</x-badge>
                                        </td>
                                        <td class="px-4 py-3"><x-badge :color="$risk->status->color()">{{ $risk->status->value }}</x-badge></td>
                                        <td class="hidden px-4 py-3 text-gray-700 md:table-cell">{{ $risk->owner?->name ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-10 text-center text-gray-500">Belum ada risiko yang cocok.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{ $risks->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
