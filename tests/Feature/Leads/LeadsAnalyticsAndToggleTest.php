<?php

namespace Tests\Feature\Leads;

use Tests\TestCase;
use App\Models\User;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\LeadSource;
use App\Models\GeneralSetting;
use App\Livewire\Leads\LeadKanban;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class LeadsAnalyticsAndToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'sales-executive']);
    }

    public function test_leads_analytics_summary_bar_and_general_settings_persistence(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $org = Organization::create(['name' => 'Org Analytics', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Analytics']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Analytics']);
        $source = LeadSource::create(['name' => 'google']);

        Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'current_stage' => 'closed_won',
            'created_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->assertSee('Leads Performance')
            ->assertSee('Total Leads')
            ->assertSee('New Leads Today')
            ->assertSee('SLA Breached')
            ->assertSee('Conversion Rate')
            ->assertSee('Breakdown Analytics');
    }

    public function test_toggling_view_mode_persists_to_general_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->call('switchViewMode', 'table')
            ->assertSet('viewMode', 'table');

        $this->assertDatabaseHas('general_settings', [
            'user_id' => $user->id,
            'key' => 'leads_view_mode',
            'value' => 'table',
        ]);

        // Verify mount reads setting back
        Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->assertSet('viewMode', 'table');
    }

    public function test_changing_analytics_date_range_updates_general_settings_and_filters(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->set('analyticsRange', 'today')
            ->assertSet('analyticsRange', 'today');

        $this->assertDatabaseHas('general_settings', [
            'user_id' => $user->id,
            'key' => 'leads_analytics_range',
            'value' => 'today',
        ]);
    }

    public function test_breakdown_tabs_switch_dimension(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->set('breakdownTab', 'project')
            ->assertSet('breakdownTab', 'project')
            ->set('breakdownTab', 'source')
            ->assertSet('breakdownTab', 'source');
    }

    public function test_custom_date_range_filters_analytics_and_persists_in_general_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $org = Organization::create(['name' => 'Org Custom Range', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Custom Range']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Custom Range']);
        $source = LeadSource::create(['name' => 'meta']);

        // Lead within custom range (June 2026)
        Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'current_stage' => 'closed_won',
            'created_at' => '2026-06-15 10:00:00',
        ]);

        // Lead outside custom range (January 2026)
        Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'current_stage' => 'closed_won',
            'created_at' => '2026-01-10 10:00:00',
        ]);

        Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->dispatch('date-range-applied', [
                'range' => 'custom',
                'from' => '2026-06-01',
                'to' => '2026-06-30',
            ])
            ->assertSet('analyticsRange', 'custom')
            ->assertSet('analyticsCustomFrom', '2026-06-01')
            ->assertSet('analyticsCustomTo', '2026-06-30');

        $this->assertDatabaseHas('general_settings', [
            'user_id' => $user->id,
            'key' => 'leads_analytics_range',
        ]);

        // Verify restoring on new mount
        Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->assertSet('analyticsRange', 'custom')
            ->assertSet('analyticsCustomFrom', '2026-06-01')
            ->assertSet('analyticsCustomTo', '2026-06-30');
    }
}
