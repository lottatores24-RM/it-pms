<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Issue;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectMemberTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->manager = User::factory()->create()->assignRole('Project Manager');
        $this->project = Project::factory()->create(['created_by' => $this->manager->id]);
        $this->project->members()->attach($this->manager, ['role_in_project' => ProjectRole::ProjectManager->value]);
    }

    private function addMember(ProjectRole $role): ProjectMember
    {
        $user = User::factory()->create()->assignRole('Team Member');

        return $this->project->projectMembers()->create(['user_id' => $user->id, 'role_in_project' => $role]);
    }

    private function membership(User $user): ?ProjectMember
    {
        return $this->project->projectMembers()->where('user_id', $user->id)->first();
    }

    // ---------------------------------------------------------------- list

    public function test_members_page_shows_role_and_active_task_count(): void
    {
        $member = $this->addMember(ProjectRole::TeamMember);
        Task::factory()->for($this->project)->count(2)->create(['assigned_to' => $member->user_id]);
        Task::factory()->for($this->project)->status(TaskStatus::Done)->create(['assigned_to' => $member->user_id]);

        $this->actingAs($this->manager)
            ->get(route('projects.members.index', $this->project))
            ->assertOk()
            ->assertSee($member->user->name)
            ->assertViewHas('activeTaskCounts', fn ($counts) => (int) $counts[$member->user_id] === 2);
    }

    public function test_viewers_can_see_the_member_list_but_not_manage_it(): void
    {
        $viewer = $this->addMember(ProjectRole::Viewer);

        $this->actingAs($viewer->user)
            ->get(route('projects.members.index', $this->project))
            ->assertOk()
            ->assertDontSee('Tambah Anggota');
    }

    public function test_non_members_cannot_see_the_member_list(): void
    {
        $outsider = User::factory()->create()->assignRole('Project Manager');

        $this->actingAs($outsider)->get(route('projects.members.index', $this->project))->assertForbidden();
    }

    // ---------------------------------------------------------------- add

    public function test_manager_adds_an_active_user_with_a_project_role(): void
    {
        $user = User::factory()->create()->assignRole('Viewer');

        $this->actingAs($this->manager)
            ->post(route('projects.members.store', $this->project), [
                'user_id' => $user->id,
                'role_in_project' => ProjectRole::TeamMember->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProjectRole::TeamMember, $user->projectRole($this->project));

        $log = ActivityLog::where('entity_type', 'ProjectMember')->sole();
        $this->assertSame(ActivityAction::Create, $log->action);
        $this->assertSame($user->name, $log->entity_name);
        $this->assertSame($this->project->id, $log->project_id);
    }

    public function test_duplicate_and_inactive_users_cannot_be_added(): void
    {
        $existing = $this->addMember(ProjectRole::TeamMember);
        $inactive = User::factory()->inactive()->create();

        $this->actingAs($this->manager)
            ->post(route('projects.members.store', $this->project), ['user_id' => $existing->user_id, 'role_in_project' => 'Viewer'])
            ->assertSessionHasErrors(['user_id' => 'User ini sudah menjadi anggota proyek.']);

        $this->actingAs($this->manager)
            ->post(route('projects.members.store', $this->project), ['user_id' => $inactive->id, 'role_in_project' => 'Viewer'])
            ->assertSessionHasErrors('user_id');

        $this->assertNull($this->membership($inactive));
        $this->assertSame(ProjectRole::TeamMember, $existing->fresh()->role_in_project);
    }

    public function test_team_members_cannot_add_members(): void
    {
        $teamMember = $this->addMember(ProjectRole::TeamMember);
        $user = User::factory()->create();

        $this->actingAs($teamMember->user)
            ->post(route('projects.members.store', $this->project), ['user_id' => $user->id, 'role_in_project' => 'Project Manager'])
            ->assertForbidden();

        $this->assertNull($this->membership($user));
    }

    // ---------------------------------------------------------------- change role

    public function test_manager_changes_a_member_role_without_removing_them(): void
    {
        $member = $this->addMember(ProjectRole::TeamMember);

        $this->actingAs($this->manager)
            ->patch(route('projects.members.update', [$this->project, $member]), ['role_in_project' => 'Viewer'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProjectRole::Viewer, $member->fresh()->role_in_project);
        $this->assertSame($member->id, $member->fresh()->id);

        $log = ActivityLog::where('entity_type', 'ProjectMember')->where('action', ActivityAction::Update)->sole();
        $this->assertSame(['role_in_project' => 'Team Member'], $log->old_values);
        $this->assertSame(['role_in_project' => 'Viewer'], $log->new_values);
    }

    public function test_the_last_project_manager_cannot_be_demoted(): void
    {
        $managerMembership = $this->membership($this->manager);

        $this->actingAs($this->manager)
            ->patch(route('projects.members.update', [$this->project, $managerMembership]), ['role_in_project' => 'Team Member'])
            ->assertSessionHasErrorsIn('updateMember', 'role_in_project');

        $this->assertSame(ProjectRole::ProjectManager, $managerMembership->fresh()->role_in_project);
    }

    public function test_manager_can_step_down_once_another_manager_exists(): void
    {
        $this->addMember(ProjectRole::ProjectManager);
        $managerMembership = $this->membership($this->manager);

        $this->actingAs($this->manager)
            ->patch(route('projects.members.update', [$this->project, $managerMembership]), ['role_in_project' => 'Team Member'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProjectRole::TeamMember, $managerMembership->fresh()->role_in_project);
    }

    public function test_a_membership_of_another_project_cannot_be_reached_through_this_project(): void
    {
        $otherProject = Project::factory()->create();
        $foreign = $otherProject->projectMembers()->create(['user_id' => User::factory()->create()->id, 'role_in_project' => ProjectRole::TeamMember]);

        $this->actingAs($this->manager)
            ->patch(route('projects.members.update', [$this->project, $foreign]), ['role_in_project' => 'Viewer'])
            ->assertNotFound();
    }

    // ---------------------------------------------------------------- remove

    public function test_removing_a_member_unassigns_their_work_in_this_project_only(): void
    {
        $member = $this->addMember(ProjectRole::TeamMember);
        $task = Task::factory()->for($this->project)->create(['assigned_to' => $member->user_id]);
        $issue = Issue::factory()->for($this->project)->create(['assigned_to' => $member->user_id]);
        $risk = Risk::factory()->for($this->project)->create(['owner_id' => $member->user_id]);
        $otherProjectTask = Task::factory()->create(['assigned_to' => $member->user_id]);

        $this->actingAs($this->manager)
            ->delete(route('projects.members.destroy', [$this->project, $member]))
            ->assertSessionHas('success');

        $this->assertNull($this->membership($member->user));
        $this->assertNull($task->fresh()->assigned_to);
        $this->assertNull($issue->fresh()->assigned_to);
        $this->assertNull($risk->fresh()->owner_id);
        $this->assertSame($member->user_id, $otherProjectTask->fresh()->assigned_to);

        $log = ActivityLog::where('entity_type', 'ProjectMember')->where('action', ActivityAction::Delete)->sole();
        $this->assertStringContainsString('1 task menjadi unassigned', $log->description);
    }

    public function test_the_last_project_manager_cannot_be_removed(): void
    {
        $this->actingAs($this->manager)
            ->delete(route('projects.members.destroy', [$this->project, $this->membership($this->manager)]))
            ->assertSessionHas('error');

        $this->assertNotNull($this->membership($this->manager));
    }

    public function test_members_cannot_be_changed_in_a_closed_project(): void
    {
        $member = $this->addMember(ProjectRole::TeamMember);
        $this->project->update(['status' => ProjectStatus::Closed, 'closed_at' => now()]);

        $this->actingAs($this->manager)
            ->delete(route('projects.members.destroy', [$this->project, $member]))
            ->assertForbidden();
        $this->actingAs($this->manager)
            ->post(route('projects.members.store', $this->project), ['user_id' => User::factory()->create()->id, 'role_in_project' => 'Viewer'])
            ->assertForbidden();

        $this->assertNotNull($member->fresh());
    }

    public function test_removed_member_loses_access_to_the_project(): void
    {
        $member = $this->addMember(ProjectRole::TeamMember);

        $this->actingAs($this->manager)->delete(route('projects.members.destroy', [$this->project, $member]));

        $this->actingAs($member->user->fresh())->get(route('projects.show', $this->project))->assertForbidden();
    }
}
