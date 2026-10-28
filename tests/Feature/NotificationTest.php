<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskStatusChanged;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
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

        $this->teamMember = User::factory()->create()->assignRole('Team Member');
        $this->project->members()->attach($this->teamMember, ['role_in_project' => ProjectRole::TeamMember->value]);
    }

    private function createTask(array $overrides = [])
    {
        return $this->actingAs($this->manager)->post(route('projects.tasks.store', $this->project), [
            'title' => 'Membuat laporan',
            'assigned_to' => $this->teamMember->id,
            'priority' => 'Medium',
            ...$overrides,
        ]);
    }

    // ---------------------------------------------------------------- sending

    public function test_assignee_is_notified_when_a_task_is_assigned_to_them(): void
    {
        Notification::fake();

        $this->createTask();

        Notification::assertSentTo($this->teamMember, TaskAssigned::class, function (TaskAssigned $notification) {
            $data = $notification->toArray($this->teamMember);

            return str_contains($data['message'], 'Membuat laporan')
                && $data['url'] === route('projects.tasks.show', [$this->project, Task::sole()]);
        });
        Notification::assertNotSentTo($this->manager, TaskAssigned::class);
    }

    public function test_nobody_is_notified_when_assigning_a_task_to_yourself(): void
    {
        Notification::fake();

        $this->createTask(['assigned_to' => $this->manager->id]);

        Notification::assertNothingSent();
    }

    public function test_reassigning_a_task_notifies_only_the_new_assignee(): void
    {
        $newAssignee = User::factory()->create()->assignRole('Team Member');
        $this->project->members()->attach($newAssignee, ['role_in_project' => ProjectRole::TeamMember->value]);
        $task = Task::factory()->for($this->project)->create(['assigned_to' => $this->teamMember->id, 'due_date' => null]);

        Notification::fake();

        $this->actingAs($this->manager)->put(route('projects.tasks.update', [$this->project, $task]), [
            'title' => $task->title,
            'assigned_to' => $newAssignee->id,
            'priority' => $task->priority->value,
        ]);

        Notification::assertSentTo($newAssignee, TaskAssigned::class);
        Notification::assertNotSentTo($this->teamMember, TaskAssigned::class);
    }

    public function test_status_change_by_the_assignee_notifies_the_manager_but_not_the_actor(): void
    {
        $task = Task::factory()->for($this->project)->create(['assigned_to' => $this->teamMember->id]);

        Notification::fake();

        $this->actingAs($this->teamMember)->patch(route('projects.tasks.updateStatus', [$this->project, $task]), ['status' => 'In Progress']);

        Notification::assertSentTo($this->manager, TaskStatusChanged::class);
        Notification::assertNotSentTo($this->teamMember, TaskStatusChanged::class);
    }

    public function test_status_change_by_the_manager_notifies_the_assignee(): void
    {
        $task = Task::factory()->for($this->project)->create(['assigned_to' => $this->teamMember->id]);

        Notification::fake();

        $this->actingAs($this->manager)->patch(route('projects.tasks.updateStatus', [$this->project, $task]), ['status' => 'In Progress']);

        Notification::assertSentTo($this->teamMember, TaskStatusChanged::class);
        Notification::assertNotSentTo($this->manager, TaskStatusChanged::class);
    }

    public function test_inactive_users_are_not_notified(): void
    {
        $this->teamMember->update(['is_active' => false]);

        Notification::fake();

        $this->createTask();

        Notification::assertNothingSent();
    }

    // ---------------------------------------------------------------- reading

    public function test_unread_badge_list_and_mark_as_read(): void
    {
        $this->createTask();
        $notification = $this->teamMember->unreadNotifications()->sole();

        $this->actingAs($this->teamMember)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Task baru untuk Anda')
            ->assertSee('Belum dibaca (1)');

        $this->actingAs($this->teamMember)
            ->patch(route('notifications.read', $notification->id))
            ->assertRedirect(route('projects.tasks.show', [$this->project, Task::sole()]));

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame(0, $this->teamMember->unreadNotifications()->count());
    }

    public function test_mark_all_as_read(): void
    {
        $this->createTask(['title' => 'Task satu']);
        $this->createTask(['title' => 'Task dua']);

        $this->actingAs($this->teamMember)->post(route('notifications.read-all'))->assertSessionHas('success');

        $this->assertSame(0, $this->teamMember->unreadNotifications()->count());
    }

    public function test_users_cannot_read_someone_elses_notifications(): void
    {
        $this->createTask();
        $notification = $this->teamMember->notifications()->sole();

        $this->actingAs($this->manager)
            ->patch(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }
}
