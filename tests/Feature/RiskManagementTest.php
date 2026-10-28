<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\RiskLevel;
use App\Enums\RiskRating;
use App\Enums\RiskStatus;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Risk;
use App\Models\User;
use App\Notifications\RiskAssigned;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RiskManagementTest extends TestCase
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
        $this->project = Project::factory()->create(['created_by' => $this->manager->id, 'status' => ProjectStatus::InProgress]);
        $this->project->members()->attach($this->manager, ['role_in_project' => ProjectRole::ProjectManager->value]);

        $this->teamMember = User::factory()->create()->assignRole('Team Member');
        $this->project->members()->attach($this->teamMember, ['role_in_project' => ProjectRole::TeamMember->value]);
    }

    private function risk(array $attributes = []): Risk
    {
        return Risk::factory()->for($this->project)->create($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Server down saat demo',
            'description' => 'Hosting tidak stabil.',
            'category' => 'Teknis',
            'probability' => 'Medium',
            'impact' => 'High',
            'response_plan' => 'Mitigate',
            'owner_id' => $this->teamMember->id,
            ...$overrides,
        ];
    }

    private function moveTo(User $user, Risk $risk, RiskStatus $status)
    {
        return $this->actingAs($user)->patch(route('projects.risks.updateStatus', [$this->project, $risk]), ['status' => $status->value]);
    }

    // ---------------------------------------------------------------- FR-013 create

    public function test_manager_registers_a_risk_with_a_calculated_score(): void
    {
        Notification::fake();

        $this->actingAs($this->manager)
            ->post(route('projects.risks.store', $this->project), $this->payload(['risk_score' => 1, 'risk_level' => 'Low', 'status' => 'Closed']))
            ->assertSessionHasNoErrors();

        $risk = Risk::sole();
        $this->assertSame(6, $risk->risk_score);
        $this->assertSame(RiskLevel::High, $risk->risk_level);
        $this->assertSame(RiskStatus::Open, $risk->status);

        $log = ActivityLog::where('entity_type', 'Risk')->sole();
        $this->assertSame(ActivityAction::Create, $log->action);
        $this->assertSame(6, $log->new_values['risk_score']);

        Notification::assertSentTo($this->teamMember, RiskAssigned::class);
    }

    public function test_risk_owner_must_be_a_working_member_of_the_project(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($this->manager)
            ->post(route('projects.risks.store', $this->project), $this->payload(['owner_id' => $outsider->id]))
            ->assertSessionHasErrors('owner_id');
    }

    public function test_ratings_and_response_plan_must_be_valid_options(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.risks.store', $this->project), $this->payload(['probability' => 'Extreme', 'impact' => '', 'response_plan' => 'Ignore']))
            ->assertSessionHasErrors(['probability', 'impact', 'response_plan']);
    }

    public function test_team_members_and_viewers_have_read_only_access(): void
    {
        $risk = $this->risk(['title' => 'Risiko terbaca']);
        $viewer = User::factory()->create();
        $this->project->members()->attach($viewer, ['role_in_project' => ProjectRole::Viewer->value]);

        foreach ([$this->teamMember, $viewer] as $user) {
            $this->actingAs($user)->get(route('projects.risks.index', $this->project))->assertOk()->assertSee('Risiko terbaca');
            $this->actingAs($user)->get(route('projects.risks.show', [$this->project, $risk]))->assertOk();
            $this->actingAs($user)->post(route('projects.risks.store', $this->project), $this->payload())->assertForbidden();
            $this->actingAs($user)->put(route('projects.risks.update', [$this->project, $risk]), $this->payload())->assertForbidden();
            $this->moveTo($user, $risk, RiskStatus::Closed)->assertForbidden();
        }

        $this->assertSame(1, Risk::count());
    }

    // ---------------------------------------------------------------- FR-014 manage

    public function test_score_is_recalculated_when_ratings_change(): void
    {
        $risk = $this->risk(['probability' => RiskRating::Low, 'impact' => RiskRating::Low]);

        $this->actingAs($this->manager)
            ->put(route('projects.risks.update', [$this->project, $risk]), $this->payload(['probability' => 'High', 'impact' => 'High']))
            ->assertSessionHasNoErrors();

        $risk->refresh();
        $this->assertSame(9, $risk->risk_score);
        $this->assertSame(RiskLevel::Critical, $risk->risk_level);

        $log = ActivityLog::where('entity_type', 'Risk')->where('action', ActivityAction::Update)->sole();
        $this->assertSame(1, $log->old_values['risk_score']);
        $this->assertSame(9, $log->new_values['risk_score']);
    }

    public function test_register_can_be_filtered_by_status_category_level_and_matrix_cell(): void
    {
        $this->risk(['title' => 'Risk Teknis Kritis', 'category' => 'Teknis', 'probability' => RiskRating::High, 'impact' => RiskRating::High]);
        $this->risk(['title' => 'Risk Jadwal Rendah', 'category' => 'Jadwal', 'probability' => RiskRating::Low, 'impact' => RiskRating::Low]);
        $this->risk(['title' => 'Risk Sudah Ditutup', 'category' => 'Teknis', 'status' => RiskStatus::Closed]);

        $url = fn (array $query) => route('projects.risks.index', [$this->project, ...$query]);

        $this->actingAs($this->manager)->get($url(['level' => 'Critical']))
            ->assertSee('Risk Teknis Kritis')->assertDontSee('Risk Jadwal Rendah');
        $this->actingAs($this->manager)->get($url(['category' => 'Jadwal']))
            ->assertSee('Risk Jadwal Rendah')->assertDontSee('Risk Teknis Kritis');
        $this->actingAs($this->manager)->get($url(['status' => 'Closed']))
            ->assertSee('Risk Sudah Ditutup')->assertDontSee('Risk Jadwal Rendah');
        $this->actingAs($this->manager)->get($url(['probability' => 'Low', 'impact' => 'Low']))
            ->assertSee('Risk Jadwal Rendah')->assertDontSee('Risk Teknis Kritis');
    }

    public function test_matrix_counts_only_unclosed_risks(): void
    {
        $this->risk(['probability' => RiskRating::High, 'impact' => RiskRating::High]);
        $this->risk(['probability' => RiskRating::High, 'impact' => RiskRating::High]);
        $this->risk(['probability' => RiskRating::High, 'impact' => RiskRating::High, 'status' => RiskStatus::Closed]);

        $this->actingAs($this->manager)
            ->get(route('projects.risks.index', $this->project))
            ->assertViewHas('matrix', fn ($matrix) => $matrix['High|High'] === 2);
    }

    public function test_status_follows_open_mitigated_closed_and_can_close_directly(): void
    {
        $mitigatedFirst = $this->risk();
        $closedDirectly = $this->risk();

        $this->moveTo($this->manager, $mitigatedFirst, RiskStatus::Mitigated)->assertSessionHasNoErrors();
        $this->moveTo($this->manager, $mitigatedFirst, RiskStatus::Closed)->assertSessionHasNoErrors();
        $this->moveTo($this->manager, $closedDirectly, RiskStatus::Closed)->assertSessionHasNoErrors();

        $this->assertSame(RiskStatus::Closed, $mitigatedFirst->fresh()->status);
        $this->assertSame(RiskStatus::Closed, $closedDirectly->fresh()->status);
        $this->assertSame(3, ActivityLog::where('entity_type', 'Risk')->where('action', ActivityAction::StatusChange)->count());
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $mitigated = $this->risk(['status' => RiskStatus::Mitigated]);

        $this->moveTo($this->manager, $mitigated, RiskStatus::Open)->assertSessionHasErrors('status');

        $this->assertSame(RiskStatus::Mitigated, $mitigated->fresh()->status);
    }

    public function test_closed_risk_is_read_only_until_the_manager_reopens_it(): void
    {
        $risk = $this->risk(['status' => RiskStatus::Closed, 'title' => 'Tetap']);

        $this->actingAs($this->manager)->get(route('projects.risks.edit', [$this->project, $risk]))->assertForbidden();
        $this->actingAs($this->manager)
            ->put(route('projects.risks.update', [$this->project, $risk]), $this->payload(['title' => 'Diubah']))
            ->assertForbidden();

        $this->moveTo($this->manager, $risk, RiskStatus::Open)->assertSessionHasNoErrors();
        $this->assertSame(RiskStatus::Open, $risk->fresh()->status);

        $this->actingAs($this->manager)
            ->put(route('projects.risks.update', [$this->project, $risk]), $this->payload(['title' => 'Diubah']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Diubah', $risk->fresh()->title);
    }

    public function test_only_administrators_can_delete_risks(): void
    {
        $risk = $this->risk();
        $admin = User::where('email', 'admin@itpms.test')->firstOrFail();

        $this->actingAs($this->manager)->delete(route('projects.risks.destroy', [$this->project, $risk]))->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('projects.risks.destroy', [$this->project, $risk]))
            ->assertRedirect(route('projects.risks.index', $this->project));

        $this->assertSoftDeleted($risk);
    }

    public function test_risks_of_a_closed_project_are_read_only(): void
    {
        $risk = $this->risk();
        $this->project->update(['status' => ProjectStatus::Closed, 'closed_at' => now()]);

        $this->moveTo($this->manager, $risk, RiskStatus::Closed)->assertForbidden();
        $this->actingAs($this->manager)->post(route('projects.risks.store', $this->project), $this->payload())->assertForbidden();
    }

    public function test_changing_the_owner_notifies_the_new_owner(): void
    {
        $risk = $this->risk(['owner_id' => null]);

        Notification::fake();

        $this->actingAs($this->manager)->put(route('projects.risks.update', [$this->project, $risk]), $this->payload());

        Notification::assertSentTo($this->teamMember, RiskAssigned::class);
    }
}
