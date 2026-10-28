<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Proyek saya" quick links in the sidebar. Checked on the notifications page,
 * which lists no projects of its own, so every project link comes from the sidebar.
 */
class SidebarProjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_sidebar_lists_only_the_members_unclosed_projects(): void
    {
        $member = User::factory()->create()->assignRole('Team Member');

        $active = Project::factory()->create(['name' => 'Proyek Berjalan', 'status' => ProjectStatus::InProgress]);
        $closed = Project::factory()->create(['name' => 'Proyek Selesai', 'status' => ProjectStatus::Closed]);
        $other = Project::factory()->create(['name' => 'Proyek Orang Lain', 'status' => ProjectStatus::InProgress]);

        foreach ([$active, $closed] as $project) {
            $project->members()->attach($member, ['role_in_project' => ProjectRole::TeamMember->value]);
        }

        $this->actingAs($member)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('id="sidebar-projects"', false)
            ->assertSee(route('projects.show', $active), false)
            ->assertDontSee(route('projects.show', $closed), false)
            ->assertDontSee(route('projects.show', $other), false);
    }

    public function test_administrator_sidebar_skips_projects_they_are_not_a_member_of(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        Project::factory()->create(['status' => ProjectStatus::InProgress]);

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('id="sidebar-projects"', false);
    }

    public function test_sidebar_section_is_hidden_without_projects(): void
    {
        $member = User::factory()->create()->assignRole('Team Member');

        $this->actingAs($member)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('id="sidebar-projects"', false);
    }
}
