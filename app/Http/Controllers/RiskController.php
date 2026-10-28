<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\ProjectRole;
use App\Enums\RiskLevel;
use App\Enums\RiskRating;
use App\Enums\RiskResponse;
use App\Enums\RiskStatus;
use App\Http\Requests\StoreRiskRequest;
use App\Http\Requests\UpdateRiskRequest;
use App\Http\Requests\UpdateRiskStatusRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Risk;
use App\Notifications\RiskAssigned;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RiskController extends Controller
{
    /**
     * Suggested categories offered next to the ones already used in the project.
     *
     * @var list<string>
     */
    private const DEFAULT_CATEGORIES = ['Teknis', 'Jadwal', 'Biaya', 'Sumber Daya', 'Eksternal', 'Organisasi'];

    /**
     * Risk register with filters and a probability × impact matrix (FR-014).
     */
    public function index(Request $request, Project $project): View
    {
        Gate::authorize('viewAny', [Risk::class, $project]);

        $filters = [
            'search' => $request->string('search')->trim()->limit(100, '')->toString(),
            'status' => RiskStatus::tryFrom($request->string('status')->toString())?->value ?? '',
            'category' => $request->string('category')->trim()->limit(100, '')->toString(),
            'level' => RiskLevel::tryFrom($request->string('level')->toString())?->value ?? '',
            'probability' => RiskRating::tryFrom($request->string('probability')->toString())?->value ?? '',
            'impact' => RiskRating::tryFrom($request->string('impact')->toString())?->value ?? '',
        ];

        $risks = $project->risks()
            ->with('owner')
            ->when($filters['search'], fn ($query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category'], fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['level'], fn ($query, string $level) => $query->where('risk_level', $level))
            ->when($filters['probability'], fn ($query, string $probability) => $query->where('probability', $probability))
            ->when($filters['impact'], fn ($query, string $impact) => $query->where('impact', $impact))
            ->orderByDesc('risk_score')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('risks.index', [
            'project' => $project,
            'risks' => $risks,
            'filters' => $filters,
            'matrix' => $project->riskMatrix(),
            'categories' => $this->usedCategories($project),
        ]);
    }

    public function create(Project $project): View
    {
        Gate::authorize('create', [Risk::class, $project]);

        return view('risks.create', ['project' => $project, ...$this->formOptions($project)]);
    }

    public function store(StoreRiskRequest $request, Project $project): RedirectResponse
    {
        $risk = DB::transaction(function () use ($request, $project): Risk {
            $risk = $project->risks()->create([...$request->validated(), 'status' => RiskStatus::Open]);

            ActivityLog::record(ActivityAction::Create, $risk, 'Membuat risiko', newValues: $this->auditValues($risk));

            return $risk;
        });

        if ($risk->owner) {
            $this->notifyUsers([$risk->owner], new RiskAssigned($risk, $request->user()));
        }

        return redirect()->route('projects.risks.show', [$project, $risk])->with('success', 'Risiko berhasil dicatat.');
    }

    public function show(Request $request, Project $project, Risk $risk): View
    {
        $risk->setRelation('project', $project);
        Gate::authorize('view', $risk);

        $risk->load(['owner', 'issues']);

        return view('risks.show', [
            'project' => $project,
            'risk' => $risk,
            'transitions' => array_values(array_filter(
                $risk->status->allowedTransitions(),
                fn (RiskStatus $to) => $request->user()->can('changeStatus', [$risk, $to]),
            )),
            'history' => ActivityLog::where('entity_type', 'Risk')->where('entity_id', $risk->id)->latest('created_at')->latest('id')->get(),
        ]);
    }

    public function edit(Project $project, Risk $risk): View
    {
        $risk->setRelation('project', $project);
        Gate::authorize('update', $risk);

        return view('risks.edit', ['project' => $project, 'risk' => $risk, ...$this->formOptions($project)]);
    }

    /**
     * Update details; the score and level are recalculated by the model (FR-014).
     */
    public function update(UpdateRiskRequest $request, Project $project, Risk $risk): RedirectResponse
    {
        $previousOwner = $risk->owner_id;

        DB::transaction(function () use ($request, $risk): void {
            $before = $this->auditValues($risk);

            $risk->update($request->validated());

            $after = $this->auditValues($risk->refresh());
            $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));

            if ($changed !== []) {
                ActivityLog::record(
                    ActivityAction::Update,
                    $risk,
                    'Memperbarui risiko',
                    array_intersect_key($before, array_flip($changed)),
                    array_intersect_key($after, array_flip($changed)),
                );
            }
        });

        if ($risk->owner_id !== null && $risk->owner_id !== $previousOwner) {
            $this->notifyUsers([$risk->owner], new RiskAssigned($risk, $request->user()));
        }

        return redirect()->route('projects.risks.show', [$project, $risk])->with('success', 'Risiko berhasil diperbarui.');
    }

    public function updateStatus(UpdateRiskStatusRequest $request, Project $project, Risk $risk): RedirectResponse
    {
        $from = $risk->status;
        $to = $request->enum('status', RiskStatus::class);

        DB::transaction(function () use ($risk, $from, $to): void {
            $risk->update(['status' => $to]);

            ActivityLog::record(
                ActivityAction::StatusChange,
                $risk,
                "Mengubah status risiko: {$from->value} → {$to->value}",
                ['status' => $from->value],
                ['status' => $to->value],
            );
        });

        return back()->with('success', "Status risiko diubah menjadi {$to->value}.");
    }

    /**
     * Soft delete (Administrator only per the permission matrix).
     */
    public function destroy(Project $project, Risk $risk): RedirectResponse
    {
        $risk->setRelation('project', $project);
        Gate::authorize('delete', $risk);

        DB::transaction(function () use ($risk): void {
            ActivityLog::record(ActivityAction::Delete, $risk, 'Menghapus risiko', $this->auditValues($risk));
            $risk->delete();
        });

        return redirect()->route('projects.risks.index', $project)->with('success', "Risiko \"{$risk->title}\" dihapus.");
    }

    /**
     * @return array{owners: mixed, categories: list<string>, ratings: list<RiskRating>, responses: list<RiskResponse>}
     */
    private function formOptions(Project $project): array
    {
        return [
            'owners' => $project->members()
                ->wherePivotIn('role_in_project', [ProjectRole::ProjectManager->value, ProjectRole::TeamMember->value])
                ->orderBy('name')
                ->get(['users.id', 'users.name']),
            'categories' => array_values(array_unique([...self::DEFAULT_CATEGORIES, ...$this->usedCategories($project)])),
            'ratings' => RiskRating::cases(),
            'responses' => RiskResponse::cases(),
        ];
    }

    /**
     * @return list<string>
     */
    private function usedCategories(Project $project): array
    {
        return $project->risks()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->all();
    }

    /**
     * Human-readable snapshot of a risk for the activity log.
     *
     * @return array<string, mixed>
     */
    private function auditValues(Risk $risk): array
    {
        $risk->loadMissing('owner');

        return [
            'title' => $risk->title,
            'description' => $risk->description,
            'category' => $risk->category,
            'probability' => $risk->probability->value,
            'impact' => $risk->impact->value,
            'risk_score' => $risk->risk_score,
            'risk_level' => $risk->risk_level->value,
            'response_plan' => $risk->response_plan->value,
            'owner' => $risk->owner?->name,
            'status' => $risk->status->value,
        ];
    }
}
