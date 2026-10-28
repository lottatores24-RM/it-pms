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

class ProjectManagementTest extends TestCase
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
     * A project with a Project Manager member.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{Project, User}
     */
    private function projectWithManager(array $attributes = []): array
    {
        $manager = $this->userWithRole('Project Manager');
        $project = Project::factory()->create(['created_by' => $manager->id, ...$attributes]);
        $project->members()->attach($manager, ['role_in_project' => ProjectRole::ProjectManager->value]);

        return [$project, $manager];
    }

    private function addMember(Project $project, ProjectRole $role, string $globalRole = 'Team Member'): User
    {
        $user = $this->userWithRole($globalRole);
        $project->members()->attach($user, ['role_in_project' => $role->value]);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Sistem Informasi Akademik',
            'code' => 'SIAKAD-01',
            'description' => 'Pengembangan SIAKAD.',
            'start_date' => '2026-11-01',
            'end_date' => '2027-03-31',
            'sponsor' => 'Universitas Respati Yogyakarta',
            ...$overrides,
        ];
    }

    // ---------------------------------------------------------------- FR-006

    public function test_project_manager_creates_a_project_and_becomes_its_manager(): void
    {
        $manager = $this->userWithRole('Project Manager');

        $response = $this->actingAs($manager)->post(route('projects.store'), $this->validPayload());

        $project = Project::where('code', 'SIAKAD-01')->firstOrFail();
        $response->assertRedirect(route('projects.show', $project));

        $this->assertSame(ProjectStatus::Planning, $project->status);
        $this->assertSame($manager->id, $project->created_by);
        $this->assertSame(ProjectRole::ProjectManager, $manager->projectRole($project));

        $log = ActivityLog::where('entity_type', 'Project')->where('entity_id', $project->id)->sole();
        $this->assertSame(ActivityAction::Create, $log->action);
        $this->assertSame($project->id, $log->project_id);
    }

    public function test_administrator_appoints_the_project_manager_who_then_sees_the_project(): void
    {
        $manager = $this->userWithRole('Project Manager');

        $this->actingAs($this->admin)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertSee($manager->name);

        $this->actingAs($this->admin)
            ->post(route('projects.store'), $this->validPayload(['manager_id' => $manager->id]))
            ->assertSessionHasNoErrors();

        $project = Project::sole();
        $this->assertSame($this->admin->id, $project->created_by);
        $this->assertSame(ProjectRole::ProjectManager, $manager->projectRole($project));
        $this->assertFalse($this->admin->isMemberOf($project));
        $this->assertSame("Membuat proyek; {$manager->name} menjadi Project Manager", ActivityLog::where('entity_type', 'Project')->sole()->description);

        $this->actingAs($manager)->get(route('projects.index'))->assertSee('Sistem Informasi Akademik');
        $this->actingAs($manager)->get(route('dashboard'))->assertSee('Sistem Informasi Akademik');
    }

    public function test_administrator_must_appoint_an_active_project_manager(): void
    {
        $teamMember = $this->userWithRole('Team Member');
        $inactiveManager = User::factory()->inactive()->create()->assignRole('Project Manager');

        foreach ([null, $teamMember->id, $inactiveManager->id] as $managerId) {
            $this->actingAs($this->admin)
                ->post(route('projects.store'), $this->validPayload(['manager_id' => $managerId]))
                ->assertSessionHasErrors('manager_id');
        }

        $this->assertSame(0, Project::count());
    }

    public function test_project_manager_cannot_appoint_someone_else(): void
    {
        $manager = $this->userWithRole('Project Manager');
        $otherManager = $this->userWithRole('Project Manager');

        $this->actingAs($manager)
            ->post(route('projects.store'), $this->validPayload(['manager_id' => $otherManager->id]))
            ->assertSessionHasErrors('manager_id');

        $this->assertSame(0, Project::count());
    }

    public function test_status_cannot_be_chosen_on_create(): void
    {
        $manager = $this->userWithRole('Project Manager');

        $this->actingAs($manager)->post(route('projects.store'), $this->validPayload(['status' => 'Closed']));

        $this->assertSame(ProjectStatus::Planning, Project::sole()->status);
    }

    public function test_code_is_generated_when_left_empty_and_uppercased_when_given(): void
    {
        $manager = $this->userWithRole('Project Manager');

        $this->actingAs($manager)->post(route('projects.store'), $this->validPayload(['code' => '']));
        $this->actingAs($manager)->post(route('projects.store'), $this->validPayload(['code' => 'web-02']));

        $this->assertDatabaseHas('projects', ['code' => 'PRJ-'.now()->year.'-001']);
        $this->assertDatabaseHas('projects', ['code' => 'WEB-02']);
    }

    public function test_create_validates_name_dates_and_unique_code(): void
    {
        [$existing] = $this->projectWithManager(['code' => 'DUP-01']);
        $manager = $this->userWithRole('Project Manager');

        $this->actingAs($manager)
            ->post(route('projects.store'), $this->validPayload([
                'name' => 'AB',
                'code' => 'dup-01',
                'start_date' => '2026-12-01',
                'end_date' => '2026-11-30',
            ]))
            ->assertSessionHasErrors(['name', 'code', 'end_date']);

        $this->assertSame(1, Project::count());
    }

    public function test_team_members_and_viewers_cannot_create_projects(): void
    {
        foreach (['Team Member', 'Viewer'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->post(route('projects.store'), $this->validPayload())
                ->assertForbidden();
        }

        $this->assertSame(0, Project::count());
    }

    // ---------------------------------------------------------------- FR-007 view

    public function test_users_only_see_projects_they_are_members_of(): void
    {
        [$mine, $manager] = $this->projectWithManager(['name' => 'Proyek Milik Saya']);
        [$other] = $this->projectWithManager(['name' => 'Proyek Orang Lain']);

        $this->actingAs($manager)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Proyek Milik Saya')
            ->assertDontSee('Proyek Orang Lain');

        $this->actingAs($manager)->get(route('projects.show', $other))->assertForbidden();
        $this->actingAs($manager)->get(route('projects.show', $mine))->assertOk();
    }

    public function test_administrator_sees_every_project(): void
    {
        [$first] = $this->projectWithManager(['name' => 'Proyek Alfa']);
        [$second] = $this->projectWithManager(['name' => 'Proyek Beta']);

        $this->actingAs($this->admin)
            ->get(route('projects.index'))
            ->assertSee('Proyek Alfa')
            ->assertSee('Proyek Beta');

        $this->actingAs($this->admin)->get(route('projects.show', $second))->assertOk();
    }

    public function test_project_list_can_be_searched_and_filtered_by_status(): void
    {
        $this->projectWithManager(['name' => 'Aplikasi Kasir', 'code' => 'KSR-01']);
        $this->projectWithManager(['name' => 'Portal Berita', 'status' => ProjectStatus::OnHold]);

        $this->actingAs($this->admin)->get(route('projects.index', ['search' => 'ksr']))
            ->assertSee('Aplikasi Kasir')
            ->assertDontSee('Portal Berita');

        $this->actingAs($this->admin)->get(route('projects.index', ['status' => 'On Hold']))
            ->assertSee('Portal Berita')
            ->assertDontSee('Aplikasi Kasir');
    }

    public function test_overview_shows_progress_from_done_tasks(): void
    {
        [$project, $manager] = $this->projectWithManager();
        Task::factory()->for($project)->count(3)->create();
        Task::factory()->for($project)->status(TaskStatus::Done)->create();

        $this->actingAs($manager)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('25%');
    }

    // ---------------------------------------------------------------- FR-007 update

    public function test_project_manager_updates_project_and_changes_are_logged(): void
    {
        [$project, $manager] = $this->projectWithManager(['name' => 'Nama Lama']);

        $this->actingAs($manager)
            ->put(route('projects.update', $project), $this->validPayload([
                'name' => 'Nama Baru',
                'code' => $project->code,
                'start_date' => $project->start_date->format('Y-m-d'),
                'end_date' => $project->end_date->format('Y-m-d'),
                'description' => $project->description,
                'sponsor' => $project->sponsor,
                'status' => 'In Progress',
            ]))
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('Nama Baru', $project->name);
        $this->assertSame(ProjectStatus::InProgress, $project->status);

        $update = ActivityLog::where('entity_id', $project->id)->where('action', ActivityAction::Update)->sole();
        $this->assertSame(['name' => 'Nama Lama'], $update->old_values);
        $this->assertSame(['name' => 'Nama Baru'], $update->new_values);

        $statusChange = ActivityLog::where('entity_id', $project->id)->where('action', ActivityAction::StatusChange)->sole();
        $this->assertSame(['status' => 'Planning'], $statusChange->old_values);
        $this->assertSame(['status' => 'In Progress'], $statusChange->new_values);
    }

    public function test_invalid_status_transitions_are_rejected(): void
    {
        [$project, $manager] = $this->projectWithManager();

        foreach (['On Hold', 'Closed'] as $status) {
            $this->actingAs($manager)
                ->put(route('projects.update', $project), $this->validPayload(['code' => $project->code, 'status' => $status]))
                ->assertSessionHasErrors('status');
        }

        $this->assertSame(ProjectStatus::Planning, $project->fresh()->status);
    }

    public function test_project_dates_must_still_cover_existing_tasks_and_milestones(): void
    {
        [$project, $manager] = $this->projectWithManager(['start_date' => '2026-11-01', 'end_date' => '2027-03-31']);
        Task::factory()->for($project)->create(['due_date' => '2027-03-15']);
        Milestone::factory()->for($project)->create(['target_date' => '2026-12-01']);

        $this->actingAs($manager)
            ->put(route('projects.update', $project), $this->validPayload([
                'code' => $project->code,
                'start_date' => '2026-12-15',
                'end_date' => '2027-02-28',
                'status' => 'Planning',
            ]))
            ->assertSessionHasErrors(['start_date', 'end_date']);
    }

    public function test_only_the_project_manager_of_the_project_can_update_it(): void
    {
        [$project] = $this->projectWithManager(['name' => 'Tetap']);
        $teamMember = $this->addMember($project, ProjectRole::TeamMember);
        $otherManager = $this->userWithRole('Project Manager');
        $globalManagerAsViewer = $this->addMember($project, ProjectRole::Viewer, 'Project Manager');

        foreach ([$teamMember, $otherManager, $globalManagerAsViewer] as $user) {
            $this->actingAs($user)
                ->put(route('projects.update', $project), $this->validPayload(['code' => $project->code, 'status' => 'Planning']))
                ->assertForbidden();
        }

        $this->assertSame('Tetap', $project->fresh()->name);
    }

    // ---------------------------------------------------------------- FR-007 close & reopen

    public function test_project_manager_closes_an_in_progress_project_with_notes(): void
    {
        [$project, $manager] = $this->projectWithManager(['status' => ProjectStatus::InProgress]);
        Task::factory()->for($project)->create();

        $this->actingAs($manager)
            ->get(route('projects.close-form', $project))
            ->assertOk()
            ->assertSee('1 item yang belum selesai');

        $this->actingAs($manager)
            ->post(route('projects.close', $project), ['closing_notes' => 'Proyek selesai sesuai target.'])
            ->assertRedirect(route('projects.show', $project));

        $project->refresh();
        $this->assertSame(ProjectStatus::Closed, $project->status);
        $this->assertNotNull($project->closed_at);
        $this->assertSame('Proyek selesai sesuai target.', $project->closing_notes);
        $this->assertDatabaseHas('activity_logs', [
            'entity_id' => $project->id,
            'action' => ActivityAction::StatusChange->value,
            'description' => 'Menutup proyek',
        ]);
    }

    public function test_closing_notes_need_at_least_ten_characters(): void
    {
        [$project, $manager] = $this->projectWithManager(['status' => ProjectStatus::InProgress]);

        $this->actingAs($manager)
            ->post(route('projects.close', $project), ['closing_notes' => 'Selesai'])
            ->assertSessionHasErrors('closing_notes');

        $this->assertSame(ProjectStatus::InProgress, $project->fresh()->status);
    }

    public function test_only_in_progress_projects_can_be_closed(): void
    {
        [$project, $manager] = $this->projectWithManager(['status' => ProjectStatus::Planning]);

        $this->actingAs($manager)
            ->post(route('projects.close', $project), ['closing_notes' => 'Ditutup sebelum mulai.'])
            ->assertForbidden();
    }

    public function test_team_member_cannot_close_a_project(): void
    {
        [$project] = $this->projectWithManager(['status' => ProjectStatus::InProgress]);
        $teamMember = $this->addMember($project, ProjectRole::TeamMember);

        $this->actingAs($teamMember)
            ->post(route('projects.close', $project), ['closing_notes' => 'Saya tutup saja ya.'])
            ->assertForbidden();
    }

    public function test_closed_project_is_read_only_even_for_its_manager(): void
    {
        [$project, $manager] = $this->projectWithManager();
        $project->update(['status' => ProjectStatus::Closed, 'closed_at' => now()]);

        $this->actingAs($manager)->get(route('projects.edit', $project))->assertForbidden();
        $this->actingAs($manager)
            ->put(route('projects.update', $project), $this->validPayload(['code' => $project->code, 'status' => 'Closed']))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('read-only');
    }

    public function test_only_an_administrator_can_reopen_a_closed_project(): void
    {
        [$project, $manager] = $this->projectWithManager();
        $project->update(['status' => ProjectStatus::Closed, 'closed_at' => now(), 'closing_notes' => 'Ditutup sementara.']);

        $this->actingAs($manager)->patch(route('projects.reopen', $project))->assertForbidden();
        $this->assertSame(ProjectStatus::Closed, $project->fresh()->status);

        $this->actingAs($this->admin)->patch(route('projects.reopen', $project))->assertRedirect(route('projects.show', $project));

        $project->refresh();
        $this->assertSame(ProjectStatus::InProgress, $project->status);
        $this->assertNull($project->closed_at);
        $this->assertDatabaseHas('activity_logs', [
            'entity_id' => $project->id,
            'user_id' => $this->admin->id,
            'description' => 'Membuka kembali proyek',
        ]);
    }
}
