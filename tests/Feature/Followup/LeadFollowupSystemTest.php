<?php

namespace Tests\Feature\Followup;

use App\Console\Commands\CheckDueFollowupsCommand;
use App\Http\Controllers\NotificationController;
use App\Livewire\Activity\FollowupList;
use App\Livewire\Leads\LeadDetail;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\CreditWallet;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Organization;
use App\Models\Project;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Models\User;
use App\Services\FollowupEscalationService;
use App\Services\NotificationDeliveryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadFollowupSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $builder;
    protected User $teamLeader;
    protected User $executive;
    protected Organization $org;
    protected Project $project;
    protected Campaign $campaign;
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

        // Permissions
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view-team-followups']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view-team-leads']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'assign-team-leads']);

        // Admin
        $this->admin = User::factory()->create(['status' => 'active', 'email' => 'admin@example.com']);
        $this->admin->assignRole('Super Admin');

        // Organization
        $this->org = Organization::create([
            'name' => 'H&S By Investors Clinic',
            'status' => 'active',
            'lead_assignment_mode' => 'automatic',
        ]);

        // Builder
        $client = Client::create([
            'organization_id' => $this->org->id,
            'name' => 'H&S Builder',
            'email' => 'builder@example.com',
            'status' => 'active',
        ]);
        $this->builder = User::find($client->id);
        $this->builder->assignRole('Builder/Developer');

        // Team Leader
        $this->teamLeader = User::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'H&S Team Leader',
            'email' => 'tl@example.com',
            'status' => 'active',
        ]);
        $this->teamLeader->assignRole('Team Leader');
        $this->teamLeader->givePermissionTo('view-team-followups');

        // Executive
        $this->executive = User::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Aman',
            'email' => 'aman@example.com',
            'status' => 'active',
        ]);
        $this->executive->assignRole('Executive');

        // Sales Team
        $this->team = SalesTeam::create([
            'ownable_type' => Organization::class,
            'ownable_id' => $this->org->id,
            'leader_id' => $this->teamLeader->id,
            'name' => "H&S Team Leader's Team",
        ]);

        SalesTeamMember::create([
            'sales_team_id' => $this->team->id,
            'user_id' => $this->teamLeader->id,
            'role' => 'Team Leader',
            'is_active' => true,
        ]);

        SalesTeamMember::create([
            'sales_team_id' => $this->team->id,
            'user_id' => $this->executive->id,
            'role' => 'Executive',
            'is_active' => true,
        ]);

        // Project & Campaign
        $this->project = Project::create([
            'client_id' => $this->builder->id,
            'name' => 'Boulevard walk by H&S',
            'status' => 'active',
        ]);

        $this->source = LeadSource::firstOrCreate(['name' => 'Meta Ads']);

        $this->campaign = Campaign::create([
            'project_id' => $this->project->id,
            'lead_source_id' => $this->source->id,
            'name' => 'BW_01',
            'status' => 'active',
        ]);
    }

    protected function createLead(array $attributes = []): Lead
    {
        $lead = Lead::create(array_merge([
            'client_id' => $this->builder->id,
            'campaign_id' => $this->campaign->id,
            'lead_source_id' => $this->source->id,
            'name' => 'Test Prospect',
            'mobile' => '9876543210',
            'email' => 'prospect@example.com',
            'lead_code' => Lead::generateUniqueLeadCode('TEST'),
            'current_stage' => 'interested',
            'status' => 'in_progress',
            'assigned_to' => $this->executive->id,
            'delivery_pending' => false,
            'internal_processing_pending' => false,
            'delivered_at' => now(),
            'executive_assigned_at' => now(),
            'executive_response_due_at' => now()->addMinutes(30),
        ], $attributes));

        $lead->updateQuietly([
            'delivery_pending' => false,
            'internal_processing_pending' => false,
        ]);

        return $lead;
    }

    public function test_executive_can_schedule_a_followup_and_transition_stage(): void
    {
        $lead = $this->createLead(['current_stage' => 'interested']);
        $this->actingAs($this->executive);

        $dueAt = now()->addDays(2)->format('Y-m-d\TH:i');

        Livewire::test(LeadDetail::class)
            ->call('openLead', $lead->id)
            ->set('followupType', 'call')
            ->set('followupDueAt', $dueAt)
            ->set('followupNote', 'Discuss 3BHK high-floor availability')
            ->call('scheduleFollowupAction')
            ->assertDispatched('toast', type: 'success');

        $this->assertDatabaseHas('followups', [
            'lead_id' => $lead->id,
            'created_by' => $this->executive->id,
            'type' => 'call',
            'note' => 'Discuss 3BHK high-floor availability',
            'status' => 'pending',
        ]);

        $lead->refresh();
        $this->assertEquals('follow_up', $lead->current_stage);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->executive->id,
            'action' => 'lead.followup_scheduled',
            'subject_id' => $lead->id,
        ]);
    }

    public function test_executive_can_complete_followup_record_outcome_and_schedule_next_step(): void
    {
        $lead = $this->createLead(['current_stage' => 'follow_up']);
        $followup = Followup::create([
            'lead_id' => $lead->id,
            'created_by' => $this->executive->id,
            'due_at' => now()->addDay(),
            'type' => 'call',
            'note' => 'Follow up on quotation',
            'status' => 'pending',
        ]);

        $this->actingAs($this->executive);
        $nextDue = now()->addDays(3)->format('Y-m-d\TH:i');

        Livewire::test(LeadDetail::class)
            ->call('openLead', $lead->id)
            ->set('followupOutcome', 'callback_requested')
            ->set('followupOutcomeNotes', 'Customer traveling, requested call Friday morning')
            ->set('scheduleNextFollowup', true)
            ->set('nextFollowupDueAt', $nextDue)
            ->set('nextFollowupType', 'whatsapp')
            ->set('nextFollowupNote', 'Share updated payment schedule')
            ->call('completeFollowupAction', $followup->id)
            ->assertDispatched('toast', type: 'success');

        $followup->refresh();
        $this->assertEquals('completed', $followup->status);
        $this->assertEquals('callback_requested', $followup->outcome);
        $this->assertEquals('Customer traveling, requested call Friday morning', $followup->outcome_notes);
        $this->assertEquals($this->executive->id, $followup->completed_by);
        $this->assertNotNull($followup->completed_at);

        // Next pending follow-up must be created
        $this->assertDatabaseHas('followups', [
            'lead_id' => $lead->id,
            'type' => 'whatsapp',
            'note' => 'Share updated payment schedule',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->executive->id,
            'action' => 'lead.followup_completed',
            'subject_id' => $lead->id,
        ]);
    }

    public function test_executive_can_reschedule_and_cancel_followup(): void
    {
        $lead = $this->createLead(['current_stage' => 'follow_up']);
        $followup = Followup::create([
            'lead_id' => $lead->id,
            'created_by' => $this->executive->id,
            'due_at' => now()->addHour(),
            'type' => 'call',
            'note' => 'Original call time',
            'status' => 'pending',
            'escalation_level' => 1,
        ]);

        $this->actingAs($this->executive);
        $newDue = now()->addDays(2)->format('Y-m-d\TH:i');

        // Reschedule
        Livewire::test(LeadDetail::class)
            ->call('openLead', $lead->id)
            ->set('followupDueAt', $newDue)
            ->set('followupType', 'meeting')
            ->set('followupNote', 'Customer agreed to meet in office')
            ->call('rescheduleFollowupAction', $followup->id)
            ->assertDispatched('toast', type: 'success');

        $followup->refresh();
        $this->assertEquals('meeting', $followup->type);
        $this->assertEquals('Customer agreed to meet in office', $followup->note);
        $this->assertEquals(0, $followup->escalation_level); // Reset upon reschedule

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->executive->id,
            'action' => 'lead.followup_rescheduled',
            'subject_id' => $lead->id,
        ]);

        // Cancel
        Livewire::test(LeadDetail::class)
            ->call('openLead', $lead->id)
            ->set('cancellationReason', 'Direct site visit organized instead')
            ->call('cancelFollowupAction', $followup->id)
            ->assertDispatched('toast', type: 'success');

        $followup->refresh();
        $this->assertEquals('cancelled', $followup->status);
        $this->assertEquals('Direct site visit organized instead', $followup->cancellation_reason);
        $this->assertEquals($this->executive->id, $followup->cancelled_by);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->executive->id,
            'action' => 'lead.followup_cancelled',
            'subject_id' => $lead->id,
        ]);
    }

    public function test_reminders_and_escalations_run_without_duplicates(): void
    {
        $lead = $this->createLead();

        // 1. Upcoming in 20 minutes
        $upcomingFu = Followup::create([
            'lead_id' => $lead->id,
            'created_by' => $this->executive->id,
            'due_at' => now()->addMinutes(20),
            'status' => 'pending',
            'type' => 'call',
            'note' => 'Upcoming reminder test',
        ]);

        // 2. Due now
        $dueFu = Followup::create([
            'lead_id' => $lead->id,
            'created_by' => $this->executive->id,
            'due_at' => now()->subMinutes(2),
            'status' => 'pending',
            'type' => 'call',
            'note' => 'Due now test',
        ]);

        // 3. Overdue by 3 hours (should escalate to Team Leader)
        $overdueTlFu = Followup::create([
            'lead_id' => $lead->id,
            'created_by' => $this->executive->id,
            'due_at' => now()->subHours(3),
            'status' => 'pending',
            'type' => 'call',
            'note' => 'Overdue TL escalation test',
            'escalation_level' => 0,
        ]);

        // 4. Overdue by 26 hours (should escalate to Builder)
        $overdueAdminFu = Followup::create([
            'lead_id' => $lead->id,
            'created_by' => $this->executive->id,
            'due_at' => now()->subHours(26),
            'status' => 'pending',
            'type' => 'call',
            'note' => 'Overdue Builder escalation test',
            'escalation_level' => 1,
        ]);

        // Run follow-up reminder & escalation command
        $this->artisan('followups:check-due')->assertSuccessful();

        // Verify Team Leader escalation
        $overdueTlFu->refresh();
        $this->assertEquals(1, $overdueTlFu->escalation_level);
        $this->assertNotNull($overdueTlFu->escalated_at);

        // Verify Builder escalation
        $overdueAdminFu->refresh();
        $this->assertEquals(2, $overdueAdminFu->escalation_level);

        // Verify Lead ownership remained preserved (never auto-reassigned)
        $lead->refresh();
        $this->assertEquals($this->executive->id, $lead->assigned_to);

        // Verify notifications created
        $this->assertTrue(DatabaseNotification::where('data->type', 'followup_due_soon')->exists());
        $this->assertTrue(DatabaseNotification::where('data->type', 'followup_due')->exists());
        $this->assertTrue(DatabaseNotification::where('data->type', 'followup_escalated_tl')->where('notifiable_id', $this->teamLeader->id)->exists());
        $this->assertTrue(DatabaseNotification::where('data->type', 'followup_escalated_admin')->where('notifiable_id', $this->builder->id)->exists());

        $initialNotificationCount = DatabaseNotification::count();

        // Re-run command: must generate 0 duplicate notifications
        $this->artisan('followups:check-due')->assertSuccessful();
        $this->assertEquals($initialNotificationCount, DatabaseNotification::count(), 'Deduplication failed: duplicate notifications were created.');
    }

    public function test_notification_click_marks_as_read_and_opens_lead(): void
    {
        $lead = $this->createLead();
        $this->actingAs($this->executive);

        $service = app(NotificationDeliveryService::class);
        $notification = $service->sendWeb(
            $this->executive,
            'followup_due',
            'Follow-up Due: ' . $lead->name,
            'Follow-up is due',
            'test_notification_key_1',
            'followup',
            $this->org->id,
            'lead',
            $lead->id
        );

        $this->assertNotNull($notification);
        $this->assertNull($notification->read_at);

        $response = $this->get('/notifications/' . $notification->id . '/open');
        $response->assertRedirect();
        $this->assertStringContainsString('notification_lead=' . $lead->id, $response->headers->get('Location'));

        $notification->refresh();
        $this->assertNotNull($notification->read_at, 'Notification was not marked as read upon clicking open.');
    }

    public function test_followup_dashboard_scoping_and_metrics(): void
    {
        $lead1 = $this->createLead(['assigned_to' => $this->executive->id]);

        $fuToday = Followup::create([
            'lead_id' => $lead1->id,
            'created_by' => $this->executive->id,
            'due_at' => now()->hour(14)->minute(0),
            'status' => 'pending',
            'type' => 'call',
            'note' => 'Today follow-up',
        ]);

        $fuOverdue = Followup::create([
            'lead_id' => $lead1->id,
            'created_by' => $this->executive->id,
            'due_at' => now()->subDay(),
            'status' => 'pending',
            'type' => 'whatsapp',
            'note' => 'Overdue follow-up',
        ]);

        // Test Executive sees own follow-ups
        $this->actingAs($this->executive);
        Livewire::test(FollowupList::class)
            ->assertSee('Today follow-up')
            ->assertSee('Overdue follow-up')
            ->call('setTab', 'today')
            ->assertSee('Today follow-up');

        // Test Team Leader sees team follow-ups
        $this->actingAs($this->teamLeader);
        Livewire::test(FollowupList::class)
            ->assertSee('Today follow-up')
            ->assertSee('Overdue follow-up');

        // Test Builder sees organization follow-ups
        $this->actingAs($this->builder);
        Livewire::test(FollowupList::class)
            ->assertSee('Today follow-up')
            ->assertSee('Overdue follow-up');
    }
}
