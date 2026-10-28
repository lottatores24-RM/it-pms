<?php

namespace App\Models;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Concerns\HasCollaboration;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'project_id',
    'title',
    'description',
    'severity',
    'status',
    'assigned_to',
    'reported_by',
    'related_task_id',
    'related_risk_id',
])]
class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use HasCollaboration, HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'Open',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'assigned_to' => 'integer',
            'reported_by' => 'integer',
            'related_task_id' => 'integer',
            'related_risk_id' => 'integer',
            'severity' => IssueSeverity::class,
            'status' => IssueStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function relatedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'related_task_id');
    }

    /**
     * @return BelongsTo<Risk, $this>
     */
    public function relatedRisk(): BelongsTo
    {
        return $this->belongsTo(Risk::class, 'related_risk_id');
    }
}
