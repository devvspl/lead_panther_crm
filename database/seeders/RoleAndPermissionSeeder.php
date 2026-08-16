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
            'super-admin' => Permission::all(),
            'builder' => ['view-leads', 'create-leads', 'edit-leads', 'assign-leads', 'replace-leads', 'manage-clients', 'manage-projects', 'manage-credits', 'view-reports'],
            'channel-partner' => ['view-leads', 'create-leads', 'replace-leads', 'view-reports'],
            'sales-executive' => ['view-leads', 'edit-leads', 'replace-leads'],
            'account-manager' => ['view-leads', 'create-leads', 'manage-credits', 'view-reports'],
            'client' => ['view-leads', 'view-reports'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
