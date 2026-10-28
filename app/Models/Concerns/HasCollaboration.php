<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Comments and attachments on a project, task or issue (FR-018).
 */
trait HasCollaboration
{
    /**
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->oldest()->oldest('id');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest()->latest('id');
    }

    /**
     * Eager load comments and attachments with their authors, pointing each
     * back at this model so authorization checks need no extra queries.
     */
    public function loadCollaboration(): static
    {
        $this->load(['comments.user', 'attachments.user']);

        $this->comments->each->setRelation('commentable', $this);
        $this->attachments->each->setRelation('attachable', $this);

        return $this;
    }

    /**
     * Short key used in forms and routes: "project", "task" or "issue".
     */
    public function collaborationType(): string
    {
        return array_search(static::class, Comment::TARGET_TYPES, true);
    }

    /**
     * The project this item belongs to, used for authorization.
     */
    public function collaborationProject(): Project
    {
        return $this instanceof Project ? $this : $this->project;
    }

    /**
     * Page where this item's comments are shown.
     */
    public function collaborationUrl(): string
    {
        return match (true) {
            $this instanceof Project => route('projects.show', $this),
            default => route("projects.{$this->collaborationType()}s.show", [$this->project_id, $this->id]),
        };
    }

    /**
     * Human-readable label, e.g. "Task: Membuat halaman login".
     */
    public function collaborationLabel(): string
    {
        return __('activity.entities.'.class_basename($this)).': '.($this->getAttribute('name') ?? $this->getAttribute('title'));
    }
}
