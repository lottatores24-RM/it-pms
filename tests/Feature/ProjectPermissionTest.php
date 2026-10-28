<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    private function memberOf(Project $project, ProjectRole $role): User
    {
        $user = User::factory()->create();
        $project->members()->attach($user, ['role_in_project' => $role->value]);

        return $user;
    }

    public function test_project_role_drives_permissions_inside_the_project(): void
    {
        $project = Project::factory()->create();

        $manager = $this->memberOf($project, ProjectRole::ProjectManager);
        $teamMember = $this->memberOf($project, ProjectRole::TeamMember);
        $viewer = $this->memberOf($project, ProjectRole::Viewer);

        $this->assertTrue($manager->hasProjectPermission($project, 'task.create'));
        $this->assertFalse($teamMember->hasProjectPermission($project, 'task.create'));
        $this->assertTrue($teamMember->hasProjectPermission($project, 'task.updateStatus'));
        $this->assertTrue($viewer->hasProjectPermission($project, 'task.view'));
        $this->assertFalse($viewer->hasProjectPermission($project, 'task.updateStatus'));
    }

    public function test_project_role_overrides_the_global_role(): void
    {
        $project = Project::factory()->create();
        $globalManager = $this->memberOf($project, ProjectRole::Viewer);
        $globalManager->assignRole('Project Manager');

        $this->assertFalse($globalManager->hasProjectPermission($project, 'task.create'));
    }

    public function test_non_member_has_no_permission_in_the_project(): void
    {
        $project = Project::factory()->create();
        $outsider = User::factory()->create();
        $outsider->assignRole('Project Manager');

        $this->assertFalse($outsider->hasProjectPermission($project, 'project.view'));
    }

    public function test_administrator_has_permission_in_any_project_without_membership(): void
    {
        $project = Project::factory()->create();
        $admin = User::where('email', 'admin@itpms.test')->firstOrFail();

        $this->assertTrue($admin->hasProjectPermission($project, 'project.reopen'));
    }

    public function test_permission_changes_on_a_role_apply_inside_projects(): void
    {
        $project = Project::factory()->create();
        $teamMember = $this->memberOf($project, ProjectRole::TeamMember);

        Role::findByName('Team Member')->revokePermissionTo('issue.create');

        $this->assertFalse($teamMember->hasProjectPermission($project, 'issue.create'));
    }

    public function test_seeded_default_accounts_have_their_global_roles(): void
    {
        $this->assertTrue(User::where('email', 'admin@itpms.test')->firstOrFail()->hasRole('Administrator'));
        $this->assertTrue(User::where('email', 'riki@itpms.test')->firstOrFail()->hasRole('Project Manager'));
        $this->assertTrue(User::where('email', 'mirco@itpms.test')->firstOrFail()->hasRole('Team Member'));
    }

    public function test_only_administrators_get_admin_only_permissions(): void
    {
        foreach (['Project Manager', 'Team Member', 'Viewer'] as $role) {
            $this->assertFalse(Role::findByName($role)->hasPermissionTo('user.create'), "{$role} must not manage users");
            $this->assertFalse(Role::findByName($role)->hasPermissionTo('project.reopen'), "{$role} must not reopen projects");
        }
    }
}
