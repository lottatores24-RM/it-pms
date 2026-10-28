<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@itpms.test')->firstOrFail();
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'project manager' => ['Project Manager'],
            'team member' => ['Team Member'],
            'viewer' => ['Viewer'],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_users_are_forbidden(string $role): void
    {
        $user = $this->userWithRole($role);
        $target = $this->userWithRole('Viewer');

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.users.store'), [])->assertForbidden();
        $this->actingAs($user)->put(route('admin.users.update', $target), ['name' => 'Diretas'])->assertForbidden();
        $this->actingAs($user)->patch(route('admin.users.toggle-active', $target))->assertForbidden();

        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_search_and_filter_users(): void
    {
        $budi = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@itpms.test'])->assignRole('Viewer');
        $siti = User::factory()->inactive()->create(['name' => 'Siti Aminah'])->assignRole('Viewer');

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['search' => 'budi']))
            ->assertOk()
            ->assertSee($budi->name)
            ->assertDontSee($siti->name);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['status' => 'inactive']))
            ->assertSee($siti->name)
            ->assertDontSee($budi->name);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['role' => 'Team Member']))
            ->assertSee('Mirco')
            ->assertDontSee($budi->name);
    }

    public function test_user_list_never_exposes_password_hashes(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertDontSee($this->admin->getAuthPassword(), false);
    }

    public function test_admin_can_create_a_user_with_a_role(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Dewi Lestari',
            'email' => 'dewi@itpms.test',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'role' => 'Team Member',
        ]);

        $user = User::where('email', 'dewi@itpms.test')->firstOrFail();
        $response->assertRedirect(route('admin.users.show', $user));

        $this->assertTrue($user->hasRole('Team Member'));
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Rahasia123', $user->password));

        $log = ActivityLog::where('entity_type', 'User')->where('entity_id', $user->id)->sole();
        $this->assertSame(ActivityAction::Create, $log->action);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('Team Member', $log->new_values['role']);
        $this->assertArrayNotHasKey('password', $log->new_values);
    }

    public function test_create_rejects_duplicate_email_weak_password_and_unknown_role(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'Duplikat',
                'email' => 'riki@itpms.test',
                'password' => 'abcdefgh',
                'password_confirmation' => 'abcdefgh',
                'role' => 'Super Admin',
            ])
            ->assertSessionHasErrors(['email', 'password', 'role']);

        $this->assertDatabaseMissing('users', ['name' => 'Duplikat']);
    }

    public function test_admin_can_update_a_user_and_change_their_role(): void
    {
        $target = User::factory()->create(['name' => 'Nama Lama'])->assignRole('Viewer');

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $target), [
                'name' => 'Nama Baru',
                'email' => $target->email,
                'role' => 'Project Manager',
            ])
            ->assertRedirect(route('admin.users.show', $target))
            ->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('Nama Baru', $target->name);
        $this->assertTrue($target->hasRole('Project Manager'));
        $this->assertFalse($target->hasRole('Viewer'));

        $log = ActivityLog::where('entity_id', $target->id)->where('action', ActivityAction::Update)->sole();
        $this->assertEquals(['name' => 'Nama Lama', 'role' => 'Viewer'], $log->old_values);
        $this->assertEquals(['name' => 'Nama Baru', 'role' => 'Project Manager'], $log->new_values);
    }

    public function test_leaving_password_empty_keeps_the_current_password(): void
    {
        $target = $this->userWithRole('Viewer');

        $this->actingAs($this->admin)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'Viewer',
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('password', $target->fresh()->password));
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->admin), [
                'name' => 'Administrator',
                'email' => $this->admin->email,
                'role' => 'Viewer',
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($this->admin->fresh()->hasRole('Administrator'));
    }

    public function test_admin_can_edit_their_own_name_without_sending_a_role(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->admin), [
                'name' => 'Admin Utama',
                'email' => $this->admin->email,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Admin Utama', $this->admin->fresh()->name);
        $this->assertTrue($this->admin->fresh()->hasRole('Administrator'));
    }

    public function test_admin_can_deactivate_and_reactivate_another_user(): void
    {
        $target = $this->userWithRole('Team Member');

        $this->actingAs($this->admin)->patch(route('admin.users.toggle-active', $target))->assertSessionHas('success');
        $this->assertFalse($target->fresh()->is_active);

        $this->actingAs($this->admin)->patch(route('admin.users.toggle-active', $target));
        $this->assertTrue($target->fresh()->is_active);

        $logs = ActivityLog::where('entity_id', $target->id)->where('action', ActivityAction::StatusChange)->orderBy('id')->get();
        $this->assertSame([['is_active' => false], ['is_active' => true]], $logs->pluck('new_values')->all());
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.users.toggle-active', $this->admin))
            ->assertForbidden();

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_admin_can_open_the_create_and_edit_forms(): void
    {
        $target = $this->userWithRole('Viewer');

        $this->actingAs($this->admin)->get(route('admin.users.create'))->assertOk()->assertSee('Role global');
        $this->actingAs($this->admin)->get(route('admin.users.edit', $target))->assertOk()->assertSee($target->email);
        $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $this->admin))
            ->assertSee('Anda tidak dapat mengubah role akun Anda sendiri.');
    }

    public function test_user_detail_page_shows_project_memberships(): void
    {
        $member = $this->userWithRole('Team Member');
        $project = Project::factory()->create(['name' => 'Sistem Inventori']);
        $project->members()->attach($member, ['role_in_project' => 'Team Member']);

        $this->actingAs($this->admin)
            ->get(route('admin.users.show', $member))
            ->assertOk()
            ->assertSee('Sistem Inventori');
    }
}
