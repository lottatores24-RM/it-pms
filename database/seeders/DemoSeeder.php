<?php

namespace Database\Seeders;

use App\Enums\ActivityAction;
use App\Enums\ChangeRequestPriority;
use App\Enums\ChangeRequestStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\RiskRating;
use App\Enums\RiskResponse;
use App\Enums\RiskStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\ChangeRequest;
use App\Models\Issue;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ChangeRequestSubmitted;
use App\Notifications\CommentAdded;
use App\Notifications\IssueAssigned;
use App\Notifications\TaskAssigned;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Realistic demo data for PT Nusantara Digital Solutions (Phase 5).
 *
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=DemoSeeder
 *
 * Everything is built chronologically with the clock moved back, so created
 * dates, status changes and the activity log tell a believable story. All
 * dates are relative to today, so overdue items stay overdue whenever it runs.
 */
class DemoSeeder extends Seeder
{
    /**
     * @var array<string, User>
     */
    private array $users = [];

    /**
     * The real "today" and "now"; demo dates are relative to these.
     */
    private Carbon $today;

    private Carbon $now;

    public function run(): void
    {
        $this->now = now();
        $this->today = $this->now->copy()->startOfDay();

        if (Project::where('code', 'SIAKAD-2026')->exists()) {
            $this->command?->warn('Data demo sudah ada. Jalankan "php artisan migrate:fresh --seed" lalu seeder ini untuk mengulang dari awal.');

            return;
        }

        $this->call(DatabaseSeeder::class);

        $today = $this->today;

        try {
            $this->seedUsers($today);
            $this->seedSiakad($today);
            $this->seedKasir($today);
            $this->seedCompanyProfile($today);
            $this->seedRekrutmen($today);
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info('Data demo dibuat. Semua akun memakai password: password');
    }

    // ------------------------------------------------------------------ users

    private function seedUsers(Carbon $today): void
    {
        $this->users = [
            'admin' => User::where('email', 'admin@itpms.test')->firstOrFail(),
            'riki' => User::where('email', 'riki@itpms.test')->firstOrFail(),
            'mirco' => User::where('email', 'mirco@itpms.test')->firstOrFail(),
        ];

        $accounts = [
            'sari' => ['Sari Wulandari', 'Project Manager', true],
            'budi' => ['Budi Santoso', 'Team Member', true],
            'dewi' => ['Dewi Lestari', 'Team Member', true],
            'agus' => ['Agus Pratama', 'Team Member', true],
            'rina' => ['Rina Kartika', 'Team Member', true],
            'hendra' => ['Hendra Gunawan', 'Viewer', true],
            'yoga' => ['Yoga Saputra', 'Team Member', false],
        ];

        $this->at($today->copy()->subDays(130), function () use ($accounts): void {
            foreach ($accounts as $key => [$name, $role, $active]) {
                $user = User::create([
                    'name' => $name,
                    'email' => "{$key}@itpms.test",
                    'password' => 'password',
                    'is_active' => $active,
                    'email_verified_at' => now(),
                ]);
                $user->assignRole($role);
                $this->users[$key] = $user;

                ActivityLog::record(ActivityAction::Create, $user, 'Membuat user baru', newValues: [
                    'name' => $name, 'email' => $user->email, 'role' => $role, 'is_active' => true,
                ], user: $this->users['admin']);
            }
        });

        $this->at($today->copy()->subDays(20), fn () => ActivityLog::record(
            ActivityAction::StatusChange,
            $this->users['yoga'],
            'Menonaktifkan user',
            ['is_active' => true],
            ['is_active' => false],
            $this->users['admin'],
        ));
    }

    // ------------------------------------------------------------------ project 1: SIAKAD (in progress, rich data)

    private function seedSiakad(Carbon $today): void
    {
        $riki = $this->users['riki'];
        $start = $today->copy()->subDays(75);

        $project = $this->project($riki, $start, [
            'name' => 'Sistem Informasi Akademik Universitas Respati',
            'code' => 'SIAKAD-2026',
            'description' => "Pengembangan SIAKAD berbasis web untuk Universitas Respati Yogyakarta: master data, KRS online, input nilai, dan KHS.\nKlien: Biro Administrasi Akademik (BAAK).",
            'start_date' => $start,
            'end_date' => $today->copy()->addDays(75),
            'sponsor' => 'Universitas Respati Yogyakarta',
        ]);

        $this->member($project, 'mirco', ProjectRole::TeamMember, $start->copy()->addDay());
        $this->member($project, 'budi', ProjectRole::TeamMember, $start->copy()->addDay());
        $this->member($project, 'dewi', ProjectRole::TeamMember, $start->copy()->addDays(2));
        $this->member($project, 'hendra', ProjectRole::Viewer, $start->copy()->addDays(3));
        $this->status($project, ProjectStatus::InProgress, $start->copy()->addDays(5), $riki);

        $m1 = $this->milestone($project, 'Analisis & Desain Sistem', -45, MilestoneStatus::Completed, $riki);
        $m2 = $this->milestone($project, 'Modul Master Data & Autentikasi', -10, MilestoneStatus::InProgress, $riki);
        $m3 = $this->milestone($project, 'Modul KRS & Nilai', 30, MilestoneStatus::InProgress, $riki);
        $m4 = $this->milestone($project, 'UAT & Go-Live', 70, MilestoneStatus::Planned, $riki);

        $tasks = [];
        foreach ([
            [$m1, 'Wawancara kebutuhan dengan BAAK', 'mirco', TaskStatus::Done, TaskPriority::High, -60, 3],
            [$m1, 'Menyusun dokumen SRS v1.0', 'riki', TaskStatus::Done, TaskPriority::High, -50, 5],
            [$m1, 'Desain ERD database akademik', 'budi', TaskStatus::Done, TaskPriority::Critical, -48, 5],
            [$m1, 'Mockup UI portal mahasiswa', 'dewi', TaskStatus::Done, TaskPriority::Medium, -46, 3],
            [$m2, 'Implementasi login & RBAC', 'mirco', TaskStatus::Done, TaskPriority::High, -20, 8],
            [$m2, 'CRUD data mahasiswa', 'budi', TaskStatus::InReview, TaskPriority::High, -12, 5],
            [$m2, 'CRUD data dosen & mata kuliah', 'dewi', TaskStatus::InProgress, TaskPriority::Medium, -5, 5],
            [$m2, 'Import data mahasiswa dari Excel', 'mirco', TaskStatus::ToDo, TaskPriority::Medium, 5, 3],
            [$m3, 'Form pengisian KRS online', 'mirco', TaskStatus::InProgress, TaskPriority::Critical, 15, 8],
            [$m3, 'Validasi bentrok jadwal KRS', 'budi', TaskStatus::ToDo, TaskPriority::High, 20, 5],
            [$m3, 'Input nilai oleh dosen', 'dewi', TaskStatus::ToDo, TaskPriority::High, 25, 5],
            [$m3, 'Cetak KHS dalam format PDF', null, TaskStatus::ToDo, TaskPriority::Low, 28, 3],
            [$m4, 'Penyusunan skenario UAT', 'riki', TaskStatus::ToDo, TaskPriority::Medium, 55, 3],
            [$m4, 'Pelatihan admin BAAK', null, TaskStatus::ToDo, TaskPriority::Low, 65, 2],
        ] as [$milestone, $title, $assignee, $status, $priority, $dueOffset, $effort]) {
            $tasks[$title] = $this->task($project, $milestone, $title, $assignee, $status, $priority, $today->copy()->addDays($dueOffset), $effort, $riki);
        }

        // Effort actually spent (EVM AC): the analysis ran close to plan, the build phase over it.
        foreach ([
            'Wawancara kebutuhan dengan BAAK' => 3,
            'Menyusun dokumen SRS v1.0' => 6,
            'Desain ERD database akademik' => 6,
            'Mockup UI portal mahasiswa' => 3,
            'Implementasi login & RBAC' => 9,
            'CRUD data mahasiswa' => 4,
        ] as $title => $actual) {
            $this->actualEffort($tasks[$title], $actual, $riki);
        }

        $this->risk($project, 'Data mahasiswa lama tidak konsisten', 'Teknis', RiskRating::High, RiskRating::High, RiskResponse::Mitigate, RiskStatus::Open, 'budi', -55, $riki,
            'Data warisan dari sistem lama memiliki format NIM dan nama yang tidak seragam.');
        $this->risk($project, 'Keterlambatan approval desain dari BAAK', 'Jadwal', RiskRating::Medium, RiskRating::Medium, RiskResponse::Accept, RiskStatus::Mitigated, 'riki', -60, $riki);
        $serverRisk = $this->risk($project, 'Server kampus sering down saat jam sibuk', 'Eksternal', RiskRating::Medium, RiskRating::High, RiskResponse::Transfer, RiskStatus::Open, 'mirco', -40, $riki,
            'Infrastruktur dikelola UPT TIK kampus; akan diusulkan migrasi ke VPS.');
        $this->risk($project, 'Developer kunci berhalangan lama', 'Sumber Daya', RiskRating::Low, RiskRating::High, RiskResponse::Mitigate, RiskStatus::Open, 'riki', -70, $riki);
        $this->risk($project, 'Perubahan kurikulum di tengah proyek', 'Organisasi', RiskRating::Low, RiskRating::Medium, RiskResponse::Accept, RiskStatus::Closed, null, -70, $riki);

        $this->issue($project, 'Validasi NIM masih menerima huruf', IssueSeverity::High, IssueStatus::InProgress, 'budi', 'dewi', $tasks['CRUD data mahasiswa'], null, -8,
            'Form tambah mahasiswa menerima NIM "22A20038". Seharusnya hanya angka 8 digit.');
        $this->issue($project, 'Sesi logout sendiri setelah 5 menit', IssueSeverity::Medium, IssueStatus::Resolved, 'mirco', 'riki', $tasks['Implementasi login & RBAC'], null, -18);
        $critical = $this->issue($project, 'Import Excel gagal untuk file di atas 2 MB', IssueSeverity::Critical, IssueStatus::Open, 'mirco', 'riki', $tasks['Import data mahasiswa dari Excel'], $serverRisk, -3,
            'Upload data angkatan 2025 (3,4 MB) berakhir dengan error 500.');
        $this->issue($project, 'Typo label "Tanggal Lahri" di profil', IssueSeverity::Low, IssueStatus::Closed, 'dewi', 'riki', null, null, -30);

        $this->changeRequest($project, 'Ganti tema warna mengikuti brand baru kampus', 'budi', ChangeRequestPriority::Low, ChangeRequestStatus::Rejected, -40, [
            'description' => 'Mengubah seluruh palet warna antarmuka ke identitas visual baru Universitas Respati.',
            'reason' => 'Humas kampus merilis brand guideline baru bulan lalu.',
            'impact_scope' => 'Semua halaman; komponen UI perlu disesuaikan.',
            'impact_schedule' => 'Sekitar 4 hari kerja.',
        ], [
            'Akan dibahas di rapat mingguan bersama BAAK.',
            'Ditolak untuk fase ini: brand guideline belum final menurut Humas. Diusulkan ulang setelah go-live.',
        ]);
        $this->changeRequest($project, 'Tambah ekspor KHS ke PDF', 'dewi', ChangeRequestPriority::High, ChangeRequestStatus::Approved, -25, [
            'description' => 'Mahasiswa dapat mengunduh KHS per semester dalam format PDF bertanda tangan digital.',
            'reason' => 'BAAK masih mencetak KHS manual; permintaan langsung dari Kepala BAAK.',
            'impact_scope' => 'Tambahan 1 fitur di modul KHS.',
            'impact_schedule' => '3 hari kerja, masih masuk buffer milestone Modul KRS & Nilai.',
            'impact_resource' => 'Dikerjakan Dewi; tidak perlu tambahan orang.',
        ], [
            'Sedang dianalisis dampaknya terhadap milestone Modul KRS & Nilai.',
            'Disetujui. Effort kecil dan menjawab kebutuhan utama klien; masuk sprint berikutnya.',
        ]);
        $this->changeRequest($project, 'Login dengan SSO akun kampus', 'mirco', ChangeRequestPriority::Medium, ChangeRequestStatus::UnderReview, -6, [
            'description' => 'Mengganti login email/password dengan SSO (OAuth2) milik UPT TIK.',
            'reason' => 'Mahasiswa tidak perlu mengingat password terpisah.',
            'impact_scope' => 'Modul autentikasi; RBAC tetap.',
            'impact_schedule' => 'Perkiraan 1–2 minggu, tergantung dokumentasi API UPT TIK.',
            'impact_resource' => 'Butuh akses sandbox SSO dari UPT TIK.',
        ], [
            'Menunggu dokumentasi API SSO dari UPT TIK sebelum memutuskan.',
        ]);
        $pendingRequest = $this->changeRequest($project, 'Pengingat jadwal KRS via WhatsApp', 'mirco', ChangeRequestPriority::Medium, ChangeRequestStatus::Submitted, -1, [
            'description' => 'Mengirim pengingat otomatis ke WhatsApp mahasiswa H-3 dan H-1 sebelum KRS ditutup.',
            'reason' => 'Banyak mahasiswa terlambat KRS semester lalu.',
            'impact_resource' => 'Butuh layanan WhatsApp Business API (berbayar).',
        ]);

        $this->comment($project, $riki, 'Kick-off meeting dengan BAAK sudah dilakukan. Notulen dan SRS ada di lampiran proyek.', $start->copy()->addDays(4));
        $this->comment($tasks['CRUD data mahasiswa'], $this->users['budi'], 'Sudah siap direview, mohon dicek terutama validasi NIM dan angkatan.', $today->copy()->subDays(4));
        $this->comment($tasks['CRUD data mahasiswa'], $riki, 'Validasi NIM masih menerima huruf, sudah saya catat sebagai issue terpisah.', $today->copy()->subDays(3));
        $this->comment($critical, $this->users['mirco'], 'Sedang saya reproduksi. Sepertinya batas upload di konfigurasi server kampus.', $today->copy()->subDays(2));

        $this->attachment($project, $riki, 'SRS-SIAKAD-v1.0.pdf', $start->copy()->addDays(25), 'Software Requirements Specification - SIAKAD v1.0', [
            'Proyek  : Sistem Informasi Akademik Universitas Respati Yogyakarta',
            'Klien   : Biro Administrasi Akademik (BAAK)',
            'Disusun : Riki Hamdan Sucipto (Project Manager), PT Nusantara Digital Solutions',
            '',
            '1. Ruang lingkup',
            '   Master data mahasiswa, dosen & mata kuliah; KRS online; input nilai; KHS.',
            '2. Kebutuhan fungsional utama',
            '   F-01 Login dan hak akses per peran (admin BAAK, dosen, mahasiswa).',
            '   F-02 Pengelolaan data mahasiswa, termasuk import dari Excel.',
            '   F-03 Pengisian KRS online dengan validasi bentrok jadwal.',
            '   F-04 Input nilai oleh dosen dan cetak KHS per semester.',
            '3. Kebutuhan non-fungsional',
            '   Halaman utama terbuka < 3 detik; data nilai hanya dapat diubah dosen pengampu.',
            '',
            'Dokumen contoh untuk data demo IT-PMS.',
        ]);

        // Unread notifications so the badge and the notification page have content.
        $this->at($today->copy()->subDays(2), function () use ($tasks, $critical, $riki): void {
            $this->users['mirco']->notify(new TaskAssigned($tasks['Form pengisian KRS online'], $riki));
            $this->users['mirco']->notify(new IssueAssigned($critical, $riki));
            $riki->notify(new CommentAdded($critical->comments()->first(), $this->users['mirco']));
        });
        $this->at($pendingRequest->created_at, fn () => $riki->notify(new ChangeRequestSubmitted($pendingRequest, $this->users['mirco'])));
    }

    // ------------------------------------------------------------------ project 2: Kasir UMKM (in progress)

    private function seedKasir(Carbon $today): void
    {
        $sari = $this->users['sari'];
        $start = $today->copy()->subDays(30);

        $project = $this->project($sari, $start, [
            'name' => 'Aplikasi Kasir UMKM Batik Jogja',
            'code' => 'KASIR-BATIK',
            'description' => 'Aplikasi point-of-sale berbasis web untuk 12 UMKM batik binaan Dinas Koperasi DIY, termasuk stok dan laporan harian.',
            'start_date' => $start,
            'end_date' => $today->copy()->addDays(60),
            'sponsor' => 'Dinas Koperasi & UKM DIY',
        ]);

        $this->member($project, 'agus', ProjectRole::TeamMember, $start->copy()->addDay());
        $this->member($project, 'rina', ProjectRole::TeamMember, $start->copy()->addDay());
        $this->member($project, 'mirco', ProjectRole::TeamMember, $start->copy()->addDays(3));
        $this->member($project, 'riki', ProjectRole::Viewer, $start->copy()->addDays(3));
        $this->status($project, ProjectStatus::InProgress, $start->copy()->addDays(2), $sari);

        $m1 = $this->milestone($project, 'MVP Transaksi Penjualan', 10, MilestoneStatus::InProgress, $sari);
        $m2 = $this->milestone($project, 'Laporan & Manajemen Stok', 50, MilestoneStatus::Planned, $sari);

        foreach ([
            [$m1, 'Desain skema produk & varian motif', 'agus', TaskStatus::Done, TaskPriority::High, -20, 3],
            [$m1, 'Halaman transaksi kasir', 'rina', TaskStatus::InReview, TaskPriority::Critical, 3, 8],
            [$m1, 'Cetak struk thermal 58mm', 'mirco', TaskStatus::InProgress, TaskPriority::High, 7, 5],
            [$m1, 'Integrasi pembayaran QRIS', 'agus', TaskStatus::ToDo, TaskPriority::High, 9, 8],
            [$m2, 'Laporan penjualan harian', 'rina', TaskStatus::ToDo, TaskPriority::Medium, 35, 5],
            [$m2, 'Peringatan stok menipis', null, TaskStatus::ToDo, TaskPriority::Low, 45, 3],
        ] as [$milestone, $title, $assignee, $status, $priority, $dueOffset, $effort]) {
            $this->task($project, $milestone, $title, $assignee, $status, $priority, $today->copy()->addDays($dueOffset), $effort, $sari);
        }

        $this->risk($project, 'Koneksi internet UMKM tidak stabil', 'Teknis', RiskRating::High, RiskRating::Medium, RiskResponse::Mitigate, RiskStatus::Open, 'agus', -25, $sari,
            'Perlu mode offline sederhana dengan sinkronisasi saat koneksi kembali.');
        $this->risk($project, 'Biaya MDR QRIS membebani UMKM', 'Biaya', RiskRating::Medium, RiskRating::Low, RiskResponse::Accept, RiskStatus::Open, 'rina', -15, $sari);

        $this->issue($project, 'Total belanja tidak membulatkan ke ratusan', IssueSeverity::Medium, IssueStatus::Open, 'rina', 'mirco', null, null, -2);

        $this->changeRequest($project, 'Dukungan multi-cabang per UMKM', 'agus', ChangeRequestPriority::High, ChangeRequestStatus::Submitted, -4, [
            'description' => 'Satu UMKM dapat memiliki beberapa toko dengan stok terpisah.',
            'reason' => '3 dari 12 UMKM memiliki lebih dari satu gerai.',
            'impact_scope' => 'Skema stok dan laporan perlu dimensi cabang.',
            'impact_schedule' => 'Perkiraan 2 minggu; milestone Laporan & Manajemen Stok bergeser.',
        ]);
    }

    // ------------------------------------------------------------------ project 3: Company Profile (closed)

    private function seedCompanyProfile(Carbon $today): void
    {
        $sari = $this->users['sari'];
        $start = $today->copy()->subDays(120);

        $project = $this->project($sari, $start, [
            'name' => 'Website Company Profile PT NDS',
            'code' => 'WEB-NDS',
            'description' => 'Redesain website company profile PT Nusantara Digital Solutions beserta halaman portofolio dan karier.',
            'start_date' => $start,
            'end_date' => $today->copy()->subDays(40),
            'sponsor' => 'Internal PT NDS',
        ]);

        $this->member($project, 'rina', ProjectRole::TeamMember, $start->copy()->addDay());
        $this->status($project, ProjectStatus::InProgress, $start->copy()->addDays(3), $sari);

        $milestone = $this->milestone($project, 'Website Live', -42, MilestoneStatus::Completed, $sari);

        foreach ([
            ['Desain UI halaman beranda', 'rina', -90, 3],
            ['Halaman portofolio & studi kasus', 'rina', -70, 5],
            ['Optimasi SEO dan kecepatan', 'rina', -55, 3],
            ['Deploy ke hosting produksi', 'sari', -45, 2],
        ] as [$title, $assignee, $dueOffset, $effort]) {
            $this->task($project, $milestone, $title, $assignee, TaskStatus::Done, TaskPriority::Medium, $today->copy()->addDays($dueOffset), $effort, $sari);
        }

        $this->at($today->copy()->subDays(38), function () use ($project, $sari): void {
            $project->update([
                'status' => ProjectStatus::Closed,
                'closing_notes' => "Website live sesuai target. Seluruh task dan milestone selesai.\nPelajaran: aset foto portofolio sebaiknya dikumpulkan sejak awal proyek.",
                'closing_summary' => $project->buildClosingSummary($sari->name),
                'closed_at' => now(),
            ]);

            ActivityLog::record(ActivityAction::StatusChange, $project, 'Menutup proyek', ['status' => 'In Progress'], ['status' => 'Closed'], $sari);
        });
    }

    // ------------------------------------------------------------------ project 4: Rekrutmen (planning)

    private function seedRekrutmen(Carbon $today): void
    {
        $riki = $this->users['riki'];

        $project = $this->project($riki, $today->copy()->subDays(5), [
            'name' => 'Portal Rekrutmen Online',
            'code' => 'REKRUT-2027',
            'description' => 'Portal lowongan dan seleksi kandidat untuk divisi HR, termasuk tes online dan penjadwalan wawancara.',
            'start_date' => $today->copy()->addDays(10),
            'end_date' => $today->copy()->addDays(120),
            'sponsor' => 'Divisi HR PT NDS',
        ]);

        $this->member($project, 'agus', ProjectRole::TeamMember, $today->copy()->subDays(4));
        $this->milestone($project, 'Analisis kebutuhan HR', 30, MilestoneStatus::Planned, $riki);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Run a callback with the clock set to the given moment, never later than
     * the real current time.
     */
    private function at(Carbon $moment, Closure $callback): mixed
    {
        Carbon::setTestNow($moment->copy()->min($this->now));

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function project(User $manager, Carbon $createdAt, array $attributes): Project
    {
        return $this->at($createdAt->copy()->setTime(9, 0), function () use ($manager, $attributes): Project {
            $project = Project::create([...$attributes, 'status' => ProjectStatus::Planning, 'created_by' => $manager->id]);
            $project->projectMembers()->create(['user_id' => $manager->id, 'role_in_project' => ProjectRole::ProjectManager, 'joined_at' => now()]);

            ActivityLog::record(ActivityAction::Create, $project, "Membuat proyek; {$manager->name} menjadi Project Manager", newValues: [
                'name' => $project->name, 'code' => $project->code, 'status' => 'Planning',
            ], user: $manager);

            return $project;
        });
    }

    private function member(Project $project, string $key, ProjectRole $role, Carbon $at): void
    {
        $this->at($at->copy()->setTime(10, 0), function () use ($project, $key, $role): void {
            $member = $project->projectMembers()->create(['user_id' => $this->users[$key]->id, 'role_in_project' => $role, 'joined_at' => now()]);

            ActivityLog::record(ActivityAction::Create, $member, "Menambahkan {$member->user->name} sebagai {$role->value}", newValues: [
                'user' => $member->user->name, 'role_in_project' => $role->value,
            ], user: $project->creator);
        });
    }

    private function status(Project $project, ProjectStatus $status, Carbon $at, User $actor): void
    {
        $this->at($at->copy()->setTime(11, 0), function () use ($project, $status, $actor): void {
            $old = $project->status->value;
            $project->update(['status' => $status]);

            ActivityLog::record(ActivityAction::StatusChange, $project, "Mengubah status proyek: {$old} → {$status->value}", ['status' => $old], ['status' => $status->value], $actor);
        });
    }

    private function milestone(Project $project, string $name, int $targetOffset, MilestoneStatus $status, User $actor): Milestone
    {
        $created = $project->start_date->copy()->addDays(3)->setTime(13, 0);
        $target = $this->today->copy()->addDays($targetOffset);

        $milestone = $this->at($created, function () use ($project, $name, $target, $actor): Milestone {
            $milestone = $project->milestones()->create(['name' => $name, 'target_date' => $target, 'status' => MilestoneStatus::Planned]);
            ActivityLog::record(ActivityAction::Create, $milestone, 'Membuat milestone', newValues: ['name' => $name, 'target_date' => $target->format('Y-m-d')], user: $actor);

            return $milestone;
        });

        $path = match ($status) {
            MilestoneStatus::InProgress => [[MilestoneStatus::InProgress, $created->copy()->addDays(2)]],
            MilestoneStatus::Completed => [
                [MilestoneStatus::InProgress, $created->copy()->addDays(2)],
                [MilestoneStatus::Completed, $target->copy()->subDay()->setTime(16, 0)],
            ],
            default => [],
        };

        foreach ($path as [$next, $moment]) {
            $this->at($moment, function () use ($milestone, $next, $actor): void {
                $old = $milestone->status->value;
                $milestone->update(['status' => $next]);
                ActivityLog::record(ActivityAction::StatusChange, $milestone, "Mengubah status milestone: {$old} → {$next->value}", ['status' => $old], ['status' => $next->value], $actor);
            });
        }

        return $milestone;
    }

    /**
     * Create a task and walk it through the workflow up to the given status,
     * one logged transition at a time, spread between creation and its due date.
     */
    private function task(Project $project, ?Milestone $milestone, string $title, ?string $assigneeKey, TaskStatus $status, TaskPriority $priority, Carbon $due, int $effort, User $manager): Task
    {
        $assignee = $assigneeKey ? $this->users[$assigneeKey] : null;
        $created = $due->copy()->subDays(14)
            ->max($project->start_date->copy()->addDays(4))
            ->min($this->today->copy()->subDays(2))
            ->setTime(9, 30);

        $task = $this->at($created, function () use ($project, $milestone, $title, $assignee, $priority, $due, $effort, $manager): Task {
            $task = $project->tasks()->create([
                'title' => $title,
                'assigned_to' => $assignee?->id,
                'priority' => $priority,
                'due_date' => $due,
                'milestone_id' => $milestone?->id,
                'effort_points' => $effort,
                'status' => TaskStatus::ToDo,
            ]);

            ActivityLog::record(ActivityAction::Create, $task, 'Membuat task', newValues: [
                'title' => $title,
                'assigned_to' => $assignee?->name,
                'priority' => $priority->value,
                'due_date' => $due->format('Y-m-d'),
                'status' => 'To Do',
            ], user: $manager);

            return $task;
        });

        $workflow = [TaskStatus::ToDo, TaskStatus::InProgress, TaskStatus::InReview, TaskStatus::Done];
        $steps = array_slice($workflow, 1, array_search($status, $workflow, true));

        if ($steps === []) {
            return $task;
        }

        $finish = $due->copy()->subDay()
            ->min($this->today->copy()->subDay())
            ->setTime(15, 0)
            ->max($created->copy()->addHours(count($steps) + 1));

        foreach ($steps as $index => $next) {
            $moment = $created->copy()->addSeconds((int) ($created->diffInSeconds($finish) * ($index + 1) / count($steps)));
            $actor = $next === TaskStatus::Done ? $manager : ($assignee ?? $manager);

            $this->at($moment, function () use ($task, $next, $actor): void {
                $old = $task->status->value;
                $task->update(['status' => $next]);
                ActivityLog::record(ActivityAction::StatusChange, $task, "Mengubah status task: {$old} → {$next->value}", ['status' => $old], ['status' => $next->value], $actor);
            });
        }

        return $task;
    }

    private function risk(Project $project, string $title, string $category, RiskRating $probability, RiskRating $impact, RiskResponse $response, RiskStatus $status, ?string $ownerKey, int $createdOffset, User $actor, ?string $description = null): Risk
    {
        $created = $this->today->copy()->addDays($createdOffset)->max($project->start_date)->setTime(14, 0);

        $risk = $this->at($created, function () use ($project, $title, $category, $probability, $impact, $response, $ownerKey, $actor, $description): Risk {
            $risk = $project->risks()->create([
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'probability' => $probability,
                'impact' => $impact,
                'response_plan' => $response,
                'status' => RiskStatus::Open,
                'owner_id' => $ownerKey ? $this->users[$ownerKey]->id : null,
            ]);

            ActivityLog::record(ActivityAction::Create, $risk, 'Membuat risiko', newValues: [
                'title' => $title,
                'risk_score' => $risk->risk_score,
                'risk_level' => $risk->risk_level->value,
            ], user: $actor);

            return $risk;
        });

        if ($status !== RiskStatus::Open) {
            $this->at($created->copy()->addDays(7), function () use ($risk, $status, $actor): void {
                $risk->update(['status' => $status]);
                ActivityLog::record(ActivityAction::StatusChange, $risk, "Mengubah status risiko: Open → {$status->value}", ['status' => 'Open'], ['status' => $status->value], $actor);
            });
        }

        return $risk;
    }

    private function actualEffort(Task $task, float $points, User $manager): void
    {
        $recordedAt = ($task->completed_at ?? $this->today->copy()->subDay()->setTime(15, 30))->copy()->addMinutes(30);

        $this->at($recordedAt, function () use ($task, $points, $manager): void {
            $task->update(['actual_effort_points' => $points]);
            ActivityLog::record(ActivityAction::Update, $task, 'Mencatat effort aktual task', ['actual_effort_points' => null], ['actual_effort_points' => $task->actual_effort_points], $task->assignee ?? $manager);
        });
    }

    private function issue(Project $project, string $title, IssueSeverity $severity, IssueStatus $status, ?string $assigneeKey, string $reporterKey, ?Task $task, ?Risk $risk, int $createdOffset, ?string $description = null): Issue
    {
        $reporter = $this->users[$reporterKey];
        $assignee = $assigneeKey ? $this->users[$assigneeKey] : null;
        $created = $this->today->copy()->addDays($createdOffset)->setTime(10, 15);

        $issue = $this->at($created, function () use ($project, $title, $severity, $assignee, $reporter, $task, $risk, $description): Issue {
            $issue = $project->issues()->create([
                'title' => $title,
                'description' => $description,
                'severity' => $severity,
                'status' => IssueStatus::Open,
                'assigned_to' => $assignee?->id,
                'reported_by' => $reporter->id,
                'related_task_id' => $task?->id,
                'related_risk_id' => $risk?->id,
            ]);

            ActivityLog::record(ActivityAction::Create, $issue, 'Melaporkan issue', newValues: [
                'title' => $title,
                'severity' => $severity->value,
                'assigned_to' => $assignee?->name,
            ], user: $reporter);

            return $issue;
        });

        $workflow = IssueStatus::cases();
        $steps = array_slice($workflow, 1, array_search($status, $workflow, true));

        foreach ($steps as $index => $next) {
            $actor = $next === IssueStatus::Closed ? $project->creator : ($assignee ?? $project->creator);

            $this->at($created->copy()->addHours(20 * ($index + 1)), function () use ($issue, $next, $actor): void {
                $old = $issue->status->value;
                $issue->update(['status' => $next]);
                ActivityLog::record(ActivityAction::StatusChange, $issue, "Mengubah status issue: {$old} → {$next->value}", ['status' => $old], ['status' => $next->value], $actor);
            });
        }

        return $issue;
    }

    /**
     * Submit a change request and walk it through review; each review step
     * takes the next justification from $justifications.
     *
     * @param  array{description: string, reason: string, impact_scope?: string, impact_schedule?: string, impact_resource?: string}  $details
     * @param  list<string>  $justifications
     */
    private function changeRequest(Project $project, string $title, string $requesterKey, ChangeRequestPriority $priority, ChangeRequestStatus $status, int $createdOffset, array $details, array $justifications = []): ChangeRequest
    {
        $requester = $this->users[$requesterKey];
        $created = $this->today->copy()->addDays($createdOffset)->setTime(13, 30);

        $changeRequest = $this->at($created, function () use ($project, $title, $requester, $priority, $details): ChangeRequest {
            $changeRequest = $project->changeRequests()->create([
                ...$details,
                'title' => $title,
                'priority' => $priority,
                'status' => ChangeRequestStatus::Submitted,
                'requested_by' => $requester->id,
            ]);

            ActivityLog::record(ActivityAction::Create, $changeRequest, 'Mengajukan change request', newValues: [
                'title' => $title,
                'priority' => $priority->value,
            ], user: $requester);

            return $changeRequest;
        });

        $steps = match ($status) {
            ChangeRequestStatus::Submitted => [],
            ChangeRequestStatus::UnderReview => [ChangeRequestStatus::UnderReview],
            default => [ChangeRequestStatus::UnderReview, $status],
        };

        foreach ($steps as $index => $next) {
            $this->at($created->copy()->addDays(2 * ($index + 1)), function () use ($project, $changeRequest, $next, $justifications, $index): void {
                $old = $changeRequest->status->value;
                $changeRequest->update(['status' => $next, 'justification' => $justifications[$index], 'reviewed_by' => $project->creator->id]);
                ActivityLog::record(ActivityAction::StatusChange, $changeRequest, "Mengubah status change request: {$old} → {$next->value}", ['status' => $old], [
                    'status' => $next->value,
                    'justification' => $justifications[$index],
                ], $project->creator);
            });
        }

        return $changeRequest;
    }

    private function comment(Project|Task|Issue $target, User $author, string $body, Carbon $at): void
    {
        $this->at($at->copy()->setTime(16, 20), function () use ($target, $author, $body): void {
            $comment = $target->comments()->create(['user_id' => $author->id, 'body' => $body]);
            ActivityLog::record(ActivityAction::Create, $comment, 'Menambahkan komentar', newValues: ['body' => $body], user: $author);
        });
    }

    /**
     * Store a one-page PDF so the download link opens a real document in the demo.
     *
     * @param  list<string>  $lines
     */
    private function attachment(Project $project, User $uploader, string $name, Carbon $at, string $title, array $lines): void
    {
        $this->at($at->copy()->setTime(11, 0), function () use ($project, $uploader, $name, $title, $lines): void {
            $content = $this->pdfDocument($title, $lines);
            $path = $project->id.'/'.Str::random(40).'.pdf';
            Storage::disk(Attachment::DISK)->put($path, $content);

            $attachment = $project->attachments()->create([
                'user_id' => $uploader->id,
                'filename' => basename($path),
                'original_name' => $name,
                'mime_type' => 'application/pdf',
                'size' => strlen($content),
                'path' => $path,
            ]);

            ActivityLog::record(ActivityAction::Create, $attachment, 'Mengunggah lampiran', newValues: ['file' => $name], user: $uploader);
        });
    }

    /**
     * Minimal valid single-page A4 PDF (PDF 1.4, built-in Helvetica fonts):
     * a bold title followed by one line of text per entry.
     *
     * @param  list<string>  $lines
     */
    private function pdfDocument(string $title, array $lines): string
    {
        $text = fn (string $value): string => '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_convert_encoding($value, 'Windows-1252', 'UTF-8')).')';

        $stream = "BT /F1 16 Tf 56 780 Td {$text($title)} Tj ET\n"
            ."BT /F2 11 Tf 16 TL 56 748 Td\n"
            .implode('', array_map(fn (string $line) => "{$text($line)} Tj T*\n", $lines))
            .'ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
