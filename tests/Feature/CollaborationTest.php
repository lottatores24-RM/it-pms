<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\IssueStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\CommentAdded;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CollaborationTest extends TestCase
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
        Storage::fake(Attachment::DISK);

        $this->manager = User::factory()->create()->assignRole('Project Manager');
        $this->project = Project::factory()->create(['created_by' => $this->manager->id, 'status' => ProjectStatus::InProgress]);
        $this->project->members()->attach($this->manager, ['role_in_project' => ProjectRole::ProjectManager->value]);

        $this->teamMember = User::factory()->create()->assignRole('Team Member');
        $this->project->members()->attach($this->teamMember, ['role_in_project' => ProjectRole::TeamMember->value]);

        $this->viewer = User::factory()->create()->assignRole('Viewer');
        $this->project->members()->attach($this->viewer, ['role_in_project' => ProjectRole::Viewer->value]);
    }

    private function comment(User $user, string $type, int $id, string $body = 'Sudah saya cek.')
    {
        return $this->actingAs($user)->post(route('projects.comments.store', $this->project), [
            'commentable_type' => $type,
            'commentable_id' => $id,
            'body' => $body,
        ]);
    }

    private function upload(User $user, string $type, int $id, UploadedFile $file)
    {
        return $this->actingAs($user)->post(route('projects.attachments.store', $this->project), [
            'attachable_type' => $type,
            'attachable_id' => $id,
            'file' => $file,
        ]);
    }

    private function pdf(string $name = 'laporan.pdf', int $kilobytes = 200): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    // ---------------------------------------------------------------- comments

    public function test_team_member_comments_on_a_task_and_people_involved_are_notified(): void
    {
        $task = Task::factory()->for($this->project)->create(['assigned_to' => $this->teamMember->id]);

        Notification::fake();

        $this->comment($this->teamMember, 'task', $task->id, 'Progress 50%, lanjut besok.')
            ->assertRedirect(route('projects.tasks.show', [$this->project, $task]).'#komentar');

        $comment = Comment::sole();
        $this->assertTrue($comment->commentable->is($task));
        $this->assertSame($this->teamMember->id, $comment->user_id);

        $log = ActivityLog::where('entity_type', 'Comment')->sole();
        $this->assertSame(ActivityAction::Create, $log->action);
        $this->assertSame($this->project->id, $log->project_id);
        $this->assertStringContainsString($task->title, $log->entity_name);

        Notification::assertSentTo($this->manager, CommentAdded::class);
        Notification::assertNotSentTo($this->teamMember, CommentAdded::class);
    }

    public function test_comments_appear_in_chronological_order_on_the_task_page(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->travelTo(now()->subHour());
        $this->comment($this->manager, 'task', $task->id, 'Komentar pertama');
        $this->travelBack();
        $this->comment($this->teamMember, 'task', $task->id, 'Komentar kedua');

        $this->actingAs($this->viewer)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertSeeInOrder(['Komentar pertama', 'Komentar kedua']);
    }

    public function test_comment_body_must_be_between_1_and_2000_characters(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->comment($this->manager, 'task', $task->id, '')->assertSessionHasErrorsIn('comment', 'body');
        $this->comment($this->manager, 'task', $task->id, str_repeat('a', 2001))->assertSessionHasErrorsIn('comment', 'body');
        $this->comment($this->manager, 'task', $task->id, str_repeat('a', 2000))->assertSessionHasNoErrors();

        $this->assertSame(1, Comment::count());
    }

    public function test_comment_bodies_are_escaped(): void
    {
        $this->comment($this->manager, 'project', $this->project->id, '<script>alert(1)</script>');

        $this->actingAs($this->manager)
            ->get(route('projects.show', $this->project))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_viewers_read_comments_but_cannot_post(): void
    {
        $this->comment($this->viewer, 'project', $this->project->id)->assertForbidden();

        $this->assertSame(0, Comment::count());
    }

    public function test_items_of_another_project_cannot_be_targeted(): void
    {
        $foreignTask = Task::factory()->create();
        $otherProject = Project::factory()->create();

        $this->comment($this->manager, 'task', $foreignTask->id)->assertNotFound();
        $this->comment($this->manager, 'project', $otherProject->id)->assertNotFound();
        $this->comment($this->manager, 'user', $this->manager->id)->assertNotFound();

        $this->assertSame(0, Comment::count());
    }

    public function test_closed_issues_accept_no_new_comments(): void
    {
        $issue = Issue::factory()->for($this->project)->create(['reported_by' => $this->manager->id, 'status' => IssueStatus::Closed]);

        $this->comment($this->manager, 'issue', $issue->id)->assertForbidden();
    }

    public function test_authors_delete_their_own_comments_and_managers_delete_any(): void
    {
        $this->comment($this->teamMember, 'project', $this->project->id, 'Komentar TM');
        $this->comment($this->manager, 'project', $this->project->id, 'Komentar PM');
        [$teamMemberComment, $managerComment] = Comment::orderBy('id')->get()->all();

        $this->actingAs($this->teamMember)
            ->delete(route('projects.comments.destroy', [$this->project, $managerComment]))
            ->assertForbidden();

        $this->actingAs($this->teamMember)->delete(route('projects.comments.destroy', [$this->project, $teamMemberComment]));
        $this->assertSoftDeleted($teamMemberComment);

        $this->actingAs($this->manager)->delete(route('projects.comments.destroy', [$this->project, $managerComment]));
        $this->assertSoftDeleted($managerComment);
    }

    public function test_comments_of_another_project_cannot_be_deleted_through_this_project(): void
    {
        $otherProject = Project::factory()->create();
        $foreign = $otherProject->comments()->create(['user_id' => $this->manager->id, 'body' => 'Asing']);

        $this->actingAs($this->manager)
            ->delete(route('projects.comments.destroy', [$this->project, $foreign]))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------- attachments

    public function test_team_member_uploads_an_allowed_file(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->upload($this->teamMember, 'task', $task->id, $this->pdf())->assertSessionHasNoErrors();

        $attachment = Attachment::sole();
        $this->assertSame('laporan.pdf', $attachment->original_name);
        $this->assertTrue($attachment->attachable->is($task));
        $this->assertStringStartsWith("{$this->project->id}/", $attachment->path);
        $this->assertNotSame('laporan.pdf', $attachment->filename);
        Storage::disk(Attachment::DISK)->assertExists($attachment->path);

        $this->assertDatabaseHas('activity_logs', ['entity_type' => 'Attachment', 'action' => 'create', 'project_id' => $this->project->id]);
    }

    public function test_disallowed_formats_and_oversized_files_are_rejected_with_clear_messages(): void
    {
        $this->upload($this->manager, 'project', $this->project->id, UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'))
            ->assertSessionHasErrorsIn('attachment', ['file' => 'Format file tidak didukung. Gunakan PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, atau ZIP.']);

        $this->upload($this->manager, 'project', $this->project->id, $this->pdf('besar.pdf', 10241))
            ->assertSessionHasErrorsIn('attachment', ['file' => 'Ukuran file maksimal 10 MB.']);

        $this->assertSame(0, Attachment::count());
    }

    public function test_viewers_download_but_cannot_upload_or_delete(): void
    {
        $this->upload($this->manager, 'project', $this->project->id, $this->pdf());
        $attachment = Attachment::sole();

        $this->upload($this->viewer, 'project', $this->project->id, $this->pdf('lain.pdf'))->assertForbidden();

        $response = $this->actingAs($this->viewer)
            ->get(route('projects.attachments.download', [$this->project, $attachment]))
            ->assertOk()
            ->assertDownload('laporan.pdf');

        $this->assertSame(Storage::disk(Attachment::DISK)->get($attachment->path), $response->streamedContent());

        $this->actingAs($this->viewer)
            ->delete(route('projects.attachments.destroy', [$this->project, $attachment]))
            ->assertForbidden();
    }

    public function test_non_members_cannot_download(): void
    {
        $this->upload($this->manager, 'project', $this->project->id, $this->pdf());
        $outsider = User::factory()->create()->assignRole('Project Manager');

        $this->actingAs($outsider)
            ->get(route('projects.attachments.download', [$this->project, Attachment::sole()]))
            ->assertForbidden();
    }

    public function test_attachments_of_another_project_cannot_be_downloaded_through_this_project(): void
    {
        $otherProject = Project::factory()->create();
        $foreign = $otherProject->attachments()->create([
            'user_id' => $this->manager->id,
            'filename' => 'x.pdf',
            'original_name' => 'rahasia.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'path' => "{$otherProject->id}/x.pdf",
        ]);

        $this->actingAs($this->manager)
            ->get(route('projects.attachments.download', [$this->project, $foreign]))
            ->assertNotFound();
    }

    public function test_only_managers_delete_attachments(): void
    {
        $this->upload($this->teamMember, 'project', $this->project->id, $this->pdf());
        $attachment = Attachment::sole();

        $this->actingAs($this->teamMember)
            ->delete(route('projects.attachments.destroy', [$this->project, $attachment]))
            ->assertForbidden();

        $this->actingAs($this->manager)->delete(route('projects.attachments.destroy', [$this->project, $attachment]));

        $this->assertSoftDeleted($attachment);
        Storage::disk(Attachment::DISK)->assertExists($attachment->path);
    }

    public function test_closed_projects_accept_no_new_comments_or_files(): void
    {
        $this->project->update(['status' => ProjectStatus::Closed, 'closed_at' => now()]);

        $this->comment($this->manager, 'project', $this->project->id)->assertForbidden();
        $this->upload($this->manager, 'project', $this->project->id, $this->pdf())->assertForbidden();
    }
}
