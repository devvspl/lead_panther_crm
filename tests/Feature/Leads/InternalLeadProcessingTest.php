<?php
namespace Tests\Feature\Leads;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Notification, DB};
use App\Models\{Organization, User, SalesTeam, SalesTeamMember, Project, Campaign, LeadSource, Lead, CreditWallet, CreditTransaction, InternalLeadProcessing, InternalProcessingStatus, AuditLog};
use App\Services\LeadDistributionService;
use App\Livewire\Leads\{LeadKanban, LeadDetail, InternalProcessingQueue};
use App\Livewire\Admin\InternalProcessingSettings;
use Spatie\Permission\Models\Role;
use Livewire\Livewire;

class InternalLeadProcessingTest extends TestCase
{
    use RefreshDatabase;
    private User $admin; private User $internal; private User $internal2; private User $builder; private User $clientAgent; private User $otherBuilder;
    private Organization $org; private Organization $directOrg; private SalesTeam $team; private Campaign $campaign; private Campaign $directCampaign;

    protected function setUp(): void
    {
        parent::setUp(); Notification::fake();
        foreach (['Super Admin','builder','sales-executive'] as $name) Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        $hq = Organization::create(['name' => 'Lead Panther platform', 'status' => 'active']);
        $this->admin = $this->user('Platform administrator', $hq, 'Super Admin');
        $this->internal = $this->user('Internal caller', $hq, 'sales-executive');
        $this->internal2 = $this->user('Other internal caller', $hq, 'sales-executive');
        $this->team = SalesTeam::create(['ownable_type' => Organization::class, 'ownable_id' => $hq->id, 'name' => 'Verification team']);
        foreach ([$this->internal, $this->internal2] as $member) SalesTeamMember::create(['sales_team_id' => $this->team->id, 'user_id' => $member->id, 'is_active' => true]);
        $this->org = Organization::create(['name' => 'Processed client', 'status' => 'active', 'auto_assign_enabled' => true, 'lead_cost' => 20]);
        $this->directOrg = Organization::create(['name' => 'Direct client', 'status' => 'active', 'auto_assign_enabled' => false, 'lead_cost' => 20]);
        $this->builder = $this->user('Processed builder', $this->org, 'builder');
        $this->clientAgent = $this->user('Client executive', $this->org, 'sales-executive');
        $this->otherBuilder = $this->user('Direct builder', $this->directOrg, 'builder');
        $source = LeadSource::create(['name' => 'Internal workflow test']);
        foreach ([[$this->builder, 'campaign'], [$this->otherBuilder, 'directCampaign']] as [$owner, $property]) {
            $project = Project::create(['client_id' => $owner->id, 'name' => $owner->name.' project', 'status' => 'active', 'delivery_daily_limit' => 0, 'delivery_interval_minutes' => 0]);
            $this->{$property} = Campaign::create(['name' => $owner->name.' campaign', 'project_id' => $project->id, 'lead_source_id' => $source->id, 'status' => 'active']);
            CreditWallet::create(['client_id' => $owner->id, 'balance' => 500, 'opening_balance' => 500]);
        }
        $this->configure();
    }
    private function user(string $name, Organization $org, string $role): User
    {
        $user = User::factory()->create(['name' => $name, 'organization_id' => $org->id, 'status' => 'active']); $user->assignRole($role); return $user;
    }
    private function configure(array $changes = []): void
    {
        app(LeadDistributionService::class)->configureInternalProcessing($this->org, array_replace([
            'enabled' => true, 'team_id' => $this->team->id, 'member_ids' => [$this->internal->id, $this->internal2->id],
            'status_codes' => InternalProcessingStatus::pluck('code')->all(), 'forward_statuses' => ['verified'], 'assignment_method' => 'round_robin', 'auto_forward' => false,
        ], $changes), $this->admin);
    }
    private function receive(bool $direct = false): Lead
    {
        $owner = $direct ? $this->otherBuilder : $this->builder; $campaign = $direct ? $this->directCampaign : $this->campaign;
        $lead = Lead::create(['lead_code' => Lead::generateUniqueLeadCode(), 'client_id' => $owner->id, 'campaign_id' => $campaign->id, 'lead_source_id' => $campaign->lead_source_id,
            'name' => 'Customer verification', 'mobile' => '9000000000', 'status' => 'new', 'current_stage' => 'new']);
        event(new \App\Events\LeadCreated($lead)); return $lead->fresh();
    }
    private function verify(InternalLeadProcessing $record, ?User $actor = null): void
    {
        $actor ??= User::findOrFail($record->assigned_user_id);
        app(LeadDistributionService::class)->updateInternalLead($record->id, $actor, ['action' => 'call', 'calling_status' => 'connected', 'remarks' => 'Customer confirmed the requirements and contact details.']);
        app(LeadDistributionService::class)->updateInternalLead($record->id, $actor, ['action' => 'verify']);
    }

