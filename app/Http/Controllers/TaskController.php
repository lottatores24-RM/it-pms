<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Concerns\ChangesTaskStatus;
use App\Http\Requests\RecordTaskEffortRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TaskController extends Controller
{
    use ChangesTaskStatus;

    /**
     * All tasks of the project with filters (FR-010).
     */
    public function index(Request $request, Project $project): View
    {
        Gate::authorize('viewAny', [Task::class, $project]);

        $filters = [
            'search' => $request->string('search')->trim()->limit(100, '')->toString(),
            'status' => TaskStatus::tryFrom($request->string('status')->toString())?->value ?? '',
            'priority' => TaskPriority::tryFrom($request->string('priority')->toString())?->value ?? '',
            'assignee' => $request->string('assignee')->toString(),
            'due' => in_array($request->query('due'), ['overdue', 'week', 'none'], true) ? $request->query('due') : '',
        ];

        $tasks = $project->tasks()
            ->with(['assignee', 'milestone'])
            ->when($filters['search'], fn ($query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['priority'], fn ($query, string $priority) => $query->where('priority', $priority))
            ->when($filters['assignee'] === 'me', fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->when($filters['assignee'] === 'none', fn ($query) => $query->whereNull('assigned_to'))
            ->when(ctype_digit($filters['assignee']), fn ($query) => $query->where('assigned_to', (int) $filters['assignee']))
            ->when($filters['due'] === 'overdue', fn ($query) => $query->whereDate('due_date', '<', today())->where('status', '!=', TaskStatus::Done))
            ->when($filters['due'] === 'week', fn ($query) => $query->whereBetween('due_date', [today(), today()->addDays(7)]))
            ->when($filters['due'] === 'none', fn ($query) => $query->whereNull('due_date'))
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $tasks->getCollection()->each->setRelation('project', $project);

        return view('tasks.index', [
            'project' => $project,
            'tasks' => $tasks,
            'filters' => $filters,
            'members' => $project->members()->orderBy('name')->get(['users.id', 'users.name']),
        ]);
    }

    public function create(Project $project): View
    {
        Gate::authorize('create', [Task::class, $project]);

        return view('tasks.create', [
            'project' => $project,
            ...$this->formOptions($project),
        ]);
    }

    /**
     * Create a task; new tasks always start as To Do (FR-009).
     */
    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $task = DB::transaction(function () use ($request, $project): Task {
            $task = $project->tasks()->create([
                ...$request->validated(),
                'status' => TaskStatus::ToDo,
            ]);

            ActivityLog::record(ActivityAction::Create, $task, 'Membuat task', newValues: $this->auditValues($task));

            return $task;
        });

        if ($task->assignee) {
            $this->notifyUsers([$task->assignee], new TaskAssigned($task, $request->user()));
        }

        return redirect()->route('projects.tasks.show', [$project, $task])->with('success', 'Task berhasil dibuat.');
    }

    public function show(Request $request, Project $project, Task $task): View
    {
        $task->setRelation('project', $project);
        Gate::authorize('view', $task);

        $task->load(['assignee', 'milestone', 'issues'])->loadCollaboration();

        return view('tasks.show', [
            'project' => $project,
            'task' => $task,
            'transitions' => $this->transitionsFor($request->user(), $task),
            'history' => ActivityLog::where('entity_type', 'Task')->where('entity_id', $task->id)->latest('created_at')->latest('id')->get(),
        ]);
    }

    public function edit(Project $project, Task $task): View
    {
        $task->setRelation('project', $project);
        Gate::authorize('update', $task);

        return view('tasks.edit', [
            'project' => $project,
            'task' => $task,
            ...$this->formOptions($project),
        ]);
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $previousAssignee = $task->assigned_to;

        DB::transaction(function () use ($request, $task): void {
            $before = $this->auditValues($task);

            $task->update($request->validated());

            $after = $this->auditValues($task->refresh());
            $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));

            if ($changed !== []) {
                ActivityLog::record(
                    ActivityAction::Update,
                    $task,
                    'Memperbarui task',
                    array_intersect_key($before, array_flip($changed)),
                    array_intersect_key($after, array_flip($changed)),
                );
            }
        });

        if ($task->assigned_to !== null && $task->assigned_to !== $previousAssignee) {
            $this->notifyUsers([$task->assignee], new TaskAssigned($task, $request->user()));
        }

        return redirect()->route('projects.tasks.show', [$project, $task])->with('success', 'Task berhasil diperbarui.');
    }

    /**
     * Soft delete; the audit trail keeps the task's last state (FR-010).
     */
    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $task->setRelation('project', $project);
        Gate::authorize('delete', $task);

        DB::transaction(function () use ($task): void {
            ActivityLog::record(ActivityAction::Delete, $task, 'Menghapus task', $this->auditValues($task));
            $task->delete();
        });

        return redirect()->route('projects.tasks.index', $project)->with('success', "Task \"{$task->title}\" dihapus.");
    }

    /**
     * Move a task through the workflow (FR-011). Answers JSON for the Kanban board.
     */
    public function updateStatus(UpdateTaskStatusRequest $request, Project $project, Task $task): RedirectResponse|JsonResponse
    {
        $to = $request->enum('status', TaskStatus::class);

        $this->changeTaskStatus($project, $task, $to, $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Status task diubah menjadi {$to->value}.",
                'status' => $to->value,
            ]);
        }

        return back()->with('success', "Status task diubah menjadi {$to->value}.");
    }

    /**
     * Record the effort actually spent, the Actual Cost of EVM (FR-022).
     */
    public function recordEffort(RecordTaskEffortRequest $request, Project $project, Task $task): RedirectResponse
    {
        $old = $task->actual_effort_points;
        $task->actual_effort_points = $request->validated('actual_effort_points');

        if ($task->isDirty('actual_effort_points')) {
            DB::transaction(function () use ($task, $old): void {
                $task->save();

                ActivityLog::record(
                    ActivityAction::Update,
                    $task,
                    'Mencatat effort aktual task',
                    ['actual_effort_points' => $old],
                    ['actual_effort_points' => $task->actual_effort_points],
                );
            });
        }

        return back()->with('success', 'Effort aktual task disimpan.');
    }

    /**
     * Options for the create/edit form.
     *
     * @return array{assignees: Collection<int, User>, milestones: Collection<int, mixed>, priorities: list<TaskPriority>}
     */
    private function formOptions(Project $project): array
    {
        return [
            'assignees' => $project->members()
                ->wherePivotIn('role_in_project', [ProjectRole::ProjectManager->value, ProjectRole::TeamMember->value])
                ->orderBy('name')
                ->get(['users.id', 'users.name']),
            'milestones' => $project->milestones()->orderBy('target_date')->get(['id', 'name', 'target_date']),
            'priorities' => TaskPriority::cases(),
        ];
    }

    /**
     * Human-readable snapshot of a task for the activity log.
     *
     * @return array<string, mixed>
     */
    private function auditValues(Task $task): array
    {
        $task->loadMissing(['assignee', 'milestone']);

        return [
            'title' => $task->title,
            'description' => $task->description,
            'assigned_to' => $task->assignee?->name,
            'priority' => $task->priority->value,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'milestone' => $task->milestone?->name,
            'effort_points' => $task->effort_points,
            'actual_effort_points' => $task->actual_effort_points,
            'status' => $task->status->value,
        ];
    }
}
