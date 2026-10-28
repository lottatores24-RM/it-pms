{{--
    Shared create/edit fields. Expects $project, optional $milestone and
    $statusOptions (edit mode).
--}}
@php $milestone ??= null; @endphp

<div class="space-y-5">
    <div>
        <x-input-label for="name">Nama milestone <span class="text-red-600">*</span></x-input-label>
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $milestone?->name)" required minlength="3" maxlength="200" autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" value="Deskripsi" />
        <textarea id="description" name="description" rows="3" maxlength="5000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $milestone?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="target_date">Tanggal target <span class="text-red-600">*</span></x-input-label>
            <x-text-input id="target_date" name="target_date" type="date" class="mt-1 block w-full" required
                          :value="old('target_date', $milestone?->target_date?->format('Y-m-d'))"
                          :min="$project->start_date->format('Y-m-d')" :max="$project->end_date->format('Y-m-d')" />
            <p class="mt-1 text-xs text-gray-500">Periode proyek: {{ $project->start_date->translatedFormat('d M Y') }} – {{ $project->end_date->translatedFormat('d M Y') }}.</p>
            <x-input-error :messages="$errors->get('target_date')" class="mt-2" />
        </div>

        @if ($milestone)
            <div x-data="{ status: @js(old('status', $milestone->status->value)) }">
                <x-input-label for="status">Status <span class="text-red-600">*</span></x-input-label>
                <x-select-input id="status" name="status" class="mt-1 block w-full" x-model="status" required>
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status->value }}">{{ $status->value }}</option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />

                @if ($milestone->status === App\Enums\MilestoneStatus::Completed)
                    <div x-show="status !== 'Completed'" x-cloak class="mt-3 rounded-md bg-yellow-50 p-3 ring-1 ring-inset ring-yellow-600/20">
                        <label class="flex items-start gap-2 text-sm text-yellow-800">
                            <input type="checkbox" name="confirm_reopen" value="1" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('confirm_reopen'))>
                            <span>Saya yakin ingin mengubah milestone yang sudah <strong>Completed</strong>.</span>
                        </label>
                        <x-input-error :messages="$errors->get('confirm_reopen')" class="mt-2" />
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
