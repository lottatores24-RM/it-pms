<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
        Project::factory()->create(['name' => 'Proyek Orang Lain', 'status' => ProjectStatus::InProgress]);

        foreach ([$active, $closed] as $project) {
            $project->members()->attach($member, ['role_in_project' => ProjectRole::TeamMember->value]);
        }

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Proyek aktif')
            ->assertSee(route('projects.show', $active), false)
            ->assertDontSee(route('projects.show', $closed), false)
            ->assertDontSee('Proyek Orang Lain');
    }

    public function test_sidebar_section_is_hidden_without_projects(): void
    {
        $member = User::factory()->create()->assignRole('Team Member');

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Proyek aktif');
    }
}
