<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Requests\StoreCommentRequest;
use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\CommentAdded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Comments on projects, tasks and issues (FR-018).
 */
class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Project $project): RedirectResponse
    {
        $target = $request->target();

        $comment = DB::transaction(function () use ($request, $target): Comment {
            $comment = $target->comments()->create([
                'user_id' => $request->user()->id,
                'body' => $request->validated('body'),
            ]);

            ActivityLog::record(ActivityAction::Create, $comment, 'Menambahkan komentar', newValues: ['body' => $comment->body]);

            return $comment;
        });

        $this->notifyUsers($this->recipients($project, $target), new CommentAdded($comment, $request->user()));

        return redirect()->to($target->collaborationUrl().'#komentar')->with('success', 'Komentar ditambahkan.');
    }

    /**
     * Authors remove their own comments; Project Managers any in their project.
     */
    public function destroy(Project $project, Comment $comment): RedirectResponse
    {
        abort_unless($comment->project()->is($project), 404);
        Gate::authorize('delete', $comment);

        DB::transaction(function () use ($comment): void {
            ActivityLog::record(ActivityAction::Delete, $comment, 'Menghapus komentar', ['body' => $comment->body, 'author' => $comment->user->name]);
            $comment->delete();
        });

        return redirect()->to($comment->commentable->collaborationUrl().'#komentar')->with('success', 'Komentar dihapus.');
    }

    /**
     * Who hears about a new comment: the project managers, the people the
     * item is about (assignee, reporter) and everyone already in the thread.
     *
     * @return Collection<int, User|null>
     */
    private function recipients(Project $project, Model $target): Collection
    {
        $involved = match (true) {
            $target instanceof Task => [$target->assignee],
            $target instanceof Issue => [$target->assignee, $target->reporter],
            default => [],
        };

        $participants = User::whereIn('id', $target->comments()->select('user_id'))->get();

        return collect([...$project->managers()->get(), ...$involved, ...$participants])
            ->filter(fn (?User $user) => $user !== null && $user->isMemberOf($project));
    }
}
