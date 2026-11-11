<?php

namespace Tests\Feature\Builder;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Lead;
use App\Models\CreditWallet;
use App\Livewire\Dashboard\DashboardShell;
use App\Livewire\Leads\LeadKanban;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class BuilderRoleSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'builder']);
    }

    public function test_builder_dashboard_renders_operational_cards_and_readonly_credit(): void
    {
        $org = Organization::create([
            'name' => 'Skyline Builders',
            'type' => 'builder',
            'status' => 'active',
        ]);

        $builderUser = User::factory()->create([
            'name' => 'Rajesh Builder',
            'organization_id' => $org->id,
            'status' => 'active',
        ]);
        $builderUser->assignRole('builder');

        CreditWallet::create([
            'client_id' => $builderUser->id,
            'balance' => 500.00,
            'opening_balance' => 500.00,
        ]);

        Livewire::actingAs($builderUser)
            ->test(DashboardShell::class, ['role' => 'builder'])
            ->assertSee('Builder / Developer Dashboard')
            ->assertSee('Credit Summary')
            ->assertSee('Read-Only')
            ->assertSee('Available Credits')
            ->assertSee('Team Performance')
            ->assertSee('Upcoming Schedule & Follow-ups')
            ->assertDontSee('Request Credit Recharge')
            ->assertDontSee('System Administration');
    }

    public function test_builder_my_leads_view_shows_8_kpis_and_11_columns(): void
    {
        $org = Organization::create([
            'name' => 'Skyline Builders',
            'type' => 'builder',
            'status' => 'active',
        ]);

        $builderUser = User::factory()->create([
            'name' => 'Rajesh Builder',
            'organization_id' => $org->id,
            'status' => 'active',
        ]);
        $builderUser->assignRole('builder');

        $component = Livewire::actingAs($builderUser)->test(LeadKanban::class);

        $this->assertTrue($component->get('isBuilder'));

        $columns = $component->instance()->tableColumns();
        $this->assertCount(11, $columns);

        $columnKeys = collect($columns)->pluck('key')->all();
        $this->assertContains('lead_code', $columnKeys);
        $this->assertContains('name', $columnKeys);
        $this->assertContains('mobile', $columnKeys);
        $this->assertContains('project_name', $columnKeys);
        $this->assertContains('lead_source_name', $columnKeys);
        $this->assertContains('created_at', $columnKeys);
        $this->assertContains('assigned_to_user', $columnKeys);
        $this->assertContains('current_stage', $columnKeys);
        $this->assertContains('status', $columnKeys);
        $this->assertContains('sla_badge', $columnKeys);
        $this->assertContains('actions', $columnKeys);

        $component->assertSee('My Leads')
            ->assertSee('Organization Pipeline')
            ->assertDontSee('Add / Ingest Lead')
            ->assertDontSee('Complete Lead Lifecycle Funnel')
            ->assertDontSee('Lead Analytics & Breakdown');
    }

    public function test_super_admin_leads_view_retains_full_funnel_and_ingestion(): void
    {
        $admin = User::factory()->create(['name' => 'Super Admin User']);
        $admin->assignRole('super-admin');

        $component = Livewire::actingAs($admin)->test(LeadKanban::class);

        $this->assertFalse($component->get('isBuilder'));
        $component->assertSee('Leads')
            ->assertSee('Live Pipeline')
            ->assertSee('Add / Ingest Lead')
            ->assertSee('Complete Lead Lifecycle Funnel')
            ->assertSee('Lead Analytics & Breakdown');
    }
}
