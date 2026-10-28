<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreProjectMemberRequest;
use App\Http\Requests\UpdateProjectMemberRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectMemberController extends Controller
{
    /**
     * Members with their role, join date and active task count (FR-008).
     */
    public function index(Project $project): View
    {
        Gate::authorize('view', $project);
        Gate::authorize('viewAny', [ProjectMember::class, $project]);

        $members = $project->projectMembers()
            ->with('user')
            ->orderByRaw('role_in_project = ? desc', [ProjectRole::ProjectManager->value])
            ->orderBy('joined_at')
            ->get()
            ->each->setRelation('project', $project);

        $activeTaskCounts = $project->tasks()
            ->where('status', '!=', TaskStatus::Done)
            ->whereNotNull('assigned_to')
            ->toBase()
            ->selectRaw('assigned_to, count(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $canAdd = Gate::allows('create', [ProjectMember::class, $project]);

        return view('members.index', [
            'project' => $project,
            'members' => $members,
            'activeTaskCounts' => $activeTaskCounts,
            'canAdd' => $canAdd,
            'candidates' => $canAdd
                ? User::active()->whereDoesntHave('projectMemberships', fn ($query) => $query->where('project_id', $project->id))->orderBy('name')->get(['id', 'name', 'email'])
                : collect(),
            'roles' => ProjectRole::cases(),
            'managerCount' => $members->where('role_in_project', ProjectRole::ProjectManager)->count(),
        ]);
    }

    public function store(StoreProjectMemberRequest $request, Project $project): RedirectResponse
    {
        $member = DB::transaction(function () use ($request, $project): ProjectMember {
            $member = $project->projectMembers()->create([
                'user_id' => $request->validated('user_id'),
                'role_in_project' => $request->validated('role_in_project'),
                'joined_at' => now(),
            ]);

            ActivityLog::record(
                ActivityAction::Create,
                $member,
                "Menambahkan {$member->user->name} sebagai {$member->role_in_project->value}",
                newValues: ['user' => $member->user->name, 'role_in_project' => $member->role_in_project->value],
            );

            return $member;
        });

        return back()->with('success', "{$member->user->name} ditambahkan ke proyek.");
    }

    public function update(UpdateProjectMemberRequest $request, Project $project, ProjectMember $projectMember): RedirectResponse
    {
        $oldRole = $projectMember->role_in_project;
        $newRole = $request->enum('role_in_project', ProjectRole::class);

        if ($oldRole === $newRole) {
            return back();
        }

        DB::transaction(function () use ($projectMember, $oldRole, $newRole): void {
            $projectMember->update(['role_in_project' => $newRole]);

            ActivityLog::record(
                ActivityAction::Update,
                $projectMember,
                "Mengubah role {$projectMember->user->name}: {$oldRole->value} → {$newRole->value}",
                ['role_in_project' => $oldRole->value],
                ['role_in_project' => $newRole->value],
            );
        });

        return back()->with('success', "Role {$projectMember->user->name} diubah menjadi {$newRole->value}.");
    }

    /**
     * Remove a member; their open work in this project becomes unassigned.
     */
    public function destroy(Project $project, ProjectMember $projectMember): RedirectResponse
    {
        Gate::authorize('delete', $projectMember);

        if ($projectMember->role_in_project === ProjectRole::ProjectManager && $project->managerCount() <= 1) {
            return back()->with('error', 'Project Manager terakhir tidak dapat dikeluarkan. Jadikan anggota lain Project Manager terlebih dahulu.');
        }

        $name = $projectMember->user->name;

        DB::transaction(function () use ($project, $projectMember, $name): void {
            $userId = $projectMember->user_id;

            $unassignedTasks = $project->tasks()->where('assigned_to', $userId)->update(['assigned_to' => null]);
            $project->issues()->where('assigned_to', $userId)->update(['assigned_to' => null]);
            $project->risks()->where('owner_id', $userId)->update(['owner_id' => null]);

            ActivityLog::record(
                ActivityAction::Delete,
                $projectMember,
                "Mengeluarkan {$name} dari proyek ({$unassignedTasks} task menjadi unassigned)",
                ['user' => $name, 'role_in_project' => $projectMember->role_in_project->value],
            );

            $projectMember->delete();
        });

        return back()->with('success', "{$name} dikeluarkan dari proyek.");
    }
}
