<?php

namespace Tests\Feature;

use App\Enums\PerformanceIndexStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarnedValueTest extends TestCase
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
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
        ]);
        $this->project->members()->attach($this->manager, ['role_in_project' => ProjectRole::ProjectManager->value]);

        $this->teamMember = User::factory()->create()->assignRole('Team Member');
        $this->project->members()->attach($this->teamMember, ['role_in_project' => ProjectRole::TeamMember->value]);
    }

    /**
     * BAC 28 · PV 16 · EV 14 · AC 14 as of 15 Feb 2026, with one task of each data gap.
     */
    private function seedTasks(): void
    {
        $this->travelTo('2026-01-18 10:00');
        Task::factory()->for($this->project)->status(TaskStatus::Done)->create(['effort_points' => 10, 'due_date' => '2026-01-20', 'actual_effort_points' => 12]);

        $this->travelTo('2026-02-15 10:00');
        Task::factory()->for($this->project)->status(TaskStatus::InProgress)->create(['effort_points' => 6, 'due_date' => '2026-02-10', 'actual_effort_points' => 2]);
        Task::factory()->for($this->project)->create(['effort_points' => 8, 'due_date' => '2026-03-20']);
        Task::factory()->for($this->project)->create(['effort_points' => null, 'due_date' => '2026-03-01']);
        Task::factory()->for($this->project)->status(TaskStatus::Done)->create(['effort_points' => 4, 'due_date' => null]);
    }

    public function test_figures_follow_the_evm_formulas(): void
    {
        $this->seedTasks();

        $evm = $this->project->earnedValue();

        $this->assertEquals(
            ['bac' => 28, 'pv' => 16, 'ev' => 14, 'ac' => 14, 'sv' => -2, 'cv' => 0, 'spi' => 0.88, 'cpi' => 1, 'eac' => 28, 'percent_complete' => 50],
            array_intersect_key($evm, array_flip(['bac', 'pv', 'ev', 'ac', 'sv', 'cv', 'spi', 'cpi', 'eac', 'percent_complete'])),
        );
        $this->assertSame(['without_effort' => 1, 'without_due_date' => 1, 'done_without_actual' => 1], $evm['gaps']);
        $this->assertSame(PerformanceIndexStatus::Below, PerformanceIndexStatus::fromIndex($evm['spi']));
        $this->assertSame(PerformanceIndexStatus::OnTarget, PerformanceIndexStatus::fromIndex($evm['cpi']));
    }

    public function test_indices_are_left_empty_until_there_is_something_to_divide_by(): void
    {
        $this->travelTo('2026-01-05');
        Task::factory()->for($this->project)->create(['effort_points' => 5, 'due_date' => '2026-02-01']);

        $evm = $this->project->earnedValue();

        $this->assertNull($evm['spi']);
        $this->assertNull($evm['cpi']);
        $this->assertNull($evm['eac']);
        $this->assertNull(PerformanceIndexStatus::fromIndex($evm['spi']));
    }

    public function test_index_interpretation_uses_the_two_decimals_shown(): void
    {
        $this->assertSame(PerformanceIndexStatus::OnTarget, PerformanceIndexStatus::fromIndex(1.004));
        $this->assertSame(PerformanceIndexStatus::Above, PerformanceIndexStatus::fromIndex(1.006));
        $this->assertSame(PerformanceIndexStatus::Below, PerformanceIndexStatus::fromIndex(0.99));
    }

    public function test_s_curve_is_cumulative_and_stops_measuring_at_the_status_date(): void
    {
        $this->seedTasks();

        $curve = $this->project->earnedValueCurve();
        $today = array_search('15 Feb 2026', $curve['labels'], true);

        $this->assertSame('01 Jan 2026', $curve['labels'][0]);
        $this->assertSame('31 Mar 2026', end($curve['labels']));
        $this->assertSame(24.0, end($curve['pv']));
        $this->assertSame(14.0, $curve['ev'][$today]);
        $this->assertSame(14.0, $curve['ac'][$today]);
        $this->assertNull($curve['ev'][$today + 1]);
        $this->assertSame($curve['pv'], collect($curve['pv'])->sort()->values()->all());
    }

    public function test_dashboard_interprets_the_indices_and_reports_missing_data(): void
    {
        $this->seedTasks();

        $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project))
            ->assertOk()
            ->assertSee('Terlambat dari jadwal')
            ->assertSee('Effort sesuai rencana')
            ->assertSee('Data belum lengkap')
            ->assertSee('1 task Done belum dicatat effort aktualnya', false)
            ->assertSee('data-chart="evm-curve"', false);
    }

    public function test_dashboard_explains_that_evm_needs_effort_points(): void
    {
        Task::factory()->for($this->project)->create(['effort_points' => null]);

        $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project))
            ->assertOk()
            ->assertSee('Belum ada task yang memiliki effort points.')
            ->assertDontSee('data-chart="evm-curve"', false);
    }

    // ---------------------------------------------------------------- recording actual effort

    public function test_assignee_records_actual_effort_on_their_task_and_it_is_logged(): void
    {
        $task = Task::factory()->for($this->project)->create(['assigned_to' => $this->teamMember->id]);

        $this->actingAs($this->teamMember)
            ->patch(route('projects.tasks.recordEffort', [$this->project, $task]), ['actual_effort_points' => 6.5])
            ->assertSessionHasNoErrors();

        $this->assertSame('6.50', $task->refresh()->actual_effort_points);
        $this->assertDatabaseHas('activity_logs', ['entity_type' => 'Task', 'entity_id' => $task->id, 'description' => 'Mencatat effort aktual task']);
    }

    public function test_team_member_cannot_record_effort_on_someone_elses_task(): void
    {
        $task = Task::factory()->for($this->project)->create(['assigned_to' => $this->manager->id]);

        $this->actingAs($this->teamMember)
            ->patch(route('projects.tasks.recordEffort', [$this->project, $task]), ['actual_effort_points' => 3])
            ->assertForbidden();

        $this->assertNull($task->refresh()->actual_effort_points);
    }

    public function test_actual_effort_must_be_a_positive_number(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->actingAs($this->manager)
            ->patch(route('projects.tasks.recordEffort', [$this->project, $task]), ['actual_effort_points' => -1])
            ->assertSessionHasErrorsIn('actualEffort', 'actual_effort_points');
    }

    public function test_effort_cannot_be_recorded_on_a_closed_project(): void
    {
        $task = Task::factory()->for($this->project)->create();
        $this->project->update(['status' => ProjectStatus::Closed]);

        $this->actingAs($this->manager)
            ->patch(route('projects.tasks.recordEffort', [$this->project, $task]), ['actual_effort_points' => 3])
            ->assertForbidden();
    }
}
