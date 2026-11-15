<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;

class RoleSeeder extends Seeder
{
    /**
     * Add canonical roles and permissions without deleting existing access grants.
     */
    public function run(): void
    {
        // 1. Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Define standard CRM permissions
        $permissions = [
            'view-leads',
            'create-leads',
            'edit-leads',
            'delete-leads',
            'assign-leads',
            'replace-leads',
            'manage-organization',
            'manage-clients',
            'manage-projects',
            'manage-credits',
            'view-reports',
            'manage-users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 4. Create ONLY the requested roles with their permissions
        $rolesWithPermissions = [
            'Super Admin' => Permission::all(),
            'Builder/Developer' => [
                'view-leads', 'create-leads', 'edit-leads', 'assign-leads',
                'replace-leads', 'manage-clients', 'manage-projects',
                'manage-credits', 'view-reports'
            ],
            'Channel Partner' => [
                'view-leads', 'create-leads', 'replace-leads', 'view-reports'
            ],
            'Broker' => [
                'view-leads', 'create-leads', 'edit-leads', 'replace-leads', 'view-reports'
            ],
            'Executive' => [
                'view-leads', 'create-leads', 'edit-leads', 'replace-leads', 'view-reports'
            ],
        ];

        foreach ($rolesWithPermissions as $roleName => $perms) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($perms);
        }

        // 5. Ensure protected admin user (admin@leadpanther.com) retains Super Admin role
        $adminUser = User::where('email', 'admin@leadpanther.com')->first();
        if ($adminUser) {
            $adminUser->assignRole('Super Admin');
        }

        $this->call(TeamLeaderRoleSeeder::class);
        // 6. Reset permission cache again to apply immediately
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}

