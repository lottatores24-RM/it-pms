<?php

namespace Tests\Feature;

use App\Enums\ChangeRequestStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\RiskRating;
use App\Enums\RiskStatus;
use App\Enums\TaskStatus;
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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $manager;

    private User $teamMember;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->travelTo('2026-12-10 09:00');

        $this->manager = User::factory()->create(['name' => 'Pak Manajer'])->assignRole('Project Manager');
        $this->project = Project::factory()->create([
            'created_by' => $this->manager->id,
            'status' => ProjectStatus::InProgress,
            'start_date' => '2026-10-01',
            'end_date' => '2027-03-31',
        ]);
        $this->project->members()->attach($this->manager, ['role_in_project' => ProjectRole::ProjectManager->value]);

        $this->teamMember = User::factory()->create(['name' => "Dian O'Neil"])->assignRole('Team Member');
        $this->project->members()->attach($this->teamMember, ['role_in_project' => ProjectRole::TeamMember->value]);
    }

    private function task(array $attributes): Task
    {
        return Task::factory()->for($this->project)->create(['due_date' => null, ...$attributes]);
    }

    // ---------------------------------------------------------------- project dashboard (FR-020)

    public function test_project_dashboard_figures_come_from_the_database(): void
    {
        $this->task(['assigned_to' => $this->teamMember->id, 'status' => TaskStatus::Done]);
        $this->task(['assigned_to' => $this->teamMember->id, 'status' => TaskStatus::InProgress, 'due_date' => '2026-12-01']);
        $this->task(['assigned_to' => $this->manager->id, 'status' => TaskStatus::Done]);
        $this->task(['assigned_to' => null, 'status' => TaskStatus::ToDo, 'due_date' => '2026-12-31']);

        Milestone::factory()->for($this->project)->create(['target_date' => '2026-11-30', 'status' => MilestoneStatus::InProgress]);
        Milestone::factory()->for($this->project)->create(['target_date' => '2026-11-15', 'status' => MilestoneStatus::Completed]);

        Risk::factory()->for($this->project)->create(['probability' => RiskRating::High, 'impact' => RiskRating::High]);
        Risk::factory()->for($this->project)->create(['probability' => RiskRating::High, 'impact' => RiskRating::Medium, 'status' => RiskStatus::Closed]);
        Risk::factory()->for($this->project)->create(['probability' => RiskRating::Low, 'impact' => RiskRating::Low]);

        Issue::factory()->for($this->project)->create(['reported_by' => $this->manager->id, 'severity' => IssueSeverity::Critical]);
        Issue::factory()->for($this->project)->create(['reported_by' => $this->manager->id, 'severity' => IssueSeverity::Critical, 'status' => IssueStatus::Closed]);

        $response = $this->actingAs($this->manager)->get(route('projects.dashboard', $this->project))->assertOk();

        $response->assertViewHas('taskCounts', ['To Do' => 1, 'In Progress' => 1, 'In Review' => 0, 'Done' => 2]);
        $response->assertViewHas('totalTasks', 4);
        $response->assertViewHas('progress', 50);
        $response->assertViewHas('overdueTasks', fn ($tasks) => $tasks->count() === 1);
        $response->assertViewHas('severeRisks', 1);
        $response->assertViewHas('riskCounts', ['Open' => 2, 'Mitigated' => 0, 'Closed' => 1]);
        $response->assertViewHas('criticalIssues', 1);
        $response->assertViewHas('milestones', fn ($milestones) => $milestones->filter->isOverdue()->count() === 1);
    }

    public function test_task_distribution_per_member_includes_unassigned_work(): void
    {
        $this->task(['assigned_to' => $this->teamMember->id, 'status' => TaskStatus::Done]);
        $this->task(['assigned_to' => $this->teamMember->id, 'status' => TaskStatus::ToDo]);
        $this->task(['assigned_to' => null, 'status' => TaskStatus::ToDo]);

        $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project))
            ->assertViewHas('memberStats', function ($stats) {
                $byName = $stats->keyBy('name');

                return $byName["Dian O'Neil"]['counts'] === ['To Do' => 1, 'In Progress' => 0, 'In Review' => 0, 'Done' => 1]
                    && $byName["Dian O'Neil"]['open'] === 1
                    && $byName['Pak Manajer']['total'] === 0
                    && $byName['Belum di-assign']['total'] === 1;
            });
    }

    public function test_names_and_chart_data_are_escaped_inside_html_attributes(): void
    {
        $this->task(['assigned_to' => $this->teamMember->id, 'effort_points' => 5, 'due_date' => '2026-12-01']);

        $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project))
            ->assertSee('title="Dian O&#039;Neil · To Do: 1 task"', false)
            ->assertDontSee("O'Neil", false)
            ->assertSee('data-chart="evm-curve"', false)
            ->assertDontSee("data-chart-data='", false);
    }

    public function test_empty_project_shows_zero_progress_without_charts(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project))
            ->assertOk()
            ->assertViewHas('progress', 0)
            ->assertSee('Belum ada task.')
            ->assertDontSee('data-chart="evm-curve"', false);
    }

    public function test_every_member_role_can_open_the_dashboard_but_outsiders_cannot(): void
    {
        $viewer = User::factory()->create();
        $this->project->members()->attach($viewer, ['role_in_project' => ProjectRole::Viewer->value]);
        $outsider = User::factory()->create()->assignRole('Project Manager');

        $this->actingAs($viewer)->get(route('projects.dashboard', $this->project))->assertOk();
        $this->actingAs($this->teamMember)->get(route('projects.dashboard', $this->project))->assertOk();
        $this->actingAs($outsider)->get(route('projects.dashboard', $this->project))->assertForbidden();
    }

    // ---------------------------------------------------------------- personal dashboard

    public function test_each_global_role_gets_its_own_dashboard(): void
    {
        $viewer = User::factory()->create()->assignRole('Viewer');
        $admin = User::where('email', 'admin@itpms.test')->firstOrFail();

        foreach ([[$admin, 'admin'], [$this->manager, 'manager'], [$this->teamMember, 'member'], [$viewer, 'viewer']] as [$user, $variant]) {
            $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertViewIs("dashboard.home.{$variant}");
        }
    }

    public function test_personal_dashboard_lists_only_my_open_tasks(): void
    {
        $this->task(['title' => 'Task Saya Terlambat', 'assigned_to' => $this->teamMember->id, 'due_date' => '2026-12-01']);
        $this->task(['title' => 'Task Saya Selesai', 'assigned_to' => $this->teamMember->id, 'status' => TaskStatus::Done]);
        $this->task(['title' => 'Task Orang Lain', 'assigned_to' => $this->manager->id]);

        $this->actingAs($this->teamMember)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Task Saya Terlambat')
            ->assertDontSee('Task Saya Selesai')
            ->assertDontSee('Task Orang Lain')
            ->assertViewHas('kpi', fn ($kpi) => $kpi['openTasks'] === 1 && $kpi['overdueTasks'] === 1);
    }

    public function test_team_member_tasks_are_grouped_by_urgency(): void
    {
        foreach (['2026-12-09', '2026-12-10', '2026-12-13', '2027-01-20', null] as $due) {
            $this->task(['assigned_to' => $this->teamMember->id, 'due_date' => $due]);
        }

        $this->actingAs($this->teamMember)
            ->get(route('dashboard'))
            ->assertSee('1 task Anda melewati tenggat')
            ->assertViewHas('buckets', fn ($buckets) => array_map(fn ($tasks) => $tasks->count(), $buckets) === ['overdue' => 1, 'today' => 1, 'week' => 1, 'later' => 2])
            ->assertViewHas('kpi', fn ($kpi) => $kpi['dueSoon'] === 2);
    }

    public function test_project_manager_sees_what_waits_for_their_decision_in_their_projects_only(): void
    {
        ChangeRequest::factory()->for($this->project)->create(['title' => 'CR Menunggu Review', 'requested_by' => $this->teamMember->id]);
        ChangeRequest::factory()->for($this->project)->create(['status' => ChangeRequestStatus::Approved, 'requested_by' => $this->teamMember->id]);
        Issue::factory()->for($this->project)->create(['status' => IssueStatus::Resolved, 'reported_by' => $this->teamMember->id]);
        $this->task(['due_date' => '2026-12-01']);
        ChangeRequest::factory()->create(['title' => 'CR Proyek Lain']);

        $this->actingAs($this->manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('CR Menunggu Review')
            ->assertDontSee('CR Proyek Lain')
            ->assertSee('Ada 3 hal yang menunggu tindakan Anda')
            ->assertViewHas('actions', fn ($actions) => $actions['requests']['count'] === 1 && $actions['issues']['count'] === 1 && $actions['tasks']['count'] === 1)
            ->assertViewHas('kpi', fn ($kpi) => $kpi['projects'] === 1 && $kpi['pendingRequests'] === 1);
    }

    public function test_viewer_sees_followed_projects_and_milestones_due_within_thirty_days(): void
    {
        $viewer = User::factory()->create()->assignRole('Viewer');
        $this->project->members()->attach($viewer, ['role_in_project' => ProjectRole::Viewer->value]);
        Milestone::factory()->for($this->project)->create(['name' => 'Rilis Beta', 'target_date' => '2026-12-20', 'status' => MilestoneStatus::Planned]);
        Milestone::factory()->for($this->project)->create(['name' => 'Rilis Final', 'target_date' => '2027-02-20', 'status' => MilestoneStatus::Planned]);
        $this->task(['status' => TaskStatus::Done]);
        $this->task([]);

        $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($this->project->name)
            ->assertSee('rata-rata progress 50%')
            ->assertViewHas('upcomingMilestones', fn ($milestones) => $milestones->pluck('name')->all() === ['Rilis Beta'])
            ->assertViewHas('kpi', fn ($kpi) => $kpi['projects'] === 1 && $kpi['averageProgress'] === 50);
    }

    public function test_personal_dashboard_only_shows_projects_the_user_belongs_to(): void
    {
        Project::factory()->create(['name' => 'Proyek Rahasia', 'status' => ProjectStatus::InProgress]);

        $this->actingAs($this->teamMember)
            ->get(route('dashboard'))
            ->assertSee($this->project->name)
            ->assertDontSee('Proyek Rahasia');
    }

    public function test_administrator_sees_the_system_overview(): void
    {
        $admin = User::where('email', 'admin@itpms.test')->firstOrFail();
        $this->task(['due_date' => '2026-12-01']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ringkasan sistem')
            ->assertSee($this->project->name)
            ->assertViewHas('projectsByStatus', fn ($counts) => $counts['In Progress'] === 1)
            ->assertViewHas('kpi', fn ($kpi) => $kpi['overdueTasks'] === 1 && $kpi['runningProjects'] === 1);
    }
}
