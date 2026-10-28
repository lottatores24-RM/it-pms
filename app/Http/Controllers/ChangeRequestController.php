<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\ChangeRequestPriority;
use App\Enums\ChangeRequestStatus;
use App\Http\Requests\StoreChangeRequestRequest;
use App\Http\Requests\UpdateChangeRequestRequest;
use App\Http\Requests\UpdateChangeRequestStatusRequest;
use App\Models\ActivityLog;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Notifications\ChangeRequestStatusChanged;
use App\Notifications\ChangeRequestSubmitted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ChangeRequestController extends Controller
{
    /**
     * Change request list, pending decisions and highest priority first.
     * Team Members only see the requests they submitted (FR-016).
     */
    public function index(Request $request, Project $project): View
    {
        Gate::authorize('viewAny', [ChangeRequest::class, $project]);

        $user = $request->user();
        $viewAll = $user->can('viewAll', [ChangeRequest::class, $project]);

        $filters = [
            'search' => $request->string('search')->trim()->limit(100, '')->toString(),
            'status' => ChangeRequestStatus::tryFrom($request->string('status')->toString())?->value ?? '',
            'priority' => ChangeRequestPriority::tryFrom($request->string('priority')->toString())?->value ?? '',
            'mine' => $viewAll && $request->boolean('mine'),
        ];

        $changeRequests = $project->changeRequests()
            ->with(['requester', 'reviewer'])
            ->when(! $viewAll || $filters['mine'], fn ($query) => $query->where('requested_by', $user->id))
            ->when($filters['search'], fn ($query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['priority'], fn ($query, string $priority) => $query->where('priority', $priority))
            ->orderByRaw("case status when 'Submitted' then 0 when 'Under Review' then 1 else 2 end")
            ->orderByRaw("case priority when 'High' then 0 when 'Medium' then 1 else 2 end")
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('change-requests.index', [
            'project' => $project,
            'changeRequests' => $changeRequests,
            'filters' => $filters,
            'viewAll' => $viewAll,
        ]);
    }

    public function create(Project $project): View
    {
        Gate::authorize('create', [ChangeRequest::class, $project]);

        return view('change-requests.create', [
            'project' => $project,
            'priorities' => ChangeRequestPriority::cases(),
        ]);
    }

    /**
     * New requests start as Submitted and the Project Managers are notified.
     */
    public function store(StoreChangeRequestRequest $request, Project $project): RedirectResponse
    {
        $changeRequest = DB::transaction(function () use ($request, $project): ChangeRequest {
            $changeRequest = $project->changeRequests()->create([
                ...$request->validated(),
                'status' => ChangeRequestStatus::Submitted,
                'requested_by' => $request->user()->id,
            ]);

            ActivityLog::record(ActivityAction::Create, $changeRequest, 'Mengajukan change request', newValues: $this->auditValues($changeRequest));

            return $changeRequest;
        });

        $changeRequest->setRelation('project', $project);
        $this->notifyUsers($project->managers()->get(), new ChangeRequestSubmitted($changeRequest, $request->user()));

        return redirect()->route('projects.change-requests.show', [$project, $changeRequest])->with('success', 'Change request berhasil diajukan.');
    }

    public function show(Request $request, Project $project, ChangeRequest $changeRequest): View
    {
        $changeRequest->setRelation('project', $project);
        Gate::authorize('view', $changeRequest);

        $changeRequest->load(['requester', 'reviewer']);

        return view('change-requests.show', [
            'project' => $project,
            'changeRequest' => $changeRequest,
            'transitions' => $request->user()->can('review', $changeRequest) ? $changeRequest->status->allowedTransitions() : [],
            'history' => ActivityLog::where('entity_type', 'ChangeRequest')->where('entity_id', $changeRequest->id)->latest('created_at')->latest('id')->get(),
        ]);
    }

    public function edit(Project $project, ChangeRequest $changeRequest): View
    {
        $changeRequest->setRelation('project', $project);
        Gate::authorize('update', $changeRequest);

        return view('change-requests.edit', [
            'project' => $project,
            'changeRequest' => $changeRequest,
            'priorities' => ChangeRequestPriority::cases(),
        ]);
    }

    public function update(UpdateChangeRequestRequest $request, Project $project, ChangeRequest $changeRequest): RedirectResponse
    {
        DB::transaction(function () use ($request, $changeRequest): void {
            $before = $this->auditValues($changeRequest);

            $changeRequest->update($request->validated());

            $after = $this->auditValues($changeRequest->refresh());
            $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));

            if ($changed !== []) {
                ActivityLog::record(
                    ActivityAction::Update,
                    $changeRequest,
                    'Memperbarui change request',
                    array_intersect_key($before, array_flip($changed)),
                    array_intersect_key($after, array_flip($changed)),
                );
            }
        });

        return redirect()->route('projects.change-requests.show', [$project, $changeRequest])->with('success', 'Change request berhasil diperbarui.');
    }

    /**
     * A review step: the justification is stored on the request (latest
     * decision) and in the activity log (full history).
     */
    public function updateStatus(UpdateChangeRequestStatusRequest $request, Project $project, ChangeRequest $changeRequest): RedirectResponse
    {
        $from = $changeRequest->status;
        $to = $request->enum('status', ChangeRequestStatus::class);
        $justification = $request->string('justification')->trim()->toString();

        DB::transaction(function () use ($request, $changeRequest, $from, $to, $justification): void {
            $changeRequest->update([
                'status' => $to,
                'justification' => $justification,
                'reviewed_by' => $request->user()->id,
            ]);

            ActivityLog::record(
                ActivityAction::StatusChange,
                $changeRequest,
                "Mengubah status change request: {$from->value} → {$to->value}",
                ['status' => $from->value],
                ['status' => $to->value, 'justification' => $justification],
            );
        });

        $this->notifyUsers(
            [...$project->managers()->get(), $changeRequest->requester],
            new ChangeRequestStatusChanged($changeRequest, $request->user(), $from, $to),
        );

        return back()->with('success', "Status change request diubah menjadi {$to->value}.");
    }

    public function destroy(Project $project, ChangeRequest $changeRequest): RedirectResponse
    {
        $changeRequest->setRelation('project', $project);
        Gate::authorize('delete', $changeRequest);

        DB::transaction(function () use ($changeRequest): void {
            ActivityLog::record(ActivityAction::Delete, $changeRequest, 'Menghapus change request', $this->auditValues($changeRequest));
            $changeRequest->delete();
        });

        return redirect()->route('projects.change-requests.index', $project)->with('success', "Change request \"{$changeRequest->title}\" dihapus.");
    }

    /**
     * Snapshot of a change request for the activity log.
     *
     * @return array<string, mixed>
     */
    private function auditValues(ChangeRequest $changeRequest): array
    {
        return [
            'title' => $changeRequest->title,
            'description' => $changeRequest->description,
            'reason' => $changeRequest->reason,
            'impact_scope' => $changeRequest->impact_scope,
            'impact_schedule' => $changeRequest->impact_schedule,
            'impact_resource' => $changeRequest->impact_resource,
            'priority' => $changeRequest->priority->value,
            'status' => $changeRequest->status->value,
        ];
    }
}
