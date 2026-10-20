<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Only run data consolidation if roles exist in the table (skip during fresh test db migrations)
        if (DB::table('roles')->count() === 0) {
            return;
        }

        // 1. Ensure all 6 canonical roles exist in Title Case
        $canonicalRoles = [
            'Super Admin',
            'Builder',
            'Channel Partner',
            'Sales Executive',
            'Account Manager',
            'Client',
        ];

        foreach ($canonicalRoles as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // 2. Mapping of kebab duplicate roles to their canonical Title Case counterpart
        $kebabToTitle = [
            'super-admin' => 'Super Admin',
            'channel-partner' => 'Channel Partner',
            'sales-executive' => 'Sales Executive',
            'account-manager' => 'Account Manager',
            'builder' => 'Builder',
            'client' => 'Client',
        ];

        foreach ($kebabToTitle as $kebab => $title) {
            $kebabRole = DB::table('roles')->where('name', $kebab)->where('guard_name', 'web')->first();
            $titleRole = DB::table('roles')->where('name', $title)->where('guard_name', 'web')->first();

            if ($kebabRole && $titleRole && $kebabRole->id !== $titleRole->id) {
                // Re-link model_has_roles
                $assignments = DB::table('model_has_roles')->where('role_id', $kebabRole->id)->get();
                foreach ($assignments as $a) {
                    $alreadyHas = DB::table('model_has_roles')
                        ->where('role_id', $titleRole->id)
                        ->where('model_id', $a->model_id)
                        ->where('model_type', $a->model_type)
                        ->exists();

                    if (!$alreadyHas) {
                        DB::table('model_has_roles')->insert([
                            'role_id' => $titleRole->id,
                            'model_id' => $a->model_id,
                            'model_type' => $a->model_type,
                        ]);
                    }
                }

                // Copy permissions from kebabRole to titleRole
                $perms = DB::table('role_has_permissions')->where('role_id', $kebabRole->id)->get();
                foreach ($perms as $p) {
                    $alreadyHasPerm = DB::table('role_has_permissions')
                        ->where('role_id', $titleRole->id)
                        ->where('permission_id', $p->permission_id)
                        ->exists();

                    if (!$alreadyHasPerm) {
                        DB::table('role_has_permissions')->insert([
                            'permission_id' => $p->permission_id,
                            'role_id' => $titleRole->id,
                        ]);
                    }
                }

                // Delete old kebab relations and role
                DB::table('model_has_roles')->where('role_id', $kebabRole->id)->delete();
                DB::table('role_has_permissions')->where('role_id', $kebabRole->id)->delete();
                DB::table('roles')->where('id', $kebabRole->id)->delete();
            } elseif ($kebabRole && !$titleRole) {
                DB::table('roles')->where('id', $kebabRole->id)->update(['name' => $title]);
            }
        }

        DB::table('roles')->where('name', 'builder')->update(['name' => 'Builder']);
        DB::table('roles')->where('name', 'client')->update(['name' => 'Client']);

        // Reset Spatie cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Consolidation to canonical roles preserves all permissions and user links
    }
};
