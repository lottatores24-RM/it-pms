<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\MilestoneStatus;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreMilestoneRequest;
use App\Http\Requests\UpdateMilestoneRequest;
use App\Models\ActivityLog;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MilestoneController extends Controller
{
    /**
     * Milestones with their task progress (FR-012).
     */
    public function index(Project $project): View
    {
        Gate::authorize('viewAny', [Milestone::class, $project]);

        $milestones = $project->milestones()
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done),
            ])
            ->orderBy('target_date')
            ->get()
            ->each->setRelation('project', $project);

        return view('milestones.index', [
            'project' => $project,
            'milestones' => $milestones,
        ]);
    }

    public function create(Project $project): View
    {
        Gate::authorize('create', [Milestone::class, $project]);

        return view('milestones.create', ['project' => $project]);
    }

    public function store(StoreMilestoneRequest $request, Project $project): RedirectResponse
    {
        $milestone = DB::transaction(function () use ($request, $project): Milestone {
            $milestone = $project->milestones()->create([
                ...$request->validated(),
                'status' => MilestoneStatus::Planned,
            ]);

            ActivityLog::record(ActivityAction::Create, $milestone, 'Membuat milestone', newValues: $this->auditValues($milestone));

            return $milestone;
        });

        return redirect()->route('projects.milestones.show', [$project, $milestone])->with('success', 'Milestone berhasil dibuat.');
    }

    /**
     * Linked tasks, a status summary and a completion suggestion (FR-012).
     */
    public function show(Project $project, Milestone $milestone): View
    {
        $milestone->setRelation('project', $project);
        Gate::authorize('view', $milestone);

        $tasks = $milestone->tasks()->with('assignee')->orderBy('status')->orderBy('due_date')->get();
        $taskCounts = collect(TaskStatus::cases())
            ->mapWithKeys(fn (TaskStatus $status) => [$status->value => $tasks->where('status', $status)->count()]);
        $allTasksDone = $tasks->isNotEmpty() && $taskCounts[TaskStatus::Done->value] === $tasks->count();

        return view('milestones.show', [
            'project' => $project,
            'milestone' => $milestone,
            'tasks' => $tasks,
            'taskCounts' => $taskCounts,
            'progress' => $tasks->isEmpty() ? 0 : (int) round($taskCounts[TaskStatus::Done->value] / $tasks->count() * 100),
            'suggestCompletion' => $allTasksDone && $milestone->status === MilestoneStatus::InProgress,
        ]);
    }

    public function edit(Project $project, Milestone $milestone): View
    {
        $milestone->setRelation('project', $project);
        Gate::authorize('update', $milestone);

        return view('milestones.edit', [
            'project' => $project,
            'milestone' => $milestone,
            'statusOptions' => [$milestone->status, ...$milestone->status->allowedTransitions()],
        ]);
    }

    public function update(UpdateMilestoneRequest $request, Project $project, Milestone $milestone): RedirectResponse
    {
        DB::transaction(function () use ($request, $milestone): void {
            $before = $this->auditValues($milestone);
            $oldStatus = $milestone->status;

            $milestone->update($request->safe()->only(['name', 'description', 'target_date', 'status']));

            $after = $this->auditValues($milestone);
            $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));

            if ($changed !== []) {
                ActivityLog::record(
                    ActivityAction::Update,
                    $milestone,
                    'Memperbarui milestone',
                    array_intersect_key($before, array_flip($changed)),
                    array_intersect_key($after, array_flip($changed)),
                );
            }

            if ($milestone->status !== $oldStatus) {
                ActivityLog::record(
                    ActivityAction::StatusChange,
                    $milestone,
                    "Mengubah status milestone: {$oldStatus->value} → {$milestone->status->value}",
                    ['status' => $oldStatus->value],
                    ['status' => $milestone->status->value],
                );
            }
        });

        return redirect()->route('projects.milestones.show', [$project, $milestone])->with('success', 'Milestone berhasil diperbarui.');
    }

    /**
     * Soft delete; linked tasks are detached from the milestone.
     */
    public function destroy(Project $project, Milestone $milestone): RedirectResponse
    {
        $milestone->setRelation('project', $project);
        Gate::authorize('delete', $milestone);

        DB::transaction(function () use ($milestone): void {
            $detached = $milestone->tasks()->update(['milestone_id' => null]);

            ActivityLog::record(
                ActivityAction::Delete,
                $milestone,
                "Menghapus milestone ({$detached} task dilepas dari milestone)",
                $this->auditValues($milestone),
            );

            $milestone->delete();
        });

        return redirect()->route('projects.milestones.index', $project)->with('success', "Milestone \"{$milestone->name}\" dihapus.");
    }

    /**
     * Fields compared when logging changes. Status changes are logged separately.
     *
     * @return array<string, string|null>
     */
    private function auditValues(Milestone $milestone): array
    {
        return [
            'name' => $milestone->name,
            'description' => $milestone->description,
            'target_date' => $milestone->target_date->format('Y-m-d'),
        ];
    }
}
