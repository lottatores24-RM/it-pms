{{--
    Shared create/edit fields. Expects $assignees, $tasks, $risks, $severities,
    $canAssign, $selectedTask and optional $issue (edit mode).
--}}
@php $issue ??= null; @endphp

<div class="space-y-5">
    <div>
        <x-input-label for="title">Judul issue <span class="text-red-600">*</span></x-input-label>
        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $issue?->title)" required minlength="3" maxlength="200" autofocus />
        <x-input-error :messages="$errors->get('title')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" value="Deskripsi" />
        <textarea id="description" name="description" rows="4" maxlength="5000" placeholder="Langkah reproduksi, perilaku yang diharapkan, dan yang terjadi…" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $issue?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="severity">Severity <span class="text-red-600">*</span></x-input-label>
            <x-select-input id="severity" name="severity" class="mt-1 block w-full" required>
                @foreach ($severities as $severity)
                    <option value="{{ $severity->value }}" @selected(old('severity', $issue?->severity->value ?? 'Medium') === $severity->value)>{{ $severity->value }}</option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('severity')" class="mt-2" />
        </div>

        @if ($canAssign)
            <div>
                <x-input-label for="assigned_to" value="Penanggung jawab" />
                <x-select-input id="assigned_to" name="assigned_to" class="mt-1 block w-full">
                    <option value="">— Belum di-assign —</option>
                    @foreach ($assignees as $assignee)
                        <option value="{{ $assignee->id }}" @selected((string) old('assigned_to', $issue?->assigned_to) === (string) $assignee->id)>{{ $assignee->name }}</option>
                    @endforeach
                </x-select-input>
                <x-input-error :messages="$errors->get('assigned_to')" class="mt-2" />
            </div>
        @else
            <div class="flex items-end">
                <p class="text-sm text-gray-500">Penanggung jawab akan ditentukan oleh Project Manager.</p>
            </div>
        @endif

        <div>
            <x-input-label for="related_task_id" value="Task terkait" />
            <x-select-input id="related_task_id" name="related_task_id" class="mt-1 block w-full">
                <option value="">— Tidak ada —</option>
                @foreach ($tasks as $task)
                    <option value="{{ $task->id }}" @selected((string) old('related_task_id', $issue?->related_task_id ?? $selectedTask) === (string) $task->id)>{{ $task->title }}</option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('related_task_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="related_risk_id" value="Risiko terkait" />
            <x-select-input id="related_risk_id" name="related_risk_id" class="mt-1 block w-full">
                <option value="">— Tidak ada —</option>
                @foreach ($risks as $risk)
                    <option value="{{ $risk->id }}" @selected((string) old('related_risk_id', $issue?->related_risk_id) === (string) $risk->id)>{{ $risk->title }}</option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('related_risk_id')" class="mt-2" />
        </div>
    </div>
</div>
