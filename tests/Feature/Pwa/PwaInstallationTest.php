<?php

namespace Tests\Feature\Pwa;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaInstallationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_manifest_json_returns_valid_pwa_configuration(): void
    {
        $response = $this->get('/manifest.json');

        $response->assertStatus(200);
        $manifest = json_decode($response->getContent(), true);

        $this->assertIsArray($manifest);
        $this->assertSame('Lead Panther', $manifest['name']);
        $this->assertSame('Lead Panther', $manifest['short_name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('#0A0A0A', $manifest['theme_color']);
        $this->assertSame('#0A0A0A', $manifest['background_color']);

        $icons = $manifest['icons'];
        $this->assertNotEmpty($icons);

        $iconSizes = array_column($icons, 'sizes');
        $this->assertContains('192x192', $iconSizes);
        $this->assertContains('512x512', $iconSizes);

        // Verify physical existence of icons referenced in manifest
        foreach ($icons as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($path, "Icon file {$icon['src']} must exist in public directory.");
        }
    }

    public function test_pwa_icons_and_assets_exist_and_service_worker_routes_respond(): void
    {
        $this->assertFileExists(public_path('icons/icon-192x192.png'));
        $this->assertFileExists(public_path('icons/icon-512x512.png'));
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));
        $this->assertFileExists(public_path('apple-touch-icon.png'));
        $this->assertFileExists(public_path('pwa-install.js'));

        $this->get('/manifest.json')->assertStatus(200);
        $this->get('/offline.html')->assertStatus(200)->assertSee('You are currently offline');
        $this->get('/sw.js')->assertStatus(200)->assertSee('browser-push-sw.js');
    }

    public function test_landing_page_includes_pwa_meta_tags_and_manifest(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('rel="manifest"', false);
        $response->assertSee('manifest.json', false);
        $response->assertSee('name="mobile-web-app-capable" content="yes"', false);
        $response->assertSee('name="apple-mobile-web-app-capable" content="yes"', false);
        $response->assertSee('name="apple-mobile-web-app-status-bar-style" content="black-translucent"', false);
        $response->assertSee('name="theme-color" content="#0A0A0A"', false);
        $response->assertSee('apple-touch-icon.png', false);
        $response->assertSee('pwa-install.js', false);
    }

    public function test_authenticated_dashboard_layout_includes_install_app_options_in_topbar_and_sidebar(): void
    {
        $org = Organization::create(['name' => 'HQ', 'status' => 'active']);
        $user = User::factory()->create(['status' => 'active', 'organization_id' => $org->id]);
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        // Manifest & PWA tags
        $response->assertSee('rel="manifest"', false);
        $response->assertSee('manifest.json', false);
        $response->assertSee('pwa-install.js', false);

        // Install App triggers in topbar dropdown & mobile sidebar
        $response->assertSee('data-pwa-install-button', false);
        $response->assertSee('data-pwa-install-label', false);
        $response->assertSee('window.triggerLeadPantherInstall()', false);
        $response->assertSee('Install Lead Panther', false);
    }

    public function test_service_worker_does_not_cache_sensitive_crm_routes(): void
    {
        $swContent = file_get_contents(public_path('browser-push-sw.js'));

        // Ensure service worker only precaches static assets, not private data
        $this->assertStringContainsString('leadpanther-pwa-static-v1', $swContent);
        $this->assertStringContainsString('/manifest.json', $swContent);
        $this->assertStringContainsString('/offline.html', $swContent);

        // Ensure dynamic navigation uses fetch directly (Network only/Network first)
        $this->assertStringContainsString("event.request.mode === 'navigate'", $swContent);
        $this->assertStringContainsString('fetch(event.request)', $swContent);
    }
}
