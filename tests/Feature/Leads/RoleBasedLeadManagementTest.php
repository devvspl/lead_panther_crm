<?php

namespace Tests\Feature\Leads;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Followup;
use App\Models\Project;
use App\Models\ReplacementReason;
use App\Models\LeadReplacement;
use App\Models\LeadCall;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\Booking;
use App\Models\PaymentReceived;
use App\Support\NavigationConfig;
use Livewire\Livewire;
use App\Livewire\Dashboard\DashboardShell;
use App\Livewire\Leads\LeadKanban;
use App\Livewire\Leads\LeadDetail;
use App\Livewire\Activity\FollowupList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class RoleBasedLeadManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $builderUser;
    protected User $exec1;
    protected User $exec2;
    protected Project $project;
    protected LeadSource $leadSource;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'builder', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'sales-executive', 'guard_name' => 'web']);

        $this->org = Organization::create([
            'name' => 'Test Org',
            'type' => 'builder',
            'status' => 'active',
            'lead_cost' => 15.00,
        ]);

        $this->builderUser = User::create([
            'name' => 'Builder User',
            'email' => 'builder_test_' . uniqid() . '@leadpanther.crm',
            'password' => bcrypt('secret'),
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);
        $this->builderUser->assignRole('builder');

        $this->exec1 = User::create([
            'name' => 'Executive One',
            'email' => 'exec1_test_' . uniqid() . '@leadpanther.crm',
            'password' => bcrypt('secret'),
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);
        $this->exec1->assignRole('sales-executive');

        $this->exec2 = User::create([
            'name' => 'Executive Two',
            'email' => 'exec2_test_' . uniqid() . '@leadpanther.crm',
            'password' => bcrypt('secret'),
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);
        $this->exec2->assignRole('sales-executive');

        $this->project = Project::create([
            'name' => 'Green Valley Heights',
            'client_id' => $this->builderUser->id,
            'status' => 'active',
        ]);

        $this->leadSource = LeadSource::create([
            'name' => 'Website Inbound',
            'default_score_weight' => 10,
            'is_active' => true,
        ]);
    }

    public function test_sales_executive_navigation_is_strictly_scoped_to_four_items(): void
    {
        $allowedKeys = NavigationConfig::getAllowedItemKeys($this->exec1);
        $this->assertEquals(['dashboard', 'leads', 'followups', 'reports'], $allowedKeys);

        $navItems = NavigationConfig::getNavItemsForUser($this->exec1);
        $primaryKeys = array_column($navItems['primary'], 'key');

        $this->assertContains('dashboard', $primaryKeys);
        $this->assertContains('leads', $primaryKeys);
        $this->assertContains('followups', $primaryKeys);
        $this->assertContains('reports', $primaryKeys);
        $this->assertNotContains('credits', $primaryKeys);
        $this->assertNotContains('team', $primaryKeys);
        $this->assertNotContains('distribution', $primaryKeys);
        $this->assertNotContains('replacements', $primaryKeys);

        $itemsByKey = collect($navItems['primary'])->keyBy('key');
        $this->assertEquals('My Leads', $itemsByKey['leads']['label']);
        $this->assertEquals('My Follow-ups', $itemsByKey['followups']['label']);
    }

    public function test_sales_executive_dashboard_renders_six_kpis_and_followups(): void
    {
        // Create 2 leads assigned to exec1
        $lead1 = Lead::create([
            'lead_code' => 'LP-TEST-001',
            'name' => 'Lead One',
            'mobile' => '9876543210',
            'client_id' => $this->builderUser->id,
            'assigned_to' => $this->exec1->id,
            'lead_source_id' => $this->leadSource->id,
            'current_stage' => 'new',
            'status' => 'new',
        ]);

        $lead2 = Lead::create([
            'lead_code' => 'LP-TEST-002',
            'name' => 'Lead Two',
            'mobile' => '9876543211',
            'client_id' => $this->builderUser->id,
            'assigned_to' => $this->exec1->id,
            'lead_source_id' => $this->leadSource->id,
            'current_stage' => 'interested',
            'status' => 'in_progress',
        ]);

        // Create 1 lead assigned to exec2 (should NOT show in exec1's stats)
        Lead::create([
            'lead_code' => 'LP-TEST-003',
            'name' => 'Lead Other',
            'mobile' => '9876543212',
            'client_id' => $this->builderUser->id,
            'assigned_to' => $this->exec2->id,
            'lead_source_id' => $this->leadSource->id,
            'current_stage' => 'new',
            'status' => 'new',
        ]);

        Followup::create([
            'lead_id' => $lead1->id,
            'created_by' => $this->exec1->id,
            'due_at' => now()->addHours(2),
            'note' => 'Call lead one',
            'status' => 'pending',
        ]);

        $this->actingAs($this->exec1);

        Livewire::test(DashboardShell::class, ['role' => 'sales'])
            ->assertSee('Sales Executive Dashboard')
            ->assertSee('My Total Leads')
            ->assertSee('New Leads')
            ->assertSee('In Progress')
            ->assertSee('Follow-ups Due')
            ->assertSee('Closed Won')
            ->assertSee('Lost Leads')
            ->assertSee('Lead One')
            ->assertDontSee('Lead Other');
    }

    public function test_sales_executive_my_leads_is_scoped_strictly_to_assigned_leads(): void
    {
        // Lead for exec1
        $lead1 = Lead::create([
            'lead_code' => 'LP-EXEC1-001',
            'name' => 'John Doe Exec1',
            'mobile' => '9876540001',
            'client_id' => $this->builderUser->id,
            'assigned_to' => $this->exec1->id,
            'lead_source_id' => $this->leadSource->id,
            'current_stage' => 'contacted',
            'status' => 'contacted',
        ]);

        // Lead for exec2
        $lead2 = Lead::create([
            'lead_code' => 'LP-EXEC2-001',
            'name' => 'Jane Smith Exec2',
            'mobile' => '9876540002',
            'client_id' => $this->builderUser->id,
            'assigned_to' => $this->exec2->id,
            'lead_source_id' => $this->leadSource->id,
            'current_stage' => 'contacted',
            'status' => 'contacted',
        ]);

        $this->actingAs($this->exec1);

        $component = Livewire::test(LeadKanban::class);
        $component->assertSee('LP-EXEC1-001')
            ->assertSee('John Doe Exec1')
            ->assertDontSee('LP-EXEC2-001')
            ->assertDontSee('Jane Smith Exec2');

        // Check columns returned for executive
        $columns = $component->instance()->tableColumns();
        $colKeys = array_column($columns, 'key');

        $this->assertEquals([
            'lead_code',
            'name',
            'mobile',
            'project_name',
            'current_stage',
            'status',
            'next_followup',
            'actions',
        ], $colKeys);
    }

    public function test_sales_executive_cannot_assign_or_auto_distribute_leads(): void
    {
        $lead = Lead::create([
            'lead_code' => 'LP-EXEC1-003',
            'name' => 'Target Lead',
            'mobile' => '9876540003',
            'client_id' => $this->builderUser->id,
            'assigned_to' => $this->exec1->id,
            'lead_source_id' => $this->leadSource->id,
            'current_stage' => 'new',
            'status' => 'new',
        ]);

        $this->actingAs($this->exec1);

        Livewire::test(LeadKanban::class)
            ->call('openAssignModalForSingleLead', $lead->id)
            ->assertDispatched('toast', type: 'error')
            ->assertSet('showAssignModal', false);

        Livewire::test(LeadKanban::class)
            ->call('autoDistributeUnassignedLeads')
            ->assertDispatched('toast', type: 'error');
    }

    public function test_builder_can_assign_leads_to_own_team_members(): void
    {
        $lead = Lead::create([
            'lead_code' => 'LP-BLD-001',
            'name' => 'Builder Lead Unassigned',
            'mobile' => '9876540004',
            'client_id' => $this->builderUser->id,
            'assigned_to' => null,
            'lead_source_id' => $this->leadSource->id,
            'current_stage' => 'new',
            'status' => 'unassigned',
        ]);

        $this->actingAs($this->builderUser);

        Livewire::test(LeadKanban::class)
            ->call('openAssignModalForSingleLead', $lead->id)
            ->assertSet('showAssignModal', true)
            ->set('assignUserId', $this->exec1->id)
            ->call('executeAssignment')
            ->assertDispatched('toast', type: 'success')
            ->assertSet('showAssignModal', false);

        $this->assertEquals($this->exec1->id, $lead->fresh()->assigned_to);
    }

    public function test_stage_wise_actions_and_lifecycle_transitions_in_lead_detail(): void
    {
        $lead = Lead::create([
            'lead_code' => 'LP-FLOW-001',
            'name' => 'Flow Customer',
            'mobile' => '9876540005',
            'client_id' => $this->builderUser->id,
            'assigned_to' => $this->exec1->id,
            'lead_source_id' => $this->leadSource->id,
            'current_stage' => 'new',
            'status' => 'new',
            'budget' => 5000000,
        ]);

        $this->actingAs($this->exec1);

        $test = Livewire::test(LeadDetail::class);

        // 1. Open Lead
        $test->call('openLead', $lead->id)
            ->assertSet('isOpen', true);

        // 2. Log Call -> Transitions to 'contacted' & sets first_response_at
        $test->set('callOutcome', 'Interested')
            ->set('callDuration', 10)
            ->call('logCallAction')
            ->assertDispatched('toast', type: 'success');

        $lead->refresh();
        $this->assertEquals('contacted', $lead->current_stage);
        $this->assertNotNull($lead->first_response_at);

        // 3. Mark Interested
        $test->call('markInterestedAction')
            ->assertDispatched('toast', type: 'success');

        $lead->refresh();
        $this->assertEquals('interested', $lead->current_stage);

        // 4. Schedule Follow-up -> Transitions to 'follow_up'
        $test->set('followupDueAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('followupNote', 'Discuss unit floor plans')
            ->call('scheduleFollowupAction')
            ->assertDispatched('toast', type: 'success');

        $lead->refresh();
        $this->assertEquals('follow_up', $lead->current_stage);
        $this->assertCount(1, $lead->followups);

        // 5. Complete Follow-up
        $fu = $lead->followups()->first();
        $test->call('completeFollowupAction', $fu->id)
            ->assertDispatched('toast', type: 'success');
        $this->assertEquals('completed', $fu->fresh()->status);

        // 6. Schedule Meeting
        $test->set('meetingScheduledAt', now()->addDays(2)->format('Y-m-d\TH:i'))
            ->set('meetingLocation', 'Site Office Experience Center')
            ->call('scheduleMeetingAction')
            ->assertDispatched('toast', type: 'success');

        $lead->refresh();
        $this->assertEquals('meeting_scheduled', $lead->current_stage);

        // 7. Site Visit
        $test->set('visitDate', now()->addDays(3)->format('Y-m-d\TH:i'))
            ->set('visitAttendance', 'attended')
            ->set('visitOutcome', 'Customer loved 3BHK corner unit')
            ->call('saveSiteVisitAction')
            ->assertDispatched('toast', type: 'success');

        $lead->refresh();
        $this->assertEquals('site_visit', $lead->current_stage);

        // 8. Negotiation
        $test->set('negotiationNotes', 'Agreed on 2% discount with booking this week')
            ->set('quotedAmount', '5200000')
            ->call('saveNegotiationAction')
            ->assertDispatched('toast', type: 'success');

        $lead->refresh();
        $this->assertEquals('negotiation', $lead->current_stage);

        // 9. Booking
        $test->set('bookingAmount', '100000')
            ->set('bookingUnit', 'Tower A - 501')
            ->call('saveBookingAction')
            ->assertDispatched('toast', type: 'success');

        $lead->refresh();
        $this->assertEquals('booking', $lead->current_stage);

        // 10. Payment Received & Closed Won
        $test->set('paymentAmount', '100000')
            ->set('paymentMethod', 'Bank Transfer')
            ->call('recordPaymentAction')
            ->assertDispatched('toast', type: 'success');

        $lead->refresh();
        $this->assertEquals('payment_received', $lead->current_stage);

        $test->call('markClosedWonAction')
            ->assertDispatched('toast', type: 'success');

        $lead->refresh();
        $this->assertEquals('closed_won', $lead->current_stage);
    }

    public function test_sales_executive_cannot_open_lead_of_another_agent(): void
    {
        $otherLead = Lead::create([
            'lead_code' => 'LP-OTHER-999',
            'name' => 'Secret Customer',
            'mobile' => '9876540099',
            'client_id' => $this->builderUser->id,
            'assigned_to' => $this->exec2->id,
            'lead_source_id' => $this->leadSource->id,
            'current_stage' => 'new',
            'status' => 'new',
        ]);

        $this->actingAs($this->exec1);

        Livewire::test(LeadDetail::class)
            ->call('openLead', $otherLead->id)
            ->assertDispatched('toast', type: 'error')
            ->assertSet('isOpen', false);
    }

    public function test_builder_can_request_lead_replacement(): void
    {
        $reason = ReplacementReason::create([
            'label' => 'Wrong / Invalid Phone Number',
            'is_eligible' => true,
            'requires_sla_check' => true,
        ]);

        $lead = Lead::create([
            'lead_code' => 'LP-REPL-001',
            'name' => 'Invalid Phone Lead',
            'mobile' => '0000000000',
            'client_id' => $this->builderUser->id,
            'assigned_to' => $this->exec1->id,
            'lead_source_id' => $this->leadSource->id,
            'current_stage' => 'contacted',
            'status' => 'contacted',
        ]);

        $this->actingAs($this->builderUser);

        Livewire::test(LeadDetail::class)
            ->call('openLead', $lead->id)
            ->set('replacementReasonId', $reason->id)
            ->set('replacementRemarks', 'Customer confirmed never inquired, number was wrong.')
            ->call('requestReplacement')
            ->assertDispatched('toast', type: 'success');

        $lead->refresh();
        $this->assertEquals('pending', $lead->replacement_status);

        $replacement = LeadReplacement::where('lead_id', $lead->id)->first();
        $this->assertNotNull($replacement);
        $this->assertEquals('pending', $replacement->status);
        $this->assertEquals($reason->id, $replacement->reason_id);
    }
}
