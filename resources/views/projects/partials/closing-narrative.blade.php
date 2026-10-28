{{--
    Auto-generated closing summary text (FR-021), composed from the closing
    snapshot. Expects: $project, $report (Project::buildClosingSummary()).
--}}
@php
$number = fn (float $value): string => rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
$date = fn (string $value): string => Illuminate\Support\Carbon::parse($value)->translatedFormat('d F Y');

$schedule = $report['schedule'];
$tasks = $report['tasks'];
$milestones = $report['milestones'];
$risks = $report['risks'];
$issues = $report['issues'];
$changeRequests = $report['change_requests'];

$variance = $schedule['variance_days'];
$timing = match (true) {
    $variance > 0 => "terlambat {$variance} hari dari rencana selesai ({$date($schedule['planned_end'])})",
    $variance < 0 => abs($variance).' hari lebih awal dari rencana selesai ('.$date($schedule['planned_end']).')',
    default => 'tepat pada tanggal rencana selesai',
};

$sentences = ["Proyek {$project->name} ditutup pada {$date($schedule['closed_on'])} oleh {$report['closed_by']}, {$timing}."];

if ($tasks['total'] === 0) {
    $sentences[] = 'Proyek tidak memiliki task.';
} else {
    $sentence = "{$tasks['done']} dari {$tasks['total']} task selesai ({$tasks['completion_rate']}%)";
    if ($tasks['effort_total'] > 0) {
        $sentence .= ", setara {$number($tasks['effort_done'])} dari {$number($tasks['effort_total'])} effort point";
    }
    if ($tasks['open'] > 0) {
        $sentence .= "; {$tasks['open']} task belum selesai saat penutupan".($tasks['overdue'] > 0 ? ", {$tasks['overdue']} di antaranya melewati tenggat" : '');
    }
    $sentences[] = $sentence.'.';
}

if ($milestones['total'] > 0) {
    $sentences[] = "{$milestones['completed']} dari {$milestones['total']} milestone tercapai"
        .($milestones['missed'] > 0 ? ", {$milestones['missed']} terlewat" : '')
        .($milestones['open'] > 0 ? ", {$milestones['open']} belum selesai" : '').'.';
}

if ($risks['total'] > 0 || $issues['total'] > 0) {
    $parts = [];
    if ($risks['total'] > 0) {
        $parts[] = "{$risks['open']} dari {$risks['total']} risiko masih terbuka".($risks['open_high'] > 0 ? " (termasuk {$risks['open_high']} berlevel High/Critical)" : '');
    }
    if ($issues['total'] > 0) {
        $parts[] = "{$issues['resolved']} dari {$issues['total']} issue terselesaikan".($issues['open_critical'] > 0 ? " ({$issues['open_critical']} issue Critical masih terbuka)" : '');
    }
    $sentences[] = Str::ucfirst(implode('; ', $parts)).'.';
}

if ($changeRequests['total'] > 0) {
    $sentences[] = "Selama proyek diajukan {$changeRequests['total']} change request: {$changeRequests['approved']} disetujui, {$changeRequests['rejected']} ditolak"
        .($changeRequests['pending'] > 0 ? ", {$changeRequests['pending']} belum diputuskan" : '').'.';
}

$openItems = $tasks['open'] + $milestones['open'] + $risks['open'] + $issues['open'] + $changeRequests['pending'];
$sentences[] = $openItems === 0
    ? 'Seluruh pekerjaan tercatat selesai saat proyek ditutup.'
    : "Total {$openItems} item masih terbuka saat penutupan; penjelasannya ada di catatan penutupan.";
@endphp

<p class="text-sm leading-relaxed text-gray-700">{{ implode(' ', $sentences) }}</p>
