<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Concerns\ChangesTaskStatus;
use App\Http\Requests\MoveKanbanTaskRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Kanban board of a project's tasks (FR-017).
 */
class KanbanController extends Controller
{
    use ChangesTaskStatus;

    public function index(Request $request, Project $project): View
    {
        Gate::authorize('viewKanban', $project);

        $user = $request->user();
        $filters = [
            'assignee' => $request->string('assignee')->toString(),
            'priority' => TaskPriority::tryFrom($request->string('priority')->toString())?->value ?? '',
        ];

        $tasks = $project->tasks()
            ->with('assignee')
            ->when($filters['assignee'] === 'me', fn ($query) => $query->where('assigned_to', $user->id))
            ->when($filters['assignee'] === 'none', fn ($query) => $query->whereNull('assigned_to'))
            ->when(ctype_digit($filters['assignee']), fn ($query) => $query->where('assigned_to', (int) $filters['assignee']))
            ->when($filters['priority'], fn ($query, string $priority) => $query->where('priority', $priority))
            ->orderByRaw("case priority when 'Critical' then 0 when 'High' then 1 when 'Medium' then 2 else 3 end")
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->get()
            ->each->setRelation('project', $project);

        $columns = collect(TaskStatus::cases())->mapWithKeys(fn (TaskStatus $status) => [
            $status->value => $tasks->where('status', $status)->values(),
        ]);

        return view('kanban.index', [
            'project' => $project,
            'columns' => $columns,
            'filters' => $filters,
            'members' => $project->members()->orderBy('name')->get(['users.id', 'users.name']),
            'transitions' => $tasks->mapWithKeys(fn (Task $task) => [
                $task->id => array_map(
                    fn (TaskStatus $status) => $status->value,
                    $this->transitionsFor($user, $task, 'moveOnKanban'),
                ),
            ]),
        ]);
    }

    /**
     * Drop a card into another column. Always answers JSON.
     */
    public function move(MoveKanbanTaskRequest $request, Project $project, Task $task): JsonResponse
    {
        $task->setRelation('project', $project);
        $to = $request->enum('status', TaskStatus::class);

        $this->changeTaskStatus($project, $task, $to, $request->user());

        return response()->json([
            'message' => "\"{$task->title}\" dipindahkan ke {$to->value}.",
            'status' => $to->value,
            'transitions' => array_map(
                fn (TaskStatus $status) => $status->value,
                $this->transitionsFor($request->user(), $task->refresh()->setRelation('project', $project), 'moveOnKanban'),
            ),
        ]);
    }
}
