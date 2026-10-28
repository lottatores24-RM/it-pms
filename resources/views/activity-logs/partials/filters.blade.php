{{--
    Filter bar for the activity log. Expects: $action (form URL), $exportUrl,
    $filters, $users, $entityTypes and optional $projects (global log only).
--}}
<form method="GET" action="{{ $action }}" class="bg-white p-4 shadow-sm sm:rounded-lg">
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
        <div>
            <x-input-label for="action" value="Aksi" />
            <x-select-input id="action" name="action" class="mt-1 w-full text-sm">
                <option value="">Semua</option>
                @foreach (App\Enums\ActivityAction::cases() as $case)
                    <option value="{{ $case->value }}" @selected(($filters['action'] ?? '') === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </x-select-input>
        </div>
        <div>
            <x-input-label for="user_id" value="User" />
            <x-select-input id="user_id" name="user_id" class="mt-1 w-full text-sm">
                <option value="">Semua</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(($filters['user_id'] ?? null) === $user->id)>{{ $user->name }}</option>
                @endforeach
            </x-select-input>
        </div>
        <div>
            <x-input-label for="entity_type" value="Entitas" />
            <x-select-input id="entity_type" name="entity_type" class="mt-1 w-full text-sm">
                <option value="">Semua</option>
                @foreach ($entityTypes as $type)
                    <option value="{{ $type }}" @selected(($filters['entity_type'] ?? '') === $type)>{{ __("activity.entities.{$type}") }}</option>
                @endforeach
            </x-select-input>
        </div>
        @isset($projects)
            <div>
                <x-input-label for="project_id" value="Proyek" />
                <x-select-input id="project_id" name="project_id" class="mt-1 w-full text-sm">
                    <option value="">Semua</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected(($filters['project_id'] ?? null) === $project->id)>{{ $project->code }}</option>
                    @endforeach
                </x-select-input>
            </div>
        @endisset
        <div>
            <x-input-label for="from" value="Dari tanggal" />
            <x-text-input id="from" name="from" type="date" class="mt-1 w-full text-sm" :value="$filters['from'] ?? ''" />
        </div>
        <div>
            <x-input-label for="to" value="Sampai tanggal" />
            <x-text-input id="to" name="to" type="date" class="mt-1 w-full text-sm" :value="$filters['to'] ?? ''" />
        </div>
    </div>

    @if ($errors->any())
        <x-input-error :messages="$errors->all()" class="mt-3" />
    @endif

    <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
        <a href="{{ $action }}" class="text-sm text-gray-600 hover:text-gray-900">Reset</a>
        <a href="{{ $exportUrl.'?'.http_build_query(request()->query()) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Ekspor CSV</a>
        <x-primary-button>Terapkan</x-primary-button>
    </div>
</form>
