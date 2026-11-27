<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use App\Support\NavigationConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SidebarNavigationStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_receives_four_reorganized_navigation_sections(): void
    {
        $role = Role::findOrCreate('Super Admin');
        $user = User::factory()->create();
        $user->assignRole($role);

        $nav = NavigationConfig::getNavItemsForUser($user);

        $this->assertArrayHasKey('sections', $nav);
        $sections = $nav['sections'];

        $this->assertArrayHasKey('main', $sections);
        $this->assertSame('Main', $sections['main']['label']);
        $mainKeys = array_column($sections['main']['items'], 'key');
        $this->assertSame([
            'dashboard',
            'leads',
            'followups',
            'bulk-import',
            'distribution',
            'credits',
            'replacements',
            'reports',
            'integrations',
        ], $mainKeys);

        $this->assertArrayHasKey('database', $sections);
        $this->assertSame('Database', $sections['database']['label']);
        $databaseKeys = array_column($sections['database']['items'], 'key');
        $this->assertSame([
            'organizations',
            'users',
        ], $databaseKeys);

        $this->assertArrayHasKey('administration', $sections);
        $this->assertSame('Administration', $sections['administration']['label']);
        $adminKeys = array_column($sections['administration']['items'], 'key');
        $this->assertSame([
            'audit-logs',
            'notifications',
            'failed-jobs',
            'recharge-queue',
        ], $adminKeys);

        $this->assertArrayHasKey('system', $sections);
        $this->assertSame('System & Maintenance', $sections['system']['label']);
        $systemKeys = array_column($sections['system']['items'], 'key');
        $this->assertContains('backups', $systemKeys);
        $this->assertContains('git-sync', $systemKeys);
        $this->assertContains('archived-leads', $systemKeys);

        // Backward compatibility keys
        $this->assertArrayHasKey('primary', $nav);
        $this->assertArrayHasKey('database', $nav);
    }

    public function test_sidebar_blade_renders_section_labels_for_super_admin(): void
    {
        $role = Role::findOrCreate('Super Admin');
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user);

        $view = $this->blade('<x-ui.sidebar />');

        $view->assertSee('Main');
        $view->assertSee('Database');
        $view->assertSee('Administration');
        $view->assertSee('System & Maintenance');
        $view->assertSee('Install Lead Panther');
    }

    public function test_sales_executive_sidebar_only_renders_allowed_items_without_empty_sections(): void
    {
        $role = Role::findOrCreate('Sales Executive');
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user);

        $nav = NavigationConfig::getNavItemsForUser($user);
        $this->assertEmpty($nav['sections']['database']['items']);
        $this->assertEmpty($nav['sections']['administration']['items']);
        $this->assertEmpty($nav['sections']['system']['items']);
        $this->assertNotEmpty($nav['sections']['main']['items']);

        $view = $this->blade('<x-ui.sidebar />');
        $view->assertSee('Main');
        $view->assertDontSee('Administration');
        $view->assertDontSee('System & Maintenance');
    }
}
