<?php

namespace App\Models;

use App\Enums\ChangeRequestStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\RiskLevel;
use App\Enums\RiskStatus;
use App\Enums\TaskStatus;
use App\Models\Concerns\CalculatesEarnedValue;
use App\Models\Concerns\HasCollaboration;
use Carbon\CarbonInterface;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable([
    'name',
    'code',
    'description',
    'start_date',
    'end_date',
    'status',
    'sponsor',
    'created_by',
    'closing_notes',
    'closing_summary',
    'closed_at',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use CalculatesEarnedValue, HasCollaboration, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => ProjectStatus::class,
            'closing_summary' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<User, $this, ProjectMember>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->using(ProjectMember::class)
            ->withPivot(['id', 'role_in_project', 'joined_at']);
    }

    /**
     * Members holding the Project Manager role in this project.
     *
     * @return BelongsToMany<User, $this, ProjectMember>
     */
    public function managers(): BelongsToMany
    {
        return $this->members()->wherePivot('role_in_project', ProjectRole::ProjectManager->value);
    }

    /**
     * @return HasMany<ProjectMember, $this>
     */
    public function projectMembers(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<Milestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    /**
     * @return HasMany<Risk, $this>
     */
    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class);
    }

    /**
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * @return HasMany<ChangeRequest, $this>
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }

    /**
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Unclosed risks counted per probability × impact cell, keyed "Prob|Impact".
     *
     * @return Collection<string, int>
     */
    public function riskMatrix(): Collection
    {
        return $this->risks()
            ->where('status', '!=', RiskStatus::Closed)
            ->toBase()
            ->selectRaw('probability, impact, count(*) as total')
            ->groupBy('probability', 'impact')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->probability}|{$row->impact}" => (int) $row->total]);
    }

    /**
     * Number of members holding the Project Manager role in this project.
     */
    public function managerCount(): int
    {
        return $this->projectMembers()->where('role_in_project', ProjectRole::ProjectManager)->count();
    }

    public function isClosed(): bool
    {
        return $this->status === ProjectStatus::Closed;
    }

    /**
     * Counts of unfinished work, used on the overview and the closing checklist (FR-021).
     *
     * @return array<string, array{total: int, open: int}>
     */
    public function openItemSummary(): array
    {
        $count = fn (HasMany $relation, array $doneValues): array => [
            'total' => (clone $relation)->count(),
            'open' => (clone $relation)->whereNotIn('status', $doneValues)->count(),
        ];

        return [
            'tasks' => $count($this->tasks(), [TaskStatus::Done]),
            'milestones' => $count($this->milestones(), [MilestoneStatus::Completed, MilestoneStatus::Missed]),
            'risks' => $count($this->risks(), [RiskStatus::Closed]),
            'issues' => $count($this->issues(), [IssueStatus::Resolved, IssueStatus::Closed]),
            'change_requests' => $count($this->changeRequests(), [ChangeRequestStatus::Approved, ChangeRequestStatus::Rejected]),
        ];
    }

    /**
     * Snapshot of the project at the moment it is closed (FR-021). Stored in
     * closing_summary so the closing report stays as it was, even if the
     * project is later reopened and edited.
     *
     * @return array{
     *     generated_at: string,
     *     closed_by: string,
     *     schedule: array{start: string, planned_end: string, closed_on: string, planned_days: int, actual_days: int, variance_days: int},
     *     team: array{managers: list<string>, members: int},
     *     tasks: array{total: int, done: int, open: int, overdue: int, completion_rate: int, effort_total: float, effort_done: float},
     *     milestones: array{total: int, completed: int, missed: int, open: int},
     *     risks: array{total: int, closed: int, open: int, open_high: int},
     *     issues: array{total: int, resolved: int, open: int, open_critical: int},
     *     change_requests: array{total: int, approved: int, rejected: int, pending: int},
     * }
     */
    public function buildClosingSummary(string $closedBy, ?CarbonInterface $closedAt = null): array
    {
        $closedOn = ($closedAt ?? now())->copy()->startOfDay();
        $statusCounts = fn (HasMany $relation): Collection => $relation->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total);

        $tasks = $this->tasks()->get(['id', 'status', 'due_date', 'effort_points']);
        $doneTasks = $tasks->where('status', TaskStatus::Done);
        $milestones = $statusCounts($this->milestones());
        $risks = $statusCounts($this->risks());
        $issues = $statusCounts($this->issues());
        $changeRequests = $statusCounts($this->changeRequests());

        return [
            'generated_at' => now()->toIso8601String(),
            'closed_by' => $closedBy,
            'schedule' => [
                'start' => $this->start_date->toDateString(),
                'planned_end' => $this->end_date->toDateString(),
                'closed_on' => $closedOn->toDateString(),
                'planned_days' => (int) $this->start_date->diffInDays($this->end_date) + 1,
                'actual_days' => (int) $this->start_date->diffInDays($closedOn) + 1,
                'variance_days' => (int) $this->end_date->diffInDays($closedOn, false),
            ],
            'team' => [
                'managers' => $this->managers()->orderBy('name')->pluck('name')->all(),
                'members' => $this->projectMembers()->count(),
            ],
            'tasks' => [
                'total' => $tasks->count(),
                'done' => $doneTasks->count(),
                'open' => $tasks->count() - $doneTasks->count(),
                'overdue' => $tasks->filter(fn (Task $task) => $task->status !== TaskStatus::Done && $task->due_date?->lt($closedOn))->count(),
                'completion_rate' => $tasks->isEmpty() ? 0 : (int) round($doneTasks->count() / $tasks->count() * 100),
                'effort_total' => (float) $tasks->sum('effort_points'),
                'effort_done' => (float) $doneTasks->sum('effort_points'),
            ],
            'milestones' => [
                'total' => $milestones->sum(),
                'completed' => $milestones[MilestoneStatus::Completed->value] ?? 0,
                'missed' => $milestones[MilestoneStatus::Missed->value] ?? 0,
                'open' => ($milestones[MilestoneStatus::Planned->value] ?? 0) + ($milestones[MilestoneStatus::InProgress->value] ?? 0),
            ],
            'risks' => [
                'total' => $risks->sum(),
                'closed' => $risks[RiskStatus::Closed->value] ?? 0,
                'open' => $risks->sum() - ($risks[RiskStatus::Closed->value] ?? 0),
                'open_high' => $this->risks()
                    ->where('status', '!=', RiskStatus::Closed)
                    ->whereIn('risk_level', [RiskLevel::High, RiskLevel::Critical])
                    ->count(),
            ],
            'issues' => [
                'total' => $issues->sum(),
                'resolved' => ($issues[IssueStatus::Resolved->value] ?? 0) + ($issues[IssueStatus::Closed->value] ?? 0),
                'open' => ($issues[IssueStatus::Open->value] ?? 0) + ($issues[IssueStatus::InProgress->value] ?? 0),
                'open_critical' => $this->issues()
                    ->whereIn('status', [IssueStatus::Open, IssueStatus::InProgress])
                    ->where('severity', IssueSeverity::Critical)
                    ->count(),
            ],
            'change_requests' => [
                'total' => $changeRequests->sum(),
                'approved' => $changeRequests[ChangeRequestStatus::Approved->value] ?? 0,
                'rejected' => $changeRequests[ChangeRequestStatus::Rejected->value] ?? 0,
                'pending' => ($changeRequests[ChangeRequestStatus::Submitted->value] ?? 0) + ($changeRequests[ChangeRequestStatus::UnderReview->value] ?? 0),
            ],
        ];
    }

    /**
     * Projects visible to the user: every project for an Administrator,
     * otherwise only those the user is a member of (NFR-011).
     *
     * @param  Builder<Project>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->whereHas('projectMembers', fn (Builder $query) => $query->where('user_id', $user->id));
    }

    /**
     * Next free code in the form PRJ-2026-001 for the given year.
     */
    public static function generateCode(?int $year = null): string
    {
        $prefix = 'PRJ-'.($year ?? now()->year).'-';

        $lastNumber = static::withTrashed()
            ->where('code', 'like', $prefix.'%')
            ->pluck('code')
            ->map(fn (string $code) => (int) substr($code, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
    }
}
