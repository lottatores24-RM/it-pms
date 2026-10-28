<?php

namespace App\Http\Controllers;

use App\Enums\ChangeRequestStatus;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\RiskLevel;
use App\Enums\RiskStatus;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\ChangeRequest;
use App\Models\Issue;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Personal start page after login, shaped by the user's global role:
 * Administrators see the whole system, Project Managers what waits for their
 * decision, Team Members their own work and Viewers the projects they follow.
 * Outside the admin view every figure is limited to the user's projects (NFR-011).
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $variant = $this->variantFor($user);

        return view("dashboard.home.{$variant}", [
            'user' => $user,
            'greeting' => $this->greeting(),
            'roleName' => $user->getRoleNames()->first() ?? 'Pengguna',
            'notifications' => $user->notifications()->limit(5)->get(),
            'unreadCount' => $user->unreadNotifications()->count(),
            ...match ($variant) {
                'admin' => $this->adminData(),
                'manager' => $this->managerData($user),
                'member' => $this->memberData($user),
                default => $this->viewerData($user),
            },
        ]);
    }

    private function variantFor(User $user): string
    {
        return match (true) {
            $user->isAdmin() => 'admin',
            $user->hasRole(ProjectRole::ProjectManager->value) => 'manager',
            $user->hasRole(ProjectRole::TeamMember->value) => 'member',
            default => 'viewer',
        };
    }

    private function greeting(): string
    {
        return match (true) {
            now()->hour < 11 => 'Selamat pagi',
            now()->hour < 15 => 'Selamat siang',
            now()->hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }

    /**
     * System-wide health for Administrators.
     *
     * @return array<string, mixed>
     */
    private function adminData(): array
    {
        $projectsByStatus = Project::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $kpi = [
            'activeUsers' => User::active()->count(),
            'inactiveUsers' => User::where('is_active', false)->count(),
            'runningProjects' => (int) ($projectsByStatus[ProjectStatus::InProgress->value] ?? 0),
            'totalProjects' => (int) $projectsByStatus->sum(),
            'overdueTasks' => $this->overdue(Task::query())->count(),
            'activityToday' => ActivityLog::where('created_at', '>=', today())->count(),
        ];

        $projects = $this->withCardCounts(Project::where('status', '!=', ProjectStatus::Closed))
            ->orderByDesc('overdue_tasks_count')
            ->orderBy('end_date')
            ->limit(4)
            ->get();

        return [
            'kpi' => $kpi,
            'summary' => "Sistem berjalan dengan {$kpi['activeUsers']} user aktif dan {$kpi['runningProjects']} proyek berjalan. ".($kpi['overdueTasks'] > 0
                ? "Ada {$kpi['overdueTasks']} task terlambat di seluruh proyek."
                : 'Tidak ada task yang terlambat.'),
            'projectsByStatus' => collect(ProjectStatus::cases())->mapWithKeys(fn (ProjectStatus $status) => [
                $status->value => (int) ($projectsByStatus[$status->value] ?? 0),
            ]),
            'usersByRole' => Role::withCount('users')->orderBy('id')->pluck('users_count', 'name'),
            'projects' => $projects,
            'nextMilestones' => $this->nextMilestones($projects->modelKeys()),
            'recentActivity' => ActivityLog::latest('created_at')->latest('id')->limit(8)->get(),
        ];
    }

    /**
     * What waits for a Project Manager's decision in the projects they manage.
     *
     * @return array<string, mixed>
     */
    private function managerData(User $user): array
    {
        $managed = $this->withCardCounts($user->projects()
            ->wherePivot('role_in_project', ProjectRole::ProjectManager->value)
            ->where('projects.status', '!=', ProjectStatus::Closed))
            ->orderBy('end_date')
            ->get();
        $ids = $managed->modelKeys();

        $requests = ChangeRequest::whereIn('project_id', $ids)->whereIn('status', [ChangeRequestStatus::Submitted, ChangeRequestStatus::UnderReview]);
        $issues = Issue::whereIn('project_id', $ids)->where('status', IssueStatus::Resolved);
        $tasks = $this->overdue(Task::whereIn('project_id', $ids));
        $milestones = Milestone::whereIn('project_id', $ids)
            ->whereIn('status', [MilestoneStatus::Planned, MilestoneStatus::InProgress])
            ->whereBetween('target_date', [today(), today()->addDays(7)]);

        $actions = [
            'requests' => ['count' => (clone $requests)->count(), 'items' => $requests->with(['project', 'requester'])->oldest()->limit(5)->get()],
            'issues' => ['count' => (clone $issues)->count(), 'items' => $issues->with(['project', 'assignee'])->oldest('updated_at')->limit(5)->get()],
            'tasks' => ['count' => (clone $tasks)->count(), 'items' => $tasks->with('project')->orderBy('due_date')->limit(5)->get()],
            'milestones' => ['count' => (clone $milestones)->count(), 'items' => $milestones->with('project')->orderBy('target_date')->limit(5)->get()],
        ];
        $waiting = array_sum(array_column($actions, 'count'));
        $portfolio = $managed->take(6);

        return [
            'summary' => match (true) {
                $managed->isEmpty() => 'Anda belum mengelola proyek aktif. Buat proyek baru untuk memulai.',
                $waiting === 0 => "Semua {$managed->count()} proyek yang Anda kelola berjalan tanpa hal mendesak.",
                default => "Ada {$waiting} hal yang menunggu tindakan Anda di {$managed->count()} proyek yang Anda kelola.",
            },
            'kpi' => [
                'projects' => $managed->count(),
                'pendingRequests' => $actions['requests']['count'],
                'overdueTasks' => $actions['tasks']['count'],
                'openRisks' => Risk::whereIn('project_id', $ids)
                    ->where('status', '!=', RiskStatus::Closed)
                    ->whereIn('risk_level', [RiskLevel::High, RiskLevel::Critical])
                    ->count(),
            ],
            'actions' => $actions,
            'managed' => $managed,
            'portfolio' => $portfolio,
            'spi' => $portfolio->mapWithKeys(fn (Project $project) => [$project->id => $project->earnedValue()['spi']]),
            'myTasks' => $this->myOpenTasks($user)->limit(5)->get(),
            'otherProjects' => $this->withCardCounts($user->projects()
                ->wherePivot('role_in_project', '!=', ProjectRole::ProjectManager->value)
                ->where('projects.status', '!=', ProjectStatus::Closed))
                ->orderBy('end_date')
                ->get(),
        ];
    }

    /**
     * A Team Member's own work, grouped by urgency.
     *
     * @return array<string, mixed>
     */
    private function memberData(User $user): array
    {
        $tasks = $this->myOpenTasks($user)->get();
        $today = today();
        $nextWeek = today()->addDays(7);

        $buckets = [
            'overdue' => $tasks->filter(fn (Task $task) => $task->isOverdue()),
            'today' => $tasks->filter(fn (Task $task) => $task->due_date?->isSameDay($today)),
            'week' => $tasks->filter(fn (Task $task) => $task->due_date?->gt($today) && $task->due_date->lte($nextWeek)),
            'later' => $tasks->filter(fn (Task $task) => $task->due_date === null || $task->due_date->gt($nextWeek)),
        ];

        $projects = $this->withCardCounts($user->projects()->where('projects.status', '!=', ProjectStatus::Closed))->orderBy('end_date')->get();

        return [
            'summary' => match (true) {
                $buckets['overdue']->isNotEmpty() => "{$buckets['overdue']->count()} task Anda melewati tenggat. Mulailah dari yang paling lama terlambat.",
                $buckets['today']->isNotEmpty() => "{$buckets['today']->count()} task jatuh tempo hari ini. Semangat menyelesaikannya!",
                $tasks->isNotEmpty() => "Tidak ada task mendesak hari ini. Anda punya {$tasks->count()} task aktif.",
                default => 'Belum ada task yang ditugaskan kepada Anda.',
            },
            'kpi' => [
                'openTasks' => $tasks->count(),
                'overdueTasks' => $buckets['overdue']->count(),
                'dueSoon' => $buckets['today']->count() + $buckets['week']->count(),
                'doneThisWeek' => Task::where('assigned_to', $user->id)
                    ->where('status', TaskStatus::Done)
                    ->where('completed_at', '>=', now()->subDays(7))
                    ->count(),
            ],
            'buckets' => $buckets,
            'issues' => Issue::where('assigned_to', $user->id)
                ->whereIn('status', [IssueStatus::Open, IssueStatus::InProgress])
                ->whereHas('project', fn (Builder $query) => $query->where('status', '!=', ProjectStatus::Closed))
                ->with('project')
                ->orderByRaw("case severity when 'Critical' then 0 when 'High' then 1 when 'Medium' then 2 else 3 end")
                ->limit(5)
                ->get(),
            'changeRequests' => ChangeRequest::where('requested_by', $user->id)->with('project')->latest()->limit(4)->get(),
            'projects' => $projects,
            'nextMilestones' => $this->nextMilestones($projects->modelKeys()),
        ];
    }

    /**
     * Progress of the projects a Viewer follows.
     *
     * @return array<string, mixed>
     */
    private function viewerData(User $user): array
    {
        $projects = $this->withCardCounts($user->projects()->where('projects.status', '!=', ProjectStatus::Closed))->orderBy('end_date')->get();
        $ids = $projects->modelKeys();

        $upcoming = Milestone::whereIn('project_id', $ids)
            ->whereIn('status', [MilestoneStatus::Planned, MilestoneStatus::InProgress])
            ->whereBetween('target_date', [today(), today()->addDays(30)])
            ->with('project')
            ->orderBy('target_date')
            ->get();

        $averageProgress = $projects->isEmpty() ? 0 : (int) round($projects->avg(
            fn (Project $project) => $project->tasks_count > 0 ? $project->done_tasks_count / $project->tasks_count * 100 : 0,
        ));

        return [
            'summary' => $projects->isEmpty()
                ? 'Anda belum ditambahkan ke proyek mana pun.'
                : "Anda memantau {$projects->count()} proyek dengan rata-rata progress {$averageProgress}%.",
            'kpi' => [
                'projects' => $projects->count(),
                'averageProgress' => $averageProgress,
                'milestonesSoon' => $upcoming->count(),
                'criticalIssues' => (int) $projects->sum('critical_issues_count'),
            ],
            'projects' => $projects,
            'nextMilestones' => $this->nextMilestones($ids),
            'upcomingMilestones' => $upcoming->take(6),
            'recentlyDone' => Task::whereIn('project_id', $ids)
                ->where('status', TaskStatus::Done)
                ->where('completed_at', '>=', now()->subDays(14))
                ->with(['project', 'assignee'])
                ->latest('completed_at')
                ->limit(6)
                ->get(),
        ];
    }

    /**
     * Counts and relations a project card needs.
     *
     * @template TQuery of Builder<Project>|BelongsToMany<Project, User>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function withCardCounts(Builder|BelongsToMany $query): Builder|BelongsToMany
    {
        return $query
            ->with('managers')
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn (Builder $query) => $query->where('status', TaskStatus::Done),
                'tasks as overdue_tasks_count' => fn (Builder $query) => $query->where('status', '!=', TaskStatus::Done)->whereDate('due_date', '<', today()),
                'issues as critical_issues_count' => fn (Builder $query) => $query
                    ->whereIn('status', [IssueStatus::Open, IssueStatus::InProgress])
                    ->where('severity', IssueSeverity::Critical),
            ]);
    }

    /**
     * The next open milestone of each project, keyed by project id.
     *
     * @param  list<int>  $projectIds
     * @return Collection<int, Milestone>
     */
    private function nextMilestones(array $projectIds): Collection
    {
        return Milestone::whereIn('project_id', $projectIds)
            ->whereIn('status', [MilestoneStatus::Planned, MilestoneStatus::InProgress])
            ->whereDate('target_date', '>=', today())
            ->orderBy('target_date')
            ->get()
            ->unique('project_id')
            ->keyBy('project_id');
    }

    /**
     * Unfinished tasks assigned to the user in projects that are still open.
     *
     * @return Builder<Task>
     */
    private function myOpenTasks(User $user): Builder
    {
        return Task::query()
            ->where('assigned_to', $user->id)
            ->where('status', '!=', TaskStatus::Done)
            ->whereHas('project', fn (Builder $query) => $query->where('status', '!=', ProjectStatus::Closed))
            ->with('project')
            ->orderByRaw('due_date is null')
            ->orderBy('due_date');
    }

    /**
     * Unfinished tasks past their due date, in projects that are still open.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    private function overdue(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', TaskStatus::Done)
            ->whereDate('due_date', '<', today())
            ->whereHas('project', fn (Builder $query) => $query->where('status', '!=', ProjectStatus::Closed));
    }
}
