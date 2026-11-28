<?php

namespace Tests\Feature\Leads;

use App\Events\LeadCreated;
use App\Jobs\ProcessInboundLeadJob;
use App\Models\Campaign;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Lead;
use App\Models\LeadDeliveryItem;
use App\Models\LeadDeliveryPlan;
use App\Models\LeadFilterRule;
use App\Models\LeadMetadata;
use App\Models\LeadSource;
use App\Models\Organization;
use App\Models\PortalAccount;
use App\Models\Project;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\LeadDistributionService;
use App\Services\LeadFilterRuleEngine;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadDistributionRentExclusionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $platformOrg;
    private Organization $developerOrg;
    private User $developer;
    private Project $project;
    private Campaign $campaign;
    private LeadSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->platformOrg = Organization::create([
            'name' => 'Lead Panther Platform',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create([
            'id' => 1,
            'organization_id' => $this->platformOrg->id,
            'name' => 'Super Admin',
            'email' => 'admin@leadpanther.com',
            'status' => 'active',
        ]);
        $this->admin->assignRole('Super Admin');

        $this->developerOrg = Organization::create([
            'name' => 'Truegrowth Realty',
            'status' => 'active',
        ]);

        $this->developer = User::factory()->create([
            'organization_id' => $this->developerOrg->id,
            'name' => 'Truegrowth Realty',
            'email' => 'truegrowth@leadpanther.com',
            'status' => 'active',
        ]);
        $this->developer->assignRole('Builder/Developer');

        // Create Wallet for developer
        CreditWallet::create([
            'client_id' => $this->developer->id,
            'balance' => 100,
            'reconciled_at' => now(),
        ]);

        // Developer owns the project
        $this->project = Project::create([
            'client_id' => $this->developer->id,
            'name' => 'Studio Buy Leads',
            'status' => 'active',
        ]);

        $this->campaign = Campaign::create([
            'project_id' => $this->project->id,
            'name' => 'Studio Noida Exp 50 – 2 gulmohar',
            'status' => 'active',
        ]);

        $this->source = LeadSource::firstOrCreate(['name' => 'meta']);

        // Create "Buy Leads Only" filter rule on Developer Org
        LeadFilterRule::create([
            'organization_id' => $this->developerOrg->id,
            'name' => 'Buy Leads Only',
            'action' => 'include',
            'group_logic' => 'OR',
            'conditions' => [
                'group_logic' => 'OR',
                'groups' => [
                    [
                        'logic' => 'AND',
                        'conditions' => [
                            [
                                'field' => 'want_to_buy_studio',
                                'operator' => 'equals',
                                'value' => 'buy',
                            ],
                        ],
                    ],
                ],
            ],
            'is_active' => true,
            'priority' => 100,
        ]);
    }

    public function test_rent_lead_is_not_allocated_to_developer_and_stays_in_master_pool(): void
    {
        // Simulate Meta Webhook Payload for Rent lead
        $payload = [
            'id' => 'lead_rent_001',
            'created_time' => now()->toIso8601String(),
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Vikas']],
                ['name' => 'phone_number', 'values' => ['+918571089707']],
                ['name' => 'want_to_buy_studio__', 'values' => ['rent']],
            ],
            'campaign_id' => '52591282486875',
            'campaign_name' => 'Studio Noida Exp 50 – 2 gulmohar',
            'form_id' => '1008694135580840',
        ];

        $page = PortalAccount::create([
            'type' => 'meta',
            'name' => 'Studio Facebook Page',
            'status' => 'active',
        ]);
        $page->setCredential('default_client_id', (string)$this->admin->id);

        $log = WebhookLog::create([
            'portal_account_id' => $page->id,
            'payload' => json_encode($payload),
            'received_at' => now(),
            'processed' => false,
        ]);

        // Process inbound lead job
        (new ProcessInboundLeadJob($log))->handle();

        $log->refresh();
        $this->assertTrue((bool)$log->processed);

        // Find the lead created
        $lead = Lead::where('mobile', 'like', '%8571089707%')->first();
        $this->assertNotNull($lead, 'Lead must be saved in the database rather than discarded.');

        // Must remain in Master Lead Pool under default/platform client (ID 1)
        $this->assertSame($this->admin->id, (int)$lead->client_id, 'Rent lead must remain in Master Lead Pool under admin/platform.');
        $this->assertNull($lead->delivered_at, 'Rent lead must not have delivered_at set.');
        $this->assertFalse((bool)$lead->credit_delivery_confirmed, 'Credit delivery must not be confirmed.');

        // Verify Developer Panel does NOT show this lead
        $developerLeads = Lead::panelQuery($this->developer)->where('id', $lead->id)->exists();
        $this->assertFalse($developerLeads, 'Rent lead must NOT appear in Developer Panel.');

        // Verify no credits deducted from Developer
        $wallet = CreditWallet::where('client_id', $this->developer->id)->first();
        $this->assertSame(100, (int)$wallet->balance, 'No credits should be deducted for rent lead.');
        $this->assertFalse(CreditTransaction::where('client_id', $this->developer->id)->where('lead_id', $lead->id)->exists());
    }

    public function test_buy_lead_with_active_plan_is_allocated_and_visible_in_builder_panel(): void
    {
        $payload = [
            'id' => 'lead_buy_001',
            'created_time' => now()->toIso8601String(),
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Pankaj Verma']],
                ['name' => 'phone_number', 'values' => ['+917351198894']],
                ['name' => 'want_to_buy_studio__', 'values' => ['buy']],
            ],
            'campaign_id' => '52591282486875',
            'campaign_name' => 'Studio Noida Exp 50 – 2 gulmohar',
            'form_id' => '1008694135580840',
        ];

        $page = PortalAccount::create([
            'type' => 'meta',
            'name' => 'Studio Facebook Page',
            'status' => 'active',
        ]);
        $page->setCredential('default_client_id', (string)$this->admin->id);

        // Map form directly to developer client
        \App\Models\LeadFormMapping::create([
            'portal_account_id' => $page->id,
            'form_id' => '1008694135580840',
            'form_name' => 'Studio Apartments',
            'client_id' => $this->developer->id,
            'project_id' => $this->project->id,
            'campaign_id' => $this->campaign->id,
        ]);

        $log = WebhookLog::create([
            'portal_account_id' => $page->id,
            'payload' => json_encode($payload),
            'received_at' => now(),
            'processed' => false,
        ]);

        (new ProcessInboundLeadJob($log))->handle();

        $log->refresh();
        $this->assertTrue((bool)$log->processed);

        $lead = Lead::where('mobile', 'like', '%7351198894%')->first();
        $this->assertNotNull($lead);
        $this->assertSame($this->developer->id, (int)$lead->client_id);
        $this->assertTrue((bool)$lead->credit_delivery_confirmed);

        // Verify Developer Panel DOES show this Buy lead
        $developerLeads = Lead::panelQuery($this->developer)->where('id', $lead->id)->exists();
        $this->assertTrue($developerLeads, 'Buy lead must appear in Developer Panel.');
    }

    public function test_client_delivery_engine_skips_rent_leads_for_buy_only_clients(): void
    {
        // Create an unallocated rent lead in Master Lead Pool
        $rentLead = Lead::create([
            'lead_code' => 'LP-POOL-RENT-01',
            'client_id' => $this->admin->id,
            'campaign_id' => $this->campaign->id,
            'lead_source_id' => $this->source->id,
            'name' => 'Pool Renter',
            'mobile' => '+919876543201',
            'status' => 'new',
            'current_stage' => 'new',
            'delivered_at' => null,
            'delivery_pending' => false,
        ]);
        LeadMetadata::create([
            'lead_id' => $rentLead->id,
            'key' => 'meta_form_answers',
            'value' => json_encode(['want_to_buy_studio' => 'rent']),
        ]);

        // Developer delivery plan
        $plan = LeadDeliveryPlan::create([
            'organization_id' => $this->developerOrg->id,
            'client_id' => $this->developer->id,
            'project_id' => $this->project->id,
            'campaign_id' => $this->campaign->id,
            'state' => 'active',
            'settings' => [
                'frequency' => 'immediate',
                'automatic' => true,
                'quantity_mode' => 'all',
                'daily_enabled' => false,
                'timezone' => 'Asia/Kolkata',
                'window_start' => '00:00',
                'window_end' => '23:59',
                'daily_time' => '09:00',
                'weekly_day' => 1,
                'working_days' => [0, 1, 2, 3, 4, 5, 6],
            ],
            'created_by' => $this->admin->id,
        ]);

        // Attempt to auto-enroll leads into Developer plan
        $service = app(LeadDistributionService::class);
        $reflection = new \ReflectionClass($service);
        $enrollMethod = $reflection->getMethod('enrollAutomaticClientDelivery');
        $enrollMethod->setAccessible(true);
        $enrollMethod->invoke($service, $plan);

        // Verify rent lead was NOT enrolled
        $this->assertFalse(
            LeadDeliveryItem::where('plan_id', $plan->id)->where('lead_id', $rentLead->id)->exists(),
            'Rent lead must not be enrolled into buy-only client delivery plan.'
        );
    }

    public function test_reconciliation_seeder_reclaims_rent_leads_and_refunds_credits(): void
    {
        // Simulate a rent lead that was previously delivered to the developer
        $misdeliveredRentLead = Lead::create([
            'lead_code' => 'LP-2026-00002392',
            'client_id' => $this->developer->id,
            'campaign_id' => $this->campaign->id,
            'lead_source_id' => $this->source->id,
            'name' => 'Vikas',
            'mobile' => '918571089707',
            'status' => 'assigned',
            'current_stage' => 'assigned',
            'assigned_to' => $this->developer->id,
            'delivered_at' => now(),
            'delivery_pending' => false,
            'credit_delivery_confirmed' => true,
        ]);

        LeadMetadata::create([
            'lead_id' => $misdeliveredRentLead->id,
            'key' => 'meta_form_answers',
            'value' => json_encode(['want_to_buy_studio__' => 'rent']),
        ]);

        // Create initial consumption transaction & debit wallet
        $wallet = CreditWallet::where('client_id', $this->developer->id)->first();
        $wallet->update(['balance' => 99]);

        CreditTransaction::create([
            'client_id' => $this->developer->id,
            'lead_id' => $misdeliveredRentLead->id,
            'credit_before' => 100,
            'credit_used' => 1,
            'credit_after' => 99,
            'transaction_type' => 'lead_consumption',
            'description' => 'Lead delivery: LP-2026-00002392',
            'is_legacy' => false,
            'created_at' => now(),
        ]);

        $plan = LeadDeliveryPlan::create([
            'organization_id' => $this->developerOrg->id,
            'client_id' => $this->developer->id,
            'project_id' => $this->project->id,
            'campaign_id' => $this->campaign->id,
            'state' => 'active',
            'settings' => [
                'frequency' => 'immediate',
                'automatic' => true,
                'quantity_mode' => 'all',
                'daily_enabled' => false,
                'timezone' => 'Asia/Kolkata',
                'window_start' => '00:00',
                'window_end' => '23:59',
                'daily_time' => '09:00',
                'weekly_day' => 1,
                'working_days' => [0, 1, 2, 3, 4, 5, 6],
            ],
            'created_by' => $this->admin->id,
        ]);

        $deliveryItem = LeadDeliveryItem::create([
            'plan_id' => $plan->id,
            'source_client_id' => 1,
            'source_campaign_id' => $this->campaign->id,
            'client_id' => $this->developer->id,
            'project_id' => $this->project->id,
            'campaign_id' => $this->campaign->id,
            'lead_id' => $misdeliveredRentLead->id,
            'requested_by' => $this->admin->id,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // Run the reconciliation seeder
        (new \Database\Seeders\ReconcileRentLeadsFromDeveloperPanelsSeeder())->run();

        // 1. Lead must be moved back to Master Lead Pool (client_id = 1)
        $misdeliveredRentLead->refresh();
        $this->assertSame($this->admin->id, (int)$misdeliveredRentLead->client_id);
        $this->assertNull($misdeliveredRentLead->delivered_at);
        $this->assertFalse((bool)$misdeliveredRentLead->credit_delivery_confirmed);
        $this->assertNull($misdeliveredRentLead->assigned_to);

        // 2. Credit must be refunded to developer wallet (restored to 100)
        $wallet->refresh();
        $this->assertSame(100, (int)$wallet->balance);

        $refundTx = CreditTransaction::where('client_id', $this->developer->id)
            ->where('lead_id', $misdeliveredRentLead->id)
            ->where('transaction_type', 'refund')
            ->first();
        $this->assertNotNull($refundTx);
        $this->assertSame(1, (int)$refundTx->credit_used);
        $this->assertSame(100, (int)$refundTx->credit_after);

        // 3. Delivery item must be marked revoked
        $deliveryItem->refresh();
        $this->assertSame('revoked', $deliveryItem->status);

        // 4. PanelQuery for developer must NOT show the lead
        $this->assertFalse(Lead::panelQuery($this->developer)->where('id', $misdeliveredRentLead->id)->exists());

        // 5. Test idempotency: Running again does NOT duplicate refund or change state
        (new \Database\Seeders\ReconcileRentLeadsFromDeveloperPanelsSeeder())->run();
        $wallet->refresh();
        $this->assertSame(100, (int)$wallet->balance);
        $this->assertSame(1, CreditTransaction::where('client_id', $this->developer->id)->where('lead_id', $misdeliveredRentLead->id)->where('transaction_type', 'refund')->count());
    }
}
