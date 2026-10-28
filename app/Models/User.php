<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ProjectRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const ADMIN_ROLE = 'Administrator';

    /**
     * Per-instance cache of the user's role in each project, keyed by project id.
     *
     * @var array<int, ProjectRole|null>
     */
    protected array $projectRoleCache = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Project, $this, ProjectMember>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->using(ProjectMember::class)
            ->withPivot(['id', 'role_in_project', 'joined_at']);
    }

    /**
     * @return HasMany<ProjectMember, $this>
     */
    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function createdProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Active users whose global role is Project Manager: the people an
     * Administrator can put in charge of a new project.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function projectManagerCandidates(Builder $query): void
    {
        $query->active()->role(ProjectRole::ProjectManager->value);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ADMIN_ROLE);
    }

    /**
     * The user's role inside the given project, or null when not a member.
     */
    public function projectRole(Project $project): ?ProjectRole
    {
        if (! array_key_exists($project->id, $this->projectRoleCache)) {
            $this->projectRoleCache[$project->id] = $this->projectMemberships()
                ->where('project_id', $project->id)
                ->first()
                ?->role_in_project;
        }

        return $this->projectRoleCache[$project->id];
    }

    public function isMemberOf(Project $project): bool
    {
        return $this->projectRole($project) !== null;
    }

    /**
     * Check a permission in the context of a project.
     *
     * Administrators use their global permissions. Everyone else is judged by
     * the permissions of the Spatie role that matches their role in the project,
     * so changes made on the role management screen apply inside projects too.
     */
    public function hasProjectPermission(Project $project, string $permission): bool
    {
        if ($this->isAdmin()) {
            return $this->hasPermissionTo($permission);
        }

        $role = $this->projectRole($project);

        if ($role === null) {
            return false;
        }

        return Role::findByName($role->value, 'web')->hasPermissionTo($permission);
    }

    /**
     * Forget cached project roles, e.g. after membership changes.
     */
    public function flushProjectRoleCache(): void
    {
        $this->projectRoleCache = [];
    }
}
