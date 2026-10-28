<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['commentable_type', 'commentable_id', 'user_id', 'body'])]
class Comment extends Model
{
    use SoftDeletes;

    /**
     * Models that accept comments and attachments (FR-018), keyed by the
     * short type used in forms.
     *
     * @var array<string, class-string<Model>>
     */
    public const TARGET_TYPES = [
        'project' => Project::class,
        'task' => Task::class,
        'issue' => Issue::class,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }

    /**
     * The project, task or issue this comment belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Project the comment lives in, used for authorization.
     */
    public function project(): Project
    {
        return $this->commentable->collaborationProject();
    }

    public function activityLogName(): string
    {
        return $this->commentable->collaborationLabel();
    }

    public function activityLogProjectId(): int
    {
        return $this->project()->id;
    }
}
