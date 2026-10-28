<x-app-layout>
    <x-slot name="title">{{ $changeRequest->title }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.change-requests.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Change Request {{ $project->name }}</a>
    </x-slot>

    @php
    $impacts = [
        'Ruang lingkup' => $changeRequest->impact_scope,
        'Jadwal' => $changeRequest->impact_schedule,
        'Sumber daya' => $changeRequest->impact_resource,
    ];
    $decides = collect($transitions)->contains(fn (App\Enums\ChangeRequestStatus $to) => $to->isFinal());
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :color="$changeRequest->status->color()">{{ $changeRequest->status->value }}</x-badge>
                                <x-badge :color="$changeRequest->priority->color()">Prioritas {{ $changeRequest->priority->value }}</x-badge>
                            </div>
                            <h2 class="mt-2 text-xl font-semibold text-gray-900">{{ $changeRequest->title }}</h2>
                        </div>

                        <div class="flex shrink-0 items-center gap-3 text-sm">
                            @can('update', $changeRequest)
                                <a href="{{ route('projects.change-requests.edit', [$project, $changeRequest]) }}" class="rounded-md border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                            @endcan
                            @can('delete', $changeRequest)
                                <form method="POST" action="{{ route('projects.change-requests.destroy', [$project, $changeRequest]) }}"
                                      data-confirm="Change request &quot;{{ $changeRequest->title }}&quot; akan dihapus permanen."
                                      data-confirm-title="Hapus change request?"
                                      data-confirm-button="Hapus">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">Hapus</button>
                                </form>
                            @endcan
                        </div>
                    </div>

                    <dl class="mt-5 space-y-4 text-sm">
                        <div>
                            <dt class="font-medium text-gray-900">Deskripsi perubahan</dt>
                            <dd class="mt-1 whitespace-pre-line text-gray-700">{{ $changeRequest->description }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-900">Alasan</dt>
                            <dd class="mt-1 whitespace-pre-line text-gray-700">{{ $changeRequest->reason }}</dd>
                        </div>
                    </dl>

                    <h3 class="mt-6 border-t border-gray-100 pt-5 text-sm font-semibold text-gray-900">Analisis dampak</h3>
                    <dl class="mt-3 grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
                        @foreach ($impacts as $label => $impact)
                            <div class="rounded-md bg-gray-50 p-3">
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</dt>
                                <dd class="mt-1 whitespace-pre-line text-gray-700">{{ $impact ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    @if ($changeRequest->justification)
                        <div @class([
                            'mt-6 rounded-md p-4 text-sm ring-1 ring-inset',
                            'bg-green-50 text-green-800 ring-green-600/20' => $changeRequest->status === App\Enums\ChangeRequestStatus::Approved,
                            'bg-red-50 text-red-800 ring-red-600/20' => $changeRequest->status === App\Enums\ChangeRequestStatus::Rejected,
                            'bg-blue-50 text-blue-800 ring-blue-600/20' => ! $changeRequest->status->isFinal(),
                        ])>
                            <p class="font-semibold">Justifikasi {{ $changeRequest->reviewer?->name ?? 'Project Manager' }} ({{ $changeRequest->status->value }})</p>
                            <p class="mt-1 whitespace-pre-line">{{ $changeRequest->justification }}</p>
                        </div>
                    @endif

                    @if ($changeRequest->status->isFinal())
                        <p class="mt-4 text-sm text-gray-500">Change request sudah diputuskan dan bersifat read-only.</p>
                    @elseif (count($transitions) === 0)
                        <p class="mt-4 text-sm text-gray-500">Menunggu review dari Project Manager.</p>
                    @endif

                    @if (count($transitions) > 0)
                        <form method="POST" action="{{ route('projects.change-requests.updateStatus', [$project, $changeRequest]) }}"
                              x-data class="mt-6 space-y-3 border-t border-gray-100 pt-5"
                              @if ($decides)
                                  data-confirm="Keputusan ini final; change request tidak dapat diubah lagi."
                                  data-confirm-title="Simpan keputusan?"
                                  data-confirm-button="Ya, simpan"
                                  data-confirm-variant="primary"
                              @endif>
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" x-ref="status" value="{{ old('status') }}">

                            <div>
                                <x-input-label for="justification">Justifikasi review <span class="text-red-600">*</span></x-input-label>
                                <textarea id="justification" name="justification" rows="3" minlength="10" maxlength="2000" required
                                          placeholder="{{ $decides ? 'Dasar keputusan menyetujui atau menolak perubahan ini…' : 'Catatan awal sebelum perubahan dianalisis…' }}"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('justification') }}</textarea>
                                <x-input-error :messages="$errors->get('justification')" class="mt-2" />
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>

                            <div class="flex flex-wrap gap-2">
                                @foreach ($transitions as $target)
                                    <button type="submit" x-on:click="$refs.status.value = '{{ $target->value }}'"
                                            @class([
                                                'rounded-md px-3 py-1.5 text-sm font-medium text-white shadow-sm',
                                                'bg-green-600 hover:bg-green-500' => $target === App\Enums\ChangeRequestStatus::Approved,
                                                'bg-red-600 hover:bg-red-500' => $target === App\Enums\ChangeRequestStatus::Rejected,
                                                'bg-indigo-600 hover:bg-indigo-500' => ! $target->isFinal(),
                                            ])>
                                        {{ $changeRequest->status->transitionLabel($target) }}
                                    </button>
                                @endforeach
                            </div>
                        </form>
                    @endif
                </div>

                @include('activity-logs.partials.history')
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg lg:self-start">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Pengaju</dt>
                        <dd class="font-medium text-gray-900">{{ $changeRequest->requester->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Diajukan</dt>
                        <dd class="font-medium text-gray-900">{{ $changeRequest->created_at->translatedFormat('d F Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Reviewer</dt>
                        <dd class="font-medium text-gray-900">{{ $changeRequest->reviewer?->name ?? 'Belum direview' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Terakhir diperbarui</dt>
                        <dd class="font-medium text-gray-900">{{ $changeRequest->updated_at->translatedFormat('d F Y H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
