<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
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

        // Create Roles & Assign Permissions
        $roleSuperAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $roleSuperAdmin->givePermissionTo(Permission::all());

        $roleBuilder = Role::firstOrCreate(['name' => 'Builder', 'guard_name' => 'web']);
        $roleBuilder->givePermissionTo(['view-leads', 'create-leads', 'edit-leads', 'assign-leads', 'replace-leads', 'manage-clients', 'manage-projects', 'manage-credits', 'view-reports']);

        $roleChannelPartner = Role::firstOrCreate(['name' => 'Channel Partner', 'guard_name' => 'web']);
        $roleChannelPartner->givePermissionTo(['view-leads', 'create-leads', 'replace-leads', 'view-reports']);

        $roleSalesExec = Role::firstOrCreate(['name' => 'Sales Executive', 'guard_name' => 'web']);
        $roleSalesExec->givePermissionTo(['view-leads', 'edit-leads', 'replace-leads']);

        $roleAccountMgr = Role::firstOrCreate(['name' => 'Account Manager', 'guard_name' => 'web']);
        $roleAccountMgr->givePermissionTo(['view-leads', 'create-leads', 'manage-credits', 'view-reports']);
    }
}
