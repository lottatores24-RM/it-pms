{{--
    Shared create/edit fields. Expects $priorities and optional $changeRequest (edit mode).
--}}
@php $changeRequest ??= null; @endphp

<div class="space-y-5">
    <div>
        <x-input-label for="title">Judul <span class="text-red-600">*</span></x-input-label>
        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $changeRequest?->title)" required minlength="3" maxlength="200" autofocus />
        <x-input-error :messages="$errors->get('title')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description">Deskripsi perubahan <span class="text-red-600">*</span></x-input-label>
        <textarea id="description" name="description" rows="4" maxlength="5000" required placeholder="Apa yang ingin diubah dari rencana proyek saat ini…" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $changeRequest?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="reason">Alasan perubahan <span class="text-red-600">*</span></x-input-label>
        <textarea id="reason" name="reason" rows="3" maxlength="5000" required placeholder="Mengapa perubahan ini diperlukan…" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('reason', $changeRequest?->reason) }}</textarea>
        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
    </div>

    <div class="max-w-xs">
        <x-input-label for="priority">Prioritas <span class="text-red-600">*</span></x-input-label>
        <x-select-input id="priority" name="priority" class="mt-1 block w-full" required>
            @foreach ($priorities as $priority)
                <option value="{{ $priority->value }}" @selected(old('priority', $changeRequest?->priority->value ?? 'Medium') === $priority->value)>{{ $priority->value }}</option>
            @endforeach
        </x-select-input>
        <x-input-error :messages="$errors->get('priority')" class="mt-2" />
    </div>

    <fieldset class="space-y-4 border-t border-gray-100 pt-5">
        <legend class="text-sm font-semibold text-gray-900">Analisis dampak</legend>
        @foreach (['impact_scope' => ['Ruang lingkup', 'Fitur/deliverable yang bertambah, berkurang, atau berubah…'], 'impact_schedule' => ['Jadwal', 'Perkiraan tambahan waktu atau milestone yang bergeser…'], 'impact_resource' => ['Sumber daya', 'Tambahan orang, biaya, atau effort…']] as $field => [$label, $placeholder])
            <div>
                <x-input-label :for="$field" :value="'Dampak terhadap '.Str::lower($label)" />
                <textarea id="{{ $field }}" name="{{ $field }}" rows="2" maxlength="5000" placeholder="{{ $placeholder }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old($field, $changeRequest?->{$field}) }}</textarea>
                <x-input-error :messages="$errors->get($field)" class="mt-2" />
            </div>
        @endforeach
    </fieldset>
</div>
