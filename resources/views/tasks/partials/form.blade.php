{{--
    Shared create/edit fields. Expects $project, $assignees, $milestones,
    $priorities and optional $task (edit mode).
--}}
@php $task ??= null; @endphp

<div class="space-y-5">
    <div>
        <x-input-label for="title">Judul <span class="text-red-600">*</span></x-input-label>
        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $task?->title)" required minlength="3" maxlength="200" autofocus />
        <x-input-error :messages="$errors->get('title')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" value="Deskripsi" />
        <textarea id="description" name="description" rows="4" maxlength="5000" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $task?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="assigned_to" value="Penanggung jawab" />
            <x-select-input id="assigned_to" name="assigned_to" class="mt-1 block w-full">
                <option value="">— Belum di-assign —</option>
                @foreach ($assignees as $assignee)
                    <option value="{{ $assignee->id }}" @selected((string) old('assigned_to', $task?->assigned_to) === (string) $assignee->id)>{{ $assignee->name }}</option>
                @endforeach
            </x-select-input>
            <p class="mt-1 text-xs text-gray-500">Hanya anggota dengan role Project Manager atau Team Member.</p>
            <x-input-error :messages="$errors->get('assigned_to')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="priority">Prioritas <span class="text-red-600">*</span></x-input-label>
            <x-select-input id="priority" name="priority" class="mt-1 block w-full" required>
                @foreach ($priorities as $priority)
                    <option value="{{ $priority->value }}" @selected(old('priority', $task?->priority->value ?? App\Enums\TaskPriority::Medium->value) === $priority->value)>{{ $priority->value }}</option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('priority')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="due_date" value="Tenggat" />
            <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full"
                          :value="old('due_date', $task?->due_date?->format('Y-m-d'))"
                          :min="$project->start_date->format('Y-m-d')" :max="$project->end_date->format('Y-m-d')" />
            <p class="mt-1 text-xs text-gray-500">Maksimal {{ $project->end_date->translatedFormat('d M Y') }} (akhir proyek).</p>
            <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="milestone_id" value="Milestone" />
            <x-select-input id="milestone_id" name="milestone_id" class="mt-1 block w-full">
                <option value="">— Tanpa milestone —</option>
                @foreach ($milestones as $milestone)
                    <option value="{{ $milestone->id }}" @selected((string) old('milestone_id', $task?->milestone_id) === (string) $milestone->id)>
                        {{ $milestone->name }} ({{ $milestone->target_date->translatedFormat('d M Y') }})
                    </option>
                @endforeach
            </x-select-input>
            <x-input-error :messages="$errors->get('milestone_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="effort_points" value="Effort points" />
            <x-text-input id="effort_points" name="effort_points" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('effort_points', $task?->effort_points)" />
            <p class="mt-1 text-xs text-gray-500">Estimasi bobot pekerjaan, dipakai untuk perhitungan EVM.</p>
            <x-input-error :messages="$errors->get('effort_points')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="actual_effort_points" value="Effort aktual" />
            <x-text-input id="actual_effort_points" name="actual_effort_points" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('actual_effort_points', $task?->actual_effort_points)" />
            <p class="mt-1 text-xs text-gray-500">Effort yang benar-benar terpakai (Actual Cost di EVM). Isi saat task selesai.</p>
            <x-input-error :messages="$errors->get('actual_effort_points')" class="mt-2" />
        </div>
    </div>
</div>
