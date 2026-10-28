<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\ChangeRequestStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\RiskRating;
use App\Enums\RiskStatus;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\ChangeRequest;
use App\Models\Issue;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectClosingReportTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->manager = User::factory()->create(['name' => 'Riki Manager'])->assignRole('Project Manager');
        $this->project = Project::factory()->create([
            'name' => 'SIAKAD',
            'created_by' => $this->manager->id,
            'status' => ProjectStatus::InProgress,
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
        ]);
        $this->project->members()->attach($this->manager, ['role_in_project' => ProjectRole::ProjectManager->value]);
    }

    private function close(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.close', $this->project), ['closing_notes' => 'Proyek selesai dengan beberapa catatan.'])
            ->assertSessionHasNoErrors();

        $this->project->refresh();
    }

    public function test_closing_stores_a_snapshot_of_the_project(): void
    {
        $this->travelTo('2026-04-05 10:00');

        Task::factory()->for($this->project)->status(TaskStatus::Done)->create(['effort_points' => 5]);
        Task::factory()->for($this->project)->status(TaskStatus::Done)->create(['effort_points' => 3]);
        Task::factory()->for($this->project)->status(TaskStatus::InProgress)->create(['effort_points' => 2, 'due_date' => '2026-03-20']);
        Milestone::factory()->for($this->project)->create(['status' => MilestoneStatus::Completed]);
        Milestone::factory()->for($this->project)->create(['status' => MilestoneStatus::Missed]);
        Risk::factory()->for($this->project)->create(['probability' => RiskRating::High, 'impact' => RiskRating::High, 'status' => RiskStatus::Open]);
        Risk::factory()->for($this->project)->create(['status' => RiskStatus::Closed]);
        Issue::factory()->for($this->project)->create(['severity' => IssueSeverity::Critical, 'status' => IssueStatus::Open]);
        Issue::factory()->for($this->project)->create(['status' => IssueStatus::Closed]);
        ChangeRequest::factory()->for($this->project)->create(['status' => ChangeRequestStatus::Approved]);
        ChangeRequest::factory()->for($this->project)->create(['status' => ChangeRequestStatus::UnderReview]);

        $this->close();

        $summary = $this->project->closing_summary;
        $this->assertSame('Riki Manager', $summary['closed_by']);
        $this->assertEquals(['start' => '2026-01-01', 'planned_end' => '2026-03-31', 'closed_on' => '2026-04-05', 'planned_days' => 90, 'actual_days' => 95, 'variance_days' => 5], $summary['schedule']);
        $this->assertEquals(['managers' => ['Riki Manager'], 'members' => 1], $summary['team']);
        $this->assertEquals(['total' => 3, 'done' => 2, 'open' => 1, 'overdue' => 1, 'completion_rate' => 67, 'effort_total' => 10, 'effort_done' => 8], $summary['tasks']);
        $this->assertEquals(['total' => 2, 'completed' => 1, 'missed' => 1, 'open' => 0], $summary['milestones']);
        $this->assertEquals(['total' => 2, 'closed' => 1, 'open' => 1, 'open_high' => 1], $summary['risks']);
        $this->assertEquals(['total' => 2, 'resolved' => 1, 'open' => 1, 'open_critical' => 1], $summary['issues']);
        $this->assertEquals(['total' => 2, 'approved' => 1, 'rejected' => 0, 'pending' => 1], $summary['change_requests']);
    }

    public function test_report_shows_the_snapshot_even_after_data_changes(): void
    {
        Task::factory()->for($this->project)->status(TaskStatus::Done)->create();
        $this->close();

        Task::factory()->for($this->project)->create();

        $this->actingAs($this->manager)
            ->get(route('projects.closing-report', $this->project))
            ->assertOk()
            ->assertSee('1 dari 1 task selesai (100%)')
            ->assertSee('Cetak / Simpan PDF')
            ->assertSee('Proyek selesai dengan beberapa catatan.')
            ->assertDontSee('dihitung dari data saat ini');
    }

    public function test_reopening_discards_the_snapshot(): void
    {
        $this->close();
        $admin = User::where('email', 'admin@itpms.test')->firstOrFail();

        $this->actingAs($admin)->patch(route('projects.reopen', $this->project))->assertRedirect();

        $this->assertNull($this->project->refresh()->closing_summary);
        $this->actingAs($admin)->get(route('projects.closing-report', $this->project))->assertForbidden();
    }

    public function test_every_member_can_read_the_report_but_outsiders_cannot(): void
    {
        $viewer = User::factory()->create()->assignRole('Viewer');
        $this->project->members()->attach($viewer, ['role_in_project' => ProjectRole::Viewer->value]);
        $outsider = User::factory()->create()->assignRole('Project Manager');

        $this->actingAs($this->manager)->get(route('projects.closing-report', $this->project))->assertForbidden();

        $this->close();

        $this->actingAs($viewer)->get(route('projects.closing-report', $this->project))->assertOk();
        $this->actingAs($viewer)->get(route('projects.show', $this->project))->assertSee('Laporan Penutupan');
        $this->actingAs($outsider)->get(route('projects.closing-report', $this->project))->assertForbidden();
    }

    public function test_project_closed_before_snapshots_existed_falls_back_to_current_data(): void
    {
        $this->project->update(['status' => ProjectStatus::Closed, 'closed_at' => '2026-03-31 15:00', 'closing_notes' => 'Ditutup sebelum fitur laporan.']);
        ActivityLog::record(ActivityAction::StatusChange, $this->project, 'Menutup proyek', user: $this->manager);

        $this->actingAs($this->manager)
            ->get(route('projects.closing-report', $this->project))
            ->assertOk()
            ->assertSee('dihitung dari data saat ini')
            ->assertSee('ditutup pada 31 Maret 2026 oleh Riki Manager, tepat pada tanggal rencana selesai');
    }
}
