<x-app-layout>
    <x-slot name="title">Activity Log</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Activity Log</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-4">
        <p class="text-sm text-gray-600">Jejak audit seluruh sistem. Catatan bersifat append-only dan tidak dapat diubah atau dihapus.</p>

        @include('activity-logs.partials.filters', [
            'action' => route('admin.activity-logs.index'),
            'exportUrl' => route('admin.activity-logs.export'),
        ])

        @include('activity-logs.partials.list', ['showProject' => true])
    </div>
</x-app-layout>
