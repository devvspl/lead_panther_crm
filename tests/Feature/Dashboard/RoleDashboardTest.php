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
            ->assertSee('Super Admin Dashboard')
            ->assertSee('Total Leads')
            ->assertSee('Won Deals')
            ->assertSee('SLA Breaches')
            ->assertSee('Available Credits');

        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Super Admin Dashboard');
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
            ->assertSee('Total Leads')
            ->assertSee('New Leads')
            ->assertSee('Unassigned Leads')
            ->assertSee('Assigned Leads')
            ->assertSee('In Progress')
            ->assertSee('Closed Won')
            ->assertSee('Credit Balance')
            ->assertSee('Available Credits');

        $this->actingAs($builder)
            ->get('/builder/dashboard')
            ->assertOk()
            ->assertSee('Builder / Developer Dashboard');

        // Navigation check: builder role key is 'builder' with allowed workspace items
        $this->assertEquals('builder', NavigationConfig::getRoleKey($builder));
        $allowed = NavigationConfig::getAllowedItemKeys($builder);
        $this->assertEquals(['dashboard', 'leads', 'team', 'replacements', 'reports'], $allowed);
        $this->assertNotContains('credits', $allowed);
        $this->assertNotContains('integrations', $allowed);
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
            ->assertSee('Sales Executive Dashboard')
            ->assertSee('My Total Leads')
            ->assertSee('New Leads')
            ->assertSee('In Progress')
            ->assertSee('Follow-ups Due')
            ->assertSee('Closed Won')
            ->assertSee('Lost Leads');

        $this->actingAs($sales)
            ->get('/sales/dashboard')
            ->assertOk()
            ->assertSee('Sales Executive Dashboard');

        $this->assertEquals('sales-executive', NavigationConfig::getRoleKey($sales));
        $allowed = NavigationConfig::getAllowedItemKeys($sales);
        $this->assertEquals(['dashboard', 'leads', 'followups', 'reports'], $allowed);
        $this->assertNotContains('distribution', $allowed);
        $this->assertNotContains('organizations', $allowed);
    }
}

