<?php

namespace Tests\Feature\Leads;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadDeliveryScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function deliveryFixture(int $limit = 1, int $interval = 0, string $timezone = 'UTC'): array
    {
        $org = \App\Models\Organization::create(['name' => 'Delivery fixture', 'status' => 'active', 'delivery_timezone' => $timezone]);
        $owner = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $project = Project::create(['name' => 'Delivery fixture project', 'client_id' => $owner->id, 'status' => 'active', 'delivery_daily_limit' => $limit, 'delivery_interval_minutes' => $interval]);
        $source = LeadSource::create(['name' => 'Fixture source']);
        $campaign = Campaign::create(['name' => 'Fixture campaign', 'project_id' => $project->id, 'lead_source_id' => $source->id, 'status' => 'active']);
        \App\Models\CreditWallet::create(['client_id' => $owner->id, 'balance' => 1000, 'opening_balance' => 1000]);
        return [$org, $owner, $project, $campaign];
    }

    private function queuedLead(Campaign $campaign): Lead
    {
        return Lead::create(['lead_code' => Lead::generateUniqueLeadCode(), 'lead_source_id' => $campaign->lead_source_id,
            'campaign_id' => $campaign->id, 'client_id' => Project::withoutGlobalScopes()->findOrFail($campaign->project_id)->client_id,
            'name' => 'Queue fixture', 'mobile' => '9000000003', 'status' => 'new', 'current_stage' => 'new']);
    }

    public function test_inherited_campaigns_have_independent_daily_capacity(): void
    {
        [$org, $owner, $project, $campaign] = $this->deliveryFixture();
        $other = $campaign->replicate(); $other->name = 'Second campaign'; $other->save();
        $service = app(\App\Services\LeadDistributionService::class);
        $first = $this->queuedLead($campaign);
        $second = $this->queuedLead($other);
        $this->assertTrue($service->releaseDelivery($first->id));
        $this->assertTrue($service->releaseDelivery($second->id));
        $this->assertSame(1, $service->deliveryStatistics($project, $campaign)['today']);
        $this->assertSame(2, $service->deliveryStatistics($project)['today']);
        $this->assertSame(0, $service->deliveryStatistics($project)['remaining']);
        $next = $this->queuedLead($campaign);
        $this->assertTrue($next->delivery_at->isFuture());
    }

    public function test_intervals_and_statistics_use_successful_delivery_records(): void
    {
        [$org, $owner, $project, $campaign] = $this->deliveryFixture(2, 30);
        $service = app(\App\Services\LeadDistributionService::class);
        $first = $this->queuedLead($campaign);
        $this->assertTrue($service->releaseDelivery($first->id));
        $second = $this->queuedLead($campaign);
        $this->assertFalse($service->releaseDelivery($second->id));
        $stats = $service->deliveryStatistics($project, $campaign);
        $this->assertSame(2, $stats['total']); $this->assertSame(1, $stats['today']);
        $this->assertSame(1, $stats['remaining']); $this->assertSame(1, $stats['pending']);
        $this->assertSame(0, $stats['failed']);
        $this->assertSame($second->delivery_at->toDateTimeString(), $stats['next']);
        $this->assertSame($first->fresh()->delivered_at->toDateTimeString(), $stats['last']);
        $this->travel(30)->minutes();
        $this->assertTrue($service->releaseDelivery($second->id));
        $this->assertSame(0, $service->deliveryStatistics($project, $campaign)['remaining']);
        $this->assertNull($service->deliveryStatistics($project, $campaign)['next']);
    }

    public function test_credit_event_failure_rolls_back_wallet_and_records_failure_without_capacity_usage(): void
    {
        [$org, $owner, $project, $campaign] = $this->deliveryFixture();
        $lead = $this->queuedLead($campaign);
        \Illuminate\Support\Facades\Event::listen(\App\Events\CreditReserved::class, function () { throw new \RuntimeException('Simulated delivery failure'); });
        $service = app(\App\Services\LeadDistributionService::class);
        $this->assertFalse($service->releaseDelivery($lead->id));
        $this->assertEquals(1000, \App\Models\CreditWallet::where('client_id', $owner->id)->value('balance'));
        $this->assertSame(0, \App\Models\CreditTransaction::withoutGlobalScopes()->where('lead_id', $lead->id)->count());
        $stats = $service->deliveryStatistics($project, $campaign);
        $this->assertSame(0, $stats['today']); $this->assertSame(1, $stats['failed']); $this->assertSame(1, $stats['remaining']);
    }

    public function test_ingestion_event_delivers_once_and_records_only_one_assignment(): void
    {
        [$org, $owner, $project, $campaign] = $this->deliveryFixture(0, 0);
        $org->update(['auto_assign_enabled' => true, 'distribution_method' => 'round_robin']);
        $executive = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'sales-executive', 'guard_name' => 'web']); $executive->assignRole('sales-executive');
        \Illuminate\Support\Facades\Notification::fake();
        $lead = $this->queuedLead($campaign);
        event(new \App\Events\LeadCreated($lead));
        event(new \App\Events\LeadCreated($lead->fresh()));
        $this->assertNotNull($lead->fresh()->delivered_at);
        $this->assertFalse($lead->fresh()->delivery_pending);
        $this->assertEquals($executive->id, $lead->fresh()->assigned_to);
        $this->assertSame(1, \App\Models\LeadAssignment::where('lead_id', $lead->id)->count());
        $this->assertSame(1, \App\Models\CreditTransaction::withoutGlobalScopes()->where('lead_id', $lead->id)->count());
        \Illuminate\Support\Facades\Notification::assertSentToTimes($executive, \App\Notifications\LeadAssignedNotification::class, 1);
    }

    public function test_campaign_from_another_organization_cannot_receive_the_lead(): void
    {
        [$org, $owner, $project, $campaign] = $this->deliveryFixture();
        $stranger = User::factory()->create();
        $this->expectException(\DomainException::class);
        Lead::create(['lead_code' => Lead::generateUniqueLeadCode(), 'client_id' => $stranger->id, 'campaign_id' => $campaign->id,
            'lead_source_id' => $campaign->lead_source_id, 'name' => 'Wrong tenant', 'mobile' => '9999999999', 'status' => 'new', 'current_stage' => 'new']);
    }

    public function test_legacy_queue_recipient_mismatch_is_blocked_before_credit_reservation(): void
    {
        [$org, $owner, $project, $campaign] = $this->deliveryFixture();
        $lead = $this->queuedLead($campaign);
        $stranger = User::factory()->create();
        \Illuminate\Support\Facades\DB::table('leads')->where('id', $lead->id)->update(['client_id' => $stranger->id]);
        $this->assertFalse(app(\App\Services\LeadDistributionService::class)->releaseDelivery($lead->id));
        $this->assertNull($lead->fresh()->delivered_at);
        $this->assertNotNull($lead->fresh()->delivery_error);
        $this->assertEquals(1000, \App\Models\CreditWallet::where('client_id', $owner->id)->value('balance'));
    }

    public function test_replayed_campaignless_event_cannot_retimestamp_or_recharge_successful_delivery(): void
    {
        [$org, $owner, $project, $campaign] = $this->deliveryFixture();
        $lead = Lead::create(['lead_code' => Lead::generateUniqueLeadCode(), 'client_id' => $owner->id,
            'lead_source_id' => $campaign->lead_source_id, 'name' => 'Manual event replay', 'mobile' => '9999999988', 'status' => 'new', 'current_stage' => 'new']);
        event(new \App\Events\LeadCreated($lead));
        $deliveredAt = $lead->fresh()->delivered_at;
        $this->assertNotNull($deliveredAt);
        $this->travel(1)->days();
        event(new \App\Events\LeadCreated($lead));
        $this->assertTrue($deliveredAt->equalTo($lead->fresh()->delivered_at));
        $this->assertSame(1, \App\Models\CreditTransaction::withoutGlobalScopes()->where('lead_id', $lead->id)->count());
    }

    public function test_failed_and_rejected_deliveries_do_not_consume_capacity(): void
    {
        [$org, $owner, $project, $campaign] = $this->deliveryFixture();
        \App\Models\CreditWallet::where('client_id', $owner->id)->update(['balance' => 0]);
        $service = app(\App\Services\LeadDistributionService::class);
        $failed = $this->queuedLead($campaign);
        $this->assertFalse($service->releaseDelivery($failed->id));
        $stats = $service->deliveryStatistics($project, $campaign);
        $this->assertSame(0, $stats['today']);
        $this->assertSame(1, $stats['failed']);
        $this->assertSame(1, $stats['remaining']);
        $this->assertNotNull($failed->fresh()->delivery_error);
        $failed->update(['status' => 'rejected', 'current_stage' => 'rejected']);
        $this->assertFalse($service->releaseDelivery($failed->id));
        $this->assertTrue($failed->fresh()->delivery_pending);
        $next = $this->queuedLead($campaign);
        $this->assertTrue($next->delivery_at->lte(now()));
        \App\Models\CreditWallet::where('client_id', $owner->id)->update(['balance' => 1000]);
        $this->assertTrue($service->releaseDelivery($next->id));
        $this->assertSame(1, $service->deliveryStatistics($project, $campaign)['today']);
    }

    public function test_timezone_midnight_resets_daily_capacity(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-13 04:55:00', 'UTC')->setTimezone(config('app.timezone')));
        [$org, $owner, $project, $campaign] = $this->deliveryFixture(1, 0, 'America/New_York');
        $service = app(\App\Services\LeadDistributionService::class);
        $first = $this->queuedLead($campaign);
        $this->assertTrue($service->releaseDelivery($first->id));
        $next = $this->queuedLead($campaign);
        $this->assertEquals('2026-11-13 05:00', $next->delivery_at->utc()->format('Y-m-d H:i'));
        $this->travel(10)->minutes();
        $this->assertSame(0, $service->deliveryStatistics($project, $campaign)['today']);
        $this->assertTrue($service->releaseDelivery($next->id));
        $this->assertSame(1, $service->deliveryStatistics($project, $campaign)['today']);
    }

    public function test_zero_overrides_are_unlimited_and_immediate_and_project_pause_holds_delivery(): void
    {
        [$org, $owner, $project, $campaign] = $this->deliveryFixture(1, 120);
        $campaign->update(['delivery_daily_limit' => 0, 'delivery_interval_minutes' => 0]);
        $service = app(\App\Services\LeadDistributionService::class);
        $first = $this->queuedLead($campaign);
        $second = $this->queuedLead($campaign);
        $project->update(['delivery_enabled' => false]);
        $this->assertFalse($service->releaseDelivery($first->id));
        $this->assertNull($first->fresh()->delivery_failed_at);
        $project->update(['delivery_enabled' => true]);
        $this->assertTrue($service->releaseDelivery($first->id));
        $this->assertTrue($service->releaseDelivery($second->id));
        $this->assertSame(2, $service->deliveryStatistics($project, $campaign)['today']);
        $this->assertNull($service->deliveryStatistics($project, $campaign)['remaining']);
        $this->assertEquals(0, $second->delivery_config['interval']);
    }

    public function test_saved_queue_configuration_is_preserved_after_project_changes(): void
    {
        [$org, $owner, $project, $campaign] = $this->deliveryFixture(2, 0);
        $first = $this->queuedLead($campaign);
        $second = $this->queuedLead($campaign);
        $scheduled = $second->delivery_at->toDateTimeString();
        $project->update(['delivery_daily_limit' => 1, 'delivery_interval_minutes' => 60]);
        $service = app(\App\Services\LeadDistributionService::class);
        $this->assertSame(1, $service->effectiveDeliveryConfig($campaign, $project)['limit']);
        $this->assertEquals(2, $second->fresh()->delivery_config['limit']);
        $this->assertSame($scheduled, $second->fresh()->delivery_at->toDateTimeString());
        $this->assertTrue($service->releaseDelivery($first->id));
        $this->assertTrue($service->releaseDelivery($second->id));
        $next = $this->queuedLead($campaign);
        $this->assertEquals(1, $next->delivery_config['limit']);
        $this->assertTrue($next->delivery_at->isFuture());
    }

    public function test_campaign_settings_and_inheritance_save_reload_and_validate(): void
    {
        [$org, $owner, $project, $campaign] = $this->deliveryFixture(5, 30);
        $admin = User::factory()->create();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']); $admin->assignRole('Super Admin');
        $key = 'campaign_'.$campaign->id;
        $component = \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\OrganizationDetail::class, ['organization' => $org])
            ->call('setTab', 'campaigns')->assertSee('Project default')
            ->set("deliverySettings.$key.inherit_limit", false)->set("deliverySettings.$key.limit", 0)
            ->set("deliverySettings.$key.inherit_interval", false)->set("deliverySettings.$key.interval", 10)
            ->call('saveDeliverySettings', 'campaign', $campaign->id)->assertHasNoErrors()->assertSee('Campaign override');
        $this->assertEquals(0, $campaign->fresh()->delivery_daily_limit);
        $this->assertEquals(10, $campaign->fresh()->delivery_interval_minutes);
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\OrganizationDetail::class, ['organization' => $org])
            ->assertSet("deliverySettings.$key.limit", 0)->assertSet("deliverySettings.$key.interval", 10);
        $component->set("deliverySettings.$key.inherit_limit", true)->set("deliverySettings.$key.inherit_interval", true)
            ->call('saveDeliverySettings', 'campaign', $campaign->id)->assertHasNoErrors();
        $this->assertNull($campaign->fresh()->delivery_daily_limit);
        $project->update(['delivery_daily_limit' => 7, 'delivery_interval_minutes' => 40]);
        $config = app(\App\Services\LeadDistributionService::class)->effectiveDeliveryConfig($campaign->fresh(), $project->fresh());
        $this->assertSame(7, $config['limit']); $this->assertSame(40, $config['interval']);
        $projectKey = 'project_'.$project->id;
        $component->set("deliverySettings.$projectKey.limit", -1)->call('saveDeliverySettings', 'project', $project->id)
            ->assertHasErrors(["deliverySettings.$projectKey.limit"]);
        $component->set("deliverySettings.$projectKey.limit", 0)->set("deliverySettings.$projectKey.interval", 0)
            ->set("deliverySettings.$projectKey.enabled", false)->call('saveDeliverySettings', 'project', $project->id)->assertHasNoErrors();
        $this->assertEquals(0, $project->fresh()->delivery_daily_limit);
        $this->assertFalse((bool) $project->fresh()->delivery_enabled);
        $component->set('deliveryTimezone', 'Invalid/Zone')->call('saveDeliveryTimezone')->assertHasErrors(['deliveryTimezone']);
        $component->set('deliveryTimezone', 'Asia/Kolkata')->call('saveDeliveryTimezone')->assertHasNoErrors();
        $this->assertSame('Asia/Kolkata', $org->fresh()->delivery_timezone);
    }

    public function test_daily_limit_and_interval_queue_leads_and_roll_over(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $user = User::factory()->create();
        $source = LeadSource::create(['name' => 'Schedule test']);
        $project = Project::create(['name' => 'Scheduled project', 'client_id' => $user->id, 'status' => 'active', 'delivery_daily_limit' => 2, 'delivery_interval_minutes' => 30]);
        $campaign = Campaign::create(['name' => 'Scheduled campaign', 'project_id' => $project->id, 'lead_source_id' => $source->id, 'status' => 'active']);
        $create = fn () => Lead::create(['lead_code' => Lead::generateUniqueLeadCode(), 'name' => 'Queued lead', 'mobile' => '9000000000', 'client_id' => $user->id, 'campaign_id' => $campaign->id, 'lead_source_id' => $source->id, 'status' => 'new', 'current_stage' => 'new']);
        $first = $create();
        $second = $create();
        $third = $create();
        $this->assertTrue($first->delivery_pending);
        $this->assertTrue($first->delivery_at->equalTo(now()));
        $this->assertTrue($second->delivery_at->equalTo(now()->addMinutes(30)));
        $this->assertTrue($third->delivery_at->equalTo(now()->addDay()->startOfDay()));
        $this->assertFalse(app(\App\Services\LeadDistributionService::class)->releaseDelivery($second->id));

        $campaign->update(['delivery_daily_limit' => 1, 'delivery_interval_minutes' => 60]);
        $fourth = $create();
        $this->assertTrue($fourth->delivery_at->equalTo(now()->addDays(2)->startOfDay()));
        $this->assertEquals(2, $first->delivery_config['limit']);
        $this->assertEquals(1, $fourth->delivery_config['limit']);

        \App\Models\CreditWallet::create(['client_id' => $user->id, 'balance' => 1000, 'opening_balance' => 1000]);
        $this->travel(2)->days();
        $service = app(\App\Services\LeadDistributionService::class);
        $this->assertTrue($service->releaseDelivery($first->id));
        $this->assertFalse($service->releaseDelivery($second->id), 'Overdue leads must still respect the delivery interval.');
    }

    public function test_unconfigured_leads_remain_immediately_available(): void
    {
        $user = User::factory()->create();
        $source = LeadSource::create(['name' => 'Immediate test']);
        $lead = Lead::create(['lead_code' => Lead::generateUniqueLeadCode(), 'lead_source_id' => $source->id, 'name' => 'Immediate lead', 'mobile' => '9000000001', 'client_id' => $user->id, 'status' => 'new', 'current_stage' => 'new']);
        $this->assertFalse($lead->fresh()->delivery_pending);
        $this->assertNull($lead->delivery_at);
    }

    public function test_held_leads_are_hidden_and_released_only_once_with_credit(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'builder', 'guard_name' => 'web']);
        $user->assignRole('builder');
        $source = LeadSource::create(['name' => 'Release test']);
        $lead = Lead::create(['lead_code' => Lead::generateUniqueLeadCode(), 'lead_source_id' => $source->id, 'name' => 'Release lead', 'mobile' => '9000000002', 'client_id' => $user->id, 'status' => 'new', 'current_stage' => 'new', 'delivery_pending' => true, 'delivery_at' => now()]);
        \App\Models\CreditWallet::create(['client_id' => $user->id, 'balance' => 1000, 'opening_balance' => 1000]);
        $this->actingAs($user);
        $this->assertFalse(Lead::panelQuery()->whereKey($lead->id)->exists());
        $service = app(\App\Services\LeadDistributionService::class);
        $this->assertTrue($service->releaseDelivery($lead->id));
        $this->assertTrue(Lead::panelQuery()->whereKey($lead->id)->exists());
        $balance = \App\Models\CreditWallet::where('client_id', $user->id)->value('balance');
        $this->assertFalse($service->releaseDelivery($lead->id));
        $this->assertEquals($balance, \App\Models\CreditWallet::where('client_id', $user->id)->value('balance'));
    }

    public function test_admin_can_save_scoped_delivery_settings_and_render_campaign_tab(): void
    {
        $org = \App\Models\Organization::create(['name' => 'Delivery org', 'status' => 'active']);
        $owner = User::factory()->create(['organization_id' => $org->id]);
        $admin = User::factory()->create();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin->assignRole('Super Admin');
        $project = Project::create(['name' => 'Delivery project', 'client_id' => $owner->id, 'status' => 'active']);
        $source = LeadSource::create(['name' => 'UI test']);
        Campaign::create(['name' => 'Delivery campaign', 'project_id' => $project->id, 'lead_source_id' => $source->id, 'status' => 'active']);
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\OrganizationDetail::class, ['organization' => $org])
            ->call('setTab', 'campaigns')->assertSee('Delivery campaign')
            ->set('deliverySettings.project_'.$project->id.'.limit', 5)
            ->set('deliverySettings.project_'.$project->id.'.interval', 30)
            ->call('saveDeliverySettings', 'project', $project->id)->assertHasNoErrors()->assertDispatched('toast');
        $this->assertEquals(5, $project->fresh()->delivery_daily_limit);
        $other = Project::create(['name' => 'Other project', 'client_id' => $admin->id, 'status' => 'active']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\OrganizationDetail::class, ['organization' => $org])
            ->call('saveDeliverySettings', 'project', $other->id);
    }
}
