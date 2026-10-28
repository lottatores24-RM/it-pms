{{--
    Shared create/edit fields. Expects $owners, $categories, $ratings,
    $responses and optional $risk (edit mode). Shows a live score preview.
--}}
@php
$risk ??= null;
$weights = collect($ratings)->mapWithKeys(fn ($rating) => [$rating->value => $rating->weight()]);
@endphp

<div class="space-y-5"
     x-data="{
        weights: @js($weights),
        probability: @js(old('probability', $risk?->probability->value ?? 'Medium')),
        impact: @js(old('impact', $risk?->impact->value ?? 'Medium')),
        get score() { return this.weights[this.probability] * this.weights[this.impact]; },
        get level() { return this.score >= 9 ? 'Critical' : (this.score >= 4 ? 'High' : (this.score === 3 ? 'Medium' : 'Low')); },
     }">
    <div>
        <x-input-label for="title">Judul risiko <span class="text-red-600">*</span></x-input-label>
        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $risk?->title)" required minlength="3" maxlength="200" autofocus />
        <x-input-error :messages="$errors->get('title')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" value="Deskripsi" />
        <textarea id="description" name="description" rows="3" maxlength="5000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $risk?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="category" value="Kategori" />
            <x-text-input id="category" name="category" type="text" class="mt-1 block w-full" list="risk-categories" :value="old('category', $risk?->category)" maxlength="100" />
            <datalist id="risk-categories">
                @foreach ($categories as $category)
                    <option value="{{ $category }}"></option>
                @endforeach
            </datalist>
            <x-input-error :messages="$errors->get('category')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="owner_id" value="Pemilik risiko" />
            <x-select-input id="owner_id" name="owner_id" class="mt-1 block w-full">
                <option value="">— Belum ditentukan —</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected((string) old('owner_id', $risk?->owner_id) === (string) $owner->id)>{{ $owner->name }}</option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('owner_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="probability">Probabilitas <span class="text-red-600">*</span></x-input-label>
            <x-select-input id="probability" name="probability" class="mt-1 block w-full" x-model="probability" required>
                @foreach ($ratings as $rating)
                    <option value="{{ $rating->value }}">{{ $rating->value }} ({{ $rating->weight() }})</option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('probability')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="impact">Dampak <span class="text-red-600">*</span></x-input-label>
            <x-select-input id="impact" name="impact" class="mt-1 block w-full" x-model="impact" required>
                @foreach ($ratings as $rating)
                    <option value="{{ $rating->value }}">{{ $rating->value }} ({{ $rating->weight() }})</option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('impact')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="response_plan">Rencana respons <span class="text-red-600">*</span></x-input-label>
            <x-select-input id="response_plan" name="response_plan" class="mt-1 block w-full" required>
                @foreach ($responses as $response)
                    <option value="{{ $response->value }}" @selected(old('response_plan', $risk?->response_plan->value ?? 'Mitigate') === $response->value)>{{ $response->value }}</option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('response_plan')" class="mt-2" />
        </div>

        <div class="flex items-end">
            <p class="w-full rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700 ring-1 ring-inset ring-gray-200">
                Skor otomatis: <strong x-text="score"></strong> → level <strong x-text="level"></strong>
            </p>
        </div>
    </div>
</div>
