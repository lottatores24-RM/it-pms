<x-app-layout>
    <x-slot name="title">Issue · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Semua proyek</a>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <form method="GET" action="{{ route('projects.issues.index', $project) }}" class="grid flex-1 grid-cols-2 gap-3 md:grid-cols-4">
                <div class="col-span-2 md:col-span-1">
                    <x-input-label for="search" value="Cari" class="sr-only" />
                    <x-text-input id="search" name="search" type="search" class="w-full" placeholder="Cari judul…" :value="$filters['search']" />
                </div>
                <div>
                    <x-input-label for="status" value="Status" class="sr-only" />
                    <x-select-input id="status" name="status" class="w-full" onchange="this.form.submit()">
                        <option value="">Semua status</option>
                        @foreach (App\Enums\IssueStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->value }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label for="severity" value="Severity" class="sr-only" />
                    <x-select-input id="severity" name="severity" class="w-full" onchange="this.form.submit()">
                        <option value="">Semua severity</option>
                        @foreach (App\Enums\IssueSeverity::cases() as $severity)
                            <option value="{{ $severity->value }}" @selected($filters['severity'] === $severity->value)>{{ $severity->value }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label for="assignee" value="Penanggung jawab" class="sr-only" />
                    <x-select-input id="assignee" name="assignee" class="w-full" onchange="this.form.submit()">
                        <option value="">Semua issue</option>
                        <option value="me" @selected($filters['assignee'] === 'me')>Ditugaskan ke saya</option>
                        <option value="reported" @selected($filters['assignee'] === 'reported')>Dilaporkan saya</option>
                        <option value="none" @selected($filters['assignee'] === 'none')>Belum di-assign</option>
                    </x-select-input>
                </div>
            </form>

            @can('create', [App\Models\Issue::class, $project])
                <a href="{{ route('projects.issues.create', $project) }}" class="inline-flex shrink-0 items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    + Laporkan Issue
                </a>
            @endcan
        </div>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">Issue</th>
                            <th scope="col" class="px-4 py-3">Severity</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="hidden px-4 py-3 md:table-cell">Penanggung jawab</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($issues as $issue)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('projects.issues.show', [$project, $issue]) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $issue->title }}</a>
                                    <p class="text-xs text-gray-500">Dilaporkan {{ $issue->reporter->name }} · {{ $issue->created_at->translatedFormat('d M Y') }}</p>
                                </td>
                                <td class="px-4 py-3"><x-badge :color="$issue->severity->color()">{{ $issue->severity->value }}</x-badge></td>
                                <td class="px-4 py-3"><x-badge :color="$issue->status->color()">{{ $issue->status->value }}</x-badge></td>
                                <td class="hidden px-4 py-3 text-gray-700 md:table-cell">{{ $issue->assignee?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-gray-500">Belum ada issue yang cocok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $issues->links() }}
    </div>
</x-app-layout>
