<?php

namespace App\Http\Controllers;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\ProjectRole;
use App\Enums\RiskLevel;
use App\Enums\RiskStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use BackedEnum;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * KPI / progress dashboard (FR-020) and EVM (FR-022) of one project. Every figure is
 * aggregated from the database on request; nothing is entered by hand.
 */
class ProjectDashboardController extends Controller
{
    public function index(Project $project): View
    {
        Gate::authorize('viewDashboard', $project);

        $taskCounts = $this->countBy($project->tasks()->toBase(), 'status', TaskStatus::cases());
        $totalTasks = array_sum($taskCounts);
        $doneTasks = $taskCounts[TaskStatus::Done->value];
        $evm = $project->earnedValue();

        return view('dashboard.project', [
            'project' => $project,
            'taskCounts' => $taskCounts,
            'totalTasks' => $totalTasks,
            'progress' => $totalTasks > 0 ? (int) round($doneTasks / $totalTasks * 100) : 0,
            'overdueTasks' => $project->tasks()
                ->whereDate('due_date', '<', today())
                ->where('status', '!=', TaskStatus::Done)
                ->with('assignee')
                ->orderBy('due_date')
                ->get(),
            'memberStats' => $this->memberStats($project),
            'milestones' => $project->milestones()
                ->withCount([
                    'tasks',
                    'tasks as done_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done),
                ])
                ->orderBy('target_date')
                ->get(),
            'riskCounts' => $this->countBy($project->risks()->toBase(), 'status', RiskStatus::cases()),
            'severeRisks' => $project->risks()
                ->where('status', '!=', RiskStatus::Closed)
                ->whereIn('risk_level', [RiskLevel::High, RiskLevel::Critical])
                ->count(),
            'riskMatrix' => $project->riskMatrix(),
            'issueCounts' => $this->countBy($project->issues()->toBase(), 'status', IssueStatus::cases()),
            'criticalIssues' => $project->issues()
                ->whereIn('status', [IssueStatus::Open, IssueStatus::InProgress])
                ->where('severity', IssueSeverity::Critical)
                ->count(),
            'statuses' => TaskStatus::cases(),
            'evm' => $evm,
            'evmCurve' => $evm['bac'] > 0 ? $project->earnedValueCurve() : null,
        ]);
    }

    /**
     * Count rows per value of an enum column, including zero counts.
     *
     * @param  list<BackedEnum>  $cases
     * @return array<string, int>
     */
    private function countBy(QueryBuilder $query, string $column, array $cases): array
    {
        $counts = $query->selectRaw("{$column} as value, count(*) as total")->groupBy($column)->pluck('total', 'value');

        return collect($cases)->mapWithKeys(fn (BackedEnum $case) => [$case->value => (int) ($counts[$case->value] ?? 0)])->all();
    }

    /**
     * Task status distribution per assignee: every Project Manager and Team
     * Member of the project, plus an "unassigned" row when relevant.
     *
     * @return Collection<int, array{name: string, counts: array<string, int>, total: int, open: int}>
     */
    private function memberStats(Project $project): Collection
    {
        $rows = $project->tasks()
            ->toBase()
            ->selectRaw('assigned_to, status, count(*) as total')
            ->groupBy('assigned_to', 'status')
            ->get()
            ->groupBy(fn ($row) => (string) $row->assigned_to);

        $row = function (string $name, ?Collection $statusRows): array {
            $counts = collect(TaskStatus::cases())->mapWithKeys(fn (TaskStatus $status) => [
                $status->value => (int) ($statusRows?->firstWhere('status', $status->value)?->total ?? 0),
            ])->all();

            return [
                'name' => $name,
                'counts' => $counts,
                'total' => array_sum($counts),
                'open' => array_sum($counts) - $counts[TaskStatus::Done->value],
            ];
        };

        $stats = $project->members()
            ->wherePivotIn('role_in_project', [ProjectRole::ProjectManager->value, ProjectRole::TeamMember->value])
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn ($member) => $row($member->name, $rows->get((string) $member->id)));

        if ($rows->has('')) {
            $stats->push($row('Belum di-assign', $rows->get('')));
        }

        return $stats->sortByDesc('total')->values();
    }
}
