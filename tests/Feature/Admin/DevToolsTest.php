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

    public function test_dev_tools_returns_404_in_production_environment(): void
    {
        $admin = User::factory()->create(['email' => 'admin@leadpanther.com']);
        $admin->assignRole('super-admin');

        config(['app.env' => 'production']);

        $response = $this->actingAs($admin)->get('/admin/dev-tools');

        // Restore environment to testing
        config(['app.env' => 'testing']);

        $response->assertNotFound();
    }
}
