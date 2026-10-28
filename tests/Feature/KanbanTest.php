<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskStatusChanged;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KanbanTest extends TestCase
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
        $this->project = Project::factory()->create(['created_by' => $this->manager->id, 'status' => ProjectStatus::InProgress]);
        $this->project->members()->attach($this->manager, ['role_in_project' => ProjectRole::ProjectManager->value]);

        $this->teamMember = User::factory()->create()->assignRole('Team Member');
        $this->project->members()->attach($this->teamMember, ['role_in_project' => ProjectRole::TeamMember->value]);
    }

    private function task(array $attributes = []): Task
    {
        return Task::factory()->for($this->project)->create($attributes);
    }

    private function move(User $user, Task $task, TaskStatus $status)
    {
        return $this->actingAs($user)->patchJson(
            route('projects.kanban.move', [$this->project, $task]),
            ['status' => $status->value],
        );
    }

    // ---------------------------------------------------------------- board

    public function test_board_shows_tasks_in_their_status_columns(): void
    {
        $this->task(['title' => 'Kartu To Do']);
        $this->task(['title' => 'Kartu Review', 'status' => TaskStatus::InReview]);

        $this->actingAs($this->manager)
            ->get(route('projects.kanban', $this->project))
            ->assertOk()
            ->assertViewHas('columns', fn ($columns) => $columns['To Do']->pluck('title')->all() === ['Kartu To Do']
                && $columns['In Review']->pluck('title')->all() === ['Kartu Review']
                && $columns['Done']->isEmpty());
    }

    public function test_team_member_can_only_drag_their_own_cards(): void
    {
        $own = $this->task(['assigned_to' => $this->teamMember->id]);
        $others = $this->task(['assigned_to' => $this->manager->id]);

        $this->actingAs($this->teamMember)
            ->get(route('projects.kanban', $this->project))
            ->assertOk()
            ->assertViewHas('transitions', fn ($transitions) => $transitions[$own->id] === ['In Progress']
                && $transitions[$others->id] === []);
    }

    public function test_viewers_see_the_board_but_cannot_move_cards(): void
    {
        $viewer = User::factory()->create();
        $this->project->members()->attach($viewer, ['role_in_project' => ProjectRole::Viewer->value]);
        $task = $this->task();

        $this->actingAs($viewer)->get(route('projects.kanban', $this->project))->assertOk();
        $this->move($viewer, $task, TaskStatus::InProgress)->assertForbidden();
    }

    public function test_non_members_cannot_open_the_board(): void
    {
        $outsider = User::factory()->create()->assignRole('Project Manager');

        $this->actingAs($outsider)->get(route('projects.kanban', $this->project))->assertForbidden();
    }

    public function test_board_filters_by_assignee_and_priority(): void
    {
        $this->task(['title' => 'Kartu Kritis Saya', 'priority' => TaskPriority::Critical, 'assigned_to' => $this->teamMember->id]);
        $this->task(['title' => 'Kartu Low Saya', 'priority' => TaskPriority::Low, 'assigned_to' => $this->teamMember->id]);
        $this->task(['title' => 'Kartu Kritis PM', 'priority' => TaskPriority::Critical, 'assigned_to' => $this->manager->id]);

        $this->actingAs($this->teamMember)
            ->get(route('projects.kanban', [$this->project, 'assignee' => 'me', 'priority' => 'Critical']))
            ->assertSee('Kartu Kritis Saya')
            ->assertDontSee('Kartu Low Saya')
            ->assertDontSee('Kartu Kritis PM');
    }

    // ---------------------------------------------------------------- moving

    public function test_team_member_drags_their_own_card_to_the_next_column(): void
    {
        $task = $this->task(['assigned_to' => $this->teamMember->id]);

        Notification::fake();

        $this->move($this->teamMember, $task, TaskStatus::InProgress)
            ->assertOk()
            ->assertJson(['status' => 'In Progress', 'transitions' => ['In Review', 'To Do']]);

        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => 'Task',
            'entity_id' => $task->id,
            'action' => ActivityAction::StatusChange->value,
            'user_id' => $this->teamMember->id,
        ]);
        Notification::assertSentTo($this->manager, TaskStatusChanged::class);
    }

    public function test_team_member_cannot_drag_someone_elses_card(): void
    {
        $task = $this->task(['assigned_to' => $this->manager->id]);

        $this->move($this->teamMember, $task, TaskStatus::InProgress)->assertForbidden();

        $this->assertSame(TaskStatus::ToDo, $task->fresh()->status);
    }

    public function test_manager_can_drag_any_card(): void
    {
        $task = $this->task(['assigned_to' => $this->teamMember->id, 'status' => TaskStatus::InReview]);

        $this->move($this->manager, $task, TaskStatus::Done)->assertOk();

        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
    }

    public function test_invalid_transition_returns_a_validation_error(): void
    {
        $task = $this->task(['assigned_to' => $this->teamMember->id]);

        $this->move($this->teamMember, $task, TaskStatus::Done)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'Perpindahan status dari To Do ke Done tidak diizinkan.']);

        $this->assertSame(TaskStatus::ToDo, $task->fresh()->status);
        $this->assertSame(0, ActivityLog::where('entity_type', 'Task')->count());
    }

    public function test_only_the_manager_can_drag_a_done_card_back(): void
    {
        $task = $this->task(['assigned_to' => $this->teamMember->id, 'status' => TaskStatus::Done]);

        $this->move($this->teamMember, $task, TaskStatus::InProgress)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->move($this->manager, $task, TaskStatus::InProgress)->assertOk();
        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_kanban_permissions_from_the_role_screen_are_respected(): void
    {
        Role::findByName('Team Member')->revokePermissionTo('kanban.moveOwnTask');
        $task = $this->task(['assigned_to' => $this->teamMember->id]);

        $this->move($this->teamMember, $task, TaskStatus::InProgress)->assertForbidden();
        $this->assertSame(TaskStatus::ToDo, $task->fresh()->status);
    }

    public function test_cards_of_a_closed_project_cannot_be_moved(): void
    {
        $task = $this->task(['assigned_to' => $this->teamMember->id]);
        $this->project->update(['status' => ProjectStatus::Closed, 'closed_at' => now()]);

        $this->move($this->manager, $task, TaskStatus::InProgress)->assertForbidden();
        $this->actingAs($this->manager)->get(route('projects.kanban', $this->project))->assertOk();
    }
}
