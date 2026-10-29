<?php

namespace Tests\Feature\Dashboard;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Lead;
use App\Models\Project;
use App\Models\CreditWallet;
use App\Livewire\Dashboard\DashboardShell;
use App\Support\NavigationConfig;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_super_admin_dashboard_metrics_and_header(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(DashboardShell::class, ['role' => 'admin'])
            ->assertSee('Super Admin Master Telemetry')
            ->assertSee('Platform Leads Today')
            ->assertSee('System Credits Supply')
            ->assertSee('Active Organizations')
            ->assertSee('Platform SLA Breaches');

        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Super Admin Master Telemetry');
    }

    public function test_builder_dashboard_metrics_and_header(): void
    {
        $org = Organization::create(['name' => 'Acme Developers', 'status' => 'active']);
        $builder = User::factory()->create(['organization_id' => $org->id]);
        $builder->assignRole('Builder/Developer');

        CreditWallet::firstOrCreate(['client_id' => $builder->id], ['balance' => 250.00]);

        Livewire::actingAs($builder)
            ->test(DashboardShell::class, ['role' => 'builder'])
            ->assertSee('Builder / Developer Dashboard')
            ->assertSee('Org Leads Today')
            ->assertSee('Active Credits')
            ->assertSee('Active Projects')
            ->assertSee('Closed Bookings');

        $this->actingAs($builder)
            ->get('/builder/dashboard')
            ->assertOk()
            ->assertSee('Builder / Developer Dashboard');

        // Navigation check: builder role key is 'builder'
        $this->assertEquals('builder', NavigationConfig::getRoleKey($builder));
        $allowed = NavigationConfig::getAllowedItemKeys($builder);
        $this->assertContains('dashboard', $allowed);
        $this->assertContains('leads', $allowed);
        $this->assertContains('credits', $allowed);
        $this->assertContains('team', $allowed);
        $this->assertNotContains('organizations', $allowed);
    }

    public function test_broker_dashboard_metrics_and_header(): void
    {
        $broker = User::factory()->create();
        $broker->assignRole('Broker');

        CreditWallet::firstOrCreate(['client_id' => $broker->id], ['balance' => 100.00]);

        Livewire::actingAs($broker)
            ->test(DashboardShell::class, ['role' => 'broker'])
            ->assertSee('Broker Performance Dashboard')
            ->assertSee('My Buyer Leads Today')
            ->assertSee('My Wallet Balance')
            ->assertSee('Site Visits Scheduled');

        $this->actingAs($broker)
            ->get('/broker/dashboard')
            ->assertOk()
            ->assertSee('Broker Performance Dashboard');

        // Navigation check: broker role key is 'broker'
        $this->assertEquals('broker', NavigationConfig::getRoleKey($broker));
        $allowed = NavigationConfig::getAllowedItemKeys($broker);
        $this->assertContains('dashboard', $allowed);
        $this->assertContains('leads', $allowed);
        $this->assertContains('credits', $allowed);
        $this->assertNotContains('organizations', $allowed);
    }

    public function test_channel_partner_dashboard_metrics_and_header(): void
    {
        $org = Organization::create(['name' => 'Apex Agency', 'status' => 'active']);
        $partner = User::factory()->create(['organization_id' => $org->id]);
        $partner->assignRole('Channel Partner');

        Livewire::actingAs($partner)
            ->test(DashboardShell::class, ['role' => 'partner'])
            ->assertSee('Channel Partner Command Center')
            ->assertSee('Agency Leads Today')
            ->assertSee('Active Agents')
            ->assertSee('Site Visits Pipeline');

        $this->actingAs($partner)
            ->get('/partner/dashboard')
            ->assertOk()
            ->assertSee('Channel Partner Command Center');

        $this->assertEquals('channel-partner', NavigationConfig::getRoleKey($partner));
        $allowed = NavigationConfig::getAllowedItemKeys($partner);
        $this->assertContains('team', $allowed);
        $this->assertNotContains('organizations', $allowed);
    }

    public function test_sales_executive_dashboard_metrics_and_header(): void
    {
        $sales = User::factory()->create();
        $sales->assignRole('Executive');

        Livewire::actingAs($sales)
            ->test(DashboardShell::class, ['role' => 'sales'])
            ->assertSee('Sales Executive Workspace')
            ->assertSee('Assigned Leads Today')
            ->assertSee('Follow-ups Pending')
            ->assertSee('SLA Critical Leads');

        $this->actingAs($sales)
            ->get('/sales/dashboard')
            ->assertOk()
            ->assertSee('Sales Executive Workspace');

        $this->assertEquals('sales-executive', NavigationConfig::getRoleKey($sales));
        $allowed = NavigationConfig::getAllowedItemKeys($sales);
        $this->assertContains('dashboard', $allowed);
        $this->assertContains('leads', $allowed);
        $this->assertContains('distribution', $allowed);
        $this->assertNotContains('organizations', $allowed);
    }
}

