<?php

namespace Tests\Feature\Admin;

use App\Livewire\Leads\ClientLeadManager;
use App\Models\{Campaign, Client, Followup, Lead, LeadSource, Organization, Project, User};
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationLeadManagementFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $org;
    private Client $client;
    private Project $projectA;
    private Project $projectB;
    private Campaign $campaignA1;
    private Campaign $campaignA2;
    private Campaign $campaignB1;
    private LeadSource $sourceMeta;
    private LeadSource $sourceGoogle;
    private User $exec1;
    private User $exec2;

    private Organization $otherOrg;
    private Client $otherClient;
    private Lead $foreignLead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);

        // Target Organization
        $this->org = Organization::create(['name' => 'Acme Corp']);
        $this->client = Client::create(['organization_id' => $this->org->id, 'name' => 'Acme Client User']);

        // Executives in this org
        $this->exec1 = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'John Executive', 'status' => 'active']);
        $this->exec1->assignRole('sales-executive');

        $this->exec2 = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Sarah Agent', 'status' => 'active']);
        $this->exec2->assignRole('sales-executive');

        // Projects & Campaigns
        $this->projectA = Project::create(['client_id' => $this->client->id, 'name' => 'Green Valley']);
        $this->projectB = Project::create(['client_id' => $this->client->id, 'name' => 'Skyline Towers']);

        $this->sourceMeta = LeadSource::create(['name' => 'Meta Ads']);
        $this->sourceGoogle = LeadSource::create(['name' => 'Google Search']);

        $this->campaignA1 = Campaign::create(['project_id' => $this->projectA->id, 'name' => 'GV Meta Spring', 'lead_source_id' => $this->sourceMeta->id]);
        $this->campaignA2 = Campaign::create(['project_id' => $this->projectA->id, 'name' => 'GV Google Search', 'lead_source_id' => $this->sourceGoogle->id]);
        $this->campaignB1 = Campaign::create(['project_id' => $this->projectB->id, 'name' => 'ST Facebook Launch', 'lead_source_id' => $this->sourceMeta->id]);

        // Unrelated Organization
        $this->otherOrg = Organization::create(['name' => 'Foreign Org']);
        $this->otherClient = Client::create(['organization_id' => $this->otherOrg->id, 'name' => 'Foreign Client']);
        $this->foreignLead = Lead::create([
            'lead_code' => 'LP-FOREIGN-01',
            'client_id' => $this->otherClient->id,
            'lead_source_id' => $this->sourceMeta->id,
            'name' => 'Foreign Person',
            'mobile' => '+919999900000',
            'status' => 'new',
            'current_stage' => 'new',
        ]);
    }

    private function createLead(array $attributes = []): Lead
    {
        static $counter = 100;
        $counter++;
        return Lead::create(array_merge([
            'lead_code' => 'LP-TEST-' . $counter,
            'client_id' => $this->client->id,
            'lead_source_id' => $this->sourceMeta->id,
            'name' => 'Test Lead ' . $counter,
            'mobile' => '+9198000' . sprintf('%05d', $counter),
            'status' => 'new',
            'current_stage' => 'new',
        ], $attributes));
    }

    private function manager()
    {
        return Livewire::test(ClientLeadManager::class, ['organizationId' => $this->org->id]);
    }

    public function test_default_overview_metrics_and_all_leads_isolation(): void
    {
        $this->createLead([
            'lead_code' => 'LP-ACME-01',
            'campaign_id' => $this->campaignA1->id,
            'name' => 'Alice Customer',
            'city' => 'Mumbai',
            'status' => 'new',
            'current_stage' => 'new',
            'assigned_to' => null,
            'created_at' => now(),
        ]);

        $this->createLead([
            'lead_code' => 'LP-ACME-02',
            'campaign_id' => $this->campaignA1->id,
            'name' => 'Bob Builder',
            'city' => 'Delhi',
            'status' => 'assigned',
            'current_stage' => 'contacted',
            'assigned_to' => $this->exec1->id,
            'created_at' => now(),
        ]);

        $manager = $this->manager();

        $manager->assertSee('LP-ACME-01')
            ->assertSee('LP-ACME-02')
            ->assertDontSee('LP-FOREIGN-01');

        $kpis = $manager->instance()->builderLeadKpis;
        $this->assertSame(2, $kpis['all']['value']);
        $this->assertSame(1, $kpis['new']['value']);
        $this->assertSame(1, $kpis['assigned']['value']);
        $this->assertSame(1, $kpis['unassigned']['value']);
        $this->assertSame(1, $kpis['in_progress']['value']);
    }

    public function test_date_range_filter_today_yesterday_and_custom(): void
    {
        $this->createLead([
            'lead_code' => 'LP-TODAY-01',
            'name' => 'Today Lead',
            'status' => 'new',
            'current_stage' => 'new',
            'created_at' => Carbon::today()->addHours(2),
        ]);

        $this->createLead([
            'lead_code' => 'LP-YEST-01',
            'name' => 'Yesterday Lead',
            'status' => 'new',
            'current_stage' => 'new',
            'created_at' => Carbon::yesterday()->addHours(3),
        ]);

        $manager = $this->manager();
        $manager->assertSee('LP-TODAY-01')->assertSee('LP-YEST-01');
        $this->assertSame(2, $manager->instance()->builderLeadKpis['all']['value']);

        $manager->set('analyticsRange', 'today')
            ->assertSee('LP-TODAY-01')
            ->assertDontSee('LP-YEST-01');
        $this->assertSame(1, $manager->instance()->builderLeadKpis['all']['value']);

        $manager->set('analyticsRange', 'yesterday')
            ->assertSee('LP-YEST-01')
            ->assertDontSee('LP-TODAY-01');
        $this->assertSame(1, $manager->instance()->builderLeadKpis['all']['value']);

        $manager->set('analyticsRange', 'all')
            ->assertSee('LP-TODAY-01')
            ->assertSee('LP-YEST-01');
        $this->assertSame(2, $manager->instance()->builderLeadKpis['all']['value']);
    }

    public function test_project_and_campaign_filters_with_dependency_reset(): void
    {
        $this->createLead([
            'lead_code' => 'LP-PROJ-A',
            'campaign_id' => $this->campaignA1->id,
            'name' => 'Project A Customer',
            'status' => 'new',
            'current_stage' => 'new',
        ]);

        $this->createLead([
            'lead_code' => 'LP-PROJ-B',
            'campaign_id' => $this->campaignB1->id,
            'name' => 'Project B Customer',
            'status' => 'new',
            'current_stage' => 'new',
        ]);

        $manager = $this->manager();

        $manager->set('project', (string) $this->projectA->id)
            ->assertSee('LP-PROJ-A')
            ->assertDontSee('LP-PROJ-B');

        $manager->set('campaign', (string) $this->campaignA1->id)
            ->assertSee('LP-PROJ-A');

        $manager->set('project', (string) $this->projectB->id)
            ->assertSet('campaign', '')
            ->assertSee('LP-PROJ-B')
            ->assertDontSee('LP-PROJ-A');
    }

    public function test_lead_source_filter(): void
    {
        $this->createLead([
            'lead_code' => 'LP-SRC-META',
            'campaign_id' => $this->campaignA1->id,
            'lead_source_id' => $this->sourceMeta->id,
            'name' => 'Meta Customer',
            'status' => 'new',
            'current_stage' => 'new',
        ]);

        $this->createLead([
            'lead_code' => 'LP-SRC-GOOGLE',
            'campaign_id' => $this->campaignA2->id,
            'lead_source_id' => $this->sourceGoogle->id,
            'name' => 'Google Customer',
            'status' => 'new',
            'current_stage' => 'new',
        ]);

        $manager = $this->manager();

        $manager->set('source', (string) $this->sourceMeta->id)
            ->assertSee('LP-SRC-META')
            ->assertDontSee('LP-SRC-GOOGLE');

        $manager->set('source', (string) $this->sourceGoogle->id)
            ->assertSee('LP-SRC-GOOGLE')
            ->assertDontSee('LP-SRC-META');
    }

    public function test_assigned_executive_filter(): void
    {
        $this->createLead([
            'lead_code' => 'LP-EXEC-1',
            'assigned_to' => $this->exec1->id,
            'name' => 'John Lead',
            'status' => 'assigned',
            'current_stage' => 'contacted',
        ]);

        $this->createLead([
            'lead_code' => 'LP-EXEC-2',
            'assigned_to' => $this->exec2->id,
            'name' => 'Sarah Lead',
            'status' => 'assigned',
            'current_stage' => 'contacted',
        ]);

        $manager = $this->manager();

        $manager->set('executive', (string) $this->exec1->id)
            ->assertSee('LP-EXEC-1')
            ->assertDontSee('LP-EXEC-2');

        $manager->set('executive', (string) $this->exec2->id)
            ->assertSee('LP-EXEC-2')
            ->assertDontSee('LP-EXEC-1');
    }

    public function test_lead_stage_and_status_filters(): void
    {
        $this->createLead([
            'lead_code' => 'LP-STAGE-VISIT',
            'name' => 'Visit Customer',
            'status' => 'site_visit',
            'current_stage' => 'site_visit',
        ]);

        $this->createLead([
            'lead_code' => 'LP-STAGE-WON',
            'name' => 'Won Customer',
            'status' => 'closed_won',
            'current_stage' => 'closed_won',
        ]);

        $manager = $this->manager();

        $manager->set('stageFilter', 'site_visit')
            ->assertSee('LP-STAGE-VISIT')
            ->assertDontSee('LP-STAGE-WON');

        $manager->set('stageFilter', '')
            ->set('statusFilter', 'closed_won')
            ->assertSee('LP-STAGE-WON')
            ->assertDontSee('LP-STAGE-VISIT');
    }

    public function test_delivery_handover_filter_and_reset(): void
    {
        $this->createLead([
            'lead_code' => 'LP-DELIV-YES',
            'name' => 'Delivered Lead',
            'status' => 'new',
            'current_stage' => 'new',
            'delivered_at' => now(),
        ]);

        $this->createLead([
            'lead_code' => 'LP-DELIV-FAIL',
            'name' => 'Failed Delivery Lead',
            'status' => 'new',
            'current_stage' => 'new',
            'delivery_failed_at' => now(),
        ]);

        $manager = $this->manager();

        $manager->set('deliveryFilter', 'delivered')
            ->assertSee('LP-DELIV-YES')
            ->assertDontSee('LP-DELIV-FAIL');

        $manager->set('deliveryFilter', 'failed')
            ->assertSee('LP-DELIV-FAIL')
            ->assertDontSee('LP-DELIV-YES');
    }

    public function test_search_by_code_name_phone_email_city_and_id(): void
    {
        $target = $this->createLead([
            'lead_code' => 'LP-FINDME-77',
            'name' => 'Dev Anand',
            'mobile' => '+919811122233',
            'email' => 'dev.anand@retro.cinema',
            'city' => 'Chandigarh',
            'status' => 'new',
            'current_stage' => 'new',
        ]);

        $this->createLead([
            'lead_code' => 'LP-OTHER-99',
            'name' => 'Raj Kapoor',
            'mobile' => '+919844455566',
            'email' => 'raj@rk.studios',
            'city' => 'Pune',
            'status' => 'new',
            'current_stage' => 'new',
        ]);

        $manager = $this->manager();

        $manager->set('search', 'FINDME')->assertSee('LP-FINDME-77')->assertDontSee('LP-OTHER-99');
        $manager->set('search', 'Anand')->assertSee('LP-FINDME-77')->assertDontSee('LP-OTHER-99');
        $manager->set('search', '111222')->assertSee('LP-FINDME-77')->assertDontSee('LP-OTHER-99');
        $manager->set('search', 'retro.cinema')->assertSee('LP-FINDME-77')->assertDontSee('LP-OTHER-99');
        $manager->set('search', 'Chandigarh')->assertSee('LP-FINDME-77')->assertDontSee('LP-OTHER-99');
        $manager->set('search', (string) $target->id)->assertSee('LP-FINDME-77')->assertDontSee('LP-OTHER-99');
    }

    public function test_sla_breached_filter(): void
    {
        $breached = $this->createLead([
            'lead_code' => 'LP-SLA-BREACHED',
            'name' => 'Breached Lead',
            'status' => 'assigned',
            'current_stage' => 'contacted',
            'assigned_to' => $this->exec1->id,
            'executive_assigned_at' => now()->subHour(),
            'executive_response_due_at' => now()->subMinutes(30),
            'executive_first_response_at' => null,
        ]);
        // Avoid observer overriding test-specific SLA dates
        \App\Models\Lead::whereKey($breached->id)->update([
            'executive_response_due_at' => now()->subMinutes(30),
            'executive_first_response_at' => null,
        ]);

        $healthy = $this->createLead([
            'lead_code' => 'LP-SLA-OK',
            'name' => 'Healthy Lead',
            'status' => 'assigned',
            'current_stage' => 'contacted',
            'assigned_to' => $this->exec1->id,
            'executive_assigned_at' => now(),
            'executive_response_due_at' => now()->addMinutes(30),
            'executive_first_response_at' => null,
        ]);
        \App\Models\Lead::whereKey($healthy->id)->update([
            'executive_response_due_at' => now()->addMinutes(30),
            'executive_first_response_at' => null,
        ]);

        $manager = $this->manager();

        $manager->set('sla_breached', true);
        $viewLeads = $manager->viewData('tableLeads');
        $this->assertTrue($viewLeads->contains('id', $breached->id));
        $this->assertFalse($viewLeads->contains('id', $healthy->id));

        $manager->set('sla_breached', false);
        $viewLeads = $manager->viewData('tableLeads');
        $this->assertTrue($viewLeads->contains('id', $breached->id));
        $this->assertTrue($viewLeads->contains('id', $healthy->id));
    }

    public function test_sorting_and_order_options(): void
    {
        $oldLead = $this->createLead([
            'lead_code' => 'LP-SORT-OLD',
            'name' => 'Old Lead',
            'lead_score' => 10,
            'status' => 'new',
            'current_stage' => 'new',
            'created_at' => now()->subDays(5),
        ]);

        $newLead = $this->createLead([
            'lead_code' => 'LP-SORT-NEW',
            'name' => 'New Lead',
            'lead_score' => 90,
            'status' => 'new',
            'current_stage' => 'new',
            'created_at' => now(),
        ]);

        $manager = $this->manager();

        $manager->set('sort_by', 'created_desc');
        $viewLeads = $manager->viewData('tableLeads');
        $this->assertSame($newLead->id, $viewLeads->first()->id);

        $manager->set('sort_by', 'created_asc');
        $viewLeads = $manager->viewData('tableLeads');
        $this->assertSame($oldLead->id, $viewLeads->first()->id);

        $manager->set('sort_by', 'score_desc');
        $viewLeads = $manager->viewData('tableLeads');
        $this->assertSame($newLead->id, $viewLeads->first()->id);
    }

    public function test_overview_metrics_cards_click_filters_table_without_distorting_card_totals(): void
    {
        $this->createLead([
            'lead_code' => 'LP-NEW-01',
            'name' => 'New Lead',
            'status' => 'new',
            'current_stage' => 'new',
            'assigned_to' => null,
        ]);

        $this->createLead([
            'lead_code' => 'LP-PROG-01',
            'name' => 'In Prog Lead',
            'status' => 'contacted',
            'current_stage' => 'contacted',
            'assigned_to' => $this->exec1->id,
        ]);

        $fuLead = $this->createLead([
            'lead_code' => 'LP-FU-01',
            'name' => 'Followup Lead',
            'status' => 'in_progress',
            'current_stage' => 'contacted',
            'assigned_to' => $this->exec2->id,
        ]);
        Followup::create([
            'lead_id' => $fuLead->id,
            'created_by' => $this->exec2->id,
            'status' => 'pending',
            'due_at' => now()->subHour(),
        ]);

        $this->createLead([
            'lead_code' => 'LP-LOST-01',
            'name' => 'Lost Lead',
            'status' => 'lost',
            'current_stage' => 'lost',
            'assigned_to' => $this->exec1->id,
        ]);

        $manager = $this->manager();

        $kpis = $manager->instance()->builderLeadKpis;
        $this->assertSame(4, $kpis['all']['value']);
        $this->assertSame(1, $kpis['new']['value']);
        $this->assertSame(3, $kpis['assigned']['value']);
        $this->assertSame(1, $kpis['unassigned']['value']);
        $this->assertSame(2, $kpis['in_progress']['value']);
        $this->assertSame(1, $kpis['followups']['value']);
        $this->assertSame(1, $kpis['lost']['value']);

        // Filter to 'lost'
        $manager->set('statusFilter', 'lost')
            ->assertSee('LP-LOST-01')
            ->assertDontSee('LP-NEW-01')
            ->assertDontSee('LP-PROG-01');

        // Card totals must remain preserved
        $kpisAfterLost = $manager->instance()->builderLeadKpis;
        $this->assertSame(4, $kpisAfterLost['all']['value']);
        $this->assertSame(1, $kpisAfterLost['lost']['value']);
        $this->assertSame(2, $kpisAfterLost['in_progress']['value']);

        // Filter to 'followups'
        $manager->set('statusFilter', 'followups')
            ->assertSee('LP-FU-01')
            ->assertDontSee('LP-LOST-01');

        // Filter to 'all'
        $manager->set('statusFilter', 'all')
            ->assertSee('LP-NEW-01')
            ->assertSee('LP-PROG-01')
            ->assertSee('LP-FU-01')
            ->assertSee('LP-LOST-01');
    }

    public function test_combined_filters_and_reset(): void
    {
        $this->createLead([
            'lead_code' => 'LP-COMBO-MATCH',
            'campaign_id' => $this->campaignA1->id,
            'lead_source_id' => $this->sourceMeta->id,
            'assigned_to' => $this->exec1->id,
            'name' => 'Target Match Person',
            'mobile' => '+919988776655',
            'city' => 'Bangalore',
            'status' => 'contacted',
            'current_stage' => 'contacted',
            'delivery_pending' => true,
        ]);

        $this->createLead([
            'lead_code' => 'LP-COMBO-DIFF-PROJ',
            'campaign_id' => $this->campaignB1->id,
            'lead_source_id' => $this->sourceMeta->id,
            'assigned_to' => $this->exec1->id,
            'name' => 'Target Match Person',
            'mobile' => '+919988776655',
            'city' => 'Bangalore',
            'status' => 'contacted',
            'current_stage' => 'contacted',
            'delivery_pending' => true,
        ]);

        $manager = $this->manager();

        $manager->set('project', (string) $this->projectA->id)
            ->set('campaign', (string) $this->campaignA1->id)
            ->set('source', (string) $this->sourceMeta->id)
            ->set('executive', (string) $this->exec1->id)
            ->set('statusFilter', 'contacted')
            ->set('search', 'Bangalore')
            ->set('deliveryFilter', 'pending')
            ->assertSee('LP-COMBO-MATCH')
            ->assertDontSee('LP-COMBO-DIFF-PROJ');

        $manager->call('resetFilters')
            ->assertSee('LP-COMBO-MATCH')
            ->assertSee('LP-COMBO-DIFF-PROJ');

        $this->assertSame('all', $manager->get('analyticsRange'));
        $this->assertSame('all', $manager->get('statusFilter'));
        $this->assertSame('', $manager->get('deliveryFilter'));
        $this->assertSame('', $manager->get('search'));
    }

    public function test_view_mode_toggle_preserves_filters(): void
    {
        $this->createLead([
            'lead_code' => 'LP-KANBAN-01',
            'name' => 'Toggle Lead',
            'status' => 'contacted',
            'current_stage' => 'contacted',
        ]);

        $manager = $this->manager();

        $this->assertSame('table', $manager->get('viewMode'));
        $manager->assertSee('LP-KANBAN-01');

        $manager->call('switchViewMode', 'kanban');
        $this->assertSame('kanban', $manager->get('viewMode'));
        $manager->assertSee('LP-KANBAN-01');

        $manager->call('switchViewMode', 'table');
        $this->assertSame('table', $manager->get('viewMode'));
        $manager->assertSee('LP-KANBAN-01');
    }
}
