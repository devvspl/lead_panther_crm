<?php

namespace Tests\Feature\Shared;

use App\Models\User;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmationModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin']);
    }

    public function test_confirmation_modal_component_renders_on_app_layout(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('leadPantherConfirmModal', false);
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('confirm-action', false);
        $response->assertSee('window.$confirm', false);
        $response->assertSee('variant === \'danger\'', false);
        $response->assertSee('variant === \'warning\'', false);
        $response->assertSee('variant === \'success\'', false);
        $response->assertSee('variant === \'info\'', false);
        $response->assertSee('Processing...', false);
    }

    public function test_theme_settings_reset_endpoint_clears_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('settings.theme.reset'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }
}
