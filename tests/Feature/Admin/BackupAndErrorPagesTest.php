<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Livewire\Admin\BackupStatus;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;

class BackupAndErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Channel Partner']);
    }

    public function test_backup_status_view_renders_and_runs_backup(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(BackupStatus::class)
            ->assertSee('System Backups')
            ->assertSee('Available Backup Archives')
            ->call('runBackup');
    }

    public function test_custom_403_error_page_renders_with_app_debug_false(): void
    {
        Config::set('app.debug', false);

        $user = User::factory()->create();
        $user->assignRole('Channel Partner');

        $response = $this->actingAs($user)->get('/admin/dashboard');
        $response->assertStatus(403);
        $response->assertSee('Access Denied');
        $response->assertSee('Back to Dashboard');
    }

    public function test_custom_404_error_page_renders_with_app_debug_false(): void
    {
        Config::set('app.debug', false);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/non-existent-route-999');
        $response->assertStatus(404);
        $response->assertSee('Page Not Found');
    }
}
