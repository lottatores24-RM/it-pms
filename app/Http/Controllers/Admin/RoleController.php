<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * List the built-in roles with their permissions (FR-004).
     */
    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::query()
            ->with(['permissions' => fn ($query) => $query->orderBy('id')])
            ->withCount('users')
            ->orderBy('id')
            ->get();

        return view('roles.index', [
            'roles' => $roles,
            'totalPermissions' => Permission::count(),
        ]);
    }

    public function edit(Role $role): View
    {
        Gate::authorize('view', $role);

        return view('roles.edit', [
            'role' => $role->loadCount('users'),
            'permissionGroups' => $this->permissionGroups(),
            'granted' => $role->permissions()->pluck('name')->all(),
            'canUpdate' => Gate::allows('update', $role),
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        $before = $role->permissions()->pluck('name')->all();
        $after = $request->validated('permissions');

        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        if ($added === [] && $removed === []) {
            return redirect()->route('admin.roles.edit', $role)->with('success', 'Tidak ada perubahan permission.');
        }

        DB::transaction(function () use ($role, $after, $added, $removed): void {
            $role->syncPermissions($after);

            ActivityLog::record(
                ActivityAction::Update,
                $role,
                sprintf('Mengubah permission role (%d ditambah, %d dicabut)', count($added), count($removed)),
                ['removed' => $removed],
                ['added' => $added],
            );
        });

        return redirect()->route('admin.roles.edit', $role)->with('success', "Permission role {$role->name} berhasil diperbarui.");
    }

    /**
     * All permissions grouped by module, in the order they were seeded.
     *
     * @return Collection<string, Collection<int, Permission>>
     */
    protected function permissionGroups(): Collection
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Permission $permission) => str($permission->name)->before('.')->toString());
    }
}
