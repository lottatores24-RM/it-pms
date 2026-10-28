<?php

namespace Tests\Feature;

use App\Enums\ChangeRequestStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\ActivityLog;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ChangeRequestStatusChanged;
use App\Notifications\ChangeRequestSubmitted;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ChangeRequestManagementTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $manager;

    private User $teamMember;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->manager = User::factory()->create()->assignRole('Project Manager');
        $this->project = Project::factory()->create(['created_by' => $this->manager->id, 'status' => ProjectStatus::InProgress]);
        $this->project->members()->attach($this->manager, ['role_in_project' => ProjectRole::ProjectManager->value]);

        $this->teamMember = User::factory()->create()->assignRole('Team Member');
        $this->project->members()->attach($this->teamMember, ['role_in_project' => ProjectRole::TeamMember->value]);

        $this->viewer = User::factory()->create()->assignRole('Viewer');
        $this->project->members()->attach($this->viewer, ['role_in_project' => ProjectRole::Viewer->value]);
    }

    private function changeRequest(array $attributes = []): ChangeRequest
    {
        return ChangeRequest::factory()->for($this->project)->create(['requested_by' => $this->teamMember->id, ...$attributes]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Tambah fitur ekspor nilai ke Excel',
            'description' => 'Dosen meminta nilai bisa diunduh dalam format Excel.',
            'reason' => 'Format Excel dipakai bagian akademik untuk rekap semester.',
            'impact_schedule' => 'Sekitar 3 hari kerja.',
            'priority' => 'High',
            ...$overrides,
        ];
    }

    private function review(User $user, ChangeRequest $changeRequest, ChangeRequestStatus $status, string $justification = 'Perubahan sudah dianalisis bersama tim.')
    {
        return $this->actingAs($user)->patch(
            route('projects.change-requests.updateStatus', [$this->project, $changeRequest]),
            ['status' => $status->value, 'justification' => $justification],
        );
    }

    // ---------------------------------------------------------------- submit

    public function test_team_member_submits_a_change_request_and_managers_are_notified(): void
    {
        Notification::fake();

        $this->actingAs($this->teamMember)
            ->post(route('projects.change-requests.store', $this->project), $this->payload(['status' => 'Approved']))
            ->assertSessionHasNoErrors();

        $changeRequest = ChangeRequest::sole();
        $this->assertSame(ChangeRequestStatus::Submitted, $changeRequest->status);
        $this->assertSame($this->teamMember->id, $changeRequest->requested_by);
        $this->assertDatabaseHas('activity_logs', ['entity_type' => 'ChangeRequest', 'entity_id' => $changeRequest->id, 'action' => 'create']);
        Notification::assertSentTo($this->manager, ChangeRequestSubmitted::class);
        Notification::assertNotSentTo($this->teamMember, ChangeRequestSubmitted::class);
    }

    public function test_description_and_reason_are_required(): void
    {
        $this->actingAs($this->teamMember)
            ->post(route('projects.change-requests.store', $this->project), $this->payload(['description' => '', 'reason' => '']))
            ->assertSessionHasErrors(['description', 'reason']);

        $this->assertSame(0, ChangeRequest::count());
    }

    public function test_viewer_cannot_submit_and_nobody_can_submit_on_a_closed_project(): void
    {
        $this->actingAs($this->viewer)
            ->post(route('projects.change-requests.store', $this->project), $this->payload())
            ->assertForbidden();

        $this->project->update(['status' => ProjectStatus::Closed]);

        $this->actingAs($this->manager)
            ->post(route('projects.change-requests.store', $this->project), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, ChangeRequest::count());
    }

    // ---------------------------------------------------------------- visibility

    public function test_team_member_only_sees_their_own_change_requests(): void
    {
        $own = $this->changeRequest(['title' => 'CR milik saya']);
        $other = $this->changeRequest(['title' => 'CR dari manager', 'requested_by' => $this->manager->id]);

        $this->actingAs($this->teamMember)
            ->get(route('projects.change-requests.index', $this->project))
            ->assertOk()
            ->assertSee('CR milik saya')
            ->assertDontSee('CR dari manager');

        $this->actingAs($this->teamMember)->get(route('projects.change-requests.show', [$this->project, $own]))->assertOk();
        $this->actingAs($this->teamMember)->get(route('projects.change-requests.show', [$this->project, $other]))->assertForbidden();
    }

    public function test_viewer_sees_every_change_request_but_cannot_review(): void
    {
        $changeRequest = $this->changeRequest(['title' => 'CR dari anggota tim']);

        $this->actingAs($this->viewer)
            ->get(route('projects.change-requests.index', $this->project))
            ->assertOk()
            ->assertSee('CR dari anggota tim');

        $this->actingAs($this->viewer)
            ->get(route('projects.change-requests.show', [$this->project, $changeRequest]))
            ->assertOk()
            ->assertDontSee('Mulai review');

        $this->review($this->viewer, $changeRequest, ChangeRequestStatus::UnderReview)->assertForbidden();
    }

    public function test_change_request_of_another_project_is_not_found(): void
    {
        $foreign = ChangeRequest::factory()->create();

        $this->actingAs($this->manager)
            ->get(route('projects.change-requests.show', [$this->project, $foreign]))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------- review

    public function test_manager_reviews_and_approves_with_a_justification_at_every_step(): void
    {
        Notification::fake();
        $changeRequest = $this->changeRequest();

        $this->review($this->manager, $changeRequest, ChangeRequestStatus::UnderReview, 'Dianalisis bersama tim developer.')->assertSessionHasNoErrors();
        $this->review($this->manager, $changeRequest, ChangeRequestStatus::Approved, 'Disetujui, jadwal masih ada buffer.')->assertSessionHasNoErrors();

        $changeRequest->refresh();
        $this->assertSame(ChangeRequestStatus::Approved, $changeRequest->status);
        $this->assertSame('Disetujui, jadwal masih ada buffer.', $changeRequest->justification);
        $this->assertSame($this->manager->id, $changeRequest->reviewed_by);

        $logs = ActivityLog::where('entity_type', 'ChangeRequest')->where('action', 'status_change')->oldest('id')->get();
        $this->assertSame(['Dianalisis bersama tim developer.', 'Disetujui, jadwal masih ada buffer.'], $logs->pluck('new_values.justification')->all());
        Notification::assertSentToTimes($this->teamMember, ChangeRequestStatusChanged::class, 2);

        $this->actingAs($this->teamMember)
            ->get(route('projects.change-requests.show', [$this->project, $changeRequest]))
            ->assertSee('Dianalisis bersama tim developer.')
            ->assertSee('read-only');
    }

    public function test_every_transition_requires_a_justification_of_at_least_ten_characters(): void
    {
        $changeRequest = $this->changeRequest();

        $this->review($this->manager, $changeRequest, ChangeRequestStatus::UnderReview, '')->assertSessionHasErrors('justification');
        $this->review($this->manager, $changeRequest, ChangeRequestStatus::UnderReview, 'Oke')->assertSessionHasErrors('justification');

        $this->assertSame(ChangeRequestStatus::Submitted, $changeRequest->refresh()->status);
    }

    public function test_review_cannot_skip_under_review(): void
    {
        $changeRequest = $this->changeRequest();

        $this->review($this->manager, $changeRequest, ChangeRequestStatus::Approved)->assertSessionHasErrors('status');

        $this->assertSame(ChangeRequestStatus::Submitted, $changeRequest->refresh()->status);
    }

    public function test_team_member_cannot_review_their_own_change_request(): void
    {
        $changeRequest = $this->changeRequest();

        $this->review($this->teamMember, $changeRequest, ChangeRequestStatus::UnderReview)->assertForbidden();
    }

    public function test_decided_change_requests_are_read_only(): void
    {
        $changeRequest = $this->changeRequest(['status' => ChangeRequestStatus::Rejected]);

        $this->review($this->manager, $changeRequest, ChangeRequestStatus::UnderReview)->assertForbidden();
        $this->actingAs($this->manager)->get(route('projects.change-requests.edit', [$this->project, $changeRequest]))->assertForbidden();
        $this->actingAs($this->manager)
            ->put(route('projects.change-requests.update', [$this->project, $changeRequest]), $this->payload())
            ->assertForbidden();
        $this->actingAs($this->manager)
            ->delete(route('projects.change-requests.destroy', [$this->project, $changeRequest]))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- edit & delete

    public function test_manager_edits_details_and_only_changed_fields_are_logged(): void
    {
        $changeRequest = $this->changeRequest($this->payload(['priority' => 'Low']));

        $this->actingAs($this->manager)
            ->put(route('projects.change-requests.update', [$this->project, $changeRequest]), $this->payload(['priority' => 'High']))
            ->assertSessionHasNoErrors();

        $log = ActivityLog::where('entity_type', 'ChangeRequest')->where('action', 'update')->sole();
        $this->assertSame(['priority' => 'Low'], $log->old_values);
        $this->assertSame(['priority' => 'High'], $log->new_values);
    }

    public function test_team_member_cannot_edit_their_submitted_request(): void
    {
        $changeRequest = $this->changeRequest();

        $this->actingAs($this->teamMember)
            ->put(route('projects.change-requests.update', [$this->project, $changeRequest]), $this->payload())
            ->assertForbidden();
    }

    public function test_only_unreviewed_change_requests_can_be_deleted(): void
    {
        $underReview = $this->changeRequest(['status' => ChangeRequestStatus::UnderReview]);
        $submitted = $this->changeRequest();

        $this->actingAs($this->manager)
            ->delete(route('projects.change-requests.destroy', [$this->project, $underReview]))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->delete(route('projects.change-requests.destroy', [$this->project, $submitted]))
            ->assertRedirect(route('projects.change-requests.index', $this->project));

        $this->assertModelMissing($submitted);
        $this->assertModelExists($underReview);
        $this->assertDatabaseHas('activity_logs', ['entity_type' => 'ChangeRequest', 'entity_id' => $submitted->id, 'action' => 'delete']);
    }
}
