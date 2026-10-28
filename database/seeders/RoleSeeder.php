<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Create the four built-in roles and the default accounts.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = collect(['Administrator', 'Project Manager', 'Team Member', 'Viewer'])
            ->mapWithKeys(fn (string $name) => [$name => Role::findOrCreate($name, 'web')]);

        $accounts = [
            ['name' => 'Administrator', 'email' => 'admin@itpms.test', 'role' => 'Administrator'],
            ['name' => 'Riki Hamdan Sucipto', 'email' => 'riki@itpms.test', 'role' => 'Project Manager'],
            ['name' => 'Mirco', 'email' => 'mirco@itpms.test', 'role' => 'Team Member'],
        ];

        foreach ($accounts as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                ['name' => $account['name'], 'password' => 'password', 'email_verified_at' => now()],
            );

            $user->syncRoles($roles[$account['role']]);
        }
    }
}
