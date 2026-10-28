<x-app-layout>
    <x-slot name="title">Aktivitas · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Semua proyek</a>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        @include('activity-logs.partials.filters', [
            'action' => route('projects.activity', $project),
            'exportUrl' => route('projects.activity.export', $project),
        ])

        @include('activity-logs.partials.list')
    </div>
</x-app-layout>
