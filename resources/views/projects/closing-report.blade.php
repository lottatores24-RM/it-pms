<x-app-layout>
    <x-slot name="title">Laporan Penutupan · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ $project->name }}</a>
    </x-slot>

    @php
    $number = fn (float $value): string => rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',');
    $date = fn (string $value): string => Illuminate\Support\Carbon::parse($value)->translatedFormat('d F Y');
    $schedule = $report['schedule'];
    $variance = $schedule['variance_days'];
    $modules = [
        ['Task selesai', "{$report['tasks']['done']} / {$report['tasks']['total']}", "{$report['tasks']['completion_rate']}% · effort {$number($report['tasks']['effort_done'])} / {$number($report['tasks']['effort_total'])}"],
        ['Milestone tercapai', "{$report['milestones']['completed']} / {$report['milestones']['total']}", "{$report['milestones']['missed']} missed · {$report['milestones']['open']} belum selesai"],
        ['Risiko terbuka', "{$report['risks']['open']} / {$report['risks']['total']}", "{$report['risks']['open_high']} berlevel High/Critical"],
        ['Issue terselesaikan', "{$report['issues']['resolved']} / {$report['issues']['total']}", "{$report['issues']['open_critical']} Critical masih terbuka"],
        ['Change request', (string) $report['change_requests']['total'], "{$report['change_requests']['approved']} disetujui · {$report['change_requests']['rejected']} ditolak · {$report['change_requests']['pending']} pending"],
        ['Tim proyek', "{$report['team']['members']} orang", count($report['team']['managers']) ? 'PM: '.implode(', ', $report['team']['managers']) : 'Tanpa Project Manager'],
    ];
    @endphp

    <div class="mx-auto max-w-4xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
            <p class="text-sm text-gray-500">Gunakan tombol cetak lalu pilih tujuan <strong>Save as PDF</strong> untuk menyimpan laporan sebagai PDF.</p>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                </svg>
                Cetak / Simpan PDF
            </button>
        </div>

        @unless ($isSnapshot)
            <div class="rounded-md bg-yellow-50 p-4 text-sm text-yellow-800 ring-1 ring-inset ring-yellow-600/20">
                Proyek ini ditutup sebelum snapshot penutupan tersedia, sehingga angka di bawah dihitung dari data saat ini.
            </div>
        @endunless

        <article class="space-y-8 bg-white p-6 shadow-sm sm:rounded-lg sm:p-10 print:p-0 print:shadow-none">
            <header class="flex flex-col gap-4 border-b border-gray-200 pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600">PT Nusantara Digital Solutions</p>
                    <h1 class="mt-1 text-2xl font-semibold text-gray-900">Laporan Penutupan Proyek</h1>
                    <p class="mt-1 text-gray-700">{{ $project->name }}</p>
                </div>
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    <dt class="text-gray-500">Kode</dt>
                    <dd class="font-mono text-gray-900">{{ $project->code }}</dd>
                    <dt class="text-gray-500">Sponsor</dt>
                    <dd class="text-gray-900">{{ $project->sponsor ?: '—' }}</dd>
                    <dt class="text-gray-500">Ditutup oleh</dt>
                    <dd class="text-gray-900">{{ $report['closed_by'] }}</dd>
                </dl>
            </header>

            <section class="break-inside-avoid">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Ringkasan</h2>
                <div class="mt-2">
                    @include('projects.partials.closing-narrative')
                </div>
            </section>

            <section class="break-inside-avoid">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Jadwal</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <th scope="row" class="py-2 pr-4 text-left font-normal text-gray-500">Tanggal mulai</th>
                                <td class="py-2 text-gray-900">{{ $date($schedule['start']) }}</td>
                            </tr>
                            <tr>
                                <th scope="row" class="py-2 pr-4 text-left font-normal text-gray-500">Rencana selesai</th>
                                <td class="py-2 text-gray-900">{{ $date($schedule['planned_end']) }} ({{ $schedule['planned_days'] }} hari)</td>
                            </tr>
                            <tr>
                                <th scope="row" class="py-2 pr-4 text-left font-normal text-gray-500">Tanggal ditutup</th>
                                <td class="py-2 text-gray-900">{{ $date($schedule['closed_on']) }} ({{ $schedule['actual_days'] }} hari)</td>
                            </tr>
                            <tr>
                                <th scope="row" class="py-2 pr-4 text-left font-normal text-gray-500">Selisih jadwal</th>
                                <td @class(['py-2 font-medium', 'text-red-700' => $variance > 0, 'text-green-700' => $variance <= 0])>
                                    @if ($variance > 0)
                                        Terlambat {{ $variance }} hari
                                    @elseif ($variance < 0)
                                        {{ abs($variance) }} hari lebih awal
                                    @else
                                        Tepat waktu
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="break-inside-avoid">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Capaian per modul</h2>
                <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 print:grid-cols-3">
                    @foreach ($modules as [$label, $value, $hint])
                        <div class="rounded-md p-4 ring-1 ring-inset ring-gray-200">
                            <dt class="text-sm text-gray-500">{{ $label }}</dt>
                            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $value }}</dd>
                            <dd class="mt-1 text-xs text-gray-500">{{ $hint }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="break-inside-avoid">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Catatan penutupan</h2>
                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-gray-700">{{ $project->closing_notes ?: '—' }}</p>
            </section>

            <section class="grid grid-cols-2 gap-8 pt-6 text-center text-sm break-inside-avoid">
                @foreach (['Project Manager' => $report['team']['managers'][0] ?? null, 'Sponsor' => $project->sponsor] as $role => $name)
                    <div>
                        <p class="text-gray-500">{{ $role }}</p>
                        <div class="mx-auto mt-16 w-48 border-b border-gray-400"></div>
                        <p class="mt-1 text-gray-900">{{ $name ?: '' }}</p>
                    </div>
                @endforeach
            </section>

            <footer class="border-t border-gray-200 pt-4 text-xs text-gray-500">
                Dibuat otomatis oleh IT-PMS
                @if ($isSnapshot)
                    saat proyek ditutup ({{ Illuminate\Support\Carbon::parse($report['generated_at'])->translatedFormat('d F Y H:i') }}).
                @else
                    pada {{ now()->translatedFormat('d F Y H:i') }}.
                @endif
            </footer>
        </article>
    </div>
</x-app-layout>
