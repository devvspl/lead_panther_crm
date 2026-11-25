<?php

namespace Tests\Feature\Followup;

use App\Livewire\Activity\FollowupList;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Organization;
use App\Models\Project;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class FollowupFiltersAndExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $builder;
    protected User $teamLeader;
    protected User $executive1;
    protected User $executive2;
    protected Organization $org1;
    protected Organization $org2;
    protected Project $project1;
    protected Project $project2;
    protected Campaign $campaign1;
    protected Campaign $campaign2;
    protected LeadSource $source;
    protected SalesTeam $team;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin']);
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'Builder/Developer']);
        Role::firstOrCreate(['name' => 'Team Leader']);
        Role::firstOrCreate(['name' => 'Executive']);

        Permission::firstOrCreate(['name' => 'view-team-followups']);
        Permission::firstOrCreate(['name' => 'view-team-leads']);

        // Organizations
        $this->org1 = Organization::create([
            'name' => 'Org One',
            'status' => 'active',
        ]);

        $this->org2 = Organization::create([
            'name' => 'Org Two',
            'status' => 'active',
        ]);

        // Users
        $this->admin = User::factory()->create(['status' => 'active', 'email' => 'admin@example.com']);
        $this->admin->assignRole('Super Admin');

        $client1 = Client::create([
            'organization_id' => $this->org1->id,
            'name' => 'Builder One',
            'email' => 'builder1@example.com',
            'status' => 'active',
        ]);
        $this->builder = User::find($client1->id);
        $this->builder->assignRole('Builder/Developer');

        $this->teamLeader = User::factory()->create([
            'organization_id' => $this->org1->id,
            'name' => 'Leader One',
            'email' => 'leader1@example.com',
            'status' => 'active',
        ]);
        $this->teamLeader->assignRole('Team Leader');
        $this->teamLeader->givePermissionTo('view-team-followups');

        $this->executive1 = User::factory()->create([
            'organization_id' => $this->org1->id,
            'name' => 'Aman Exec',
            'email' => 'aman@example.com',
            'status' => 'active',
        ]);
        $this->executive1->assignRole('Executive');

        $this->executive2 = User::factory()->create([
            'organization_id' => $this->org1->id,
            'name' => 'Rahul Exec',
            'email' => 'rahul@example.com',
            'status' => 'active',
        ]);
        $this->executive2->assignRole('Executive');

        // Teams
        $this->team = SalesTeam::create([
            'ownable_type' => Organization::class,
            'ownable_id' => $this->org1->id,
            'leader_id' => $this->teamLeader->id,
            'name' => 'Alpha Team',
        ]);

        SalesTeamMember::create(['sales_team_id' => $this->team->id, 'user_id' => $this->teamLeader->id, 'role' => 'Team Leader', 'is_active' => true]);
        SalesTeamMember::create(['sales_team_id' => $this->team->id, 'user_id' => $this->executive1->id, 'role' => 'Executive', 'is_active' => true]);
        SalesTeamMember::create(['sales_team_id' => $this->team->id, 'user_id' => $this->executive2->id, 'role' => 'Executive', 'is_active' => true]);

        // Projects & Campaigns
        $this->project1 = Project::create([
            'client_id' => $this->builder->id,
            'name' => 'Project Alpha',
            'status' => 'active',
        ]);

        $this->project2 = Project::create([
            'client_id' => $this->builder->id,
            'name' => 'Project Beta',
            'status' => 'active',
        ]);

        $this->source = LeadSource::firstOrCreate(['name' => 'Google Ads']);

        $this->campaign1 = Campaign::create([
            'project_id' => $this->project1->id,
            'lead_source_id' => $this->source->id,
            'name' => 'Alpha Campaign',
            'status' => 'active',
        ]);

        $this->campaign2 = Campaign::create([
            'project_id' => $this->project2->id,
            'lead_source_id' => $this->source->id,
            'name' => 'Beta Campaign',
            'status' => 'active',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function createLeadWithFollowup(array $leadData = [], array $followupData = []): Followup
    {
        $lead = Lead::create(array_merge([
            'client_id' => $this->builder->id,
            'campaign_id' => $this->campaign1->id,
            'lead_source_id' => $this->source->id,
            'name' => 'John Doe',
            'mobile' => '9876543210',
            'email' => 'john@example.com',
            'city' => 'Noida',
            'lead_code' => Lead::generateUniqueLeadCode('TEST'),
            'requirement' => '3 BHK Apartment',
            'status' => 'contacted',
            'current_stage' => 'contacted',
            'assigned_to' => $this->executive1->id,
            'delivery_pending' => false,
            'internal_processing_pending' => false,
            'delivered_at' => now(),
            'executive_assigned_at' => now(),
        ], $leadData));

        $lead->updateQuietly([
            'delivery_pending' => false,
            'internal_processing_pending' => false,
        ]);

        return Followup::create(array_merge([
            'lead_id' => $lead->id,
            'assigned_to' => $lead->assigned_to,
            'created_by' => $this->builder->id,
            'due_at' => now()->addDay(),
            'type' => 'call',
            'note' => 'Follow up on unit floor plan',
            'status' => 'pending',
        ], $followupData));
    }

    public function test_followup_filters_search_project_campaign_type_and_stage(): void
    {
        $this->actingAs($this->builder);

        // Followup 1: Project Alpha, Call, Contacted stage
        $fu1 = $this->createLeadWithFollowup(
            ['name' => 'Varun Yadav', 'mobile' => '9711179372', 'current_stage' => 'interested', 'campaign_id' => $this->campaign1->id],
            ['note' => 'Varun wants 100 sq ft retail space', 'type' => 'call']
        );

        // Followup 2: Project Beta, Meeting, Site Visit stage
        $fu2 = $this->createLeadWithFollowup(
            ['name' => 'Ramesh Kumar', 'mobile' => '9811122233', 'current_stage' => 'site_visit', 'campaign_id' => $this->campaign2->id],
            ['note' => 'Site visit discussion at site office', 'type' => 'meeting']
        );

        // Test search
        Livewire::test(FollowupList::class)
            ->set('search', 'Varun')
            ->assertSee('Varun Yadav')
            ->assertDontSee('Ramesh Kumar');

        // Test search by phone
        Livewire::test(FollowupList::class)
            ->set('search', '9811122233')
            ->assertSee('Ramesh Kumar')
            ->assertDontSee('Varun Yadav');

        // Test search by note
        Livewire::test(FollowupList::class)
            ->set('search', 'retail space')
            ->assertSee('Varun Yadav')
            ->assertDontSee('Ramesh Kumar');

        // Test project filter
        Livewire::test(FollowupList::class)
            ->set('projectId', $this->project1->id)
            ->assertSee('Varun Yadav')
            ->assertDontSee('Ramesh Kumar');

        // Test campaign filter
        Livewire::test(FollowupList::class)
            ->set('campaignId', $this->campaign2->id)
            ->assertSee('Ramesh Kumar')
            ->assertDontSee('Varun Yadav');

        // Test action type filter
        Livewire::test(FollowupList::class)
            ->set('typeFilter', 'meeting')
            ->assertSee('Ramesh Kumar')
            ->assertDontSee('Varun Yadav');

        // Test lead stage filter
        Livewire::test(FollowupList::class)
            ->set('stageFilter', 'site_visit')
            ->assertSee('Ramesh Kumar')
            ->assertDontSee('Varun Yadav');
    }

    public function test_followup_status_and_timing_filters(): void
    {
        $this->actingAs($this->builder);

        // Due today
        $todayFu = $this->createLeadWithFollowup(
            ['name' => 'Today Lead'],
            ['due_at' => now()->startOfDay()->addHours(14), 'note' => 'Urgent today action']
        );

        // Overdue
        $overdueFu = $this->createLeadWithFollowup(
            ['name' => 'Overdue Lead'],
            ['due_at' => now()->subDays(2), 'note' => 'Past deadline']
        );

        // Completed
        $completedFu = $this->createLeadWithFollowup(
            ['name' => 'Completed Lead'],
            ['due_at' => now()->subDay(), 'status' => 'completed', 'completed_at' => now()->subHours(2), 'note' => 'Already discussed']
        );

        // Filter by status: today
        Livewire::test(FollowupList::class)
            ->set('statusFilter', 'today')
            ->assertSee('Today Lead')
            ->assertDontSee('Overdue Lead')
            ->assertDontSee('Completed Lead');

        // Filter by status: overdue
        Livewire::test(FollowupList::class)
            ->set('statusFilter', 'overdue')
            ->assertSee('Overdue Lead')
            ->assertDontSee('Today Lead')
            ->assertDontSee('Completed Lead');

        // Filter by status: completed
        Livewire::test(FollowupList::class)
            ->set('statusFilter', 'completed')
            ->set('activeTab', 'completed')
            ->assertSee('Completed Lead')
            ->assertDontSee('Today Lead')
            ->assertDontSee('Overdue Lead');
    }

    public function test_date_range_preset_and_custom_dates(): void
    {
        $this->actingAs($this->builder);

        $fu = $this->createLeadWithFollowup(
            ['name' => 'Date Test Lead'],
            ['due_at' => now()->startOfDay()->addHours(10)]
        );

        $test = Livewire::test(FollowupList::class)
            ->set('dateRange', 'today');

        $this->assertEquals(now()->toDateString(), $test->get('dateFrom'));
        $this->assertEquals(now()->toDateString(), $test->get('dateTo'));
        $test->assertSee('Date Test Lead');

        // Set date to last month - should not see today's followup
        $test->set('dateRange', 'lastMonth')
            ->assertDontSee('Date Test Lead');
    }

    public function test_team_and_executive_filters(): void
    {
        $this->actingAs($this->builder);

        $fu1 = $this->createLeadWithFollowup(
            ['name' => 'Lead For Aman', 'assigned_to' => $this->executive1->id],
            ['assigned_to' => $this->executive1->id, 'note' => 'Handled by Aman']
        );

        $fu2 = $this->createLeadWithFollowup(
            ['name' => 'Lead For Rahul', 'assigned_to' => $this->executive2->id],
            ['assigned_to' => $this->executive2->id, 'note' => 'Handled by Rahul']
        );

        // Filter by executive 1 (Aman)
        Livewire::test(FollowupList::class)
            ->set('executiveId', $this->executive1->id)
            ->assertSee('Lead For Aman')
            ->assertDontSee('Lead For Rahul');

        // Filter by team
        Livewire::test(FollowupList::class)
            ->set('teamId', $this->team->id)
            ->assertSee('Lead For Aman')
            ->assertSee('Lead For Rahul');
    }

    public function test_collapsible_filter_controls_and_reset(): void
    {
        $this->actingAs($this->builder);

        $test = Livewire::test(FollowupList::class)
            ->assertSet('filtersOpen', false)
            ->call('toggleFilters')
            ->assertSet('filtersOpen', true)
            ->set('search', 'TestSearch')
            ->set('typeFilter', 'call')
            ->assertSet('activeFilterCount', 2)
            ->call('clearFilter', 'search')
            ->assertSet('search', '')
            ->assertSet('activeFilterCount', 1)
            ->call('resetFilters')
            ->assertSet('typeFilter', '')
            ->assertSet('activeFilterCount', 0);
    }

    public function test_export_filtered_followups(): void
    {
        $this->actingAs($this->builder);

        $this->createLeadWithFollowup(
            ['name' => 'Export Customer 1', 'mobile' => '9999911111', 'current_stage' => 'interested'],
            ['note' => 'Special requirement for retail', 'type' => 'call']
        );

        Livewire::test(FollowupList::class)
            ->set('search', 'Export Customer 1')
            ->call('exportFiltered')
            ->assertDispatched('toast', type: 'success')
            ->assertFileDownloaded();
    }

    public function test_export_all_authorized_followups(): void
    {
        $this->actingAs($this->builder);

        $this->createLeadWithFollowup(['name' => 'Customer A']);
        $this->createLeadWithFollowup(['name' => 'Customer B']);

        // Even with a restrictive search filter, exportAll should export all authorized records
        Livewire::test(FollowupList::class)
            ->set('search', 'Customer A')
            ->call('exportAll')
            ->assertDispatched('toast', type: 'success')
            ->assertFileDownloaded();
    }

    public function test_export_current_view(): void
    {
        $this->actingAs($this->builder);

        $this->createLeadWithFollowup(['name' => 'Current View Customer']);

        Livewire::test(FollowupList::class)
            ->call('exportCurrentView')
            ->assertDispatched('toast', type: 'success')
            ->assertFileDownloaded();
    }

    public function test_export_empty_records_dispatches_warning(): void
    {
        $this->actingAs($this->builder);

        $response = Livewire::test(FollowupList::class)
            ->set('search', 'NonExistentCustomerNameXYZ')
            ->call('exportFiltered');

        $response->assertDispatched('toast', type: 'warning', message: 'No follow-up records found matching the active filters to export.');
    }
}
