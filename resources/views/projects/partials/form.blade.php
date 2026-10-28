{{--
    Shared create/edit fields. Expects optional $project (edit mode),
    $suggestedCode (create) and $statusOptions (edit).
--}}
@php $editing = isset($project); @endphp

<div class="space-y-5">
    <div>
        <x-input-label for="name">Nama proyek <span class="text-red-600">*</span></x-input-label>
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $project->name ?? '')" required minlength="3" maxlength="150" autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="code">Kode proyek @if ($editing)<span class="text-red-600">*</span>@endif</x-input-label>
            <x-text-input id="code" name="code" type="text" class="mt-1 block w-full font-mono uppercase" :value="old('code', $project->code ?? '')" :placeholder="$suggestedCode ?? ''" :required="$editing" maxlength="50" />
            @unless ($editing)
                <p class="mt-1 text-xs text-gray-500">Kosongkan untuk dibuat otomatis ({{ $suggestedCode }}).</p>
            @endunless
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="sponsor" value="Sponsor" />
            <x-text-input id="sponsor" name="sponsor" type="text" class="mt-1 block w-full" :value="old('sponsor', $project->sponsor ?? '')" maxlength="150" />
            <x-input-error :messages="$errors->get('sponsor')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="description" value="Deskripsi" />
        <textarea id="description" name="description" rows="4" maxlength="5000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $project->description ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="start_date">Tanggal mulai <span class="text-red-600">*</span></x-input-label>
            <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date', $project?->start_date?->format('Y-m-d') ?? '')" required />
            <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="end_date">Tanggal selesai <span class="text-red-600">*</span></x-input-label>
            <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="old('end_date', $project?->end_date?->format('Y-m-d') ?? '')" required />
            <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
        </div>
    </div>

    @if ($editing)
        <div>
            <x-input-label for="status">Status <span class="text-red-600">*</span></x-input-label>
            <x-select-input id="status" name="status" class="mt-1 block w-full sm:w-1/2" required>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $project->status->value) === $status->value)>{{ $status->value }}</option>
                @endforeach
            </x-select-input>
            <p class="mt-1 text-xs text-gray-500">Alur: Planning → In Progress ⇄ On Hold. Penutupan proyek melalui tombol "Tutup Proyek".</p>
            <x-input-error :messages="$errors->get('status')" class="mt-2" />
        </div>
    @endif
</div>
