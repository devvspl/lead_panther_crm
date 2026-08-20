<?php

namespace Tests\Feature\Settings;

use App\Models\GeneralSetting;
use App\Models\User;
use App\Support\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_theme_endpoints(): void
    {
        $response = $this->postJson(route('settings.theme.update'), [
            'theme_primary_color' => '#3B82F6',
        ]);
        $response->assertStatus(401);

        $response = $this->postJson(route('settings.theme.reset'));
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_save_theme_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $payload = [
            'theme_primary_color' => '#3B82F6',
            'theme_secondary_color' => '#64748B',
            'theme_accent_color' => '#2563EB',
            'theme_sidebar_bg' => '#0F172A',
            'theme_sidebar_text' => '#94A3B8',
            'theme_active_menu_style' => 'text_only',
            'theme_active_menu_color' => '#38BDF8',
            'theme_active_menu_bg' => '#1E293B',
            'theme_header_bg' => '#0F172A',
            'theme_header_text' => '#F8FAFC',
            'theme_page_bg' => '#020617',
            'theme_card_bg' => '#0F172A',
            'theme_border_color' => '#1E293B',
            'theme_font_family' => 'Outfit, sans-serif',
            'theme_font_size' => '15px',
            'theme_border_radius' => '0.75rem',
            'theme_mode' => 'dark',
        ];

        $response = $this->postJson(route('settings.theme.update'), $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Theme customizations saved successfully!',
        ]);

        $this->assertDatabaseHas('general_settings', [
            'user_id' => $user->id,
            'key' => 'theme_primary_color',
            'value' => '#3B82F6',
        ]);

        $this->assertDatabaseHas('general_settings', [
            'user_id' => $user->id,
            'key' => 'theme_active_menu_style',
            'value' => 'text_only',
        ]);

        $theme = ThemeService::getUserTheme($user->id);
        $this->assertEquals('#3B82F6', $theme['theme_primary_color']);
        $this->assertEquals('text_only', $theme['theme_active_menu_style']);
        $this->assertEquals('dark', $theme['theme_mode']);
    }

    public function test_updating_single_setting_preserves_other_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Step 1: Save primary color and mode
        $this->postJson(route('settings.theme.update'), [
            'theme_primary_color' => '#E11D48',
            'theme_mode' => 'dark',
        ]);

        // Step 2: Update only header background
        $response = $this->postJson(route('settings.theme.update'), [
            'theme_header_bg' => '#18181B',
        ]);

        $response->assertStatus(200);

        $theme = ThemeService::getUserTheme($user->id);
        $this->assertEquals('#E11D48', $theme['theme_primary_color'], 'Primary color must be preserved');
        $this->assertEquals('dark', $theme['theme_mode'], 'Dark mode must be preserved');
        $this->assertEquals('#18181B', $theme['theme_header_bg'], 'Header background must be updated');
    }

    public function test_user_can_reset_theme_to_defaults(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        GeneralSetting::setValue($user->id, 'theme_primary_color', '#FF0000');
        GeneralSetting::setValue($user->id, 'theme_mode', 'dark');

        $this->assertDatabaseHas('general_settings', [
            'user_id' => $user->id,
            'key' => 'theme_primary_color',
            'value' => '#FF0000',
        ]);

        $response = $this->postJson(route('settings.theme.reset'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Theme reset to defaults successfully!',
        ]);

        $this->assertDatabaseMissing('general_settings', [
            'user_id' => $user->id,
            'key' => 'theme_primary_color',
        ]);

        $theme = ThemeService::getUserTheme($user->id);
        $this->assertEquals(ThemeService::DEFAULTS['theme_primary_color'], $theme['theme_primary_color']);
        $this->assertEquals(ThemeService::DEFAULTS['theme_mode'], $theme['theme_mode']);
    }

    public function test_dashboard_renders_theme_customizer_and_style_tags(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin']);
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('theme-dynamic-styles', false);
        $response->assertSee('Theme Customizer', false);
        $response->assertSee('theme_active_menu_style', false);
        $response->assertDontSee('Midnight Dark', false);
    }
}
