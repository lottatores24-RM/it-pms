<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\ProjectRole;
use App\Http\Requests\StoreIssueRequest;
use App\Http\Requests\UpdateIssueRequest;
use App\Http\Requests\UpdateIssueStatusRequest;
use App\Models\ActivityLog;
use App\Models\Issue;
use App\Models\Project;
use App\Notifications\IssueAssigned;
use App\Notifications\IssueStatusChanged;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class IssueController extends Controller
{
    /**
     * Issue list, open and most severe first (FR-015).
     */
    public function index(Request $request, Project $project): View
    {
        Gate::authorize('viewAny', [Issue::class, $project]);

        $filters = [
            'search' => $request->string('search')->trim()->limit(100, '')->toString(),
            'status' => IssueStatus::tryFrom($request->string('status')->toString())?->value ?? '',
            'severity' => IssueSeverity::tryFrom($request->string('severity')->toString())?->value ?? '',
            'assignee' => $request->string('assignee')->toString(),
        ];

        $issues = $project->issues()
            ->with(['assignee', 'reporter'])
            ->when($filters['search'], fn ($query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['severity'], fn ($query, string $severity) => $query->where('severity', $severity))
            ->when($filters['assignee'] === 'me', fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->when($filters['assignee'] === 'reported', fn ($query) => $query->where('reported_by', $request->user()->id))
            ->when($filters['assignee'] === 'none', fn ($query) => $query->whereNull('assigned_to'))
            ->orderByRaw("case status when 'Open' then 0 when 'In Progress' then 1 when 'Resolved' then 2 else 3 end")
            ->orderByRaw("case severity when 'Critical' then 0 when 'High' then 1 when 'Medium' then 2 else 3 end")
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('issues.index', [
            'project' => $project,
            'issues' => $issues,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, Project $project): View
    {
        Gate::authorize('create', [Issue::class, $project]);

        return view('issues.create', [
            'project' => $project,
            'canAssign' => $request->user()->can('assign', [Issue::class, $project]),
            'selectedTask' => $request->integer('task') ?: null,
            ...$this->formOptions($project),
        ]);
    }

    /**
     * Any Project Manager or Team Member may report an issue (FR-015).
     */
    public function store(StoreIssueRequest $request, Project $project): RedirectResponse
    {
        $issue = DB::transaction(function () use ($request, $project): Issue {
            $issue = $project->issues()->create([
                ...$request->validated(),
                'status' => IssueStatus::Open,
                'reported_by' => $request->user()->id,
            ]);

            ActivityLog::record(ActivityAction::Create, $issue, 'Melaporkan issue', newValues: $this->auditValues($issue));

            return $issue;
        });

        if ($issue->assignee) {
            $this->notifyUsers([$issue->assignee], new IssueAssigned($issue, $request->user()));
        }

        return redirect()->route('projects.issues.show', [$project, $issue])->with('success', 'Issue berhasil dilaporkan.');
    }

    public function show(Request $request, Project $project, Issue $issue): View
    {
        $issue->setRelation('project', $project);
        Gate::authorize('view', $issue);

        $issue->load(['assignee', 'reporter', 'relatedTask', 'relatedRisk'])->loadCollaboration();

        return view('issues.show', [
            'project' => $project,
            'issue' => $issue,
            'transitions' => array_values(array_filter(
                $issue->status->allowedTransitions(),
                fn (IssueStatus $to) => $request->user()->can('updateStatus', [$issue, $to]),
            )),
            'history' => ActivityLog::where('entity_type', 'Issue')->where('entity_id', $issue->id)->latest('created_at')->latest('id')->get(),
        ]);
    }

    public function edit(Project $project, Issue $issue): View
    {
        $issue->setRelation('project', $project);
        Gate::authorize('update', $issue);

        return view('issues.edit', [
            'project' => $project,
            'issue' => $issue,
            'canAssign' => true,
            'selectedTask' => null,
            ...$this->formOptions($project),
        ]);
    }

    public function update(UpdateIssueRequest $request, Project $project, Issue $issue): RedirectResponse
    {
        $previousAssignee = $issue->assigned_to;

        DB::transaction(function () use ($request, $issue): void {
            $before = $this->auditValues($issue);

            $issue->update($request->validated());

            $after = $this->auditValues($issue->refresh());
            $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));

            if ($changed !== []) {
                ActivityLog::record(
                    ActivityAction::Update,
                    $issue,
                    'Memperbarui issue',
                    array_intersect_key($before, array_flip($changed)),
                    array_intersect_key($after, array_flip($changed)),
                );
            }
        });

        if ($issue->assigned_to !== null && $issue->assigned_to !== $previousAssignee) {
            $this->notifyUsers([$issue->assignee], new IssueAssigned($issue, $request->user()));
        }

        return redirect()->route('projects.issues.show', [$project, $issue])->with('success', 'Issue berhasil diperbarui.');
    }

    /**
     * Move an issue through its workflow; managers, assignee and reporter are notified.
     */
    public function updateStatus(UpdateIssueStatusRequest $request, Project $project, Issue $issue): RedirectResponse
    {
        $from = $issue->status;
        $to = $request->enum('status', IssueStatus::class);

        DB::transaction(function () use ($issue, $from, $to): void {
            $issue->update(['status' => $to]);

            ActivityLog::record(
                ActivityAction::StatusChange,
                $issue,
                "Mengubah status issue: {$from->value} → {$to->value}",
                ['status' => $from->value],
                ['status' => $to->value],
            );
        });

        $this->notifyUsers(
            [...$project->managers()->get(), $issue->assignee, $issue->reporter],
            new IssueStatusChanged($issue, $request->user(), $from, $to),
        );

        return back()->with('success', "Status issue diubah menjadi {$to->value}.");
    }

    public function destroy(Project $project, Issue $issue): RedirectResponse
    {
        $issue->setRelation('project', $project);
        Gate::authorize('delete', $issue);

        DB::transaction(function () use ($issue): void {
            ActivityLog::record(ActivityAction::Delete, $issue, 'Menghapus issue', $this->auditValues($issue));
            $issue->delete();
        });

        return redirect()->route('projects.issues.index', $project)->with('success', "Issue \"{$issue->title}\" dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Project $project): array
    {
        return [
            'assignees' => $project->members()
                ->wherePivotIn('role_in_project', [ProjectRole::ProjectManager->value, ProjectRole::TeamMember->value])
                ->orderBy('name')
                ->get(['users.id', 'users.name']),
            'tasks' => $project->tasks()->orderBy('title')->get(['id', 'title']),
            'risks' => $project->risks()->orderBy('title')->get(['id', 'title']),
            'severities' => IssueSeverity::cases(),
        ];
    }

    /**
     * Human-readable snapshot of an issue for the activity log.
     *
     * @return array<string, mixed>
     */
    private function auditValues(Issue $issue): array
    {
        $issue->loadMissing(['assignee', 'relatedTask', 'relatedRisk']);

        return [
            'title' => $issue->title,
            'description' => $issue->description,
            'severity' => $issue->severity->value,
            'assigned_to' => $issue->assignee?->name,
            'related_task' => $issue->relatedTask?->title,
            'related_risk' => $issue->relatedRisk?->title,
            'status' => $issue->status->value,
        ];
    }
}
