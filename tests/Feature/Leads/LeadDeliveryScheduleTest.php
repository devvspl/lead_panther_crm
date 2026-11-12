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

        \App\Models\CreditWallet::create(['client_id' => $user->id, 'balance' => 1000, 'opening_balance' => 1000]);
        $this->travel(2)->days();
        $service = app(\App\Services\LeadDistributionService::class);
        $this->assertTrue($service->releaseDelivery($first->id));
        $this->assertFalse($service->releaseDelivery($second->id), 'Overdue leads must still respect the delivery interval.');
        $this->travelBack();
        $this->travelTo($first->delivery_at);

        $campaign->update(['delivery_daily_limit' => 1, 'delivery_interval_minutes' => 60]);
        $fourth = $create();
        $this->assertTrue($fourth->delivery_at->equalTo(now()->addDays(2)->startOfDay()));
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
