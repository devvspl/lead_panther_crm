<?php
namespace Tests\Feature\Leads;

use App\Livewire\Leads\ClientDeliveryCenter;
use App\Models\{Campaign, Client, CreditTransaction, CreditWallet, Lead, LeadDeliveryItem, LeadDeliveryPlan, LeadSource, Organization, Project, User};
use App\Services\LeadDistributionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ClientDeliveryTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private Client $source;
    private Client $destination;
    private Project $project;
    private Campaign $campaign;
    private Campaign $sourceCampaign;

    public function test_archiving_a_queued_lead_stops_delivery_without_losing_transfer_evidence(): void
    {
        $ids = $this->leads(2);
        $plan = $this->plan(['frequency'=>'immediate','quantity'=>2]);
        $this->service()->enqueueClientLeads($plan,$ids,true,$this->admin);
        $run = $this->service()->startClientDeliveryCycle($plan->id,true);
        $this->assertNotNull($run);
        app(\App\Services\LeadArchiveService::class)->archive($ids[0], 'Pause customer review');
        $this->assertDatabaseHas('lead_delivery_items',['lead_id'=>$ids[0],'status'=>'pending','run_id'=>null]);
        $this->service()->processClientDeliveryRun($run->id);
        $this->assertDatabaseHas('lead_delivery_items',['lead_id'=>$ids[1],'status'=>'sent']);
        $this->assertDatabaseHas('leads',['id'=>$ids[0],'delivered_at'=>null]);
        $this->assertSame(0,CreditTransaction::where('lead_id',$ids[0])->count());
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id,true));
        $this->deleteJson(route('admin.leads.purge',$ids[0]),['confirmed'=>true])->assertUnprocessable();
        app(\App\Services\LeadArchiveService::class)->restore($ids[0]);
        $restoredRun = $this->service()->startClientDeliveryCycle($plan->id,true);
        $this->assertNotNull($restoredRun);
        $this->service()->processClientDeliveryRun($restoredRun->id);
        $this->assertDatabaseHas('lead_delivery_items',['lead_id'=>$ids[0],'status'=>'sent']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'UTC']);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-17 10:00:00', 'UTC'));
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create(['status' => 'active']); $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);
        $a = Organization::create(['name' => 'Source client', 'status' => 'active', 'auto_assign_enabled' => false]);
        $b = Organization::create(['name' => 'Destination client', 'status' => 'active', 'auto_assign_enabled' => false]);
        $this->source = Client::create(['name' => 'Source owner', 'organization_id' => $a->id, 'status' => 'active']);
        $this->destination = Client::create(['name' => 'Destination owner', 'organization_id' => $b->id, 'status' => 'active']);
        User::findOrFail($this->source->id)->assignRole('Builder/Developer');
        User::findOrFail($this->destination->id)->assignRole('Builder/Developer');
        $source = LeadSource::create(['name' => 'Actual campaign source']);
        $this->project = Project::create(['client_id' => $this->destination->id, 'name' => 'Destination project', 'status' => 'active', 'delivery_enabled' => true]);
        $this->campaign = Campaign::create(['project_id' => $this->project->id, 'name' => 'Destination campaign', 'lead_source_id' => $source->id, 'status' => 'active']);
        $sourceProject = Project::create(['client_id' => $this->source->id, 'name' => 'Source project', 'status' => 'active']);
        $this->sourceCampaign = Campaign::create(['project_id' => $sourceProject->id, 'name' => 'Source campaign', 'lead_source_id' => $source->id, 'status' => 'active']);
        CreditWallet::create(['client_id' => $this->destination->id, 'balance' => 10000, 'opening_balance' => 10000]);
    }

    public function test_campaign_is_optional_and_project_only_push_delivers_to_client(): void
    {
        Queue::fake();
        $project = Project::create(['client_id'=>$this->destination->id,'name'=>'Project without campaigns','status'=>'active','delivery_enabled'=>true]);
        $ids = $this->leads(1);
        Livewire::test(ClientDeliveryCenter::class,['organizationId'=>$this->source->organization_id])
            ->call('openPush',$this->source->organization_id,$ids)
            ->set('settings.client_id',$this->destination->id)->set('settings.project_id',$project->id)
            ->set('settings.campaign_id','')->call('pushLeads')->assertHasNoErrors();
        $plan = LeadDeliveryPlan::where('organization_id',$this->source->organization_id)->firstOrFail();
        $this->assertNull($plan->campaign_id);
        $item = $plan->items()->firstOrFail();
        $this->assertSame('sent',$item->status);
        $this->assertSame($project->id,$item->campaign->project_id);
        $this->assertSame('General Campaign',$item->campaign->name);
        $this->actingAs(User::findOrFail($this->destination->id));
        $this->assertTrue(Lead::panelQuery()->whereKey($ids[0])->exists());
    }

    public function test_deltek_configuration_seeder_replaces_settings_preserves_history_and_delivers_one_every_fifteen_minutes(): void
    {
        Queue::fake();
        Organization::findOrFail($this->destination->organization_id)->update(['name' => 'Deltek']);
        $ids = $this->leads(3);
        Lead::withoutGlobalScopes()->whereIn('id', $ids)->update(['client_id' => $this->destination->id]);
        $seeder = \Database\Seeders\DeltekDeliveryConfigurationSeeder::class;
        $this->seed($seeder);
        $plan = LeadDeliveryPlan::where('organization_id', $this->destination->organization_id)->firstOrFail();
        $this->assertSame('15_minutes', $plan->settings['frequency']);
        $this->assertSame(1, $plan->settings['quantity']);
        $this->assertFalse($plan->settings['daily_enabled']);
        $this->assertNull($plan->campaign_id);
        $this->assertSame(3, $plan->items()->count());
        $run = $this->service()->startClientDeliveryCycle($plan->id);
        $this->service()->processClientDeliveryRun($run->id);
        $this->assertSame(1, $plan->items()->where('status', 'sent')->count());
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        $this->travel(15)->minutes();
        $run2 = $this->service()->startClientDeliveryCycle($plan->id);
        $this->service()->processClientDeliveryRun($run2->id);
        $this->assertSame(2, $plan->items()->where('status', 'sent')->count());
        $plan->update(['settings' => array_merge($plan->settings, ['frequency' => 'manual', 'quantity' => 10])]);
        $this->seed($seeder);
        $this->assertSame($plan->id, LeadDeliveryPlan::where('organization_id', $plan->organization_id)->sole()->id);
        $this->assertSame(3, $plan->items()->count());
        $this->assertSame(2, $plan->runs()->where('status', 'completed')->count());
        $this->assertSame(2, $plan->items()->where('status', 'sent')->count());
        $this->assertSame(1, $plan->fresh()->settings['quantity']);
        $this->actingAs(User::findOrFail($this->destination->id));
        $this->assertSame(2, Lead::panelQuery()->whereIn('id', $ids)->count());
    }

    public function test_optional_campaign_preserves_matching_campaign_and_rejects_wrong_project(): void
    {
        $ids = $this->leads(1);
        Lead::withoutGlobalScopes()->whereKey($ids[0])->update(['campaign_id'=>$this->campaign->id]);
        $plan = $this->plan(['campaign_id'=>null]);
        $this->assertNull($plan->campaign_id);
        $this->service()->enqueueClientLeads($plan,$ids,true,$this->admin);
        $this->assertSame($this->campaign->id,$plan->items()->firstOrFail()->campaign_id);
        $this->expectException(ValidationException::class);
        $this->plan(['campaign_id'=>$this->sourceCampaign->id]);
    }

    public function test_row_push_delivers_immediately_on_repeated_clicks_without_waiting_for_next_cycle(): void
    {
        Queue::fake();
        $plan=$this->plan(['frequency'=>'immediate','quantity'=>1,'daily_enabled'=>false]);
        $ids=$this->leads(2);
        $component=Livewire::test(ClientDeliveryCenter::class,['organizationId'=>$this->source->organization_id]);
        $component->dispatch('client-push-selected',organizationId:$this->source->organization_id,leadIds:[$ids[0]],sendNow:true)->assertHasNoErrors();
        $this->assertSame(1,$plan->items()->where('status','sent')->count());
        $this->assertTrue($plan->fresh()->next_run_at->isFuture());
        $component->dispatch('client-push-selected',organizationId:$this->source->organization_id,leadIds:[$ids[1]],sendNow:true)->assertHasNoErrors();
        $this->assertSame(2,$plan->items()->where('status','sent')->count());
        $component->dispatch('client-push-selected',organizationId:$this->source->organization_id,leadIds:[$ids[1]],sendNow:true)->assertHasNoErrors();
        $this->assertSame(2,CreditTransaction::where('client_id',$this->destination->id)->count());
        $this->actingAs(User::findOrFail($this->destination->id));
        $this->assertSame(2,Lead::panelQuery()->whereIn('id',$ids)->count());
    }

    public function test_row_push_processes_a_lead_that_is_already_pending_in_the_queue(): void
    {
        Queue::fake();
        $ids=$this->leads(1);
        $plan=$this->plan(['frequency'=>'immediate','quantity'=>1]);
        $this->service()->enqueueClientLeads($plan,$ids,true,$this->admin);
        $plan->update(['next_run_at'=>now()->addHour()]);
        Livewire::test(ClientDeliveryCenter::class,['organizationId'=>$this->source->organization_id])
            ->dispatch('client-push-selected',organizationId:$this->source->organization_id,leadIds:$ids,sendNow:true)->assertHasNoErrors();
        $this->assertSame('sent',$plan->items()->firstOrFail()->status);
    }

    public function test_recovery_command_enrolls_consent_and_delivers_one_lead_on_fifteen_minute_plan(): void
    {
        Queue::fake();
        $plan=$this->plan(['frequency'=>'15_minutes','quantity'=>1]); $ids=$this->leads(3);
        $this->artisan('client-delivery:recover',['organization'=>$this->source->organization_id,'--confirm-sharing'=>true,'--run'=>true])->assertSuccessful();
        $this->assertSame(1,$plan->items()->where('status','sent')->count());
        $this->assertSame(2,$plan->items()->where('status','pending')->count());
        $this->assertTrue($plan->fresh()->next_run_at->equalTo(now()->addMinutes(15)));
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        $this->travel(15)->minutes(); $this->cycle($plan,false);
        $this->assertSame(2,$plan->items()->where('status','sent')->count());
        $this->actingAs(User::findOrFail($this->destination->id));
        $this->assertSame(2,Lead::panelQuery()->whereIn('id',$ids)->count());
    }
    public function test_delivery_health_reports_consent_limits_and_worker_errors(): void
    {
        $plan=$this->plan(['daily_limit'=>5]); $this->leads(2);
        \App\Models\ScheduledTaskRun::create(['task_key'=>'queue-worker','label'=>'Queue worker','expression'=>'* * * * *','timezone'=>'UTC','origin'=>'scheduled','status'=>'failed','error'=>'proc_open unavailable']);
        $health=$this->service()->clientDeliveryHealth($plan);
        $this->assertSame(0,$health['awaiting']);
        $this->assertSame(2,$health['ready']);
        $this->assertStringContainsString('proc_open',collect($health['alerts'])->pluck('message')->implode(' '));
        Livewire::test(ClientDeliveryCenter::class,['organizationId'=>$this->source->organization_id])->assertSee('Queue worker failed')->assertSee('Daily limit: 5');
    }
    public function test_recovery_can_retry_a_failed_transfer_without_duplicate_charging(): void
    {
        Queue::fake();
        $plan=$this->plan(['frequency'=>'15_minutes','quantity'=>1]); $ids=$this->leads(1);
        $this->service()->enqueueClientLeads($plan,$ids,true,$this->admin);
        CreditWallet::where('client_id',$this->destination->id)->update(['balance'=>0]);
        $this->cycle($plan);
        $this->assertSame('failed',$plan->items()->firstOrFail()->status);
        CreditWallet::where('client_id',$this->destination->id)->update(['balance'=>100]);
        $this->artisan('client-delivery:recover',['organization'=>$this->source->organization_id,'--retry-failed'=>true,'--run'=>true])->assertSuccessful();
        $this->assertSame('sent',$plan->items()->firstOrFail()->status);
        $this->assertSame(1,CreditTransaction::where('lead_id',$ids[0])->count());
    }

    private function service(): LeadDistributionService { return app(LeadDistributionService::class); }
    private function settings(array $overrides = []): array
    {
        return array_merge(['client_id' => $this->destination->id, 'project_id' => $this->project->id, 'campaign_id' => $this->campaign->id,
            'frequency' => '30_minutes', 'quantity_mode' => 'fixed', 'quantity' => 2, 'max_cycle' => null, 'daily_enabled' => true, 'daily_limit' => 5,
            'automatic' => true, 'timezone' => 'UTC', 'start_date' => null, 'end_date' => null, 'window_start' => '00:00', 'window_end' => '23:59',
            'daily_time' => '10:00', 'weekly_day' => 2, 'working_days' => [0,1,2,3,4,5,6], 'confirmed_send_all' => true, 'confirmed_unlimited' => true], $overrides);
    }
    private function plan(array $overrides = []): LeadDeliveryPlan
    {
        return $this->service()->saveClientDeliveryPlan($this->source->organization, $this->settings($overrides), $this->admin);
    }
    private function leads(int $count): array
    {
        $ids = [];
        for ($n = 0; $n < $count; $n++) $ids[] = Lead::create(['client_id' => $this->source->id, 'campaign_id' => $this->sourceCampaign->id, 'lead_source_id' => $this->sourceCampaign->lead_source_id,
            'lead_code' => Lead::generateUniqueLeadCode(), 'name' => 'Customer '.Lead::withoutGlobalScopes()->count(), 'mobile' => '9'.str_pad((string) (Lead::withoutGlobalScopes()->count() + 1), 9, '0', STR_PAD_LEFT), 'status' => 'new', 'current_stage' => 'new'])->id;
        return $ids;
    }
    private function enroll(LeadDeliveryPlan $plan, int $count): array
    {
        $ids = $this->leads($count);
        $this->service()->enqueueClientLeads($plan, $ids, true, $this->admin);
        return $ids;
    }
    private function cycle(LeadDeliveryPlan $plan, bool $manual = true)
    {
        $run = $this->service()->startClientDeliveryCycle($plan->id, $manual);
        if ($run) $this->service()->processClientDeliveryRun($run->id);
        return $run?->fresh();
    }

    public function test_manual_bulk_cycles_daily_limit_and_duplicate_protection(): void
    {
        $plan = $this->plan(); $ids = $this->enroll($plan, 7);
        $this->assertFalse($this->service()->releaseDelivery($ids[0]));
        $this->assertSame(['queued' => 0, 'skipped' => 7], $this->service()->enqueueClientLeads($plan, $ids, true, $this->admin));
        $this->assertSame('completed', $this->cycle($plan)->status);
        $this->assertSame(2, $plan->items()->where('status', 'sent')->count());
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        $this->travel(30)->minutes(); $this->cycle($plan, false);
        $this->assertSame(4, $plan->items()->where('status', 'sent')->count());
        $this->travel(30)->minutes(); $this->cycle($plan, false);
        $this->assertSame(5, $plan->items()->where('status', 'sent')->count());
        $this->assertSame(2, $plan->items()->where('status', 'pending')->count());
        $this->assertSame(0, $this->service()->remainingClientDeliveryCapacity($plan));
        $this->assertSame(5, CreditTransaction::where('client_id', $this->destination->id)->count());
        $this->travel(1)->days(); $this->cycle($plan);
        $this->assertSame(7, $plan->items()->where('status', 'sent')->count());
    }

    public function test_send_all_bypasses_quantity_and_max_cycle_but_respects_separate_daily_limit(): void
    {
        $plan = $this->plan(['quantity_mode' => 'all', 'quantity' => 0, 'max_cycle' => 0, 'daily_enabled' => false, 'daily_limit' => 0]);
        $this->enroll($plan, 6); $run = $this->cycle($plan);
        $this->assertSame(6, $run->total);
        $this->assertSame(6, $plan->items()->where('status', 'sent')->count());
        $this->assertNull($this->service()->remainingClientDeliveryCapacity($plan));
        $plan = $this->plan(['quantity_mode' => 'all', 'max_cycle' => 1, 'daily_limit' => 8]);
        $this->enroll($plan, 4); $run = $this->cycle($plan);
        $this->assertSame(2, $run->total);
        $this->assertSame(2, $plan->items()->where('status', 'pending')->count());
    }

    public function test_pause_resume_stop_and_changes_preserve_unsent_leads(): void
    {
        Queue::fake();
        $plan = $this->plan(['quantity_mode' => 'all', 'daily_enabled' => false]); $this->enroll($plan, 5);
        $run = $this->service()->startClientDeliveryCycle($plan->id, true);
        $this->assertTrue($this->service()->processClientDeliveryRun($run->id, 1));
        $this->service()->controlClientDelivery($plan, 'pause', $this->admin);
        $this->assertFalse($this->service()->processClientDeliveryRun($run->id));
        $this->assertSame(1, $plan->items()->where('status', 'sent')->count());
        $this->service()->controlClientDelivery($plan, 'resume', $this->admin);
        $this->service()->processClientDeliveryRun($run->id, 1);
        $this->service()->controlClientDelivery($plan, 'stop', $this->admin);
        $this->assertSame(3, $plan->items()->where('status', 'pending')->count());
        $plan = $this->plan(['frequency' => 'hourly', 'quantity' => 1]);
        $this->assertSame(3, $plan->items()->where('status', 'pending')->count());
        $this->service()->controlClientDelivery($plan, 'resume', $this->admin); $this->cycle($plan);
        $this->assertSame(3, $plan->items()->where('status', 'sent')->count());
    }

    public function test_failure_retry_consent_and_client_visibility(): void
    {
        $plan = $this->plan(['frequency' => 'manual', 'automatic' => false]); $ids = $this->enroll($plan, 1);
        CreditWallet::where('client_id', $this->destination->id)->update(['balance' => 0]);
        $this->cycle($plan);
        $item = $plan->items()->firstOrFail();
        $this->assertSame('failed', $item->status); $this->assertNotEmpty($item->failure_reason);
        $this->assertSame(5, $this->service()->remainingClientDeliveryCapacity($plan));
        $this->actingAs(User::findOrFail($this->destination->id)); $this->assertFalse(Lead::panelQuery()->whereKey($ids[0])->exists());
        $this->actingAs($this->admin);
        CreditWallet::where('client_id', $this->destination->id)->update(['balance' => 1000]);
        $this->service()->retryClientDelivery($item, $this->admin); $this->cycle($plan);
        $this->assertSame('sent', $item->fresh()->status);
        $this->assertSame(2, $item->attemptHistory()->count());
        $this->actingAs(User::findOrFail($this->destination->id)); $this->assertTrue(Lead::panelQuery()->whereKey($ids[0])->exists());
    }

    public function test_calendar_daily_weekly_dates_and_working_hours(): void
    {
        $plan = $this->plan(['frequency' => 'daily', 'daily_time' => '11:00', 'window_start' => '09:00', 'window_end' => '17:00']);
        $this->assertSame('2026-11-17 11:00', $plan->next_run_at->format('Y-m-d H:i'));
        $this->enroll($plan, 1); $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        $this->travel(1)->hours(); $this->cycle($plan, false);
        $this->assertSame('2026-11-18 11:00', $plan->fresh()->next_run_at->format('Y-m-d H:i'));
        $plan = $this->plan(['frequency' => 'weekly', 'weekly_day' => 4, 'daily_time' => '12:00', 'timezone' => 'Asia/Kolkata']);
        $this->assertSame(4, $plan->next_run_at->copy()->setTimezone('Asia/Kolkata')->dayOfWeek);
        $plan = $this->plan(['start_date' => '2026-12-01', 'end_date' => '2026-12-02']);
        $this->assertSame('2026-12-01', $plan->next_run_at->toDateString());
        $this->assertNull($this->service()->nextClientDeliveryAt($plan, now()->addMonth()));
    }

    public function test_ui_history_export_acceptance_and_role_isolation(): void
    {
        $plan = $this->plan(); $this->enroll($plan, 1); $this->cycle($plan);
        $component = Livewire::test(ClientDeliveryCenter::class, ['organizationId' => $this->source->organization_id])->assertSee('Client Delivery')->assertSee('Destination owner');
        $item = $plan->items()->first(); $component->call('inspectAttempts', $item->id)->assertSee('Actual attempt records')->call('accept', $item->id)->assertHasNoErrors();
        $this->assertSame('accepted', $item->fresh()->status);
        Excel::fake(); $component->set('historyStatus', 'accepted')->call('exportHistory');
        Excel::assertDownloaded('client-delivery-history-'.$this->source->organization_id.'.xlsx', fn ($export) => $export->collection()->count() === 1);
        Livewire::test(ClientDeliveryCenter::class, ['organizationId' => $this->destination->organization_id])->call('retry', $item->id)->assertNotFound();
        $this->actingAs($this->destination); Livewire::test(ClientDeliveryCenter::class, ['organizationId' => $this->source->organization_id])->assertForbidden();
    }

    public function test_new_intake_with_processing_disabled_waits_only_for_manual_cycle(): void
    {
        $plan = $this->plan(['frequency' => 'manual', 'automatic' => false]);
        $ids = $this->leads(2);
        $this->assertSame(2, $plan->items()->where('status', 'pending')->count());
        $this->assertFalse($this->service()->releaseDelivery($ids[0]));
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        $run = $this->cycle($plan);
        $this->assertSame(2, $plan->items()->where('status', 'sent')->count());
        $this->assertFalse($this->service()->processClientDeliveryRun($run->id));
        $this->assertSame(2, CreditTransaction::where('client_id', $this->destination->id)->count());
    }

    public function test_daily_capacity_resets_at_the_selected_timezone_midnight(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-17 18:20:00', 'UTC'));
        $plan = $this->plan(['timezone' => 'Asia/Kolkata', 'daily_limit' => 1]);
        $this->enroll($plan, 2); $this->cycle($plan);
        $this->assertSame(0, $this->service()->remainingClientDeliveryCapacity($plan));
        $this->travel(11)->minutes();
        $this->assertSame(1, $this->service()->remainingClientDeliveryCapacity($plan));
        $this->cycle($plan);
        $this->assertSame(2, $plan->items()->where('status', 'sent')->count());
    }

    public function test_immediate_fixed_cycle_maximum_and_queue_job(): void
    {
        $plan = $this->plan(['frequency' => 'immediate', 'quantity' => 5, 'max_cycle' => 1]);
        $this->enroll($plan, 3);
        $run = $this->service()->startClientDeliveryCycle($plan->id);
        $this->assertSame(1, $run->total);
        (new \App\Jobs\ProcessClientDeliveryRun($run->id))->handle($this->service());
        $this->assertSame(1, $plan->items()->where('status', 'sent')->count());
        $this->assertSame(2, $plan->items()->where('status', 'pending')->count());
        $this->assertSame('completed', $run->fresh()->status);
    }

    public function test_disabled_processing_does_not_block_on_stale_internal_flags_or_missing_confirmation(): void
    {
        $plan = $this->plan(['quantity_mode' => 'all', 'daily_enabled' => false]);
        $ids = $this->enroll($plan, 3);
        Lead::withoutGlobalScopes()->find($ids[0])->update(['status' => 'invalid']);
        Lead::withoutGlobalScopes()->find($ids[1])->update(['internal_processing_pending' => true]);
        Lead::withoutGlobalScopes()->find($ids[2])->update(['delivery_consent_at' => null]);
        $this->cycle($plan);
        $this->assertSame(2, $plan->items()->where('status', 'sent')->count());
        $this->assertSame(1, $plan->items()->where('status', 'failed')->count());
        $this->assertSame(0, $plan->items()->where('status', 'pending')->count());
        $this->assertFalse(Lead::withoutGlobalScopes()->find($ids[1])->internal_processing_pending);
        $this->assertNull(Lead::withoutGlobalScopes()->find($ids[2])->delivery_consent_at);
    }

    public function test_enabled_processing_requires_real_verification_and_handover_before_scheduled_delivery(): void
    {
        Queue::fake();
        $id = $this->leads(1)[0];
        $plan = $this->plan();
        $this->source->organization->update(['internal_processing_enabled' => true, 'internal_processing_config' => ['forward_statuses' => ['verified']]]);
        $lead = Lead::withoutGlobalScopes()->findOrFail($id);
        $lead->update(['internal_processing_pending' => true, 'delivery_consent_at' => now()]);
        $record = \App\Models\InternalLeadProcessing::create(['lead_id' => $id, 'organization_id' => $plan->organization_id, 'status_code' => 'received']);
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        $this->assertSame(0, $this->service()->readyClientDeliveryLeads($plan->organization_id)->count());
        $record->update(['verification_status' => 'verified', 'verified_at' => now(), 'verified_by' => $this->admin->id, 'status_code' => 'verified']);
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id, true));
        $lead->update(['delivery_consent_at' => null]);
        $this->service()->requestInternalHandover($record->id, $this->admin);
        $this->assertNotNull($record->fresh()->handover_requested_at);
        $this->assertSame($plan->id, $lead->fresh()->delivery_plan_id);
        $this->assertNull($lead->fresh()->delivered_at);
        $this->assertNull($record->fresh()->forwarded_at);
        $this->travel(31)->minutes();
        $run = $this->service()->startClientDeliveryCycle($plan->id);
        $this->assertNotNull($run);
        $this->service()->processClientDeliveryRun($run->id);
        $this->assertNotNull($lead->fresh()->delivered_at);
        $this->assertNotNull($record->fresh()->forwarded_at);
        $this->assertSame('sent', $plan->items()->firstOrFail()->status);
        $this->assertNull($lead->fresh()->delivery_consent_at);
    }

    public function test_enabled_processing_routes_existing_unverified_intake_to_the_internal_queue(): void
    {
        Queue::fake();
        $id = $this->leads(1)[0];
        $plan = $this->plan();
        Organization::findOrFail($plan->organization_id)->update(['internal_processing_enabled' => true, 'internal_processing_config' => ['forward_statuses' => ['verified']]]);
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        $lead = Lead::withoutGlobalScopes()->findOrFail($id);
        $this->assertTrue($lead->internal_processing_pending);
        $this->assertNotNull($lead->internalProcessing);
        $this->assertNull($lead->internalProcessing->verified_at);
        $this->assertSame(1, $this->service()->clientDeliveryHealth($plan)['awaiting']);
        $this->assertNull($lead->delivered_at);
        $this->service()->startClientDeliveryCycle($plan->id);
        $this->assertSame(1, $plan->items()->count());
        $this->assertSame(1, \App\Models\InternalLeadProcessing::where('lead_id', $id)->count());
    }

    public function test_enabling_processing_after_queue_selection_blocks_release_and_disabling_allows_retry(): void
    {
        Queue::fake();
        $id = $this->leads(1)[0];
        $plan = $this->plan(['frequency' => 'immediate']);
        $run = $this->service()->startClientDeliveryCycle($plan->id);
        $this->assertNotNull($run);
        $organization = Organization::findOrFail($plan->organization_id);
        $organization->update(['internal_processing_enabled' => true]);
        $this->service()->processClientDeliveryRun($run->id);
        $this->assertNull(Lead::withoutGlobalScopes()->findOrFail($id)->delivered_at);
        $this->assertSame('failed', $plan->items()->firstOrFail()->status);
        $this->assertEquals(10000, CreditWallet::where('client_id', $this->destination->id)->value('balance'));
        $organization->update(['internal_processing_enabled' => false]);
        $this->assertSame(0, $this->service()->clientDeliveryHealth($plan)['awaiting']);
        $this->travel(2)->minutes();
        $retry = $this->service()->startClientDeliveryCycle($plan->id);
        $this->assertNotNull($retry);
        $this->service()->processClientDeliveryRun($retry->id);
        $this->assertNotNull(Lead::withoutGlobalScopes()->findOrFail($id)->delivered_at);
        $this->assertSame(2, $plan->items()->firstOrFail()->attemptHistory()->count());
    }

    public function test_simple_push_prefills_the_destination_and_delivers_through_the_queue_job(): void
    {
        Queue::fake();
        $ids = $this->leads(2);
        $component = Livewire::test(ClientDeliveryCenter::class, ['organizationId' => $this->source->organization_id])
            ->assertSet('settings.client_id', $this->source->id)
            ->assertSet('settings.project_id', $this->sourceCampaign->project_id)
            ->assertSet('settings.campaign_id', $this->sourceCampaign->id)
            ->call('openPush', $this->source->organization_id, $ids)->assertSet('settings.quantity', 2)
            ->set('settings.client_id', $this->destination->id)
            ->assertSet('settings.project_id', $this->project->id)->assertSet('settings.campaign_id', $this->campaign->id)
            ->call('pushLeads')->assertHasNoErrors()->assertSet('showConfiguration', false);
        $plan = LeadDeliveryPlan::where('organization_id', $this->source->organization_id)->firstOrFail();
        $run = $plan->runs()->firstOrFail();
        Queue::assertPushed(\App\Jobs\ProcessClientDeliveryRun::class, fn ($job) => $job->runId === $run->id);
        $this->assertSame(1, $plan->items()->where('status', 'sent')->count());
        (new \App\Jobs\ProcessClientDeliveryRun($run->id))->handle($this->service());
        $this->assertSame(2, $plan->items()->where('status', 'sent')->count());
        $this->assertSame(2, CreditTransaction::where('client_id', $this->destination->id)->count());
        $this->actingAs(User::findOrFail($this->destination->id));
        $this->assertSame(2, Lead::panelQuery()->whereIn('id', $ids)->count());
    }

    public function test_manual_push_starts_a_cycle_without_an_extra_send_button(): void
    {
        Queue::fake();
        $ids = $this->leads(1);
        $component = Livewire::test(ClientDeliveryCenter::class, ['organizationId' => $this->source->organization_id])
            ->call('openPush', $this->source->organization_id, $ids)->set('settings.client_id', $this->destination->id)
            ->set('settings.frequency', 'manual')->call('pushLeads')->assertHasNoErrors();
        $plan = LeadDeliveryPlan::where('organization_id', $this->source->organization_id)->firstOrFail();
        $this->assertFalse($plan->settings['automatic']);
        $run = $plan->runs()->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertSame('sent', $plan->items()->firstOrFail()->status);
        $nextIds = $this->leads(1);
        $this->service()->enqueueClientLeads($plan, $nextIds, true, $this->admin);
        $component->call('openPush', $this->source->organization_id, $ids)->call('pushLeads')->assertHasNoErrors();
        $this->assertSame(1, $plan->runs()->count());
        $this->assertSame(1, $plan->items()->where('status', 'pending')->count());
        Queue::assertNotPushed(\App\Jobs\ProcessClientDeliveryRun::class);
    }

    public function test_send_cycle_now_recovers_a_queued_run_and_makes_the_lead_visible_without_a_worker(): void
    {
        Queue::fake();
        $ids = $this->leads(1);
        $plan = $this->plan(['frequency' => 'immediate', 'quantity' => 1]);
        $this->service()->enqueueClientLeads($plan, $ids, true, $this->admin);
        $run = $this->service()->startClientDeliveryCycle($plan->id, true);
        $this->assertSame('queued', $run->status);
        Livewire::test(ClientDeliveryCenter::class, ['organizationId' => $this->source->organization_id])
            ->call('runNow')->assertHasNoErrors();
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame(1, $plan->items()->where('status', 'sent')->count());
        Queue::assertNotPushed(\App\Jobs\ProcessClientDeliveryRun::class);
        $this->actingAs(User::findOrFail($this->destination->id));
        $this->assertSame(1, Lead::panelQuery()->whereIn('id', $ids)->count());
        $this->service()->processClientDeliveryRun($run->id, 1);
        $this->assertSame(1, CreditTransaction::where('client_id', $this->destination->id)->count());
    }

    public function test_simple_scheduled_settings_enable_automation_and_send_all_needs_no_checkbox(): void
    {
        $ids = $this->leads(3);
        Livewire::test(ClientDeliveryCenter::class, ['organizationId' => $this->source->organization_id])
            ->call('openPush', $this->source->organization_id, $ids)->set('settings.client_id', $this->destination->id)
            ->set('settings.frequency', '30_minutes')->set('settings.quantity_mode', 'all')
            ->call('pushLeads')->assertHasNoErrors();
        $plan = LeadDeliveryPlan::where('organization_id', $this->source->organization_id)->firstOrFail();
        $this->assertTrue($plan->settings['automatic']);
        $this->assertFalse($plan->settings['daily_enabled']);
        $this->cycle($plan, false);
        $this->assertSame(3, $plan->items()->where('status', 'sent')->count());
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
    }

    public function test_empty_cycle_automatically_enrolls_existing_consented_leads(): void
    {
        Queue::fake();
        $ids = $this->leads(2); // Existing intake predates the saved plan, as in organization 38.
        $plan = $this->plan(['frequency' => 'immediate']);
        Lead::withoutGlobalScopes()->whereIn('id', $ids)->update(['delivery_consent_at' => now(), 'delivery_consent_by' => $this->admin->id]);
        $this->assertSame(0, $plan->items()->count());
        $component = Livewire::test(ClientDeliveryCenter::class, ['organizationId' => $this->source->organization_id])
            ->assertSee('Send Cycle Now')->assertDontSee('Queue All Eligible')->call('runNow')
            ->assertSet('showConfiguration', false)->assertHasNoErrors();
        $this->assertSame(2, $plan->items()->count());
        $run = $plan->runs()->firstOrFail();
        (new \App\Jobs\ProcessClientDeliveryRun($run->id))->handle($this->service());
        $this->actingAs(User::findOrFail($this->destination->id));
        $this->assertSame(2, Lead::panelQuery()->whereIn('id', $ids)->count());
    }

    public function test_automatic_discovery_with_processing_disabled_respects_batch_interval_and_limit(): void
    {
        Queue::fake();
        $ids = $this->leads(4);
        Lead::withoutGlobalScopes()->whereIn('id', [$ids[0], $ids[1], $ids[3]])->update(['delivery_consent_at' => now(), 'delivery_consent_by' => $this->admin->id]);
        Lead::withoutGlobalScopes()->whereKey($ids[3])->update(['internal_processing_pending' => true]);
        $plan = $this->plan(['quantity' => 1, 'daily_limit' => 1]);
        $run = $this->service()->startClientDeliveryCycle($plan->id);
        $this->assertSame(1, $run->total);
        $this->assertSame(4, $plan->items()->count());
        $this->service()->processClientDeliveryRun($run->id);
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        $this->assertSame(4, $plan->items()->count());
        $this->assertSame(1, $plan->items()->whereNotNull('sent_at')->count());
        $this->assertNull(Lead::withoutGlobalScopes()->find($ids[2])->delivery_consent_at);
        $this->assertNull(Lead::withoutGlobalScopes()->find($ids[2])->delivered_at);
        $this->assertNull(Lead::withoutGlobalScopes()->find($ids[3])->delivered_at);
        $this->travel(31)->minutes();
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        $this->travel(1)->days();
        $run = $this->service()->startClientDeliveryCycle($plan->id);
        $this->assertNotNull($run);
        $this->service()->processClientDeliveryRun($run->id);
        $this->assertSame(2, $plan->items()->whereNotNull('sent_at')->count());
    }

    public function test_automatic_retry_recovers_credits_without_duplicate_delivery_and_keeps_batch_history(): void
    {
        Queue::fake();
        $ids = $this->leads(1);
        Lead::withoutGlobalScopes()->whereIn('id', $ids)->update(['delivery_consent_at' => now(), 'delivery_consent_by' => $this->admin->id]);
        $plan = $this->plan(['frequency' => 'immediate', 'daily_enabled' => false, 'daily_limit' => null]);
        CreditWallet::where('client_id', $this->destination->id)->update(['balance' => 0]);
        $first = $this->service()->startClientDeliveryCycle($plan->id);
        $this->service()->processClientDeliveryRun($first->id);
        $item = $plan->items()->firstOrFail();
        $this->assertSame('failed', $item->status);
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        CreditWallet::where('client_id', $this->destination->id)->update(['balance' => 10000]);
        $this->travel(2)->minutes();
        $retry = $this->service()->startClientDeliveryCycle($plan->id);
        $this->assertNotNull($retry);
        $this->service()->processClientDeliveryRun($retry->id);
        $this->assertSame('sent', $item->fresh()->status);
        $this->assertSame(2, $item->attemptHistory()->count());
        $this->assertSame(1, $item->attemptHistory()->where('run_id', $first->id)->where('status', 'failed')->count());
        $this->assertSame(1, $item->attemptHistory()->where('run_id', $retry->id)->where('status', 'sent')->count());
        $balance = CreditWallet::where('client_id', $this->destination->id)->value('balance');
        $this->travel(2)->minutes();
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        $this->assertSame(1, $plan->items()->count());
        $this->assertEquals($balance, CreditWallet::where('client_id', $this->destination->id)->value('balance'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'delivery.auto_enrolled', 'subject_id' => $item->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'delivery.retry_scheduled', 'subject_id' => $item->id]);
    }

    public function test_automatic_cycles_obey_working_hours_interval_unlimited_capacity_and_pause(): void
    {
        Queue::fake();
        $ids = $this->leads(2);
        Lead::withoutGlobalScopes()->whereIn('id', $ids)->update(['delivery_consent_at' => now()]);
        $plan = $this->plan(['frequency' => '15_minutes', 'daily_enabled' => false, 'window_start' => '12:00', 'window_end' => '13:00']);
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        $this->assertSame(2, $plan->items()->count());
        $this->assertSame(0, $plan->runs()->count());
        $this->assertNull($this->service()->remainingClientDeliveryCapacity($plan));
        $this->travel(2)->hours();
        $run = $this->service()->startClientDeliveryCycle($plan->id);
        $this->service()->processClientDeliveryRun($run->id);
        $this->assertSame(2, $plan->items()->whereNotNull('sent_at')->count());
        $next = $this->leads(1);
        Lead::withoutGlobalScopes()->whereIn('id', $next)->update(['delivery_consent_at' => now()]);
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        $this->travel(15)->minutes();
        $this->service()->controlClientDelivery($plan, 'pause', $this->admin);
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        $this->service()->controlClientDelivery($plan, 'resume', $this->admin);
        $run = $this->service()->startClientDeliveryCycle($plan->id);
        $this->service()->processClientDeliveryRun($run->id);
        $this->assertSame(3, $plan->items()->whereNotNull('sent_at')->count());
    }

    public function test_empty_push_does_not_save_a_plan_or_change_delivery_routing(): void
    {
        Livewire::test(ClientDeliveryCenter::class, ['organizationId' => $this->source->organization_id])->call('pushLeads')->assertHasErrors('selectedLeadIds');
        $this->assertSame(0, LeadDeliveryPlan::count());
    }

    public function test_configuration_and_selected_push_use_real_records(): void
    {
        Queue::fake();
        $ids = $this->leads(1);
        Livewire::test(ClientDeliveryCenter::class, ['organizationId' => $this->source->organization_id])
            ->call('openPush', $this->source->organization_id, $ids)->assertSet('showConfiguration', true)
            ->set('settings', $this->settings(['automatic' => false, 'frequency' => 'manual']))->set('consentConfirmed', true)
            ->call('queueSelected')->assertHasNoErrors()->assertSet('showConfiguration', false);
        $this->assertDatabaseHas('lead_delivery_items', ['lead_id' => $ids[0], 'client_id' => $this->destination->id, 'status' => 'pending']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'delivery.enqueued']);
    }

    public function test_opt_in_confirmations_and_consent_are_required(): void
    {
        try { $this->plan(['quantity_mode' => 'all', 'confirmed_send_all' => false]); $this->fail('Send All requires confirmation.'); } catch (ValidationException $e) { $this->assertArrayHasKey('confirmed_send_all', $e->errors()); }
        try { $this->plan(['daily_enabled' => false, 'confirmed_unlimited' => false]); $this->fail('Unlimited requires confirmation.'); } catch (ValidationException $e) { $this->assertArrayHasKey('confirmed_unlimited', $e->errors()); }
        $plan = $this->plan();
        $this->expectException(ValidationException::class);
        $this->service()->enqueueClientLeads($plan, $this->leads(1), false, $this->admin);
    }
}
