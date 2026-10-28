<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Every permission in the system, grouped by module (module.action).
     *
     * @var array<string, list<string>>
     */
    public const PERMISSIONS = [
        'user' => ['viewAny', 'view', 'create', 'update', 'deactivate'],
        'role' => ['viewAny', 'view', 'update'],
        'project' => ['viewAny', 'view', 'create', 'update', 'close', 'reopen'],
        'member' => ['viewAny', 'create', 'updateRole', 'remove'],
        'task' => ['viewAny', 'view', 'create', 'update', 'updateStatus', 'delete'],
        'milestone' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'risk' => ['viewAny', 'view', 'create', 'update', 'close', 'delete'],
        'issue' => ['viewAny', 'view', 'create', 'update', 'updateStatus', 'delete'],
        'change_request' => ['viewAny', 'view', 'create', 'update', 'review', 'delete'],
        'comment' => ['view', 'create', 'delete', 'deleteSelf'],
        'attachment' => ['upload', 'download', 'delete'],
        'activity_log' => ['viewAny', 'view'],
        'notification' => ['viewAny', 'view'],
        'dashboard' => ['view'],
        'kanban' => ['view', 'moveTask', 'moveOwnTask'],
    ];

    /**
     * Permissions per role. "module.*" expands to every action of the module.
     * Ownership rules ("only their own task") are enforced by policies on top.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'Project Manager' => [
            'project.viewAny', 'project.view', 'project.create', 'project.update', 'project.close',
            'member.*', 'task.*', 'milestone.*',
            'risk.viewAny', 'risk.view', 'risk.create', 'risk.update', 'risk.close',
            'issue.*', 'change_request.*',
            'comment.*', 'attachment.*',
            'activity_log.view', 'notification.view', 'dashboard.view', 'kanban.*',
        ],
        'Team Member' => [
            'project.viewAny', 'project.view',
            'member.viewAny',
            'task.viewAny', 'task.view', 'task.updateStatus',
            'milestone.viewAny', 'milestone.view',
            'risk.viewAny', 'risk.view',
            'issue.viewAny', 'issue.view', 'issue.create', 'issue.updateStatus',
            'change_request.viewAny', 'change_request.view', 'change_request.create',
            'comment.view', 'comment.create', 'comment.deleteSelf',
            'attachment.upload', 'attachment.download',
            'notification.view', 'dashboard.view', 'kanban.view', 'kanban.moveOwnTask',
        ],
        'Viewer' => [
            'project.viewAny', 'project.view',
            'member.viewAny',
            'task.viewAny', 'task.view',
            'milestone.viewAny', 'milestone.view',
            'risk.viewAny', 'risk.view',
            'issue.viewAny', 'issue.view',
            'change_request.viewAny', 'change_request.view',
            'comment.view', 'attachment.download',
            'notification.view', 'dashboard.view', 'kanban.view',
        ],
    ];

    /**
     * Create all permissions and assign them to the built-in roles.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(self::PERMISSIONS)
            ->flatMap(fn (array $actions, string $module) => array_map(fn (string $action) => "{$module}.{$action}", $actions));

        $all->each(fn (string $name) => Permission::findOrCreate($name, 'web'));

        Role::findByName('Administrator', 'web')->syncPermissions($all);

        foreach (self::ROLE_PERMISSIONS as $roleName => $patterns) {
            Role::findByName($roleName, 'web')->syncPermissions($this->expand($patterns, $all->all()));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Expand "module.*" wildcards into concrete permission names.
     *
     * @param  list<string>  $patterns
     * @param  list<string>  $all
     * @return list<string>
     */
    protected function expand(array $patterns, array $all): array
    {
        return collect($patterns)
            ->flatMap(fn (string $pattern) => str_ends_with($pattern, '.*')
                ? array_filter($all, fn (string $name) => str_starts_with($name, substr($pattern, 0, -1)))
                : [$pattern])
            ->unique()
            ->values()
            ->all();
    }
}
