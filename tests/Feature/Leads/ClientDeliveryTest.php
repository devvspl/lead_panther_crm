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

    protected function setUp(): void
    {
        parent::setUp();
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

    public function test_new_intake_waits_for_consent_and_manual_release(): void
    {
        $plan = $this->plan(['frequency' => 'manual', 'automatic' => false]);
        $ids = $this->leads(2);
        $this->assertSame(2, $plan->items()->where('status', 'pending')->count());
        $this->assertFalse($this->service()->releaseDelivery($ids[0]));
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id));
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id, true));
        $this->assertSame(['queued' => 2, 'skipped' => 0], $this->service()->enqueueClientLeads($plan, $ids, true, $this->admin));
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

    public function test_rejected_internal_and_unconsented_leads_cannot_be_sent(): void
    {
        $plan = $this->plan(['quantity_mode' => 'all', 'daily_enabled' => false]);
        $ids = $this->enroll($plan, 3);
        Lead::withoutGlobalScopes()->find($ids[0])->update(['status' => 'invalid']);
        Lead::withoutGlobalScopes()->find($ids[1])->update(['internal_processing_pending' => true]);
        Lead::withoutGlobalScopes()->find($ids[2])->update(['delivery_consent_at' => null]);
        $this->cycle($plan);
        $this->assertSame(0, $plan->items()->where('status', 'sent')->count());
        $this->assertSame(2, $plan->items()->where('status', 'failed')->count());
        $this->assertSame(1, $plan->items()->where('status', 'pending')->count());
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

    public function test_empty_cycle_opens_existing_leads_for_confirmation_then_the_client_sees_delivery(): void
    {
        Queue::fake();
        $ids = $this->leads(2); // Existing intake predates the saved plan, as in organization 38.
        $plan = $this->plan(['frequency' => 'immediate']);
        $this->assertSame(0, $plan->items()->count());
        $component = Livewire::test(ClientDeliveryCenter::class, ['organizationId' => $this->source->organization_id])
            ->assertSee('Push Existing Leads')->call('runNow')
            ->assertSet('showConfiguration', true)->assertSet('selectedLeadIds', $ids);
        $this->assertSame(0, $plan->items()->count()); // Opening the confirmation does not share data or charge.
        $component->call('pushLeads')->assertHasNoErrors();
        $run = $plan->runs()->firstOrFail();
        (new \App\Jobs\ProcessClientDeliveryRun($run->id))->handle($this->service());
        $this->actingAs(User::findOrFail($this->destination->id));
        $this->assertSame(2, Lead::panelQuery()->whereIn('id', $ids)->count());
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
