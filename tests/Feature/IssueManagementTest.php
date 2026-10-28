<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\ActivityLog;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use App\Notifications\IssueAssigned;
use App\Notifications\IssueStatusChanged;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class IssueManagementTest extends TestCase
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

    private function issue(array $attributes = []): Issue
    {
        return Issue::factory()->for($this->project)->create(['reported_by' => $this->manager->id, ...$attributes]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Tombol simpan tidak merespons',
            'description' => 'Klik simpan di form task tidak terjadi apa-apa.',
            'severity' => 'High',
            ...$overrides,
        ];
    }

    private function moveTo(User $user, Issue $issue, IssueStatus $status)
    {
        return $this->actingAs($user)->patch(route('projects.issues.updateStatus', [$this->project, $issue]), ['status' => $status->value]);
    }

    // ---------------------------------------------------------------- create

    public function test_team_member_reports_an_issue_without_choosing_an_assignee(): void
    {
        $task = Task::factory()->for($this->project)->create();
        $risk = Risk::factory()->for($this->project)->create();

        $this->actingAs($this->teamMember)
            ->post(route('projects.issues.store', $this->project), $this->payload([
                'related_task_id' => $task->id,
                'related_risk_id' => $risk->id,
                'status' => 'Closed',
            ]))
            ->assertSessionHasNoErrors();

        $issue = Issue::sole();
        $this->assertSame(IssueStatus::Open, $issue->status);
        $this->assertSame($this->teamMember->id, $issue->reported_by);
        $this->assertSame($task->id, $issue->related_task_id);
        $this->assertSame($risk->id, $issue->related_risk_id);
        $this->assertDatabaseHas('activity_logs', ['entity_type' => 'Issue', 'entity_id' => $issue->id, 'action' => 'create']);
    }

    public function test_team_member_cannot_assign_when_reporting(): void
    {
        $this->actingAs($this->teamMember)
            ->post(route('projects.issues.store', $this->project), $this->payload(['assigned_to' => $this->teamMember->id]))
            ->assertSessionHasErrors('assigned_to');

        $this->assertSame(0, Issue::count());
    }

    public function test_manager_reports_and_assigns_an_issue_and_the_assignee_is_notified(): void
    {
        Notification::fake();

        $this->actingAs($this->manager)
            ->post(route('projects.issues.store', $this->project), $this->payload(['assigned_to' => $this->teamMember->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->teamMember->id, Issue::sole()->assigned_to);
        Notification::assertSentTo($this->teamMember, IssueAssigned::class);
    }

    public function test_related_task_and_risk_must_belong_to_the_project(): void
    {
        $foreignTask = Task::factory()->create();
        $foreignRisk = Risk::factory()->create();

        $this->actingAs($this->manager)
            ->post(route('projects.issues.store', $this->project), $this->payload([
                'related_task_id' => $foreignTask->id,
                'related_risk_id' => $foreignRisk->id,
            ]))
            ->assertSessionHasErrors(['related_task_id', 'related_risk_id']);
    }

    public function test_viewers_can_read_but_not_report_issues(): void
    {
        $viewer = User::factory()->create();
        $this->project->members()->attach($viewer, ['role_in_project' => ProjectRole::Viewer->value]);
        $issue = $this->issue(['title' => 'Issue terbaca']);

        $this->actingAs($viewer)->get(route('projects.issues.index', $this->project))->assertOk()->assertSee('Issue terbaca');
        $this->actingAs($viewer)->get(route('projects.issues.show', [$this->project, $issue]))->assertOk();
        $this->actingAs($viewer)->post(route('projects.issues.store', $this->project), $this->payload())->assertForbidden();
    }

    // ---------------------------------------------------------------- update

    public function test_only_the_manager_edits_severity_and_assignee(): void
    {
        $issue = $this->issue(['assigned_to' => $this->teamMember->id, 'severity' => IssueSeverity::Low]);

        $this->actingAs($this->teamMember)
            ->put(route('projects.issues.update', [$this->project, $issue]), $this->payload(['severity' => 'Critical']))
            ->assertForbidden();
        $this->assertSame(IssueSeverity::Low, $issue->fresh()->severity);

        $this->actingAs($this->manager)
            ->put(route('projects.issues.update', [$this->project, $issue]), $this->payload(['severity' => 'Critical', 'assigned_to' => $this->teamMember->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame(IssueSeverity::Critical, $issue->fresh()->severity);

        $log = ActivityLog::where('entity_type', 'Issue')->where('action', ActivityAction::Update)->sole();
        $this->assertSame('Low', $log->old_values['severity']);
        $this->assertSame('Critical', $log->new_values['severity']);
    }

    // ---------------------------------------------------------------- workflow

    public function test_assignee_moves_their_issue_up_to_resolved_but_cannot_close_it(): void
    {
        $issue = $this->issue(['assigned_to' => $this->teamMember->id]);

        $this->moveTo($this->teamMember, $issue, IssueStatus::InProgress)->assertSessionHasNoErrors();
        $this->moveTo($this->teamMember, $issue, IssueStatus::Resolved)->assertSessionHasNoErrors();
        $this->moveTo($this->teamMember, $issue, IssueStatus::Closed)->assertForbidden();

        $this->assertSame(IssueStatus::Resolved, $issue->fresh()->status);

        $this->moveTo($this->manager, $issue, IssueStatus::Closed)->assertSessionHasNoErrors();
        $this->assertSame(IssueStatus::Closed, $issue->fresh()->status);
    }

    public function test_team_member_cannot_move_issues_assigned_to_someone_else(): void
    {
        $issue = $this->issue(['assigned_to' => $this->manager->id]);

        $this->moveTo($this->teamMember, $issue, IssueStatus::InProgress)->assertForbidden();

        $this->assertSame(IssueStatus::Open, $issue->fresh()->status);
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $issue = $this->issue();

        $this->moveTo($this->manager, $issue, IssueStatus::Resolved)->assertSessionHasErrors('status');

        $this->assertSame(IssueStatus::Open, $issue->fresh()->status);
    }

    public function test_closed_issue_is_read_only(): void
    {
        $issue = $this->issue(['status' => IssueStatus::Closed]);

        $this->actingAs($this->manager)->get(route('projects.issues.edit', [$this->project, $issue]))->assertForbidden();
        $this->actingAs($this->manager)->put(route('projects.issues.update', [$this->project, $issue]), $this->payload())->assertForbidden();
        $this->moveTo($this->manager, $issue, IssueStatus::Open)->assertForbidden();
    }

    public function test_status_changes_are_logged_and_notify_manager_and_reporter(): void
    {
        $reporter = User::factory()->create()->assignRole('Team Member');
        $this->project->members()->attach($reporter, ['role_in_project' => ProjectRole::TeamMember->value]);
        $issue = $this->issue(['assigned_to' => $this->teamMember->id, 'reported_by' => $reporter->id]);

        Notification::fake();

        $this->moveTo($this->teamMember, $issue, IssueStatus::InProgress);

        Notification::assertSentTo($this->manager, IssueStatusChanged::class);
        Notification::assertSentTo($reporter, IssueStatusChanged::class);
        Notification::assertNotSentTo($this->teamMember, IssueStatusChanged::class);

        $log = ActivityLog::where('entity_type', 'Issue')->where('action', ActivityAction::StatusChange)->sole();
        $this->assertSame(['status' => 'Open'], $log->old_values);
        $this->assertSame($this->teamMember->id, $log->user_id);
    }

    // ---------------------------------------------------------------- list & delete

    public function test_list_shows_open_and_severe_issues_first_and_filters_by_assignee(): void
    {
        $this->issue(['title' => 'Issue Low Open', 'severity' => IssueSeverity::Low]);
        $this->issue(['title' => 'Issue Critical Open', 'severity' => IssueSeverity::Critical, 'assigned_to' => $this->teamMember->id]);
        $this->issue(['title' => 'Issue Critical Closed', 'severity' => IssueSeverity::Critical, 'status' => IssueStatus::Closed]);

        $this->actingAs($this->manager)
            ->get(route('projects.issues.index', $this->project))
            ->assertSeeInOrder(['Issue Critical Open', 'Issue Low Open', 'Issue Critical Closed']);

        $this->actingAs($this->teamMember)
            ->get(route('projects.issues.index', [$this->project, 'assignee' => 'me']))
            ->assertSee('Issue Critical Open')
            ->assertDontSee('Issue Low Open');
    }

    public function test_manager_soft_deletes_an_issue_but_team_member_cannot(): void
    {
        $issue = $this->issue();

        $this->actingAs($this->teamMember)->delete(route('projects.issues.destroy', [$this->project, $issue]))->assertForbidden();
        $this->actingAs($this->manager)->delete(route('projects.issues.destroy', [$this->project, $issue]))
            ->assertRedirect(route('projects.issues.index', $this->project));

        $this->assertSoftDeleted($issue);
    }

    public function test_issues_of_a_closed_project_are_read_only(): void
    {
        $issue = $this->issue(['assigned_to' => $this->teamMember->id]);
        $this->project->update(['status' => ProjectStatus::Closed, 'closed_at' => now()]);

        $this->moveTo($this->teamMember, $issue, IssueStatus::InProgress)->assertForbidden();
        $this->actingAs($this->teamMember)->post(route('projects.issues.store', $this->project), $this->payload())->assertForbidden();
    }
}
