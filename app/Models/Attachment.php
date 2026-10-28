<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Number;

#[Fillable([
    'attachable_type',
    'attachable_id',
    'user_id',
    'filename',
    'original_name',
    'mime_type',
    'size',
    'path',
])]
class Attachment extends Model
{
    use SoftDeletes;

    /**
     * Storage disk holding the files (config/filesystems.php).
     */
    public const DISK = 'attachments';

    /**
     * Allowed extensions (FR-018).
     *
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'zip'];

    /**
     * Maximum size in kilobytes (10 MB).
     */
    public const MAX_KILOBYTES = 10240;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'size' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
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
     * Project the attachment lives in, used for authorization.
     */
    public function project(): Project
    {
        return $this->attachable->collaborationProject();
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }

    public function activityLogName(): string
    {
        return $this->original_name;
    }

    public function activityLogProjectId(): int
    {
        return $this->project()->id;
    }
}
