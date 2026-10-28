{{-- Latest notifications on the Midnight Navy dashboards. Expects: $notifications, $unreadCount. Opening one marks it read. --}}
@php
$icons = ['task' => 'check-circle', 'status' => 'refresh', 'risk' => 'warning', 'issue' => 'alert', 'comment' => 'chat', 'change_request' => 'arrows'];
@endphp

<x-night.panel title="Notifikasi" :description="$unreadCount > 0 ? $unreadCount.' belum dibaca' : 'Semua sudah dibaca'"
               :href="route('notifications.index')" link-label="Semua" flush>
    <ul class="divide-y divide-night-700">
        @forelse ($notifications as $notification)
            <li>
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="flex w-full items-start gap-3 px-5 py-3 text-left transition hover:bg-white/[0.03]">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-indigo-500/15 text-indigo-300">
                            <x-icon :name="$icons[$notification->data['icon'] ?? 'task'] ?? 'bell'" class="h-3.5 w-3.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span @class(['truncate text-[13px] text-night-100', 'font-semibold' => $notification->unread(), 'font-medium' => $notification->read()])>{{ $notification->data['title'] ?? 'Notifikasi' }}</span>
                                @if ($notification->unread())
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400" aria-label="Belum dibaca"></span>
                                @endif
                            </span>
                            <span class="mt-0.5 line-clamp-2 block text-xs text-night-400">{{ $notification->data['message'] ?? '' }}</span>
                            <span class="mt-1 block font-mono text-[11px] text-night-400/70">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </button>
                </form>
            </li>
        @empty
            <li class="p-5">
                <x-night.empty icon="bell" title="Belum ada notifikasi" message="Notifikasi muncul saat ada tugas, komentar, atau perubahan status untuk Anda." />
            </li>
        @endforelse
    </ul>
</x-night.panel>
