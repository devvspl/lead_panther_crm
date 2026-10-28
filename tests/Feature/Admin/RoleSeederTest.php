<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_truncates_and_seeds_only_the_four_canonical_roles(): void
    {
        // Pre-create extra dummy roles
        Role::create(['name' => 'Legacy Role A', 'guard_name' => 'web']);
        Role::create(['name' => 'Legacy Role B', 'guard_name' => 'web']);
        Role::create(['name' => 'Sales Executive', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'email' => 'admin@leadpanther.com',
            'name' => 'Lead Panther Admin',
        ]);

        $this->seed(RoleSeeder::class);

        $roles = Role::pluck('name')->sort()->values()->toArray();

        $this->assertEquals([
            'Builder/Developer',
            'Channel Partner',
            'Executive',
            'Super Admin',
        ], $roles);

        $this->assertEquals(4, Role::count());

        $admin->refresh();
        $this->assertTrue($admin->hasRole('Super Admin'));
    }

    public function test_role_permissions_are_correctly_assigned(): void
    {
        $this->seed(RoleSeeder::class);

        $superAdmin = Role::where('name', 'Super Admin')->first();
        $builder = Role::where('name', 'Builder/Developer')->first();
        $partner = Role::where('name', 'Channel Partner')->first();
        $executive = Role::where('name', 'Executive')->first();

        $this->assertCount(12, $superAdmin->permissions);
        $this->assertCount(9, $builder->permissions);
        $this->assertCount(4, $partner->permissions);
        $this->assertCount(5, $executive->permissions);

        $this->assertTrue($builder->hasPermissionTo('manage-projects'));
        $this->assertTrue($partner->hasPermissionTo('view-leads'));
        $this->assertTrue($executive->hasPermissionTo('edit-leads'));
        $this->assertFalse($executive->hasPermissionTo('manage-organization'));
    }

    public function test_user_has_role_and_normalization_helpers(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Builder/Developer');

        $this->assertTrue($user->hasRole('Builder/Developer'));
        $this->assertTrue($user->hasRole('Builder'));
        $this->assertTrue($user->hasRole('builder'));

        $execUser = User::factory()->create();
        $execUser->assignRole('Executive');

        $this->assertTrue($execUser->hasRole('Executive'));
        $this->assertTrue($execUser->hasRole('Sales Executive'));
        $this->assertTrue($execUser->hasRole('sales-executive'));
    }
}

