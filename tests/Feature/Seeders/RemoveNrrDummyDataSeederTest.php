<?php

namespace Tests\Feature\Seeders;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\Organization34SampleSeeder;
use Database\Seeders\RemoveNrrDummyDataSeeder;
use App\Models\Organization;
use App\Models\User;
use App\Models\Project;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\SalesTeam;
use Spatie\Permission\Models\Role;

class RemoveNrrDummyDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_seeder_removes_nrr_dummy_data_and_preserves_organization(): void
    {
        // Setup Roles
        Role::firstOrCreate(['name' => 'Builder/Developer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Sales Executive', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        User::factory()->create(['id' => 1]);

        // Seed NRR dummy sample data
        $seeder = new Organization34SampleSeeder();
        $seeder->run();

        // Verify dummy data was seeded
        $org = Organization::find(34);
        $this->assertNotNull($org);
        $this->assertEquals('NRR', $org->name);

        $this->assertGreaterThan(0, User::where('organization_id', 34)->count());
        $this->assertGreaterThan(0, Project::withoutGlobalScopes()->where('name', 'like', 'NRR %')->count());
        $this->assertGreaterThan(0, Campaign::withoutGlobalScopes()->where('name', 'like', 'NRR_%')->count());
        $this->assertGreaterThan(0, Lead::withoutGlobalScopes()->where('lead_code', 'like', 'LP-2026-000034%')->count());

        // Run Cleanup Seeder
        $cleanup = new RemoveNrrDummyDataSeeder();
        $cleanup->run();

        // 1. Verify NRR Organization still exists and is untouched
        $orgAfter = Organization::find(34);
        $this->assertNotNull($orgAfter);
        $this->assertEquals(34, $orgAfter->id);
        $this->assertEquals('NRR', $orgAfter->name);
        $this->assertEquals('active', $orgAfter->status);

        // 2. Verify all dummy data removed
        $this->assertEquals(0, User::where('organization_id', 34)->count());
        $this->assertEquals(0, Project::withoutGlobalScopes()->where('name', 'like', 'NRR %')->count());
        $this->assertEquals(0, Campaign::withoutGlobalScopes()->where('name', 'like', 'NRR_%')->count());
        $this->assertEquals(0, Lead::withoutGlobalScopes()->where('lead_code', 'like', 'LP-2026-000034%')->count());
        $this->assertEquals(0, SalesTeam::where('ownable_type', Organization::class)->where('ownable_id', 34)->count());

        // 3. Verify Idempotence (running a second time causes no errors)
        $cleanup->run();
        $this->assertNotNull(Organization::find(34));
    }

    public function test_cleanup_seeder_executes_successfully_via_tracked_seeder_service(): void
    {
        Role::firstOrCreate(['name' => 'Builder/Developer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Sales Executive', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        // Seed NRR dummy sample data
        (new Organization34SampleSeeder())->run();
        $this->assertNotNull(Organization::find(34));
        $this->assertGreaterThan(0, User::where('organization_id', 34)->count());

        // Execute via SeederRunService (same path used by GitSync web UI)
        $service = app(\App\Services\SeederRunService::class);
        $run = $service->run('RemoveNrrDummyDataSeeder', $admin);

        $this->assertSame('completed', $run->status);
        $this->assertGreaterThan(0, $run->change_count);

        // Verify NRR Organization is preserved
        $org = Organization::find(34);
        $this->assertNotNull($org);
        $this->assertSame('NRR', $org->name);

        // Verify all dummy data removed
        $this->assertEquals(0, User::where('organization_id', 34)->count());
        $this->assertEquals(0, Project::withoutGlobalScopes()->where('name', 'like', 'NRR %')->count());

        // Running it a second time via SeederRunService is idempotent and completes
        $secondRun = $service->run('RemoveNrrDummyDataSeeder', $admin);
        $this->assertSame('completed', $secondRun->status);
        $this->assertSame(0, $secondRun->change_count);
    }
}
