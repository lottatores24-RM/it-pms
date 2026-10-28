<x-app-layout>
    <x-slot name="title">Notifikasi</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Notifikasi</h2>
    </x-slot>

    @php
    $icons = [
        'task' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'status' => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99',
        'risk' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
        'issue' => 'M12 12.75c1.148 0 2.278.08 3.383.237 1.037.146 1.866.966 1.866 2.013 0 3.728-2.35 6.75-5.25 6.75S6.75 18.728 6.75 15c0-1.046.83-1.867 1.866-2.013A24.204 24.204 0 0 1 12 12.75Z',
        'comment' => 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z',
        'change_request' => 'M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5',
    ];
    @endphp

    <div class="mx-auto max-w-3xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <nav class="flex gap-1 rounded-md bg-gray-200 p-1 text-sm">
                <a href="{{ route('notifications.index') }}" @class(['rounded px-3 py-1', 'bg-white font-medium shadow-sm' => ! $onlyUnread, 'text-gray-600' => $onlyUnread])>Semua</a>
                <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" @class(['rounded px-3 py-1', 'bg-white font-medium shadow-sm' => $onlyUnread, 'text-gray-600' => ! $onlyUnread])>
                    Belum dibaca ({{ $unreadCount }})
                </a>
            </nav>

            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Tandai semua dibaca</button>
                </form>
            @endif
        </div>

        <ul class="divide-y divide-gray-100 overflow-hidden bg-white shadow-sm sm:rounded-lg">
            @forelse ($notifications as $notification)
                @php $data = $notification->data; @endphp
                <li @class(['bg-indigo-50/60' => $notification->unread()])>
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="flex w-full items-start gap-3 px-4 py-4 text-left hover:bg-gray-50 sm:px-6">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$data['icon'] ?? 'task'] ?? $icons['task'] }}" />
                                </svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-900">{{ $data['title'] ?? 'Notifikasi' }}</span>
                                    @if ($notification->unread())
                                        <span class="h-2 w-2 rounded-full bg-indigo-600" aria-label="Belum dibaca"></span>
                                    @endif
                                </span>
                                <span class="mt-0.5 block text-sm text-gray-700">{{ $data['message'] ?? '' }}</span>
                                <span class="mt-1 block text-xs text-gray-500">
                                    @if (! empty($data['project'])){{ $data['project'] }} · @endif{{ $notification->created_at->diffForHumans() }}
                                </span>
                            </span>
                        </button>
                    </form>
                </li>
            @empty
                <li class="px-6 py-12 text-center text-sm text-gray-500">
                    {{ $onlyUnread ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.' }}
                </li>
            @endforelse
        </ul>

        {{ $notifications->links() }}
    </div>
</x-app-layout>