    public function test_enabled_routing_is_internal_and_disabled_organization_preserves_direct_delivery(): void
    {
        $held = $this->receive(); $direct = $this->receive(true);
        $record = $held->internalProcessing;
        $this->assertTrue($held->internal_processing_pending); $this->assertTrue($held->delivery_pending); $this->assertNull($held->delivery_at);
        $this->assertEquals($this->internal->id, $record->assigned_user_id);
        $this->assertSame('calling_pending', $record->status_code);
        $this->assertFalse($direct->internal_processing_pending); $this->assertNotNull($direct->delivered_at); $this->assertNull($direct->internalProcessing);
        $this->assertSame(0, CreditTransaction::withoutGlobalScopes()->where('lead_id', $held->id)->count());
        $this->assertEquals(500, CreditWallet::withoutGlobalScopes()->where('client_id', $this->builder->id)->value('balance'));
        $this->actingAs($this->builder); $this->assertFalse(Lead::panelQuery()->whereKey($held->id)->exists());
        $this->assertFalse(app(LeadDistributionService::class)->releaseDelivery($held->id));
        $second = $this->receive(); $this->assertEquals($this->internal2->id, $second->internalProcessing->assigned_user_id);
    }

    public function test_internal_calls_followups_verification_and_handover_preserve_separate_client_progress(): void
    {
        $lead = $this->receive(); $record = $lead->internalProcessing;
        $this->actingAs($this->internal);
        $service = app(LeadDistributionService::class);
        $service->updateInternalLead($record->id, $this->internal, ['action' => 'call', 'calling_status' => 'connected', 'remarks' => 'Verified customer location and budget.']);
        $service->updateInternalLead($record->id, $this->internal, ['action' => 'followup', 'followup_at' => now()->addHour()->toDateTimeString()]);
        $this->assertSame('followup_required', $record->fresh()->status_code);
        $this->assertNull($lead->fresh()->first_response_at); $this->assertSame('new', $lead->fresh()->current_stage);
        $service->updateInternalLead($record->id, $this->internal, ['action' => 'verify']);
        $forwarded = $service->requestInternalHandover($record->id, $this->internal);
        $this->assertNotNull($forwarded->forwarded_at); $this->assertEquals($this->internal->id, $forwarded->forwarded_by);
        $this->assertSame('verified', $forwarded->verification_status); $this->assertSame(1, $forwarded->call_attempts);
        $this->assertFalse($lead->fresh()->internal_processing_pending); $this->assertEquals($this->clientAgent->id, $lead->fresh()->assigned_to);
        $this->assertSame('assigned', $lead->fresh()->current_stage); $this->assertNull($lead->fresh()->first_response_at);
        $this->assertSame(1, CreditTransaction::withoutGlobalScopes()->where('lead_id', $lead->id)->where('transaction_type', 'lead_consumption')->count());
        $service->requestInternalHandover($record->id, $this->internal);
        $this->assertSame(1, $record->events()->where('action', 'forwarded')->count());
        $this->assertEquals(480, CreditWallet::withoutGlobalScopes()->where('client_id', $this->builder->id)->value('balance'));
        $this->actingAs($this->builder); $this->assertTrue(Lead::panelQuery()->whereKey($lead->id)->exists());
        Livewire::test(LeadDetail::class)->call('openLead', $lead->id)->assertSet('isOpen', true)->assertSee('Lead Panther Verification & Handover')->assertSee('Verified customer location and budget.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'internal_processing.forwarded', 'organization_id' => $this->org->id]);
        $this->assertSame(1, $this->builder->notifications()->where('data->type', 'internal_forwarded')->count());
    }

    public function test_internal_staff_cannot_open_another_executives_lead_or_client_pipeline(): void
    {
        $lead = $this->receive(); $other = $this->receive();
        $this->actingAs($this->internal);
        $this->assertFalse(Lead::panelQuery()->whereKey($lead->id)->exists());
        Livewire::test(InternalProcessingQueue::class)->assertSee($lead->lead_code)->assertDontSee($other->lead_code);
        Livewire::test(LeadKanban::class)->assertSee('Internal Processing Queue');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(LeadDistributionService::class)->requestInternalHandover($other->internalProcessing->id, $this->internal);
    }

    public function test_auto_forward_and_failure_retry_do_not_charge_or_handover_twice(): void
    {
        $this->configure(['auto_forward' => true]);
        CreditWallet::withoutGlobalScopes()->where('client_id', $this->builder->id)->update(['balance' => 0]);
        $lead = $this->receive(); $record = $lead->internalProcessing; $this->verify($record);
        $this->assertNotNull($record->fresh()->handover_requested_at); $this->assertNull($record->fresh()->forwarded_at);
        $this->assertNotNull($record->fresh()->forwarding_error); $this->assertTrue($lead->fresh()->internal_processing_pending);
        $this->assertSame(0, CreditTransaction::withoutGlobalScopes()->where('lead_id', $lead->id)->count());
        CreditWallet::withoutGlobalScopes()->where('client_id', $this->builder->id)->update(['balance' => 500]);
        $service = app(LeadDistributionService::class); $service->requestInternalHandover($record->id, $this->internal); $service->requestInternalHandover($record->id, $this->internal);
        $this->assertNotNull($record->fresh()->forwarded_at); $this->assertNull($record->fresh()->forwarding_error);
        $this->assertSame(1, $record->events()->where('action', 'forwarded')->count());
    }

    public function test_overview_settings_and_queue_actions_use_database_records(): void
    {
        Livewire::actingAs($this->admin)->test(InternalProcessingSettings::class, ['organizationId' => $this->org->id])->assertSet('enabled', true)->assertSee('Verification team')
            ->set('autoForward', true)->call('saveSettings')->assertHasNoErrors()->assertDispatched('toast');
        $lead = $this->receive(); $record = $lead->internalProcessing;
        Livewire::actingAs($this->internal)->test(InternalProcessingQueue::class)->call('openRecord', $record->id, 'call')
            ->set('callingStatus', 'connected')->set('remarks', 'Customer contact successfully verified.')->call('saveAction')->assertHasNoErrors()->assertDispatched('toast')
            ->call('openRecord', $record->id, 'verify')->call('saveAction')->assertHasNoErrors();
        $this->assertNotNull($record->fresh()->forwarded_at);
    }

    public function test_rejected_statuses_block_forwarding_and_a_new_call_invalidates_verification(): void
    {
        $record = $this->receive()->internalProcessing; $service = app(LeadDistributionService::class);
        $this->verify($record);
        $service->updateInternalLead($record->id, $this->internal, ['action' => 'call', 'calling_status' => 'not_answered', 'remarks' => 'Second call was not answered.']);
        $this->assertSame('pending', $record->fresh()->verification_status);
        $this->assertNotContains('forward', $service->availableInternalActions($record->fresh(), $this->internal));
        $service->updateInternalLead($record->id, $this->internal, ['action' => 'status', 'status_code' => 'invalid', 'remarks' => 'Customer number details are invalid.']);
        $this->assertSame([], $service->availableInternalActions($record->fresh(), $this->internal));
        $this->assertSame('Customer number details are invalid.', $record->fresh()->calling_remarks);
        $this->assertSame(1, $service->internalStatistics($this->org)['Rejected / Invalid Leads']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->requestInternalHandover($record->id, $this->admin);
    }

    public function test_manual_assignment_and_interested_forwarding_policy_are_applied(): void
    {
        $this->configure(['assignment_method' => 'manual', 'forward_statuses' => ['interested']]);
        $record = $this->receive()->internalProcessing; $service = app(LeadDistributionService::class);
        $this->assertNull($record->assigned_user_id);
        $service->assignInternalExecutive($record->id, $this->internal2->id, $this->admin);
        $service->updateInternalLead($record->id, $this->internal2, ['action' => 'call', 'calling_status' => 'connected', 'remarks' => 'Customer is interested in this property.']);
        $service->updateInternalLead($record->id, $this->internal2, ['action' => 'status', 'status_code' => 'interested']);
        $service->updateInternalLead($record->id, $this->internal2, ['action' => 'verify']);
        $this->assertSame('interested', $record->fresh()->status_code);
        $this->assertNotNull($service->requestInternalHandover($record->id, $this->internal2)->forwarded_at);
    }

    public function test_due_reminders_are_deduplicated_and_not_sent_to_client_before_handover(): void
    {
        $lead = $this->receive(); $record = $lead->internalProcessing; $service = app(LeadDistributionService::class);
        $service->updateInternalLead($record->id, $this->internal, ['action' => 'followup', 'followup_at' => now()->addMinutes(5)->toDateTimeString()]);
        $this->travel(6)->minutes();
        $this->artisan('followups:check-due')->assertSuccessful(); $this->artisan('followups:check-due')->assertSuccessful();
        $this->assertSame(1, $this->internal->notifications()->where('data->type', 'internal_followup_due')->count());
        $this->assertSame(0, $this->builder->notifications()->count());
        $this->assertSame(1, $service->internalStatistics($this->org)['Follow-ups Pending']);
        $this->assertSame(0, $service->internalStatistics($this->directOrg)['Pending Internal Processing']);
        $this->assertSame(0, $service->internalStatistics($this->org, now()->addDay()->toDateTimeString())['Total Leads Received']);
    }

    public function test_handover_obeys_delivery_limits_and_starts_client_sla_at_actual_delivery(): void
    {
        $this->campaign->update(['delivery_daily_limit' => 1, 'delivery_interval_minutes' => 30]);
        $first = $this->receive(); $second = $this->receive(); $service = app(LeadDistributionService::class);
        $this->travel(2)->days();
        $this->verify($first->internalProcessing); $this->verify($second->internalProcessing);
        $service->requestInternalHandover($first->internalProcessing->id, $this->internal);
        $queued = $service->requestInternalHandover($second->internalProcessing->id, $this->internal2);
        $this->assertNotNull($first->fresh()->delivered_at); $this->assertNull($queued->forwarded_at);
        $this->assertTrue($second->fresh()->delivery_at->isFuture());
        $this->assertFalse($service->releaseDelivery($second->id));
        $this->assertEquals($first->fresh()->delivered_at, $first->fresh()->client_response_start);
        $this->assertSame('SLA Pending', $first->fresh()->sla_badge);
        $this->travel(1)->days();
        $this->assertTrue($service->releaseDelivery($second->id));
        $this->assertNotNull($queued->fresh()->forwarded_at);
        $this->assertSame(2, $service->internalStatistics($this->org)['Forwarded to Client']);
    }

    public function test_disabling_processing_only_affects_new_leads_and_revoked_members_lose_access(): void
    {
        $held = $this->receive(); $service = app(LeadDistributionService::class);
        $service->configureInternalProcessing($this->org, ['enabled' => false], $this->admin);
        $direct = $this->receive();
        $this->assertNotNull($direct->delivered_at); $this->assertNull($direct->internalProcessing);
        $this->assertTrue($held->fresh()->internal_processing_pending);
        $this->assertTrue($service->canProcessInternal($held->internalProcessing, $this->internal));
        SalesTeamMember::where('sales_team_id', $this->team->id)->where('user_id', $this->internal->id)->update(['is_active' => false]);
        $this->assertFalse($service->canProcessInternal($held->internalProcessing, $this->internal));
        $this->assertFalse($service->hasInternalQueueAccess($this->internal));
    }

    public function test_client_members_cannot_be_configured_as_platform_callers(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->configure(['member_ids' => [$this->clientAgent->id]]);
    }

    public function test_client_cannot_change_configuration_or_handover_records(): void
    {
        $record = $this->receive()->internalProcessing;
        $this->assertFalse(app(LeadDistributionService::class)->canProcessInternal($record, $this->builder));
        Livewire::actingAs($this->builder)->test(InternalProcessingSettings::class, ['organizationId' => $this->org->id])->assertForbidden();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(LeadDistributionService::class)->requestInternalHandover($record->id, $this->builder);
    }

    public function test_uncontacted_leads_cannot_be_verified_through_the_status_action(): void
    {
        $record = $this->receive()->internalProcessing;
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(LeadDistributionService::class)->updateInternalLead($record->id, $this->internal, ['action' => 'status', 'status_code' => 'verified']);
    }

    public function test_capacity_assignment_and_real_date_filtered_statistics(): void
    {
        $this->configure(['assignment_method' => 'capacity']);
        $first = $this->receive(); $second = $this->receive();
        $this->assertNotEquals($first->internalProcessing->assigned_user_id, $second->internalProcessing->assigned_user_id);
        $service = app(LeadDistributionService::class);
        $service->updateInternalLead($first->internalProcessing->id, $this->internal, ['action' => 'status', 'status_code' => 'duplicate']);
        $stats = $service->internalStatistics($this->org, now()->startOfDay()->toDateTimeString(), now()->endOfDay()->toDateTimeString());
        $this->assertSame(2, $stats['Total Leads Received']); $this->assertSame(1, $stats['Assigned for Calling']);
        $this->assertSame(1, $stats['Pending Internal Processing']); $this->assertSame(1, $stats['Rejected / Invalid Leads']);
    }

    public function test_internal_notification_links_authorize_the_record_and_open_existing_leads_page(): void
    {
        $record = $this->receive()->internalProcessing;
        $notification = $this->internal->notifications()->where('data->type', 'internal_assigned')->firstOrFail();
        $this->actingAs($this->internal)->get(route('notifications.open', $notification->id))->assertRedirect(route('leads.index', ['queue' => 'internal', 'processing' => $record->id]));
        $this->assertNotNull($notification->fresh()->read_at);
        $this->actingAs($this->builder)->get(route('notifications.open', $notification->id))->assertNotFound();
    }

    public function test_handover_sla_reporting_excludes_internal_wait_time(): void
    {
        $lead = $this->receive(); $this->travel(2)->days();
        $service = app(LeadDistributionService::class);
        $this->actingAs($this->admin);
        $this->assertSame(0, Lead::withoutGlobalScopes()->whereRaw(Lead::clientSlaStartExpression().' < ?', [now()->subMinutes(15)])->count());
        $this->verify($lead->internalProcessing); $service->requestInternalHandover($lead->internalProcessing->id, $this->internal);
        $this->assertSame(0, Lead::withoutGlobalScopes()->whereRaw(Lead::clientSlaStartExpression().' < ?', [now()->subMinutes(15)])->count());
        Livewire::test(\App\Livewire\Dashboard\DashboardShell::class)->assertOk();
        Livewire::test(\App\Livewire\Reports\SlaDashboard::class)->assertDontSee($lead->lead_code);
        $this->travel(16)->minutes();
        Livewire::test(\App\Livewire\Reports\SlaDashboard::class)->assertSee($lead->lead_code);
    }
}
