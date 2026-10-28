<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneManagementTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->manager = User::factory()->create()->assignRole('Project Manager');
        $this->project = Project::factory()->create([
            'created_by' => $this->manager->id,
            'status' => ProjectStatus::InProgress,
            'start_date' => '2026-10-01',
            'end_date' => '2027-01-31',
        ]);
        $this->project->members()->attach($this->manager, ['role_in_project' => ProjectRole::ProjectManager->value]);
    }

    private function member(ProjectRole $role): User
    {
        $user = User::factory()->create()->assignRole('Team Member');
        $this->project->members()->attach($user, ['role_in_project' => $role->value]);

        return $user;
    }

    private function milestone(array $attributes = []): Milestone
    {
        return Milestone::factory()->for($this->project)->create(['target_date' => '2026-12-01', ...$attributes]);
    }

    private function update(Milestone $milestone, array $overrides = [])
    {
        return $this->actingAs($this->manager)->put(route('projects.milestones.update', [$this->project, $milestone]), [
            'name' => $milestone->name,
            'description' => $milestone->description,
            'target_date' => $milestone->target_date->format('Y-m-d'),
            'status' => $milestone->status->value,
            ...$overrides,
        ]);
    }

    // ---------------------------------------------------------------- create

    public function test_manager_creates_a_planned_milestone(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.milestones.store', $this->project), [
                'name' => 'Rilis Beta',
                'description' => 'Versi beta untuk UAT.',
                'target_date' => '2026-12-15',
                'status' => 'Completed',
            ])
            ->assertSessionHasNoErrors();

        $milestone = Milestone::sole();
        $this->assertSame(MilestoneStatus::Planned, $milestone->status);
        $this->assertDatabaseHas('activity_logs', ['entity_type' => 'Milestone', 'entity_id' => $milestone->id, 'action' => 'create']);
    }

    public function test_target_date_must_be_inside_the_project_period(): void
    {
        foreach (['2026-09-30', '2027-02-01', ''] as $date) {
            $this->actingAs($this->manager)
                ->post(route('projects.milestones.store', $this->project), ['name' => 'Rilis', 'target_date' => $date])
                ->assertSessionHasErrors('target_date');
        }

        $this->assertSame(0, Milestone::count());
    }

    public function test_team_members_and_viewers_can_view_but_not_manage_milestones(): void
    {
        $milestone = $this->milestone(['name' => 'Rilis Alpha']);

        foreach ([ProjectRole::TeamMember, ProjectRole::Viewer] as $role) {
            $user = $this->member($role);

            $this->actingAs($user)->get(route('projects.milestones.index', $this->project))->assertOk()->assertSee('Rilis Alpha');
            $this->actingAs($user)->get(route('projects.milestones.show', [$this->project, $milestone]))->assertOk();
            $this->actingAs($user)
                ->post(route('projects.milestones.store', $this->project), ['name' => 'Baru', 'target_date' => '2026-12-01'])
                ->assertForbidden();
            $this->actingAs($user)
                ->delete(route('projects.milestones.destroy', [$this->project, $milestone]))
                ->assertForbidden();
        }
    }

    // ---------------------------------------------------------------- status workflow

    public function test_milestone_follows_the_status_flow_and_changes_are_logged(): void
    {
        $milestone = $this->milestone();

        $this->update($milestone, ['status' => 'In Progress'])->assertSessionHasNoErrors();
        $this->update($milestone->fresh(), ['status' => 'Completed'])->assertSessionHasNoErrors();

        $this->assertSame(MilestoneStatus::Completed, $milestone->fresh()->status);

        $logs = ActivityLog::where('entity_type', 'Milestone')->where('action', ActivityAction::StatusChange)->orderBy('id')->get();
        $this->assertSame([['status' => 'In Progress'], ['status' => 'Completed']], $logs->pluck('new_values')->all());
    }

    public function test_planned_milestone_cannot_jump_straight_to_completed(): void
    {
        $milestone = $this->milestone();

        $this->update($milestone, ['status' => 'Completed'])->assertSessionHasErrors('status');

        $this->assertSame(MilestoneStatus::Planned, $milestone->fresh()->status);
    }

    public function test_leaving_completed_requires_explicit_confirmation(): void
    {
        $milestone = $this->milestone(['status' => MilestoneStatus::Completed]);

        $this->update($milestone, ['status' => 'Planned'])->assertSessionHasErrors('confirm_reopen');
        $this->assertSame(MilestoneStatus::Completed, $milestone->fresh()->status);

        $this->update($milestone, ['status' => 'Planned', 'confirm_reopen' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(MilestoneStatus::Planned, $milestone->fresh()->status);
    }

    public function test_editing_a_completed_milestone_without_changing_status_needs_no_confirmation(): void
    {
        $milestone = $this->milestone(['status' => MilestoneStatus::Completed]);

        $this->update($milestone, ['name' => 'Rilis Final'])->assertSessionHasNoErrors();

        $this->assertSame('Rilis Final', $milestone->fresh()->name);
    }

    // ---------------------------------------------------------------- tasks & suggestion

    public function test_detail_page_lists_linked_tasks_with_a_status_summary(): void
    {
        $milestone = $this->milestone(['status' => MilestoneStatus::InProgress]);
        Task::factory()->for($this->project)->create(['milestone_id' => $milestone->id, 'title' => 'Desain database', 'status' => TaskStatus::Done]);
        Task::factory()->for($this->project)->create(['milestone_id' => $milestone->id, 'title' => 'Implementasi API']);

        $this->actingAs($this->manager)
            ->get(route('projects.milestones.show', [$this->project, $milestone]))
            ->assertOk()
            ->assertSee('Desain database')
            ->assertSee('Implementasi API')
            ->assertSee('50%')
            ->assertDontSee('Tandai Completed');
    }

    public function test_completion_is_suggested_once_every_linked_task_is_done(): void
    {
        $milestone = $this->milestone(['status' => MilestoneStatus::InProgress]);
        Task::factory()->for($this->project)->count(2)->create(['milestone_id' => $milestone->id, 'status' => TaskStatus::Done]);

        $this->actingAs($this->manager)
            ->get(route('projects.milestones.show', [$this->project, $milestone]))
            ->assertSee('Tandai Completed');
    }

    public function test_deleting_a_milestone_detaches_its_tasks(): void
    {
        $milestone = $this->milestone();
        $task = Task::factory()->for($this->project)->create(['milestone_id' => $milestone->id]);

        $this->actingAs($this->manager)
            ->delete(route('projects.milestones.destroy', [$this->project, $milestone]))
            ->assertRedirect(route('projects.milestones.index', $this->project));

        $this->assertSoftDeleted($milestone);
        $this->assertNull($task->fresh()->milestone_id);
    }

    // ---------------------------------------------------------------- overdue → Missed

    public function test_overdue_open_milestones_are_marked_missed_by_the_scheduled_command(): void
    {
        $this->travelTo('2026-12-10');

        $overduePlanned = $this->milestone(['target_date' => '2026-12-01']);
        $overdueInProgress = $this->milestone(['target_date' => '2026-12-09', 'status' => MilestoneStatus::InProgress]);
        $dueToday = $this->milestone(['target_date' => '2026-12-10']);
        $completed = $this->milestone(['target_date' => '2026-11-01', 'status' => MilestoneStatus::Completed]);

        $this->artisan('milestones:mark-missed')->expectsOutput('2 milestone ditandai Missed.')->assertSuccessful();

        $this->assertSame(MilestoneStatus::Missed, $overduePlanned->fresh()->status);
        $this->assertSame(MilestoneStatus::Missed, $overdueInProgress->fresh()->status);
        $this->assertSame(MilestoneStatus::Planned, $dueToday->fresh()->status);
        $this->assertSame(MilestoneStatus::Completed, $completed->fresh()->status);

        $log = ActivityLog::where('entity_id', $overduePlanned->id)->where('action', ActivityAction::StatusChange)->sole();
        $this->assertNull($log->user_id);
        $this->assertSame('System', $log->user_name);
    }

    public function test_milestones_of_closed_projects_are_left_untouched(): void
    {
        $this->travelTo('2026-12-10');
        $milestone = $this->milestone(['target_date' => '2026-12-01']);
        $this->project->update(['status' => ProjectStatus::Closed, 'closed_at' => now()]);

        $this->artisan('milestones:mark-missed')->assertSuccessful();

        $this->assertSame(MilestoneStatus::Planned, $milestone->fresh()->status);
    }
}
