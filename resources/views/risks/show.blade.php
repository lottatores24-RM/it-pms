<x-app-layout>
    <x-slot name="title">{{ $risk->title }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.risks.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Risiko {{ $project->name }}</a>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :color="$risk->status->color()">{{ $risk->status->value }}</x-badge>
                                <x-badge :color="$risk->risk_level->color()">Level {{ $risk->risk_level->value }}</x-badge>
                            </div>
                            <h2 class="mt-2 text-xl font-semibold text-gray-900">{{ $risk->title }}</h2>
                        </div>

                        <div class="flex shrink-0 items-center gap-3 text-sm">
                            @can('update', $risk)
                                <a href="{{ route('projects.risks.edit', [$project, $risk]) }}" class="rounded-md border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                            @endcan
                            @can('delete', $risk)
                                <form method="POST" action="{{ route('projects.risks.destroy', [$project, $risk]) }}"
                                      data-confirm="Risiko &quot;{{ $risk->title }}&quot; akan dihapus."
                                      data-confirm-title="Hapus risiko?"
                                      data-confirm-button="Hapus">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">Hapus</button>
                                </form>
                            @endcan
                        </div>
                    </div>

                    <p class="mt-4 whitespace-pre-line text-sm text-gray-700">{{ $risk->description ?: 'Tidak ada deskripsi.' }}</p>

                    @if ($risk->status === App\Enums\RiskStatus::Closed)
                        <p class="mt-4 text-sm text-gray-500">Risiko sudah ditutup dan bersifat read-only.</p>
                    @endif

                    @if (count($transitions) > 0)
                        <div class="mt-6 flex flex-wrap gap-2 border-t border-gray-100 pt-5">
                            @foreach ($transitions as $target)
                                <form method="POST" action="{{ route('projects.risks.updateStatus', [$project, $risk]) }}"
                                      @if ($target === App\Enums\RiskStatus::Closed)
                                          data-confirm="Risiko yang ditutup menjadi read-only sampai dibuka kembali."
                                          data-confirm-title="Tutup risiko?"
                                          data-confirm-button="Tutup risiko"
                                          data-confirm-variant="primary"
                                      @endif>
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $target->value }}">
                                    <button type="submit" @class([
                                        'rounded-md px-3 py-1.5 text-sm font-medium shadow-sm',
                                        'bg-indigo-600 text-white hover:bg-indigo-500' => $target !== App\Enums\RiskStatus::Open,
                                        'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => $target === App\Enums\RiskStatus::Open,
                                    ])>{{ $risk->status->transitionLabel($target) }}</button>
                                </form>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    @endif
                </div>

                @if ($risk->issues->isNotEmpty())
                    <div class="bg-white shadow-sm sm:rounded-lg">
                        <h3 class="border-b border-gray-100 px-6 py-4 font-semibold text-gray-900">Issue Terkait</h3>
                        <ul class="divide-y divide-gray-100">
                            @foreach ($risk->issues as $issue)
                                <li class="flex items-center justify-between gap-3 px-6 py-3 text-sm">
                                    <a href="{{ route('projects.issues.show', [$project, $issue]) }}" class="text-gray-900 hover:text-indigo-600">{{ $issue->title }}</a>
                                    <x-badge :color="$issue->status->color()" class="shrink-0">{{ $issue->status->value }}</x-badge>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @include('activity-logs.partials.history')
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg lg:self-start">
                <div class="text-center">
                    <p class="text-sm text-gray-500">Skor risiko</p>
                    <p class="text-4xl font-bold text-gray-900">{{ $risk->risk_score }}</p>
                    <p class="text-xs text-gray-500">{{ $risk->probability->value }} ({{ $risk->probability->weight() }}) × {{ $risk->impact->value }} ({{ $risk->impact->weight() }})</p>
                </div>
                <dl class="mt-6 space-y-4 border-t border-gray-100 pt-6 text-sm">
                    <div>
                        <dt class="text-gray-500">Kategori</dt>
                        <dd class="font-medium text-gray-900">{{ $risk->category ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Rencana respons</dt>
                        <dd class="font-medium text-gray-900">{{ $risk->response_plan->value }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Pemilik risiko</dt>
                        <dd class="font-medium text-gray-900">{{ $risk->owner?->name ?? 'Belum ditentukan' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Dicatat</dt>
                        <dd class="font-medium text-gray-900">{{ $risk->created_at->translatedFormat('d F Y') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
