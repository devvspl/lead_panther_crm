<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations: permanently remove Account Manager role and reassign users to Executive.
     */
    public function up(): void
    {
        // Skip during empty test migrations
        if (DB::table('roles')->count() === 0) {
            return;
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $executiveRole = Role::firstOrCreate(['name' => 'Executive', 'guard_name' => 'web']);

        $amRoles = DB::table('roles')->whereIn('name', ['Account Manager', 'account-manager'])->get();

        foreach ($amRoles as $amRole) {
            $modelRoles = DB::table('model_has_roles')->where('role_id', $amRole->id)->get();
            foreach ($modelRoles as $mr) {
                $alreadyHas = DB::table('model_has_roles')
                    ->where('role_id', $executiveRole->id)
                    ->where('model_id', $mr->model_id)
                    ->where('model_type', $mr->model_type)
                    ->exists();

                if (!$alreadyHas) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $executiveRole->id,
                        'model_id' => $mr->model_id,
                        'model_type' => $mr->model_type,
                    ]);
                }
            }

            DB::table('model_has_roles')->where('role_id', $amRole->id)->delete();
            DB::table('role_has_permissions')->where('role_id', $amRole->id)->delete();
            DB::table('roles')->where('id', $amRole->id)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No action needed on rollback
    }
};
