<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
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

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $manager;

    private User $teamMember;

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
        $this->teamMember = $this->addMember(ProjectRole::TeamMember);
    }

    private function addMember(ProjectRole $role): User
    {
        $user = User::factory()->create()->assignRole('Team Member');
        $this->project->members()->attach($user, ['role_in_project' => $role->value]);

        return $user;
    }

    private function task(array $attributes = []): Task
    {
        return Task::factory()->for($this->project)->create($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Membuat halaman login',
            'description' => 'Form login dengan validasi.',
            'assigned_to' => $this->teamMember->id,
            'priority' => 'High',
            'due_date' => '2026-12-15',
            'milestone_id' => null,
            'effort_points' => '5',
            ...$overrides,
        ];
    }

    private function moveTo(User $user, Task $task, TaskStatus $status)
    {
        return $this->actingAs($user)->patch(
            route('projects.tasks.updateStatus', [$this->project, $task]),
            ['status' => $status->value],
        );
    }

    // ---------------------------------------------------------------- FR-009 create

    public function test_manager_creates_a_task_that_starts_as_to_do(): void
    {
        $milestone = Milestone::factory()->for($this->project)->create(['target_date' => '2026-12-31']);

        $this->actingAs($this->manager)
            ->post(route('projects.tasks.store', $this->project), $this->payload(['milestone_id' => $milestone->id, 'status' => 'Done']))
            ->assertSessionHasNoErrors();

        $task = Task::sole();
        $this->assertSame(TaskStatus::ToDo, $task->status);
        $this->assertSame($this->teamMember->id, $task->assigned_to);
        $this->assertSame($milestone->id, $task->milestone_id);

        $log = ActivityLog::where('entity_type', 'Task')->sole();
        $this->assertSame(ActivityAction::Create, $log->action);
        $this->assertSame($this->teamMember->name, $log->new_values['assigned_to']);
        $this->assertSame($this->project->id, $log->project_id);
    }

    public function test_assignee_must_be_a_manager_or_team_member_of_this_project(): void
    {
        $viewer = $this->addMember(ProjectRole::Viewer);
        $outsider = User::factory()->create();

        foreach ([$viewer, $outsider] as $user) {
            $this->actingAs($this->manager)
                ->post(route('projects.tasks.store', $this->project), $this->payload(['assigned_to' => $user->id]))
                ->assertSessionHasErrors('assigned_to');
        }

        $this->assertSame(0, Task::count());
    }

    public function test_due_date_must_fall_inside_the_project_period(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.tasks.store', $this->project), $this->payload(['due_date' => '2027-02-01']))
            ->assertSessionHasErrors('due_date');

        $this->actingAs($this->manager)
            ->post(route('projects.tasks.store', $this->project), $this->payload(['due_date' => '2026-09-30']))
            ->assertSessionHasErrors('due_date');
    }

    public function test_milestone_must_belong_to_the_same_project(): void
    {
        $foreignMilestone = Milestone::factory()->create();

        $this->actingAs($this->manager)
            ->post(route('projects.tasks.store', $this->project), $this->payload(['milestone_id' => $foreignMilestone->id]))
            ->assertSessionHasErrors('milestone_id');
    }

    public function test_title_is_required_and_between_3_and_200_characters(): void
    {
        foreach (['', 'ab', str_repeat('a', 201)] as $title) {
            $this->actingAs($this->manager)
                ->post(route('projects.tasks.store', $this->project), $this->payload(['title' => $title]))
                ->assertSessionHasErrors('title');
        }
    }

    public function test_team_members_cannot_create_tasks(): void
    {
        $this->actingAs($this->teamMember)
            ->post(route('projects.tasks.store', $this->project), $this->payload())
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- FR-010 view

    public function test_team_member_sees_every_task_in_the_project(): void
    {
        $this->task(['title' => 'Task milik saya', 'assigned_to' => $this->teamMember->id]);
        $this->task(['title' => 'Task milik PM', 'assigned_to' => $this->manager->id]);

        $this->actingAs($this->teamMember)
            ->get(route('projects.tasks.index', $this->project))
            ->assertOk()
            ->assertSee('Task milik saya')
            ->assertSee('Task milik PM');
    }

    public function test_task_list_filters_by_assignee_status_and_overdue(): void
    {
        $this->task(['title' => 'Task Mine Open', 'assigned_to' => $this->teamMember->id]);
        $this->task(['title' => 'Task Other Done', 'assigned_to' => $this->manager->id, 'status' => TaskStatus::Done]);
        $this->task(['title' => 'Task Late', 'due_date' => today()->subDay()]);

        $this->actingAs($this->teamMember)->get(route('projects.tasks.index', [$this->project, 'assignee' => 'me']))
            ->assertSee('Task Mine Open')->assertDontSee('Task Other Done')->assertDontSee('Task Late');

        $this->actingAs($this->teamMember)->get(route('projects.tasks.index', [$this->project, 'status' => 'Done']))
            ->assertSee('Task Other Done')->assertDontSee('Task Mine Open');

        $this->actingAs($this->teamMember)->get(route('projects.tasks.index', [$this->project, 'due' => 'overdue']))
            ->assertSee('Task Late')->assertDontSee('Task Mine Open');
    }

    public function test_non_members_cannot_see_tasks(): void
    {
        $task = $this->task();
        $outsider = User::factory()->create()->assignRole('Team Member');

        $this->actingAs($outsider)->get(route('projects.tasks.index', $this->project))->assertForbidden();
        $this->actingAs($outsider)->get(route('projects.tasks.show', [$this->project, $task]))->assertForbidden();
    }

    public function test_task_of_another_project_cannot_be_reached_through_this_project(): void
    {
        $foreignTask = Task::factory()->create();

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', [$this->project, $foreignTask]))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------- FR-010 update & delete

    public function test_manager_updates_task_details_and_changes_are_logged(): void
    {
        $task = $this->task(['title' => 'Judul lama', 'priority' => 'Low', 'assigned_to' => null, 'due_date' => null]);

        $this->actingAs($this->manager)
            ->put(route('projects.tasks.update', [$this->project, $task]), $this->payload([
                'title' => 'Judul baru',
                'description' => $task->description,
                'priority' => 'Critical',
                'due_date' => '',
                'effort_points' => $task->effort_points,
            ]))
            ->assertRedirect(route('projects.tasks.show', [$this->project, $task]));

        $task->refresh();
        $this->assertSame('Judul baru', $task->title);
        $this->assertSame($this->teamMember->id, $task->assigned_to);

        $log = ActivityLog::where('entity_type', 'Task')->where('action', ActivityAction::Update)->sole();
        $this->assertEquals(['title' => 'Judul lama', 'assigned_to' => null, 'priority' => 'Low'], $log->old_values);
        $this->assertEquals(['title' => 'Judul baru', 'assigned_to' => $this->teamMember->name, 'priority' => 'Critical'], $log->new_values);
    }

    public function test_team_member_cannot_edit_task_details_even_on_their_own_task(): void
    {
        $task = $this->task(['title' => 'Tetap', 'assigned_to' => $this->teamMember->id]);

        $this->actingAs($this->teamMember)
            ->put(route('projects.tasks.update', [$this->project, $task]), $this->payload(['title' => 'Diubah TM']))
            ->assertForbidden();

        $this->assertSame('Tetap', $task->fresh()->title);
    }

    public function test_manager_soft_deletes_a_task(): void
    {
        $task = $this->task();

        $this->actingAs($this->teamMember)->delete(route('projects.tasks.destroy', [$this->project, $task]))->assertForbidden();

        $this->actingAs($this->manager)
            ->delete(route('projects.tasks.destroy', [$this->project, $task]))
            ->assertRedirect(route('projects.tasks.index', $this->project));

        $this->assertSoftDeleted($task);
        $this->assertDatabaseHas('activity_logs', ['entity_id' => $task->id, 'entity_type' => 'Task', 'action' => 'delete']);
    }

    // ---------------------------------------------------------------- FR-011 workflow

    public function test_team_member_moves_their_own_task_through_the_workflow(): void
    {
        $task = $this->task(['assigned_to' => $this->teamMember->id]);

        foreach ([TaskStatus::InProgress, TaskStatus::InReview, TaskStatus::Done] as $status) {
            $this->moveTo($this->teamMember, $task, $status)->assertSessionHasNoErrors();
            $this->assertSame($status, $task->fresh()->status);
        }

        $this->assertNotNull($task->fresh()->completed_at);

        $logs = ActivityLog::where('entity_id', $task->id)->where('action', ActivityAction::StatusChange)->orderBy('id')->get();
        $this->assertSame(
            [['status' => 'To Do'], ['status' => 'In Progress'], ['status' => 'In Review']],
            $logs->pluck('old_values')->all(),
        );
        $this->assertSame($this->teamMember->id, $logs->first()->user_id);
    }

    public function test_team_member_cannot_move_someone_elses_task(): void
    {
        $task = $this->task(['assigned_to' => $this->manager->id]);

        $this->moveTo($this->teamMember, $task, TaskStatus::InProgress)->assertForbidden();

        $this->assertSame(TaskStatus::ToDo, $task->fresh()->status);
    }

    public function test_transitions_outside_the_workflow_are_rejected(): void
    {
        $task = $this->task(['assigned_to' => $this->teamMember->id]);

        $this->moveTo($this->manager, $task, TaskStatus::Done)->assertSessionHasErrors('status');
        $this->moveTo($this->manager, $task, TaskStatus::InReview)->assertSessionHasErrors('status');

        $this->assertSame(TaskStatus::ToDo, $task->fresh()->status);
    }

    public function test_rollback_and_revision_transitions_are_allowed(): void
    {
        $task = $this->task(['assigned_to' => $this->teamMember->id, 'status' => TaskStatus::InReview]);

        $this->moveTo($this->teamMember, $task, TaskStatus::InProgress)->assertSessionHasNoErrors();
        $this->moveTo($this->teamMember, $task, TaskStatus::ToDo)->assertSessionHasNoErrors();

        $this->assertSame(TaskStatus::ToDo, $task->fresh()->status);
    }

    public function test_only_the_manager_can_reopen_a_done_task(): void
    {
        $task = $this->task(['assigned_to' => $this->teamMember->id, 'status' => TaskStatus::Done]);

        $this->moveTo($this->teamMember, $task, TaskStatus::InProgress)
            ->assertSessionHasErrors(['status' => 'Hanya Project Manager yang dapat membuka kembali task yang sudah Done.']);
        $this->assertSame(TaskStatus::Done, $task->fresh()->status);

        $this->moveTo($this->manager, $task, TaskStatus::InProgress)->assertSessionHasNoErrors();
        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_viewers_cannot_change_status(): void
    {
        $viewer = $this->addMember(ProjectRole::Viewer);
        $task = $this->task();

        $this->moveTo($viewer, $task, TaskStatus::InProgress)->assertForbidden();
    }

    public function test_status_endpoint_answers_json_for_the_kanban_board(): void
    {
        $task = $this->task(['assigned_to' => $this->teamMember->id]);

        $this->actingAs($this->teamMember)
            ->patchJson(route('projects.tasks.updateStatus', [$this->project, $task]), ['status' => 'In Progress'])
            ->assertOk()
            ->assertJson(['status' => 'In Progress']);

        $this->actingAs($this->teamMember)
            ->patchJson(route('projects.tasks.updateStatus', [$this->project, $task]), ['status' => 'Done'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_tasks_of_a_closed_project_are_read_only(): void
    {
        $task = $this->task(['assigned_to' => $this->teamMember->id]);
        $this->project->update(['status' => ProjectStatus::Closed, 'closed_at' => now()]);

        $this->moveTo($this->teamMember, $task, TaskStatus::InProgress)->assertForbidden();
        $this->moveTo($this->manager, $task, TaskStatus::InProgress)->assertForbidden();
        $this->actingAs($this->manager)->post(route('projects.tasks.store', $this->project), $this->payload())->assertForbidden();

        $this->actingAs($this->teamMember)->get(route('projects.tasks.show', [$this->project, $task]))->assertOk();
    }

    public function test_task_page_offers_only_the_transitions_the_user_may_perform(): void
    {
        $task = $this->task(['assigned_to' => $this->teamMember->id, 'status' => TaskStatus::Done]);

        $this->actingAs($this->teamMember)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertDontSee('Buka kembali')
            ->assertSee('Hanya Project Manager yang dapat membukanya kembali.');

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertSee('Buka kembali');
    }
}
