<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityAction;
use App\Enums\ProjectRole;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@itpms.test')->firstOrFail();
    }

    private function role(string $name): Role
    {
        return Role::findByName($name, 'web');
    }

    public function test_non_admin_users_are_forbidden(): void
    {
        $manager = User::where('email', 'riki@itpms.test')->firstOrFail();
        $teamMember = $this->role('Team Member');

        $this->actingAs($manager)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.roles.edit', $teamMember))->assertForbidden();
        $this->actingAs($manager)
            ->put(route('admin.roles.update', $teamMember), ['permissions' => ['user.create']])
            ->assertForbidden();

        $this->assertFalse($teamMember->fresh()->hasPermissionTo('user.create'));
    }

    public function test_admin_sees_every_role_with_its_permission_count(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.roles.index'))->assertOk();

        foreach (['Administrator', 'Project Manager', 'Team Member', 'Viewer'] as $name) {
            $response->assertSee($name);
        }

        $response->assertSee(Permission::count().' dari '.Permission::count().' permission');
    }

    public function test_admin_can_grant_and_revoke_permissions_and_the_change_is_logged(): void
    {
        $teamMember = $this->role('Team Member');
        $permissions = $teamMember->permissions()->pluck('name')
            ->reject(fn (string $name) => $name === 'issue.create')
            ->push('task.create')
            ->values()
            ->all();

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', $teamMember), ['permissions' => $permissions])
            ->assertRedirect(route('admin.roles.edit', $teamMember))
            ->assertSessionHasNoErrors();

        $teamMember = $teamMember->fresh();
        $this->assertTrue($teamMember->hasPermissionTo('task.create'));
        $this->assertFalse($teamMember->hasPermissionTo('issue.create'));

        $log = ActivityLog::where('entity_type', 'Role')->where('entity_id', $teamMember->id)->sole();
        $this->assertSame(ActivityAction::Update, $log->action);
        $this->assertSame(['added' => ['task.create']], $log->new_values);
        $this->assertSame(['removed' => ['issue.create']], $log->old_values);
    }

    public function test_changed_permissions_apply_to_project_members_with_that_role(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();
        $project->members()->attach($member, ['role_in_project' => ProjectRole::Viewer->value]);

        $this->assertFalse($member->hasProjectPermission($project, 'issue.create'));

        $viewer = $this->role('Viewer');
        $this->actingAs($this->admin)->put(route('admin.roles.update', $viewer), [
            'permissions' => [...$viewer->permissions()->pluck('name')->all(), 'issue.create'],
        ]);

        $this->assertTrue($member->fresh()->hasProjectPermission($project, 'issue.create'));
    }

    public function test_submitting_no_checkboxes_removes_every_permission(): void
    {
        $viewer = $this->role('Viewer');

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', $viewer), [])
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $viewer->fresh()->permissions);
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $viewer = $this->role('Viewer');
        $before = $viewer->permissions()->count();

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', $viewer), ['permissions' => ['project.view', 'system.destroy']])
            ->assertSessionHasErrors('permissions.1');

        $this->assertSame($before, $viewer->permissions()->count());
    }

    public function test_administrator_role_permissions_are_locked(): void
    {
        $administrator = $this->role('Administrator');

        $this->actingAs($this->admin)
            ->get(route('admin.roles.edit', $administrator))
            ->assertOk()
            ->assertSee('selalu memiliki seluruh permission')
            ->assertDontSee('Simpan Permission');

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', $administrator), ['permissions' => ['dashboard.view']])
            ->assertForbidden();

        $this->assertSame(Permission::count(), $administrator->fresh()->permissions()->count());
    }

    public function test_saving_without_changes_does_not_write_a_log_entry(): void
    {
        $viewer = $this->role('Viewer');

        $this->actingAs($this->admin)->put(route('admin.roles.update', $viewer), [
            'permissions' => $viewer->permissions()->pluck('name')->all(),
        ]);

        $this->assertDatabaseMissing('activity_logs', ['entity_type' => 'Role']);
    }
}
