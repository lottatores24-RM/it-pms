<x-app-layout>
    <x-slot name="title">Change Request · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Semua proyek</a>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <form method="GET" action="{{ route('projects.change-requests.index', $project) }}" class="grid flex-1 grid-cols-2 gap-3 md:grid-cols-4">
                <div class="col-span-2 md:col-span-1">
                    <x-input-label for="search" value="Cari" class="sr-only" />
                    <x-text-input id="search" name="search" type="search" class="w-full" placeholder="Cari judul…" :value="$filters['search']" />
                </div>
                <div>
                    <x-input-label for="status" value="Status" class="sr-only" />
                    <x-select-input id="status" name="status" class="w-full" onchange="this.form.submit()">
                        <option value="">Semua status</option>
                        @foreach (App\Enums\ChangeRequestStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->value }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label for="priority" value="Prioritas" class="sr-only" />
                    <x-select-input id="priority" name="priority" class="w-full" onchange="this.form.submit()">
                        <option value="">Semua prioritas</option>
                        @foreach (App\Enums\ChangeRequestPriority::cases() as $priority)
                            <option value="{{ $priority->value }}" @selected($filters['priority'] === $priority->value)>{{ $priority->value }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                @if ($viewAll)
                    <div>
                        <x-input-label for="mine" value="Pengaju" class="sr-only" />
                        <x-select-input id="mine" name="mine" class="w-full" onchange="this.form.submit()">
                            <option value="">Semua pengaju</option>
                            <option value="1" @selected($filters['mine'])>Diajukan saya</option>
                        </x-select-input>
                    </div>
                @endif
            </form>

            @can('create', [App\Models\ChangeRequest::class, $project])
                <a href="{{ route('projects.change-requests.create', $project) }}" class="inline-flex shrink-0 items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    + Ajukan Change Request
                </a>
            @endcan
        </div>

        @unless ($viewAll)
            <p class="text-sm text-gray-500">Daftar ini hanya berisi change request yang Anda ajukan.</p>
        @endunless

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">Change Request</th>
                            <th scope="col" class="px-4 py-3">Prioritas</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="hidden px-4 py-3 md:table-cell">Reviewer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($changeRequests as $changeRequest)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('projects.change-requests.show', [$project, $changeRequest]) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $changeRequest->title }}</a>
                                    <p class="text-xs text-gray-500">Diajukan {{ $changeRequest->requester->name }} · {{ $changeRequest->created_at->translatedFormat('d M Y') }}</p>
                                </td>
                                <td class="px-4 py-3"><x-badge :color="$changeRequest->priority->color()">{{ $changeRequest->priority->value }}</x-badge></td>
                                <td class="px-4 py-3"><x-badge :color="$changeRequest->status->color()">{{ $changeRequest->status->value }}</x-badge></td>
                                <td class="hidden px-4 py-3 text-gray-700 md:table-cell">{{ $changeRequest->reviewer?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-gray-500">Belum ada change request yang cocok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $changeRequests->links() }}
    </div>
</x-app-layout>
