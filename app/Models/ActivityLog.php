<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

#[Fillable([
    'user_id',
    'user_name',
    'action',
    'entity_type',
    'entity_id',
    'entity_name',
    'project_id',
    'old_values',
    'new_values',
    'description',
    'ip_address',
])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * Attributes that must never be written to the audit trail (NFR-011).
     *
     * @var list<string>
     */
    protected const REDACTED = ['password', 'remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ActivityAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * The audit trail is append-only (NFR-008).
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Activity logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Activity logs are append-only.'));
    }

    /**
     * Write an audit entry for an action performed on an entity.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function record(
        ActivityAction $action,
        Model $entity,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null,
    ): self {
        $user ??= auth()->user();

        return self::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'action' => $action,
            'entity_type' => class_basename($entity),
            'entity_id' => $entity->getKey(),
            'entity_name' => method_exists($entity, 'activityLogName')
                ? $entity->activityLogName()
                : $entity->getAttribute('name') ?? $entity->getAttribute('title'),
            'project_id' => match (true) {
                $entity instanceof Project => $entity->id,
                method_exists($entity, 'activityLogProjectId') => $entity->activityLogProjectId(),
                default => $entity->getAttribute('project_id'),
            },
            'old_values' => self::redact($oldValues),
            'new_values' => self::redact($newValues),
            'description' => $description,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    protected static function redact(?array $values): ?array
    {
        return $values === null ? null : array_diff_key($values, array_flip(self::REDACTED));
    }

    /**
     * Apply the activity log filters (FR-019): action, user, entity, project, date range.
     *
     * @param  Builder<ActivityLog>  $query
     * @param  array{action?: ?string, user_id?: ?int, entity_type?: ?string, project_id?: ?int, from?: ?string, to?: ?string}  $filters
     */
    #[Scope]
    protected function filteredBy(Builder $query, array $filters): void
    {
        $query
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['user_id'] ?? null, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['entity_type'] ?? null, fn (Builder $query, string $type) => $query->where('entity_type', $type))
            ->when($filters['project_id'] ?? null, fn (Builder $query, int $projectId) => $query->where('project_id', $projectId))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()));
    }

    public function entityLabel(): string
    {
        return __("activity.entities.{$this->entity_type}");
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
