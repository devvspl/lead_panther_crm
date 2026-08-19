<?php

namespace Tests\Feature\Shared;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Admin\AuditLogBrowser;
use App\Livewire\Admin\UserManager;
use App\Livewire\Admin\OrganizationManager;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\GeneralSetting;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdvancedTableTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Sales Executive', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'name' => 'Master Super Admin',
            'email' => 'admin@leadpanther.com',
        ]);
        $this->superAdmin->assignRole('Super Admin');
    }

    public function test_audit_log_browser_renders_advanced_table_and_filters(): void
    {
        $this->actingAs($this->superAdmin);

        AuditLog::create([
            'user_id' => $this->superAdmin->id,
            'action' => 'lead.create',
            'subject_type' => 'App\Models\Lead',
            'subject_id' => 101,
            'from_value' => null,
            'to_value' => 'Lead created with code LP-101',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'created_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $this->superAdmin->id,
            'action' => 'auth.login_successful',
            'subject_type' => 'App\Models\User',
            'subject_id' => $this->superAdmin->id,
            'from_value' => null,
            'to_value' => 'User logged in',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'created_at' => now(),
        ]);

        Livewire::test(AuditLogBrowser::class)
            ->assertSee('System Audit Logs Browser')
            ->assertSee('Lead.create')
            ->assertSee('Auth.login successful')
            ->set('search', 'LP-101')
            ->assertSee('Lead.create')
            ->assertDontSee('Auth.login successful')
            ->set('search', '')
            ->call('setStatusFilter', 'auth')
            ->assertSee('Auth.login successful')
            ->assertDontSee('Lead.create');
    }

    public function test_column_visibility_persists_to_general_settings(): void
    {
        $this->actingAs($this->superAdmin);

        $component = Livewire::test(AuditLogBrowser::class);
        $component->call('toggleColumn', 'formatted_ip');

        // Verify that setting was saved in general_settings
        $settingValue = GeneralSetting::getValue($this->superAdmin->id, 'table_columns_audit_log_browser');
        $this->assertNotNull($settingValue);
        $decoded = json_decode($settingValue, true);
        $this->assertNotContains('formatted_ip', $decoded); // It was visible by default, toggling removes it

        // Toggle it back on
        $component->call('toggleColumn', 'formatted_ip');
        $settingValueAfter = GeneralSetting::getValue($this->superAdmin->id, 'table_columns_audit_log_browser');
        $decodedAfter = json_decode($settingValueAfter, true);
        $this->assertContains('formatted_ip', $decodedAfter);
    }

    public function test_sorting_modifies_sort_field_and_direction(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(AuditLogBrowser::class)
            ->assertSet('sortField', '')
            ->assertSet('sortDirection', 'desc')
            ->call('sortBy', 'created_at')
            ->assertSet('sortField', 'created_at')
            ->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'created_at')
            ->assertSet('sortField', 'created_at')
            ->assertSet('sortDirection', 'desc');
    }

    public function test_user_manager_advanced_table_search_and_quick_filters(): void
    {
        $this->actingAs($this->superAdmin);

        $salesUser = User::factory()->create([
            'name' => 'Kavita Iyer',
            'email' => 'kavita@realty.com',
        ]);
        $salesUser->assignRole('Sales Executive');

        Livewire::test(UserManager::class)
            ->assertSee('Global User Management')
            ->assertSee('Kavita Iyer')
            ->assertSee('Master Super Admin')
            ->call('setStatusFilter', 'super_admin')
            ->assertSee('Master Super Admin')
            ->assertDontSee('Kavita Iyer')
            ->call('setStatusFilter', 'sales_executive')
            ->assertSee('Kavita Iyer')
            ->assertDontSee('Master Super Admin');
    }

    public function test_organization_manager_advanced_table(): void
    {
        $this->actingAs($this->superAdmin);

        Organization::create([
            'name' => 'Lodha Developers',
            'type' => 'builder',
            'status' => 'active',
        ]);

        Organization::create([
            'name' => 'Apex Channel Partner',
            'type' => 'channel_partner',
            'status' => 'suspended',
        ]);

        Livewire::test(OrganizationManager::class)
            ->assertSee('Organizations Management')
            ->assertSee('Lodha Developers')
            ->assertSee('Apex Channel Partner')
            ->call('setStatusFilter', 'active')
            ->assertSee('Lodha Developers')
            ->assertDontSee('Apex Channel Partner');
    }
}
