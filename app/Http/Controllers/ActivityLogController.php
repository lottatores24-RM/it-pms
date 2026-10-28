<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityLogFilterRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only views of the append-only audit trail (FR-019, NFR-008).
 */
class ActivityLogController extends Controller
{
    /**
     * Global log across all projects (Administrator).
     */
    public function index(ActivityLogFilterRequest $request): View
    {
        Gate::authorize('viewAny', ActivityLog::class);

        $filters = $request->filters();

        return view('activity-logs.index', [
            'logs' => $this->query($filters)->with('project')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'projects' => Project::orderBy('name')->get(['id', 'name', 'code']),
            'entityTypes' => $this->entityTypes(ActivityLog::query()),
        ]);
    }

    public function export(ActivityLogFilterRequest $request): StreamedResponse
    {
        Gate::authorize('viewAny', ActivityLog::class);

        return $this->csv($this->query($request->filters()), 'activity-log-'.now()->format('Ymd-His').'.csv');
    }

    /**
     * Log of a single project (its Project Manager, or an Administrator).
     */
    public function project(ActivityLogFilterRequest $request, Project $project): View
    {
        Gate::authorize('viewProject', [ActivityLog::class, $project]);

        $filters = ['project_id' => $project->id, ...$this->withoutProject($request->filters())];

        return view('activity-logs.project', [
            'project' => $project,
            'logs' => $this->query($filters)->paginate(25)->withQueryString(),
            'filters' => $filters,
            'users' => User::whereIn('id', $project->activityLogs()->select('user_id'))->orderBy('name')->get(['id', 'name']),
            'entityTypes' => $this->entityTypes($project->activityLogs()->getQuery()),
        ]);
    }

    public function projectExport(ActivityLogFilterRequest $request, Project $project): StreamedResponse
    {
        Gate::authorize('viewProject', [ActivityLog::class, $project]);

        $filters = ['project_id' => $project->id, ...$this->withoutProject($request->filters())];

        return $this->csv($this->query($filters), "activity-log-{$project->code}-".now()->format('Ymd-His').'.csv');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ActivityLog>
     */
    private function query(array $filters): Builder
    {
        return ActivityLog::query()->filteredBy($filters)->latest('created_at')->latest('id');
    }

    /**
     * A project page must never be widened to another project via the query string.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function withoutProject(array $filters): array
    {
        unset($filters['project_id']);

        return $filters;
    }

    /**
     * @param  Builder<ActivityLog>  $query
     * @return list<string>
     */
    private function entityTypes(Builder $query): array
    {
        return $query->clone()->reorder()->distinct()->orderBy('entity_type')->pluck('entity_type')->all();
    }

    /**
     * Stream the filtered log as CSV (NFR-008). A UTF-8 BOM keeps Excel happy.
     *
     * @param  Builder<ActivityLog>  $query
     */
    private function csv(Builder $query, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Waktu', 'User', 'Aksi', 'Entitas', 'ID Entitas', 'Nama Entitas', 'ID Proyek', 'Deskripsi', 'Nilai Lama', 'Nilai Baru', 'IP']);

            $query->reorder()->lazyByIdDesc(500)->each(function (ActivityLog $log) use ($handle): void {
                fputcsv($handle, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user_name,
                    $log->action->label(),
                    $log->entityLabel(),
                    $log->entity_id,
                    $log->entity_name,
                    $log->project_id,
                    $log->description,
                    $log->old_values === null ? '' : json_encode($log->old_values, JSON_UNESCAPED_UNICODE),
                    $log->new_values === null ? '' : json_encode($log->new_values, JSON_UNESCAPED_UNICODE),
                    $log->ip_address,
                ]);
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
