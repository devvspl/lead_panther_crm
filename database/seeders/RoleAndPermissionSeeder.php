<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

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

        $roles = [
            'Super Admin' => Permission::all(),
            'Builder' => ['view-leads', 'create-leads', 'edit-leads', 'assign-leads', 'replace-leads', 'manage-clients', 'manage-projects', 'manage-credits', 'view-reports'],
            'Channel Partner' => ['view-leads', 'create-leads', 'replace-leads', 'view-reports'],
            'Sales Executive' => ['view-leads', 'edit-leads', 'replace-leads'],
            'Account Manager' => ['view-leads', 'create-leads', 'manage-credits', 'view-reports'],
            'Client' => ['view-leads', 'view-reports'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }

        // Clean up legacy duplicate kebab roles if present
        $kebabRoles = ['super-admin', 'channel-partner', 'sales-executive', 'account-manager', 'builder', 'client'];
        foreach ($kebabRoles as $kebab) {
            $legacy = Role::where('name', $kebab)->where('guard_name', 'web')->first();
            if ($legacy) {
                $canonicalName = ucwords(str_replace('-', ' ', $kebab));
                $canonical = Role::where('name', $canonicalName)->where('guard_name', 'web')->first();
                if ($canonical && $canonical->id !== $legacy->id) {
                    \Illuminate\Support\Facades\DB::table('model_has_roles')->where('role_id', $legacy->id)->update(['role_id' => $canonical->id]);
                    \Illuminate\Support\Facades\DB::table('role_has_permissions')->where('role_id', $legacy->id)->delete();
                    $legacy->delete();
                }
            }
        }
    }
}
