<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Models\CreditWallet;
use App\Models\CreditTransaction;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatusHistory;
use App\Models\LeadAssignment;
use App\Models\LeadCommunication;
use App\Models\DistributionRule;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Services\LeadPricingService;
use App\Services\LeadDistributionService;
use App\Services\NotificationDeliveryService;
use App\Services\LeadJourneyService;
use App\Livewire\Admin\OrganizationDetail;
use App\Livewire\Admin\AdminCredits;
use App\Livewire\Admin\NotificationTemplates;
use App\Livewire\Credits\CreditWallet as CreditWalletComponent;
use App\Livewire\Leads\LeadTimeline;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class LeadPantherEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Organization $orgA;
    protected Organization $orgB;
    protected Client $clientA;
    protected Project $projectA;
    protected SalesTeam $teamA;
    protected User $agentA1;
    protected User $agentA2;
    protected User $agentB1;
    protected CreditWallet $walletA;
    protected LeadSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin']);
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'Sales Executive']);
        Role::firstOrCreate(['name' => 'sales-executive']);

        // 1. Create protected super admin id = 1
        $this->superAdmin = User::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Super Administrator',
                'email' => 'admin@leadpanther.com',
                'password' => bcrypt('password'),
                'status' => 'active',
            ]
        );
        $this->superAdmin->assignRole('Super Admin');

        // 2. Setup Organizations
        $this->orgA = Organization::create([
            'name' => 'Apex Real Estate',
            'type' => 'builder',
            'status' => 'active',
            'lead_cost' => 20.00,
        ]);

        $this->orgB = Organization::create([
            'name' => 'Zenith Realty',
            'type' => 'broker',
            'status' => 'active',
            'lead_cost' => 15.00,
        ]);

        // 3. Setup Client & Wallet
        $this->clientA = Client::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Apex Signature Homes',
        ]);

        $this->walletA = CreditWallet::create([
            'client_id' => $this->clientA->id,
            'opening_balance' => 200.00,
            'balance' => 200.00,
        ]);

        // 4. Setup Team & Project
        $this->teamA = SalesTeam::create([
            'ownable_type' => Client::class,
            'ownable_id' => $this->clientA->id,
            'name' => 'Apex Alpha Team',
            'lead_cost' => null,
        ]);

        $this->projectA = Project::create([
            'client_id' => $this->clientA->id,
            'name' => 'Apex Heights',
            'lead_cost' => null,
        ]);

        // 5. Setup Agents
        $this->agentA1 = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Agent A One',
            'email' => 'agent.a1@apex.com',
            'phone' => '9800000001',
            'status' => 'active',
        ]);
        $this->agentA1->assignRole('sales-executive');

        $this->agentA2 = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Agent A Two',
            'email' => 'agent.a2@apex.com',
            'phone' => '9800000002',
            'status' => 'active',
        ]);
        $this->agentA2->assignRole('sales-executive');

        $this->agentB1 = User::factory()->create([
            'organization_id' => $this->orgB->id,
            'name' => 'Agent B One',
            'email' => 'agent.b1@zenith.com',
            'phone' => '9800000003',
            'status' => 'active',
        ]);
        $this->agentB1->assignRole('sales-executive');

        // Add agents to SalesTeam
        SalesTeamMember::create([
            'sales_team_id' => $this->teamA->id,
            'user_id' => $this->agentA1->id,
            'max_capacity' => 20,
            'is_active' => true,
        ]);

        SalesTeamMember::create([
            'sales_team_id' => $this->teamA->id,
            'user_id' => $this->agentA2->id,
            'max_capacity' => 20,
            'is_active' => true,
        ]);

        $this->source = LeadSource::firstOrCreate(['name' => 'meta']);
    }

    /**
     * Test 1: Hierarchical Lead Pricing Resolution (Project -> Team -> Org -> Global Default)
     */
    public function test_hierarchical_lead_pricing_resolution(): void
    {
        $pricingService = app(LeadPricingService::class);

        // Step 1: Global default fallback when everything is null
        $this->orgA->update(['lead_cost' => null]);
        $this->teamA->update(['lead_cost' => null]);
        $this->projectA->update(['lead_cost' => null]);

        $pricingService->setGlobalDefaultCost(12.50);
        $cost1 = $pricingService->resolveCost($this->projectA, $this->teamA, $this->orgA)['cost'];
        $this->assertEquals(12.50, $cost1);

        // Step 2: Org override takes precedence over global default
        $this->orgA->update(['lead_cost' => 18.00]);
        $cost2 = $pricingService->resolveCost($this->projectA, $this->teamA, $this->orgA)['cost'];
        $this->assertEquals(18.00, $cost2);

        // Step 3: Team override takes precedence over org override
        $this->teamA->update(['lead_cost' => 24.00]);
        $cost3 = $pricingService->resolveCost($this->projectA, $this->teamA, $this->orgA)['cost'];
        $this->assertEquals(24.00, $cost3);

        // Step 4: Project override has top priority
        $this->projectA->update(['lead_cost' => 32.50]);
        $cost4 = $pricingService->resolveCost($this->projectA, $this->teamA, $this->orgA)['cost'];
        $this->assertEquals(32.50, $cost4);
    }

    /**
     * Test 2: Dynamic Lead Pricing Deduction in CreditTransaction::reserveForLead()
     */
    public function test_dynamic_lead_pricing_credit_reservation(): void
    {
        $this->projectA->update(['lead_cost' => 28.00]);

        $lead = Lead::factory()->create([
            'project_id' => $this->projectA->id,
            'client_id' => $this->clientA->id,
            'lead_source_id' => $this->source->id,
        ]);

        $success = CreditTransaction::reserveForLead($lead);
        $this->assertTrue($success);

        $tx = CreditTransaction::where('lead_id', $lead->id)->latest('id')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(28.00, (float) $tx->credit_used);
        $this->assertEquals(172.00, (float) $tx->credit_after);
        $this->assertEquals(28.00, (float) $lead->fresh()->lead_cost);
        $this->assertEquals(172.00, (float) $this->walletA->fresh()->balance);
    }

    /**
     * Test 3: Organization-Secure Lead Distribution Engine (Prevents cross-tenant leak)
     */
    public function test_organization_secure_lead_distribution_prevents_leakage(): void
    {
        $lead = Lead::factory()->create([
            'project_id' => $this->projectA->id,
            'client_id' => $this->clientA->id,
            'lead_source_id' => $this->source->id,
            'city' => 'Mumbai',
        ]);

        DistributionRule::create([
            'project_id' => $this->projectA->id,
            'rule_type' => 'location',
            'config' => [
                'location_map' => [
                    ['city' => 'Mumbai', 'user_id' => $this->agentA1->id],
                    ['city' => 'Pune', 'user_id' => $this->agentA2->id],
                ],
            ],
            'is_active' => true,
        ]);

        $distributionService = app(LeadDistributionService::class);

        // Distribute via round-robin
        $res1 = $distributionService->distribute($lead, 'round_robin');
        $assignee1 = $res1['assigned_user'];
        $this->assertNotNull($assignee1);
        $this->assertEquals($this->orgA->id, $assignee1->organization_id);
        $this->assertNotEquals($this->agentB1->id, $assignee1->id);

        // Distribute via location
        $res2 = $distributionService->distribute($lead, 'location');
        $assignee2 = $res2['assigned_user'];
        $this->assertNotNull($assignee2);
        $this->assertEquals($this->agentA1->id, $assignee2->id, 'Agent in Mumbai from Org A should match');
        $this->assertEquals($this->orgA->id, $assignee2->organization_id);

        // Distribute via capacity
        $res3 = $distributionService->distribute($lead, 'capacity');
        $assignee3 = $res3['assigned_user'];
        $this->assertNotNull($assignee3);
        $this->assertEquals($this->orgA->id, $assignee3->organization_id);

        // Verify assignment record in database
        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $assignee3->id,
        ]);
    }

    /**
     * Test 4: Multi-Channel Notification Delivery & Retrying
     */
    public function test_multi_channel_notifications_and_retries(): void
    {
        $notificationService = app(NotificationDeliveryService::class);

        $channels = ['sms', 'whatsapp', 'email', 'crm', 'push'];

        foreach ($channels as $channel) {
            $log = $notificationService->send(
                channel: $channel,
                recipient: 'test@example.com',
                templateKey: 'lead_assigned_' . $channel,
                tokens: ['lead_name' => 'John Doe', 'lead_code' => 'LP-101'],
                notifiable: $this->agentA1
            );

            $this->assertInstanceOf(NotificationLog::class, $log);
            $this->assertEquals($channel, $log->channel);
            $this->assertEquals(NotificationDeliveryService::STATUS_DELIVERED, $log->status);
        }

        // Test retry on a failed log
        $failedLog = NotificationLog::create([
            'notifiable_type' => User::class,
            'notifiable_id' => $this->agentA1->id,
            'channel' => 'sms',
            'status' => NotificationDeliveryService::STATUS_FAILED,
            'payload' => json_encode(['recipient' => '+919999999999', 'body' => 'Test retry body']),
            'retries' => 0,
            'last_error' => 'Network timeout',
            'sent_at' => now(),
        ]);

        $retriedLog = $notificationService->retry($failedLog->id);
        $this->assertEquals(1, $retriedLog->retries);
        $this->assertEquals(NotificationDeliveryService::STATUS_DELIVERED, $retriedLog->status);
    }

    /**
     * Test 5: Lead Journey Timeline Collation
     */
    public function test_lead_journey_timeline_collation(): void
    {
        $lead = Lead::factory()->create([
            'project_id' => $this->projectA->id,
            'client_id' => $this->clientA->id,
            'lead_source_id' => $this->source->id,
            'assigned_to' => $this->agentA1->id,
            'status' => 'contacted',
            'created_at' => now()->subHours(3),
        ]);

        // Add credit transaction
        CreditTransaction::create([
            'client_id' => $this->clientA->id,
            'lead_id' => $lead->id,
            'transaction_type' => 'deduct',
            'credit_before' => 200,
            'credit_used' => 20,
            'credit_after' => 180,
            'created_at' => now()->subHours(2),
        ]);

        // Add status history
        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => 'new',
            'to_status' => 'contacted',
            'changed_by' => $this->agentA1->id,
            'changed_at' => now()->subHours(1),
        ]);

        // Add communication
        LeadCommunication::create([
            'lead_id' => $lead->id,
            'client_id' => $this->clientA->id,
            'user_id' => $this->agentA1->id,
            'channel' => 'whatsapp',
            'template' => 'lead_assigned_whatsapp',
            'message' => 'Hello from Apex!',
            'status' => 'delivered',
            'sent_at' => now()->subMinutes(30),
        ]);

        $journeyService = app(LeadJourneyService::class);
        $result = $journeyService->getJourney($lead);

        $this->assertNotEmpty($result['events']);
        $types = collect($result['events'])->pluck('type')->all();

        $this->assertContains('creation', $types);
        $this->assertContains('credit', $types);
        $this->assertContains('status', $types);
        $this->assertContains('communication', $types);
    }

    /**
     * Test 6: Organization Detail - Direct Lead Tab Removed, Opening & Available Balance Visible, Pricing Editable
     */
    public function test_organization_detail_removes_direct_leads_and_shows_balances(): void
    {
        $comp = Livewire::actingAs($this->superAdmin)
            ->test(OrganizationDetail::class, ['organization' => $this->orgA]);

        // Direct leads tab cannot be set
        $comp->call('setTab', 'leads');
        $this->assertNotEquals('leads', $comp->get('activeTab'));

        // Assert opening balance and available balance are displayed
        $comp->assertSee('Opening Balance')
            ->assertSee('Available Balance')
            ->assertSee('200.00');

        // Test updating organization per-lead pricing
        $comp->set('orgLeadCost', 27.50)
            ->call('updateOrgLeadCost');

        $this->assertEquals(27.50, (float) $this->orgA->fresh()->lead_cost);
    }

    /**
     * Test 7: Super Admin Credits Settings - Global Default Per-Lead Cost Update
     */
    public function test_admin_credits_updates_global_default_lead_cost(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(AdminCredits::class)
            ->set('globalDefaultLeadCost', 14.75)
            ->call('updateGlobalDefaultCost')
            ->assertDispatched('toast');

        $this->assertEquals(14.75, app(LeadPricingService::class)->getGlobalDefaultCost());
    }

    /**
     * Test 8: Notification Templates UI - Channels, Test Dispatch and Retries
     */
    public function test_notification_templates_ui_test_dispatch(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(NotificationTemplates::class)
            ->set('testChannel', 'email')
            ->set('testRecipient', 'client@leadpanther.com')
            ->call('sendTestNotification')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('notification_logs', [
            'channel' => 'email',
            'status' => 'delivered',
        ]);
    }
}
