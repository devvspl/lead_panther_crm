<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;

class BrokerRoleSeeder extends Seeder
{
    /**
     * Run the database seed to add the Broker role and permissions.
     */
    public function run(): void
    {
        // 1. Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Define standard CRM permissions for Broker role
        $brokerPermissions = [
            'view-leads',
            'create-leads',
            'edit-leads',
            'replace-leads',
            'view-reports',
        ];

        foreach ($brokerPermissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // 3. Create or update the Broker role
        $brokerRole = Role::firstOrCreate([
            'name' => 'Broker',
            'guard_name' => 'web',
        ]);

        $brokerRole->syncPermissions($brokerPermissions);

        // 4. Assign Broker role to any user matching broker name or email
        $brokerUsers = User::where(function ($q) {
            $q->where('name', 'like', '%broker%')
              ->orWhere('email', 'like', '%broker%');
        })->get();

        foreach ($brokerUsers as $user) {
            $user->assignRole('Broker');
        }

        // 5. Reset cached permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}

