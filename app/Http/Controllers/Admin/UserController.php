<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * List users with search and role/status filters (FR-003).
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = [
            'search' => $request->string('search')->trim()->limit(100, '')->toString(),
            'role' => $request->string('role')->toString(),
            'status' => in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : '',
        ];

        $users = User::query()
            ->with('roles')
            ->when($filters['search'], function ($query, string $search): void {
                $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($filters['role'], fn ($query, string $role) => $query->whereHas('roles', fn ($query) => $query->where('name', $role)))
            ->when($filters['status'], fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::orderBy('id')->pluck('name'),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', [
            'roles' => Role::orderBy('id')->pluck('name'),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                ...$request->safe()->only(['name', 'email', 'password']),
                'is_active' => true,
            ]);
            $user->assignRole($request->validated('role'));

            ActivityLog::record(ActivityAction::Create, $user, 'Membuat user baru', newValues: [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $request->validated('role'),
                'is_active' => true,
            ]);

            return $user;
        });

        return redirect()->route('admin.users.show', $user)->with('success', "User {$user->name} berhasil dibuat.");
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);

        $user->load(['roles', 'projects' => fn ($query) => $query->orderBy('name')]);

        return view('users.show', [
            'user' => $user,
            'activeTaskCount' => $user->assignedTasks()->where('status', '!=', 'Done')->count(),
            'recentActivity' => ActivityLog::where('user_id', $user->id)->latest('created_at')->latest('id')->limit(10)->get(),
        ]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', [
            'user' => $user->load('roles'),
            'roles' => Role::orderBy('id')->pluck('name'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $oldRole = $user->getRoleNames()->first();
            $original = $user->only(['name', 'email']);

            $user->fill($request->safe()->only(['name', 'email']));

            if ($request->filled('password')) {
                $user->password = $request->validated('password');
            }

            $passwordChanged = $user->isDirty('password');
            $user->save();

            $newRole = $request->validated('role', $oldRole);
            if ($newRole !== $oldRole) {
                $user->syncRoles($newRole);
            }

            $changedFields = array_keys(array_diff_assoc($user->only(['name', 'email']), $original));
            $old = array_intersect_key($original, array_flip($changedFields));
            $new = $user->only($changedFields);

            if ($newRole !== $oldRole) {
                $old['role'] = $oldRole;
                $new['role'] = $newRole;
            }

            if ($old === [] && ! $passwordChanged) {
                return;
            }

            ActivityLog::record(
                ActivityAction::Update,
                $user,
                $passwordChanged ? 'Memperbarui user (termasuk password)' : 'Memperbarui user',
                $old,
                $new,
            );
        });

        return redirect()->route('admin.users.show', $user)->with('success', "User {$user->name} berhasil diperbarui.");
    }

    /**
     * Activate or deactivate an account instead of deleting it (FR-003).
     */
    public function toggleActive(User $user): RedirectResponse
    {
        Gate::authorize('toggleActive', $user);

        $user->update(['is_active' => ! $user->is_active]);

        ActivityLog::record(
            ActivityAction::StatusChange,
            $user,
            $user->is_active ? 'Mengaktifkan user' : 'Menonaktifkan user',
            ['is_active' => ! $user->is_active],
            ['is_active' => $user->is_active],
        );

        $message = $user->is_active ? "User {$user->name} diaktifkan kembali." : "User {$user->name} dinonaktifkan.";

        return back()->with('success', $message);
    }
}
