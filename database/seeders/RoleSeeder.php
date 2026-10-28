<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds: Truncate roles table and keep only the 4 allowed roles.
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

        // 3. Safely truncate/clear existing roles and role pivot relationships
        Schema::disableForeignKeyConstraints();

        DB::table('model_has_roles')->truncate();
        DB::table('role_has_permissions')->truncate();
        DB::table('roles')->truncate();

        Schema::enableForeignKeyConstraints();

        // 4. Create ONLY the 4 requested roles with their permissions
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
            'Executive' => [
                'view-leads', 'create-leads', 'edit-leads', 'replace-leads', 'view-reports'
            ],
        ];

        foreach ($rolesWithPermissions as $roleName => $perms) {
            $role = Role::create([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
            $role->syncPermissions($perms);
        }

        // 5. Ensure protected admin user (admin@leadpanther.com) retains Super Admin role
        $adminUser = User::where('email', 'admin@leadpanther.com')->first();
        if ($adminUser) {
            $adminUser->assignRole('Super Admin');
        }

        // Re-assign appropriate new roles to any remaining users in the system
        foreach (User::where('email', '!=', 'admin@leadpanther.com')->get() as $user) {
            $name = strtolower($user->name);
            $email = strtolower($user->email);

            if (str_contains($email, 'builder') || str_contains($name, 'builder')) {
                $user->assignRole('Builder/Developer');
            } elseif (str_contains($email, 'partner') || str_contains($email, 'cp.com') || str_contains($name, 'partner')) {
                $user->assignRole('Channel Partner');
            } else {
                $user->assignRole('Executive');
            }
        }

        // 6. Reset permission cache again to apply immediately
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}

