<?php

namespace Tests\Feature\Leads;

use App\Livewire\Leads\ClientDeliveryCenter;
use App\Models\{Campaign, Client, CreditWallet, Lead, LeadDeliveryPlan, LeadSource, Organization, Project, User};
use App\Services\LeadDistributionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ClientDeliveryRandomFrequencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $sourceOrg;
    private Organization $destOrg;
    private Client $sourceClient;
    private Client $destClient;
    private Project $project;
    private Campaign $campaign;
    private Campaign $sourceCampaign;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-22 10:00:00'));
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);

        $this->sourceOrg = Organization::create(['name' => 'Source Org', 'status' => 'active', 'auto_assign_enabled' => false]);
        $this->destOrg = Organization::create(['name' => 'Destination Org', 'status' => 'active', 'auto_assign_enabled' => false]);

        $this->sourceClient = Client::create(['name' => 'Source Client', 'organization_id' => $this->sourceOrg->id, 'status' => 'active']);
        $this->destClient = Client::create(['name' => 'Destination Client', 'organization_id' => $this->destOrg->id, 'status' => 'active']);

        User::findOrFail($this->sourceClient->id)->assignRole('Builder/Developer');
        User::findOrFail($this->destClient->id)->assignRole('Builder/Developer');

        $source = LeadSource::create(['name' => 'Test Lead Source']);
        $this->project = Project::create([
            'client_id' => $this->destClient->id,
            'name' => 'Test Destination Project',
            'status' => 'active',
            'delivery_enabled' => true,
        ]);
        $this->campaign = Campaign::create([
            'project_id' => $this->project->id,
            'name' => 'Test Destination Campaign',
            'lead_source_id' => $source->id,
            'status' => 'active',
        ]);

        $sourceProject = Project::create(['client_id' => $this->sourceClient->id, 'name' => 'Source project', 'status' => 'active']);
        $this->sourceCampaign = Campaign::create(['project_id' => $sourceProject->id, 'name' => 'Source campaign', 'lead_source_id' => $source->id, 'status' => 'active']);

        CreditWallet::create(['client_id' => $this->destClient->id, 'balance' => 10000, 'opening_balance' => 10000]);
    }

    private function service(): LeadDistributionService
    {
        return app(LeadDistributionService::class);
    }

    private function settings(array $overrides = []): array
    {
        return array_merge([
            'client_id' => $this->destClient->id,
            'project_id' => $this->project->id,
            'campaign_id' => $this->campaign->id,
            'frequency' => 'random_1_30',
            'quantity_mode' => 'fixed',
            'quantity' => 1,
            'max_cycle' => null,
            'daily_enabled' => true,
            'daily_limit' => 10,
            'automatic' => true,
            'timezone' => 'UTC',
            'start_date' => null,
            'end_date' => null,
            'window_start' => '00:00',
            'window_end' => '23:59',
            'daily_time' => '10:00',
            'weekly_day' => 2,
            'working_days' => [0, 1, 2, 3, 4, 5, 6],
            'confirmed_send_all' => true,
            'confirmed_unlimited' => true,
        ], $overrides);
    }

    private function createLead(): Lead
    {
        return Lead::create([
            'client_id' => $this->sourceClient->id,
            'campaign_id' => $this->sourceCampaign->id,
            'lead_source_id' => $this->sourceCampaign->lead_source_id,
            'lead_code' => Lead::generateUniqueLeadCode(),
            'name' => 'Customer ' . Lead::withoutGlobalScopes()->count(),
            'mobile' => '9' . str_pad((string) (Lead::withoutGlobalScopes()->count() + 1), 9, '0', STR_PAD_LEFT),
            'status' => 'new',
            'current_stage' => 'new',
        ]);
    }

    public function test_saving_plan_with_random_1_30_frequency_sets_interval_and_next_run_at(): void
    {
        $plan = $this->service()->saveClientDeliveryPlan($this->sourceOrg, $this->settings(), $this->admin);

        $this->assertSame('random_1_30', $plan->settings['frequency']);
        $this->assertArrayHasKey('random_interval_minutes', $plan->settings);
        $this->assertGreaterThanOrEqual(1, $plan->settings['random_interval_minutes']);
        $this->assertLessThanOrEqual(30, $plan->settings['random_interval_minutes']);

        $this->assertNotNull($plan->next_run_at);
        $expectedNextRun = now()->addMinutes($plan->settings['random_interval_minutes']);
        $this->assertEquals($expectedNextRun->timestamp, $plan->next_run_at->timestamp);
        $this->assertSame('Random (1–30 Minutes)', $plan->frequency_label);
    }

    public function test_advancing_client_delivery_generates_new_random_interval_between_1_and_30(): void
    {
        $lead = $this->createLead();
        $plan = $this->service()->saveClientDeliveryPlan($this->sourceOrg, $this->settings(), $this->admin);
        $this->service()->enqueueClientLeads($plan, [$lead->id], true, $this->admin);

        $run = $this->service()->startClientDeliveryCycle($plan->id, true);
        $this->assertNotNull($run);

        // Process run triggers advanceClientDelivery
        $this->service()->processClientDeliveryRun($run->id);

        $plan->refresh();
        $this->assertNotNull($plan->next_run_at);
        $this->assertGreaterThan(now()->timestamp, $plan->next_run_at->timestamp);
        $this->assertLessThanOrEqual(now()->addMinutes(30)->timestamp, $plan->next_run_at->timestamp);
        $this->assertGreaterThanOrEqual(1, $plan->settings['random_interval_minutes']);
        $this->assertLessThanOrEqual(30, $plan->settings['random_interval_minutes']);
    }

    public function test_scheduler_respects_next_run_at_for_random_1_30_cycle(): void
    {
        $lead = $this->createLead();
        $plan = $this->service()->saveClientDeliveryPlan($this->sourceOrg, $this->settings(), $this->admin);
        $this->service()->enqueueClientLeads($plan, [$lead->id], true, $this->admin);

        // Before next_run_at, automatic cycle should NOT start
        $this->assertNull($this->service()->startClientDeliveryCycle($plan->id, false));

        // Fast forward to next_run_at
        Carbon::setTestNow($plan->next_run_at->copy()->addSecond());

        // Now automatic cycle should start
        $run = $this->service()->startClientDeliveryCycle($plan->id, false);
        $this->assertNotNull($run);
        $this->assertSame('queued', $run->status);
    }

    public function test_livewire_ui_renders_random_option_and_saves_successfully(): void
    {
        Queue::fake();
        $lead = $this->createLead();

        Livewire::test(ClientDeliveryCenter::class, ['organizationId' => $this->sourceOrg->id])
            ->call('openPush', $this->sourceOrg->id, [$lead->id])
            ->set('settings.client_id', $this->destClient->id)
            ->set('settings.project_id', $this->project->id)
            ->set('settings.campaign_id', $this->campaign->id)
            ->set('settings.frequency', 'random_1_30')
            ->call('pushLeads')
            ->assertHasNoErrors();

        $plan = LeadDeliveryPlan::where('organization_id', $this->sourceOrg->id)->firstOrFail();
        $this->assertSame('random_1_30', $plan->settings['frequency']);
        $this->assertNotNull($plan->next_run_at);
        $this->assertSame('Random (1–30 Minutes)', $plan->frequency_label);
    }
}
