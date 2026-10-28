{{-- Entity-level change history (FR-019). Expects: $history (collection of ActivityLog). --}}
<div class="bg-white shadow-sm sm:rounded-lg">
    <h3 class="border-b border-gray-100 px-6 py-4 font-semibold text-gray-900">Riwayat</h3>
    <ul class="divide-y divide-gray-100">
        @forelse ($history as $log)
            <li class="px-6 py-3 text-sm">
                <p class="text-gray-900">{{ $log->description }}</p>
                @if ($log->action === App\Enums\ActivityAction::Update && $log->new_values)
                    <ul class="mt-1 space-y-0.5 text-xs text-gray-600">
                        @foreach ($log->new_values as $field => $value)
                            <li>{{ $field }}: <span class="line-through">{{ Str::limit((string) ($log->old_values[$field] ?? '—'), 60) }}</span> → {{ Str::limit((string) ($value ?? '—'), 60) }}</li>
                        @endforeach
                    </ul>
                @elseif ($log->action === App\Enums\ActivityAction::StatusChange && filled($log->new_values['justification'] ?? null))
                    <p class="mt-1 whitespace-pre-line text-xs text-gray-600">Justifikasi: {{ $log->new_values['justification'] }}</p>
                @endif
                <p class="mt-0.5 text-xs text-gray-500">{{ $log->user_name }} · {{ $log->created_at->translatedFormat('d M Y H:i') }}</p>
            </li>
        @empty
            <li class="px-6 py-6 text-sm text-gray-500">Belum ada riwayat.</li>
        @endforelse
    </ul>
</div>
