{{--
    Paginated activity entries with expandable old/new values.
    Expects: $logs, optional $showProject (bool).
--}}
@php $showProject ??= false; @endphp

<div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
    <ul class="divide-y divide-gray-100">
        @forelse ($logs as $log)
            <li class="px-4 py-3 sm:px-6">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:gap-4">
                    <div class="shrink-0 text-xs text-gray-500 sm:w-32">
                        <p>{{ $log->created_at->translatedFormat('d M Y') }}</p>
                        <p>{{ $log->created_at->format('H:i:s') }}</p>
                    </div>

                    <div class="min-w-0 flex-1 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :color="$log->action->color()">{{ $log->action->label() }}</x-badge>
                            <span class="font-medium text-gray-900">{{ $log->user_name }}</span>
                            <span class="text-gray-500">·</span>
                            <span class="text-gray-700">{{ $log->entityLabel() }}{{ $log->entity_name ? ': '.$log->entity_name : '' }}</span>
                            @if ($showProject && $log->project)
                                <span class="font-mono text-xs text-gray-400">{{ $log->project->code }}</span>
                            @endif
                        </div>

                        @if ($log->description)
                            <p class="mt-1 text-gray-700">{{ $log->description }}</p>
                        @endif

                        @if ($log->old_values || $log->new_values)
                            <details class="mt-1">
                                <summary class="cursor-pointer text-xs text-indigo-600 hover:text-indigo-800">Detail perubahan</summary>
                                <dl class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1 rounded-md bg-gray-50 p-3 text-xs sm:grid-cols-[10rem_1fr]">
                                    @foreach (array_unique([...array_keys($log->old_values ?? []), ...array_keys($log->new_values ?? [])]) as $field)
                                        @php
                                            $format = fn ($value) => is_array($value) ? implode(', ', $value) : (is_bool($value) ? ($value ? 'ya' : 'tidak') : (string) ($value ?? '—'));
                                        @endphp
                                        <dt class="font-medium text-gray-600">{{ $field }}</dt>
                                        <dd class="break-words text-gray-800">
                                            @if ($log->old_values !== null && array_key_exists($field, $log->old_values))
                                                <span class="text-red-700 line-through">{{ Str::limit($format($log->old_values[$field]), 200) }}</span>
                                            @endif
                                            @if ($log->new_values !== null && array_key_exists($field, $log->new_values))
                                                <span class="text-green-700">{{ Str::limit($format($log->new_values[$field]), 200) }}</span>
                                            @endif
                                        </dd>
                                    @endforeach
                                </dl>
                            </details>
                        @endif
                    </div>

                    @if ($log->ip_address)
                        <p class="shrink-0 font-mono text-xs text-gray-400">{{ $log->ip_address }}</p>
                    @endif
                </div>
            </li>
        @empty
            <li class="px-6 py-10 text-center text-sm text-gray-500">Tidak ada aktivitas yang cocok dengan filter.</li>
        @endforelse
    </ul>
</div>

{{ $logs->links() }}
