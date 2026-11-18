<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        // Older live databases can have the role/memberships without the newer permissions.
        // Preserve other permissions and repair only the existing Team Leader roles.
        $permissions = ['view-leads', 'assign-leads', 'view-reports', 'view-team-leads', 'assign-team-leads', 'manage-team-members', 'view-team-reports', 'view-team-followups'];
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (Role::where('guard_name', 'web')->whereIn('name', ['Team Leader', 'team-leader'])->get() as $role) {
            foreach ($permissions as $name) {
                $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Do not revoke pre-existing permissions on rollback.
    }
};
