<?php

namespace Tests\Feature\Leads;

use Tests\TestCase;
use App\Models\User;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\Campaign;
use App\Models\LeadSource;
use App\Models\Followup;
use App\Livewire\Leads\LeadKanban;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class LeadsPageRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'sales-executive']);
        Role::firstOrCreate(['name' => 'Builder/Developer']);
    }

    public function test_leads_page_renders_with_leads_and_analytics_tabs_defaulting_to_leads(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->assertSet('activeMainTab', 'leads')
            ->assertSee('Leads')
            ->assertSee('Analytics')
            ->assertDontSeeHtml('class="rounded-lg border border-border px-4 py-2 bg-ink text-white">Client Leads</button>')
            ->assertSee('Filters')
            ->assertSee('Sort By');
    }

    public function test_collapsible_filter_panel_toggles_and_calculates_active_filter_count(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $org = Organization::create(['name' => 'Alpha Org', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Alpha Client']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Alpha Tower']);

        $component = Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->assertSet('filtersOpen', false)
            ->call('toggleFilters')
            ->assertSet('filtersOpen', true);

        // Apply a filter and verify active filter count increases
        $component->set('project', (string) $project->id);
        $this->assertGreaterThanOrEqual(1, $component->get('activeFilterCount'));

        // Reset filters
        $component->call('resetFilters');
        $this->assertEquals('', $component->get('project'));
        $this->assertEquals(0, $component->get('activeFilterCount'));
    }

    public function test_analytics_tab_renders_real_database_metrics_and_charts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $org = Organization::create(['name' => 'Beta Org', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Beta Client']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Beta Heights']);
        $source = LeadSource::create(['name' => 'Meta Ads']);

        $salesUser = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'status' => 'active',
        ]);
        $salesUser->assignRole('sales-executive');

        // Create 3 leads: 1 new, 1 in_progress, 1 closed_won
        $lead1 = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'assigned_to' => $salesUser->id,
            'status' => 'new',
            'current_stage' => 'new',
            'created_at' => now(),
        ]);

        $lead2 = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'assigned_to' => $salesUser->id,
            'status' => 'in_progress',
            'current_stage' => 'contacted',
            'first_response_at' => now(),
            'created_at' => now(),
        ]);

        $lead3 = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'assigned_to' => $salesUser->id,
            'status' => 'converted',
            'current_stage' => 'closed_won',
            'created_at' => now(),
        ]);

        // Add pending followup
        Followup::create([
            'lead_id' => $lead2->id,
            'created_by' => $salesUser->id,
            'status' => 'pending',
            'due_at' => now()->subHour(),
            'note' => 'Call customer',
        ]);

        $component = Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->call('setMainTab', 'analytics')
            ->assertSet('activeMainTab', 'analytics')
            ->assertSee('Lead Analytics')
            ->assertSee('Lead Trend')
            ->assertSee('Leads by Stage')
            ->assertSee('Lead Status Distribution')
            ->assertSee('Lead Source Breakdown')
            ->assertSee('Team Performance')
            ->assertSee('Export Report')
            ->assertSee('John Doe');

        $analyticsData = $component->get('analyticsData');
        $this->assertEquals(3, $analyticsData['kpis']['total']);
        $this->assertEquals(1, $analyticsData['kpis']['new']);
        $this->assertEquals(1, $analyticsData['kpis']['in_progress']);
        $this->assertEquals(1, $analyticsData['kpis']['followups_due']);
        $this->assertEquals(1, $analyticsData['kpis']['closed_won']);
        $this->assertEquals(0, $analyticsData['kpis']['lost']);

        // Check Team Performance
        $this->assertCount(1, $analyticsData['team_performance']);
        $memberStat = $analyticsData['team_performance']->first();
        $this->assertEquals('John Doe', $memberStat['name']);
        $this->assertEquals(3, $memberStat['assigned']);
        $this->assertEquals(1, $memberStat['converted']);
        $this->assertEquals(33.3, $memberStat['conversion_rate']);
    }

    public function test_granularity_toggle_updates_trend_chart_grouping(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->call('setMainTab', 'analytics')
            ->assertSet('trendGranularity', 'daily')
            ->set('trendGranularity', 'weekly')
            ->assertSet('trendGranularity', 'weekly')
            ->set('trendGranularity', 'monthly')
            ->assertSet('trendGranularity', 'monthly');
    }

    public function test_click_to_filter_from_analytics_switches_to_leads_tab_with_filter(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->call('setMainTab', 'analytics')
            ->assertSet('activeMainTab', 'analytics')
            ->call('filterFromAnalytics', 'status', 'new')
            ->assertSet('activeMainTab', 'leads')
            ->assertSet('statusFilter', 'new')
            ->call('setMainTab', 'analytics')
            ->call('filterFromAnalytics', 'stage', 'site_visit')
            ->assertSet('activeMainTab', 'leads')
            ->assertSet('stageFilter', 'site_visit')
            ->assertSet('statusFilter', 'all');
    }

    public function test_export_analytics_report_returns_download_response(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $response = Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->call('exportAnalyticsReport');

        $response->assertFileDownloaded();
    }

    public function test_organization_data_isolation_between_builders_and_super_admin(): void
    {
        $org1 = Organization::create(['name' => 'Org One', 'type' => 'builder']);
        $builder1 = User::factory()->create(['organization_id' => $org1->id]);
        $builder1->assignRole('Builder/Developer');
        $project1 = Project::create(['client_id' => $builder1->id, 'name' => 'Tower One']);

        $org2 = Organization::create(['name' => 'Org Two', 'type' => 'builder']);
        $builder2 = User::factory()->create(['organization_id' => $org2->id]);
        $builder2->assignRole('Builder/Developer');
        $project2 = Project::create(['client_id' => $builder2->id, 'name' => 'Tower Two']);

        $source = LeadSource::firstOrCreate(['name' => 'Org Source']);

        $lead1 = Lead::factory()->create([
            'client_id' => $builder1->id,
            'project_id' => $project1->id,
            'lead_source_id' => $source->id,
            'internal_processing_pending' => false,
            'status' => 'new',
            'current_stage' => 'new',
            'created_at' => now(),
        ]);
        $lead1->forceFill(['delivery_pending' => false])->saveQuietly();

        $lead2 = Lead::factory()->create([
            'client_id' => $builder2->id,
            'project_id' => $project2->id,
            'lead_source_id' => $source->id,
            'internal_processing_pending' => false,
            'status' => 'new',
            'current_stage' => 'new',
            'created_at' => now(),
        ]);
        $lead2->forceFill(['delivery_pending' => false])->saveQuietly();

        // Builder 1 only sees Org 1 leads
        $builderComponent = Livewire::actingAs($builder1)
            ->test(LeadKanban::class)
            ->call('setMainTab', 'analytics');

        $analytics1 = $builderComponent->get('analyticsData');
        $this->assertEquals(1, $analytics1['kpis']['total']);

        // Super Admin sees all leads
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $adminComponent = Livewire::actingAs($superAdmin)
            ->test(LeadKanban::class)
            ->call('setMainTab', 'analytics');

        $adminAnalytics = $adminComponent->get('analyticsData');
        $this->assertEquals(2, $adminAnalytics['kpis']['total']);
    }
}
