<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Lead;
use App\Livewire\Admin\DevTools;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class DevToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'client']);
    }

    public function test_dev_tools_view_renders_successfully_in_local_environment_for_super_admin(): void
    {
        $admin = User::factory()->create(['email' => 'admin@leadpanther.com']);
        $admin->assignRole('super-admin');

        Livewire::actingAs($admin)
            ->test(DevTools::class)
            ->assertSee('Developer Tools')
            ->assertSee('Reseed Full Demo Dataset')
            ->assertSee('Truncate');
    }

    public function test_clear_all_data_requires_exact_confirmation_phrase(): void
    {
        $admin = User::factory()->create(['email' => 'admin@leadpanther.com']);
        $admin->assignRole('super-admin');

        Organization::create(['name' => 'Test Org']);

        Livewire::actingAs($admin)
            ->test(DevTools::class)
            ->set('confirmPhrase', 'WRONG PHRASE')
            ->call('clearAllData')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('organizations', ['name' => 'Test Org']);
    }

    public function test_clear_all_data_truncates_tables_and_preserves_super_admin(): void
    {
        $admin = User::factory()->create(['email' => 'admin@leadpanther.com']);
        $admin->assignRole('super-admin');

        User::factory()->create(['email' => 'user@example.com']);
        Organization::create(['name' => 'Test Org']);

        Livewire::actingAs($admin)
            ->test(DevTools::class)
            ->set('confirmPhrase', 'DELETE ALL DATA')
            ->call('clearAllData');

        $this->assertDatabaseMissing('users', ['email' => 'user@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'admin@leadpanther.com']);
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_reseed_database_populates_fresh_demo_dataset(): void
    {
        $admin = User::factory()->create(['email' => 'admin@leadpanther.com']);
        $admin->assignRole('super-admin');

        Livewire::actingAs($admin)
            ->test(DevTools::class)
            ->call('reseedDatabase');

        $this->assertDatabaseHas('users', ['email' => 'admin@leadpanther.com']);
        $this->assertTrue(Lead::count() > 0);
        $this->assertTrue(Organization::count() > 0);
    }

    public function test_dev_tools_is_accessible_in_production_environment_for_super_admin(): void
    {
        $admin = User::factory()->create(['email' => 'admin@leadpanther.com']);
        $admin->assignRole('super-admin');

        config(['app.env' => 'production']);

        $response = $this->actingAs($admin)->get('/admin/dev-tools');

        // Restore environment to testing
        config(['app.env' => 'testing']);

        $response->assertOk();
    }

    public function test_dev_tools_returns_403_for_non_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $response = $this->actingAs($user)->get('/admin/dev-tools');
        $response->assertForbidden();
    }

    public function test_dev_tools_renders_clean_dummy_data_card(): void
    {
        $admin = User::factory()->create(['email' => 'admin@leadpanther.com']);
        $admin->assignRole('super-admin');

        Livewire::actingAs($admin)
            ->test(DevTools::class)
            ->assertSee('Remove Dummy Data')
            ->assertSee('CLEAN DUMMY DATA')
            ->assertSee('Keep Required');
    }

    public function test_clean_dummy_data_requires_confirmation_phrase(): void
    {
        $admin = User::factory()->create(['email' => 'admin@leadpanther.com']);
        $admin->assignRole('super-admin');

        Organization::create(['name' => 'Demo Org']);

        Livewire::actingAs($admin)
            ->test(DevTools::class)
            ->set('cleanConfirmPhrase', 'WRONG PHRASE')
            ->call('cleanDummyData')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('organizations', ['name' => 'Demo Org']);
    }

    public function test_clean_dummy_data_purges_demo_data_and_preserves_required_data(): void
    {
        $admin = User::factory()->create(['email' => 'admin@leadpanther.com']);
        $admin->assignRole('super-admin');

        $demoUser = User::factory()->create(['email' => 'demouser@demo.com']);
        $demoOrg = Organization::create(['name' => 'Demo Real Estate Inc']);

        Livewire::actingAs($admin)
            ->test(DevTools::class)
            ->set('cleanConfirmPhrase', 'CLEAN DUMMY DATA')
            ->call('cleanDummyData')
            ->assertDispatched('toast');

        // Demo data should be removed
        $this->assertDatabaseMissing('users', ['email' => 'demouser@demo.com']);
        $this->assertDatabaseMissing('organizations', ['name' => 'Demo Real Estate Inc']);

        // Required data should be preserved
        $this->assertDatabaseHas('users', ['email' => 'admin@leadpanther.com', 'id' => 1]);
        $this->assertDatabaseHas('organizations', ['name' => 'Lead Panther Platform HQ']);
        $this->assertDatabaseHas('lead_sources', ['name' => 'meta']);
    }

    public function test_artisan_clean_dummy_data_command_executes_successfully(): void
    {
        $admin = User::factory()->create(['email' => 'admin@leadpanther.com']);
        $admin->assignRole('super-admin');

        $demoUser = User::factory()->create(['email' => 'sales99@demo.com']);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('dev:clean-dummy-data', ['--force' => true]);
        $this->assertEquals(0, $exitCode);

        $this->assertDatabaseMissing('users', ['email' => 'sales99@demo.com']);
        $this->assertDatabaseHas('users', ['email' => 'admin@leadpanther.com', 'id' => 1]);
        $this->assertDatabaseHas('organizations', ['name' => 'Lead Panther Platform HQ']);
    }
}
