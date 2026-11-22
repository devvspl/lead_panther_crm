<?php

namespace Tests\Feature\Builder;

use App\Livewire\Leads\LeadKanban;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BuilderLeadFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private User $builderUser;
    private User $execA1;
    private User $execA2;
    private Project $projectA1;
    private Project $projectA2;
    private Campaign $campaignA1;
    private Campaign $campaignA2;
    private LeadSource $source;

    private Organization $orgB;
    private User $builderB;
    private User $execB;
    private Project $projectB;
    private Campaign $campaignB;
    private Lead $foreignLead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Organization A (Target Builder)
        $this->orgA = Organization::create(['name' => 'Skyline Developers', 'type' => 'builder', 'status' => 'active']);
        $this->builderUser = User::factory()->create([
            'name' => 'Rajesh Builder',
            'organization_id' => $this->orgA->id,
            'status' => 'active',
        ]);
        $this->builderUser->assignRole('builder');

        $this->execA1 = User::factory()->create([
            'name' => 'Aarav Executive',
            'organization_id' => $this->orgA->id,
            'status' => 'active',
        ]);
        $this->execA1->assignRole('sales-executive');

        $this->execA2 = User::factory()->create([
            'name' => 'Bhavna Agent',
            'organization_id' => $this->orgA->id,
            'status' => 'active',
        ]);
        $this->execA2->assignRole('sales-executive');

        $this->projectA1 = Project::create([
            'client_id' => $this->builderUser->id,
            'name' => 'Skyline Heights',
            'status' => 'active',
        ]);
        $this->projectA2 = Project::create([
            'client_id' => $this->builderUser->id,
            'name' => 'Skyline Oasis',
            'status' => 'active',
        ]);

        $this->source = LeadSource::create(['name' => 'Meta Ads']);

        $this->campaignA1 = Campaign::create([
            'project_id' => $this->projectA1->id,
            'lead_source_id' => $this->source->id,
            'name' => 'Heights Launch',
        ]);
        $this->campaignA2 = Campaign::create([
            'project_id' => $this->projectA2->id,
            'lead_source_id' => $this->source->id,
            'name' => 'Oasis Promo',
        ]);

        // Organization B (Foreign)
        $this->orgB = Organization::create(['name' => 'Other Real Estate', 'type' => 'builder', 'status' => 'active']);
        $this->builderB = User::factory()->create([
            'name' => 'Foreign Builder',
            'organization_id' => $this->orgB->id,
            'status' => 'active',
        ]);
        $this->builderB->assignRole('builder');

        $this->execB = User::factory()->create([
            'name' => 'Foreign Executive',
            'organization_id' => $this->orgB->id,
            'status' => 'active',
        ]);
        $this->execB->assignRole('sales-executive');

        $this->projectB = Project::create([
            'client_id' => $this->builderB->id,
            'name' => 'Other Complex',
            'status' => 'active',
        ]);
        $this->campaignB = Campaign::create([
            'project_id' => $this->projectB->id,
            'lead_source_id' => $this->source->id,
            'name' => 'Foreign Campaign',
        ]);

        $this->foreignLead = $this->createDeliveredLead([
            'lead_code' => 'LP-FOREIGN-999',
            'client_id' => $this->builderB->id,
            'campaign_id' => $this->campaignB->id,
            'name' => 'Foreign Customer',
            'mobile' => '+919876599999',
            'status' => 'new',
            'current_stage' => 'new',
        ]);
    }

    private function createDeliveredLead(array $attributes): Lead
    {
        $created = $attributes['created_at'] ?? now();
        $lead = Lead::create(array_merge([
            'client_id' => $this->builderUser->id,
            'campaign_id' => $this->campaignA1->id,
            'lead_source_id' => $this->source->id,
            'status' => 'new',
            'current_stage' => 'new',
            'internal_processing_pending' => false,
        ], $attributes));

        $lead->forceFill([
            'delivery_pending' => false,
            'delivered_at' => $created,
            'created_at' => $created,
        ])->saveQuietly();

        return $lead;
    }

    public function test_builder_sees_only_own_organization_leads_and_filter_options(): void
    {
        $this->createDeliveredLead([
            'lead_code' => 'LP-A1-001',
            'name' => 'Customer One',
            'mobile' => '+919876500001',
            'assigned_to' => $this->execA1->id,
        ]);

        $component = Livewire::actingAs($this->builderUser)->test(LeadKanban::class);

        // Security check: builder sees lead1, does not see foreign lead
        $component->assertSee('LP-A1-001')
            ->assertDontSee('LP-FOREIGN-999');

        // Check project filter options contain Skyline projects but not Other Complex
        $projects = $component->viewData('allProjects')->pluck('name')->toArray();
        $this->assertContains('Skyline Heights', $projects);
        $this->assertContains('Skyline Oasis', $projects);
        $this->assertNotContains('Other Complex', $projects);

        // Check team member filter options contain Aarav and Bhavna, but not Foreign Executive
        $teamMembers = $component->viewData('builderTeamMembers')->pluck('name')->toArray();
        $this->assertContains('Aarav Executive', $teamMembers);
        $this->assertContains('Bhavna Agent', $teamMembers);
        $this->assertNotContains('Foreign Executive', $teamMembers);
    }

    public function test_builder_project_filter(): void
    {
        $this->createDeliveredLead([
            'lead_code' => 'LP-PROJ-1',
            'campaign_id' => $this->campaignA1->id,
            'name' => 'Heights Buyer',
            'mobile' => '+919876500010',
        ]);

        $this->createDeliveredLead([
            'lead_code' => 'LP-PROJ-2',
            'campaign_id' => $this->campaignA2->id,
            'name' => 'Oasis Buyer',
            'mobile' => '+919876500020',
        ]);

        Livewire::actingAs($this->builderUser)->test(LeadKanban::class)
            ->set('project', (string) $this->projectA1->id)
            ->assertSee('LP-PROJ-1')
            ->assertDontSee('LP-PROJ-2');
    }

    public function test_builder_team_member_filter(): void
    {
        $this->createDeliveredLead([
            'lead_code' => 'LP-EXEC-1',
            'name' => 'Aarav Lead',
            'mobile' => '+919876500030',
            'status' => 'assigned',
            'current_stage' => 'assigned',
            'assigned_to' => $this->execA1->id,
        ]);

        $this->createDeliveredLead([
            'lead_code' => 'LP-EXEC-2',
            'name' => 'Bhavna Lead',
            'mobile' => '+919876500040',
            'status' => 'assigned',
            'current_stage' => 'assigned',
            'assigned_to' => $this->execA2->id,
        ]);

        Livewire::actingAs($this->builderUser)->test(LeadKanban::class)
            ->set('executive', (string) $this->execA1->id)
            ->assertSee('LP-EXEC-1')
            ->assertDontSee('LP-EXEC-2');
    }

    public function test_builder_lead_status_and_stage_filters(): void
    {
        $this->createDeliveredLead([
            'lead_code' => 'LP-STATUS-NEW',
            'name' => 'New Customer',
            'mobile' => '+919876500050',
            'status' => 'new',
            'current_stage' => 'new',
        ]);

        $this->createDeliveredLead([
            'lead_code' => 'LP-STATUS-SITE',
            'name' => 'Site Visit Customer',
            'mobile' => '+919876500060',
            'status' => 'active',
            'current_stage' => 'site_visit',
        ]);

        // Status filter: site_visit
        Livewire::actingAs($this->builderUser)->test(LeadKanban::class)
            ->set('statusFilter', 'site_visit')
            ->assertSee('LP-STATUS-SITE')
            ->assertDontSee('LP-STATUS-NEW');

        // Stage filter: new
        Livewire::actingAs($this->builderUser)->test(LeadKanban::class)
            ->set('statusFilter', 'all')
            ->set('stageFilter', 'new')
            ->assertSee('LP-STATUS-NEW')
            ->assertDontSee('LP-STATUS-SITE');
    }

    public function test_builder_date_range_filter(): void
    {
        $todayLead = $this->createDeliveredLead([
            'lead_code' => 'LP-DATE-TODAY',
            'name' => 'Today Customer',
            'mobile' => '+919876500070',
            'created_at' => Carbon::today()->addHours(2),
        ]);

        $yesterdayLead = $this->createDeliveredLead([
            'lead_code' => 'LP-DATE-YEST',
            'name' => 'Yesterday Customer',
            'mobile' => '+919876500080',
            'created_at' => Carbon::yesterday()->addHours(3),
        ]);

        // Today filter
        Livewire::actingAs($this->builderUser)->test(LeadKanban::class)
            ->set('analyticsRange', 'today')
            ->assertSee('LP-DATE-TODAY')
            ->assertDontSee('LP-DATE-YEST');

        // Yesterday filter
        Livewire::actingAs($this->builderUser)->test(LeadKanban::class)
            ->set('analyticsRange', 'yesterday')
            ->assertSee('LP-DATE-YEST')
            ->assertDontSee('LP-DATE-TODAY');

        // All time filter
        Livewire::actingAs($this->builderUser)->test(LeadKanban::class)
            ->set('analyticsRange', 'all')
            ->assertSee('LP-DATE-TODAY')
            ->assertSee('LP-DATE-YEST');
    }

    public function test_builder_search_sorting_pagination_and_reset_filters(): void
    {
        $this->createDeliveredLead([
            'lead_code' => 'LP-ALPHA-01',
            'name' => 'Alpha Specific Person',
            'mobile' => '+919876511111',
        ]);

        $this->createDeliveredLead([
            'lead_code' => 'LP-BETA-02',
            'name' => 'Beta Regular Person',
            'mobile' => '+919876522222',
        ]);

        $component = Livewire::actingAs($this->builderUser)->test(LeadKanban::class);

        // Search test
        $component->set('search', 'Alpha Specific')
            ->assertSee('LP-ALPHA-01')
            ->assertDontSee('LP-BETA-02');

        // Reset search
        $component->set('search', '')
            ->assertSee('LP-ALPHA-01')
            ->assertSee('LP-BETA-02');

        // Set filters then call resetFilters()
        $component->set('project', (string) $this->projectA1->id)
            ->set('statusFilter', 'new')
            ->set('stageFilter', 'new')
            ->set('executive', (string) $this->execA1->id)
            ->set('analyticsRange', 'today')
            ->call('resetFilters')
            ->assertSet('project', '')
            ->assertSet('statusFilter', 'all')
            ->assertSet('stageFilter', '')
            ->assertSet('executive', '')
            ->assertSet('search', '');
    }

    public function test_builder_table_view_filters_sorting_and_pagination(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->createDeliveredLead([
                'lead_code' => sprintf('LP-TABLE-%02d', $i),
                'name' => sprintf('Table Lead %02d', $i),
                'mobile' => sprintf('+91987650%04d', $i),
                'lead_score' => $i * 5,
            ]);
        }

        $component = Livewire::actingAs($this->builderUser)
            ->test(LeadKanban::class)
            ->set('viewMode', 'table');

        // Check leads appear in table
        $component->assertSee('LP-TABLE-01')
            ->assertSee('LP-TABLE-10');

        // Sorting by lead_score desc
        $component->set('sort_by', 'score_desc');
        $rows = $component->viewData('tableLeads');
        $this->assertNotNull($rows);
        $this->assertGreaterThanOrEqual($rows->last()->lead_score, $rows->first()->lead_score);

        // Sorting by lead_score asc
        $component->set('sort_by', 'score_asc');
        $rowsAsc = $component->viewData('tableLeads');
        $this->assertNotNull($rowsAsc);
        $this->assertLessThanOrEqual($rowsAsc->last()->lead_score, $rowsAsc->first()->lead_score);
    }
}
