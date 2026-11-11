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
use App\Livewire\Reports\SalesExecutivePerformance;
use App\Livewire\Reports\SourcePerformance;
use App\Models\LeadAssignment;
use App\Models\ProjectUnit;
use App\Notifications\LeadAssignedNotification;
use Illuminate\Support\Facades\Notification;

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
            'project_id' => $this->project->id,
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

        ProjectUnit::create(['project_id' => $this->project->id, 'unit_number' => 'Tower A - 501', 'status' => 'available']);
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
            ->set('paymentTransactionId', 'TXN-FLOW-001')
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

    protected function panelLead(string $stage = 'new', ?User $assignee = null): Lead
    {
        return Lead::create(['lead_code' => 'LP-PANEL-'.uniqid(), 'name' => 'Panel Customer', 'mobile' => '9876543210', 'client_id' => $this->builderUser->id, 'assigned_to' => ($assignee ?? $this->exec1)->id, 'lead_source_id' => $this->leadSource->id, 'current_stage' => $stage, 'status' => $stage]);
}

    public function test_builder_cannot_execute_sales_actions(): void
    {
        $lead = $this->panelLead();
        $this->actingAs($this->builderUser);
        Livewire::test(LeadDetail::class)->set('leadId', $lead->id)->call('markInterestedAction')->assertForbidden();
        $this->assertSame('new', $lead->fresh()->current_stage);
    }

    public function test_closed_lead_rejects_earlier_stage_actions(): void
    {
        $lead = $this->panelLead('closed_won');
        $this->actingAs($this->exec1);
        Livewire::test(LeadDetail::class)->call('openLead', $lead->id)->call('markInterestedAction')->assertHasErrors('action');
        $this->assertSame('closed_won', $lead->fresh()->current_stage);
    }

    public function test_detail_render_does_not_expose_another_executives_lead(): void
    {
        $lead = $this->panelLead('new', $this->exec2);
        $this->actingAs($this->exec1);
        Livewire::test(LeadDetail::class)->set('leadId', $lead->id)->set('isOpen', true)->assertDontSee('Panel Customer');
    }

    public function test_kanban_cannot_skip_required_stage_actions(): void
    {
        $lead = $this->panelLead();
        $this->actingAs($this->exec1);
        Livewire::test(LeadKanban::class)->call('updateLeadStage', $lead->id, 'closed_won')->assertHasErrors('action');
        $this->assertSame('new', $lead->fresh()->current_stage);
    }

    public function test_builder_cannot_assign_to_another_organization(): void
    {
        $lead = $this->panelLead();
        $org = Organization::create(['name' => 'Other org']);
        $other = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $other->assignRole('sales-executive');
        $this->actingAs($this->builderUser);
        Livewire::test(LeadKanban::class)->set('selectedLeadIds', [$lead->id])->set('assignUserId', $other->id)->call('executeAssignment')->assertForbidden();
        $this->assertSame($this->exec1->id, $lead->fresh()->assigned_to);
    }

    public function test_reassignment_closes_previous_assignment_and_notifies_once(): void
    {
        Notification::fake();
        $lead = $this->panelLead('assigned');
        $old = LeadAssignment::create(['lead_id' => $lead->id, 'assigned_to' => $this->exec1->id, 'assigned_by' => $this->builderUser->id, 'assigned_at' => now()]);
        $this->actingAs($this->builderUser);
        Livewire::test(LeadKanban::class)->set('selectedLeadIds', [$lead->id])->set('assignUserId', $this->exec2->id)->call('executeAssignment')->assertHasNoErrors();
        $this->assertNotNull($old->fresh()->unassigned_at);
        $this->assertSame($this->exec2->id, $lead->fresh()->assigned_to);
        $this->assertSame(1, LeadAssignment::where('lead_id', $lead->id)->whereNull('unassigned_at')->count());
        Notification::assertSentToTimes($this->exec2, LeadAssignedNotification::class, 1);
    }

    public function test_executive_cannot_change_another_users_followup(): void
    {
        $lead = $this->panelLead('follow_up', $this->exec2);
        $followup = Followup::create(['lead_id' => $lead->id, 'created_by' => $this->exec2->id, 'due_at' => now(), 'status' => 'pending']);
        $this->actingAs($this->exec1);
        Livewire::test(FollowupList::class)->set('completingFollowupId', $followup->id)->call('completeWithNextStep')->assertForbidden();
        $this->assertSame('pending', $followup->fresh()->status);
    }

    public function test_replacement_requires_remarks_and_stays_pending(): void
    {
        $lead = $this->panelLead('invalid');
        $reason = ReplacementReason::create(['label' => 'Invalid contact', 'is_eligible' => true, 'requires_sla_check' => false]);
        $this->actingAs($this->builderUser);
        $component = Livewire::test(LeadDetail::class)->call('openLead', $lead->id)->set('replacementReasonId', $reason->id);
        $component->call('requestReplacement')->assertHasErrors('replacementRemarks');
        $component->set('replacementRemarks', 'Wrong number confirmed by customer')->call('requestReplacement')->assertHasNoErrors();
        $this->assertSame('pending', $lead->replacements()->first()->status);
        $component->call('requestReplacement')->assertHasErrors('eligible');
        $this->assertSame(1, $lead->replacements()->count());
    }

    public function test_executive_management_routes_are_forbidden(): void
    {
        $this->actingAs($this->exec1);
        foreach (['/settings/organization', '/settings/users', '/distribution/availability', '/credits', '/leads/upload'] as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_reports_do_not_substitute_sample_rows_for_an_empty_panel(): void
    {
        $this->actingAs($this->exec1);
        Livewire::test(SourcePerformance::class)->assertDontSee('Meta Ads');
        Livewire::test(SalesExecutivePerformance::class)->assertDontSee('Rahul Sharma');
    }

    public function test_existing_return_modal_submits_to_the_existing_pending_review_flow(): void
    {
        $lead = $this->panelLead('invalid');
        $reason = ReplacementReason::create(['label' => 'Invalid contact', 'is_eligible' => true, 'requires_sla_check' => false]);
        $this->actingAs($this->builderUser);
        Livewire::test(LeadKanban::class)->set('returnLeadId', $lead->id)->set('returnReasonId', $reason->id)->set('returnRemarks', 'Verified incorrect contact number')->call('submitReturnLead')->assertHasNoErrors();
        $this->assertSame('pending', $lead->replacements()->first()->status);
        $this->assertSame('pending', $lead->fresh()->replacement_status);
    }

    public function test_builder_detail_has_no_sales_mutation_buttons(): void
    {
        $lead = $this->panelLead();
        $this->actingAs($this->builderUser);
        Livewire::test(LeadDetail::class)->call('openLead', $lead->id)->assertSee('Reassign Lead')->assertDontSee('wire:click="logCallAction"', false)->assertDontSee('wire:click="markInterestedAction"', false);
    }

    public function test_executive_can_upload_payment_proof_only_at_payment_stage(): void
    {
        $lead = $this->panelLead('new');
        $this->actingAs($this->exec1);
        Livewire::test(LeadDetail::class)->set('leadId', $lead->id)->call('uploadPaymentProofAction')->assertHasErrors('action');
        $this->assertDatabaseMissing('lead_metadata',['lead_id' => $lead->id, 'key' => 'payment_proof']);
    }

    public function test_payment_proof_is_stored_privately_and_logged(): void
    {
        \Illuminate\Support\Facades\Storage::fake('documents');
        $lead=$this->panelLead('payment_received');
        $unit=\App\Models\ProjectUnit::create(['project_id'=>$this->project->id,'unit_number'=>'PROOF-101','status'=>'reserved']);
        $booking=Booking::create(['lead_id'=>$lead->id,'project_unit_id'=>$unit->id,'booking_amount'=>100,'booked_at'=>now(),'status'=>'confirmed']);
        PaymentReceived::create(['booking_id'=>$booking->id,'amount'=>100,'method'=>'UPI','transaction_id'=>'PROOF-TEST','paid_at'=>now(),'status'=>'completed']);
        $this->actingAs($this->exec1);
        Livewire::test(LeadDetail::class)->set('leadId',$lead->id)->set('paymentProof',\Illuminate\Http\UploadedFile::fake()->create('receipt.pdf',20,'application/pdf'))->call('uploadPaymentProofAction')->assertHasNoErrors();
        $proof=$lead->metadata()->where('key','payment_proof')->firstOrFail();
        \Illuminate\Support\Facades\Storage::disk('documents')->assertExists($proof->value);
        $this->assertDatabaseHas('audit_logs',['subject_id'=>$lead->id,'action'=>'lead.payment_proof_uploaded']);
        $this->assertSame('payment_received',$lead->fresh()->current_stage);
    }

    public function test_missing_followup_route_does_not_break_role_navigation(): void
    {
        $routes = new \Illuminate\Routing\RouteCollection;
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            if ($route->getName() !== 'followups.index') {
                $routes->add($route);
            }
        }
        app('router')->setRoutes($routes);
        app('url')->setRoutes($routes);

        foreach ([$this->builderUser, $this->exec1] as $user) {
            $items = NavigationConfig::getNavItemsForUser($user);
            $keys = array_column($items['primary'], 'key');
            $this->assertContains('dashboard', $keys);
            $this->assertContains('leads', $keys);
            $this->assertNotContains('followups', $keys);
        }
    }

    public function test_followup_route_is_registered_and_used_by_executive_navigation(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('followups.index');
        $this->assertNotNull($route);
        $this->assertSame('followups', $route->uri());
        $this->assertContains('auth', $route->middleware());
        $items = collect(NavigationConfig::getNavItemsForUser($this->exec1)['primary'])->keyBy('key');
        $this->assertSame(route('followups.index'), $items['followups']['url']);
        $this->get('/followups')->assertRedirect(route('login'));
    }
}
