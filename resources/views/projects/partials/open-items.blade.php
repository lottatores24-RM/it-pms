{{-- Summary of unfinished work per module. Expects: $summary from ProjectController::openItemSummary(). --}}
@php
$labels = [
    'tasks' => ['Task', 'belum Done'],
    'milestones' => ['Milestone', 'belum selesai'],
    'risks' => ['Risiko', 'belum ditutup'],
    'issues' => ['Issue', 'belum resolved'],
    'change_requests' => ['Change Request', 'belum diputuskan'],
];
@endphp

<ul class="divide-y divide-gray-100">
    @foreach ($summary as $key => $counts)
        <li class="flex items-center justify-between gap-3 px-6 py-3 text-sm">
            <span class="text-gray-700">{{ $labels[$key][0] }}</span>
            <span class="text-right">
                <span class="font-medium text-gray-900">{{ $counts['total'] }}</span>
                @if ($counts['open'] > 0)
                    <span class="ml-1 text-xs text-orange-600">({{ $counts['open'] }} {{ $labels[$key][1] }})</span>
                @endif
            </span>
        </li>
    @endforeach
</ul>
