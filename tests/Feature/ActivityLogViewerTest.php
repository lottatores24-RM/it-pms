<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\ProjectRole;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@itpms.test')->firstOrFail();
        $this->manager = User::factory()->create(['name' => 'Pak Manajer'])->assignRole('Project Manager');
        $this->project = Project::factory()->create(['created_by' => $this->manager->id]);
        $this->project->members()->attach($this->manager, ['role_in_project' => ProjectRole::ProjectManager->value]);
    }

    private function member(ProjectRole $role): User
    {
        $user = User::factory()->create()->assignRole('Team Member');
        $this->project->members()->attach($user, ['role_in_project' => $role->value]);

        return $user;
    }

    private function log(ActivityAction $action, $entity, string $description, ?User $user = null): ActivityLog
    {
        return ActivityLog::record($action, $entity, $description, user: $user ?? $this->manager);
    }

    // ---------------------------------------------------------------- access

    public function test_global_log_is_for_administrators_only(): void
    {
        $this->actingAs($this->admin)->get(route('admin.activity-logs.index'))->assertOk();
        $this->actingAs($this->manager)->get(route('admin.activity-logs.index'))->assertForbidden();
        $this->actingAs($this->manager)->get(route('admin.activity-logs.export'))->assertForbidden();
    }

    public function test_project_log_is_visible_to_its_manager_but_not_to_team_members(): void
    {
        $teamMember = $this->member(ProjectRole::TeamMember);

        $this->actingAs($this->manager)->get(route('projects.activity', $this->project))->assertOk();
        $this->actingAs($this->admin)->get(route('projects.activity', $this->project))->assertOk();
        $this->actingAs($teamMember)->get(route('projects.activity', $this->project))->assertForbidden();

        $this->actingAs($teamMember)->get(route('projects.show', $this->project))->assertDontSee('Aktivitas');
        $this->actingAs($this->manager)->get(route('projects.show', $this->project))->assertSee('Aktivitas');
    }

    public function test_project_log_only_contains_that_projects_entries(): void
    {
        $other = Project::factory()->create();
        $this->log(ActivityAction::Create, Task::factory()->for($this->project)->create(), 'Log proyek ini');
        $this->log(ActivityAction::Create, Task::factory()->for($other)->create(), 'Log proyek lain');

        $this->actingAs($this->manager)
            ->get(route('projects.activity', [$this->project, 'project_id' => $other->id]))
            ->assertSee('Log proyek ini')
            ->assertDontSee('Log proyek lain');
    }

    // ---------------------------------------------------------------- filters

    public function test_log_can_be_filtered_by_action_user_entity_and_date_range(): void
    {
        $task = Task::factory()->for($this->project)->create();
        $otherUser = $this->member(ProjectRole::TeamMember);

        $this->travelTo('2026-11-01 10:00');
        $this->log(ActivityAction::Create, $task, 'Entri November');
        $this->travelTo('2026-12-01 10:00');
        $this->log(ActivityAction::StatusChange, $task, 'Entri status oleh anggota', $otherUser);
        $this->log(ActivityAction::Update, $this->project, 'Entri proyek');

        $url = fn (array $query) => route('projects.activity', [$this->project, ...$query]);

        $this->actingAs($this->manager)->get($url(['action' => 'status_change']))
            ->assertSee('Entri status oleh anggota')->assertDontSee('Entri November')->assertDontSee('Entri proyek');

        $this->actingAs($this->manager)->get($url(['user_id' => $otherUser->id]))
            ->assertSee('Entri status oleh anggota')->assertDontSee('Entri proyek');

        $this->actingAs($this->manager)->get($url(['entity_type' => 'Project']))
            ->assertSee('Entri proyek')->assertDontSee('Entri November');

        $this->actingAs($this->manager)->get($url(['from' => '2026-10-01', 'to' => '2026-11-30']))
            ->assertSee('Entri November')->assertDontSee('Entri proyek');
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.activity', [$this->project, 'action' => 'hack', 'from' => '2026-12-01', 'to' => '2026-11-01']))
            ->assertSessionHasErrors(['action', 'to']);
    }

    // ---------------------------------------------------------------- export

    public function test_project_log_can_be_exported_as_csv(): void
    {
        $this->log(ActivityAction::Create, Task::factory()->for($this->project)->create(['title' => 'Task Ekspor']), 'Membuat task');

        $response = $this->actingAs($this->manager)->get(route('projects.activity.export', $this->project));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Waktu,User,Aksi', $csv);
        $this->assertStringContainsString('Task Ekspor', $csv);
        $this->assertStringContainsString('Pak Manajer', $csv);
    }
}
