<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Requests\CloseProjectRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /**
     * Fields compared when logging an update.
     *
     * @var list<string>
     */
    private const AUDITED_FIELDS = ['name', 'code', 'description', 'start_date', 'end_date', 'sponsor'];

    /**
     * Projects the user is a member of (all projects for an Administrator), FR-007.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Project::class);

        $user = $request->user();
        $filters = [
            'search' => $request->string('search')->trim()->limit(100, '')->toString(),
            'status' => ProjectStatus::tryFrom($request->string('status')->toString())?->value ?? '',
        ];

        $projects = Project::query()
            ->visibleTo($user)
            ->with(['projectMembers' => fn ($query) => $query->where('user_id', $user->id)])
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done),
                'projectMembers as members_count',
            ])
            ->when($filters['search'], function ($query, string $search): void {
                $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"));
            })
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'filters' => $filters,
            'statuses' => ProjectStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Project::class);

        return view('projects.create', [
            'suggestedCode' => Project::generateCode(),
            'managers' => $request->user()->isAdmin()
                ? User::projectManagerCandidates()->orderBy('name')->get(['id', 'name', 'email'])
                : null,
        ]);
    }

    /**
     * A Project Manager creating a project becomes its manager (FR-006); an
     * Administrator appoints one, so the project shows up for that manager.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $user = $request->user();
        $manager = $user->isAdmin() ? User::findOrFail($request->validated('manager_id')) : $user;

        $project = DB::transaction(function () use ($request, $user, $manager): Project {
            $project = Project::create([
                ...$request->safe()->only(['name', 'description', 'start_date', 'end_date', 'sponsor']),
                'code' => $request->validated('code') ?: Project::generateCode(),
                'status' => ProjectStatus::Planning,
                'created_by' => $user->id,
            ]);

            $project->members()->attach($manager->id, ['role_in_project' => ProjectRole::ProjectManager->value]);

            ActivityLog::record(ActivityAction::Create, $project, "Membuat proyek; {$manager->name} menjadi Project Manager", newValues: [
                ...$project->only(self::AUDITED_FIELDS),
                'status' => ProjectStatus::Planning->value,
            ]);

            return $project;
        });

        return redirect()->route('projects.show', $project)->with('success', "Proyek {$project->name} berhasil dibuat.");
    }

    /**
     * Project overview: details, members and a summary of every module.
     */
    public function show(Request $request, Project $project): View
    {
        Gate::authorize('view', $project);

        $project->load([
            'creator',
            'projectMembers' => fn ($query) => $query->with('user')->orderByRaw('role_in_project = ? desc', [ProjectRole::ProjectManager->value])->orderBy('joined_at'),
        ])->loadCollaboration();

        $taskCounts = $project->tasks()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $totalTasks = $taskCounts->sum();

        return view('projects.show', [
            'project' => $project,
            'myRole' => $request->user()->projectRole($project),
            'taskCounts' => collect(TaskStatus::cases())->mapWithKeys(fn (TaskStatus $status) => [$status->value => (int) ($taskCounts[$status->value] ?? 0)]),
            'totalTasks' => $totalTasks,
            'progress' => $totalTasks > 0 ? (int) round(($taskCounts[TaskStatus::Done->value] ?? 0) / $totalTasks * 100) : 0,
            'summary' => $project->openItemSummary(),
        ]);
    }

    public function edit(Project $project): View
    {
        Gate::authorize('update', $project);

        return view('projects.edit', [
            'project' => $project,
            'statusOptions' => [$project->status, ...$project->status->allowedTransitions()],
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project): void {
            $oldStatus = $project->status;

            $project->fill($request->safe()->only([...self::AUDITED_FIELDS, 'status']));
            $changes = array_intersect_key($project->getDirty(), array_flip(self::AUDITED_FIELDS));
            $old = $this->formatDates(array_intersect_key($project->getOriginal(), $changes));
            $project->save();

            if ($changes !== []) {
                ActivityLog::record(ActivityAction::Update, $project, 'Memperbarui data proyek', $old, $this->formatDates($project->only(array_keys($changes))));
            }

            if ($project->status !== $oldStatus) {
                ActivityLog::record(
                    ActivityAction::StatusChange,
                    $project,
                    "Mengubah status proyek: {$oldStatus->value} → {$project->status->value}",
                    ['status' => $oldStatus->value],
                    ['status' => $project->status->value],
                );
            }
        });

        return redirect()->route('projects.show', $project)->with('success', 'Proyek berhasil diperbarui.');
    }

    /**
     * Closing checklist: open items are shown as a warning, not a blocker (FR-021).
     */
    public function closeForm(Project $project): View
    {
        Gate::authorize('close', $project);

        return view('projects.close', [
            'project' => $project,
            'summary' => $project->openItemSummary(),
        ]);
    }

    public function close(CloseProjectRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project): void {
            $oldStatus = $project->status;

            $project->update([
                'status' => ProjectStatus::Closed,
                'closing_notes' => $request->validated('closing_notes'),
                'closing_summary' => $project->buildClosingSummary($request->user()->name),
                'closed_at' => now(),
            ]);

            ActivityLog::record(
                ActivityAction::StatusChange,
                $project,
                'Menutup proyek',
                ['status' => $oldStatus->value],
                ['status' => ProjectStatus::Closed->value, 'closing_notes' => $project->closing_notes],
            );
        });

        return redirect()->route('projects.show', $project)->with('success', "Proyek {$project->name} telah ditutup.");
    }

    /**
     * Printable closing report (FR-021): the snapshot taken when the project
     * was closed. Projects closed before snapshots existed fall back to the
     * current data.
     */
    public function closingReport(Project $project): View
    {
        Gate::authorize('viewClosingReport', $project);

        $report = $project->closing_summary ?? $project->buildClosingSummary(
            $project->activityLogs()->where('action', ActivityAction::StatusChange)->where('description', 'Menutup proyek')->latest('id')->value('user_name') ?? $project->creator->name,
            $project->closed_at,
        );

        return view('projects.closing-report', [
            'project' => $project,
            'report' => $report,
            'isSnapshot' => $project->closing_summary !== null,
        ]);
    }

    /**
     * Administrator only: bring a closed project back to In Progress.
     */
    public function reopen(Project $project): RedirectResponse
    {
        Gate::authorize('reopen', $project);

        DB::transaction(function () use ($project): void {
            $old = ['status' => $project->status->value, 'closing_notes' => $project->closing_notes];

            $project->update([
                'status' => ProjectStatus::InProgress,
                'closing_notes' => null,
                'closing_summary' => null,
                'closed_at' => null,
            ]);

            ActivityLog::record(ActivityAction::StatusChange, $project, 'Membuka kembali proyek', $old, ['status' => ProjectStatus::InProgress->value]);
        });

        return redirect()->route('projects.show', $project)->with('success', "Proyek {$project->name} dibuka kembali.");
    }

    /**
     * Store dates in the audit trail as plain Y-m-d strings.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function formatDates(array $values): array
    {
        return array_map(fn ($value) => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value, $values);
    }
}
