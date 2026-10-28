{{-- Latest notifications. Expects: $notifications, $unreadCount. Opening one marks it read. --}}
@php
$icons = ['task' => 'check-circle', 'status' => 'refresh', 'risk' => 'warning', 'issue' => 'alert', 'comment' => 'chat', 'change_request' => 'arrows'];
@endphp

<x-dashboard.card title="Notifikasi terbaru" :description="$unreadCount > 0 ? $unreadCount.' belum dibaca' : 'Semua sudah dibaca'"
                  :href="route('notifications.index')" link-label="Semua" :delay="$delay ?? 300" flush>
    <ul class="divide-y divide-gray-100">
        @forelse ($notifications as $notification)
            <li>
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="flex w-full items-start gap-3 px-6 py-3.5 text-left transition duration-200 hover:bg-gray-50">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 ring-1 ring-inset ring-indigo-600/10">
                            <x-icon :name="$icons[$notification->data['icon'] ?? 'task'] ?? 'bell'" class="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span @class(['truncate text-sm text-gray-900', 'font-semibold' => $notification->unread(), 'font-medium' => $notification->read()])>{{ $notification->data['title'] ?? 'Notifikasi' }}</span>
                                @if ($notification->unread())
                                    <span class="h-2 w-2 shrink-0 rounded-full bg-indigo-600" aria-label="Belum dibaca"></span>
                                @endif
                            </span>
                            <span class="mt-0.5 line-clamp-2 block text-xs text-gray-600">{{ $notification->data['message'] ?? '' }}</span>
                            <span class="mt-1 block text-[11px] text-gray-400">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </button>
                </form>
            </li>
        @empty
            <li class="p-6">
                <x-dashboard.empty icon="bell" title="Belum ada notifikasi" message="Notifikasi muncul saat ada tugas, komentar, atau perubahan status untuk Anda." />
            </li>
        @endforelse
    </ul>
</x-dashboard.card>
