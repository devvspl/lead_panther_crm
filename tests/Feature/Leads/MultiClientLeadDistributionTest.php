<?php

namespace Tests\Feature\Leads;

use App\Livewire\Leads\{ClientDeliveryCenter, LeadKanban};
use App\Models\{Campaign, CreditTransaction, CreditWallet, Lead, LeadDeliveryItem, LeadDeliveryPlan, LeadDeliveryRevocation, LeadSource, Organization, Project, User};
use App\Services\LeadDistributionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultiClientLeadDistributionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $sourceClient;
    private User $clientA;
    private User $clientB;
    private Organization $sourceOrg;
    private Organization $orgA;
    private Organization $orgB;
    private Project $sourceProject;
    private Project $projectA;
    private Project $projectB;
    private LeadSource $leadSource;
    private Campaign $sourceCampaign;
    private Campaign $campaignA;
    private Campaign $campaignB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->leadSource = LeadSource::create(['name' => 'direct']);

        $this->admin = User::factory()->create(['status' => 'active', 'email' => 'superadmin@leadpanther.com']);
        $this->admin->assignRole('Super Admin');

        $this->sourceOrg = Organization::create([
            'name' => 'Source Org',
            'status' => 'active',
            'delivery_timezone' => 'Asia/Kolkata',
        ]);

        $this->orgA = Organization::create([
            'name' => 'Client A Organization',
            'status' => 'active',
            'delivery_timezone' => 'Asia/Kolkata',
        ]);

        $this->orgB = Organization::create([
            'name' => 'Client B Organization',
            'status' => 'active',
            'delivery_timezone' => 'Asia/Kolkata',
        ]);

        $this->sourceClient = User::factory()->create([
            'organization_id' => $this->sourceOrg->id,
            'status' => 'active',
            'email' => 'source@leadpanther.com',
        ]);
        $this->sourceClient->assignRole('Builder');

        $this->clientA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'status' => 'active',
            'email' => 'clienta@leadpanther.com',
        ]);
        $this->clientA->assignRole('Builder');

        $this->clientB = User::factory()->create([
            'organization_id' => $this->orgB->id,
            'status' => 'active',
            'email' => 'clientb@leadpanther.com',
        ]);
        $this->clientB->assignRole('Builder');

        $this->sourceProject = Project::withoutGlobalScopes()->create([
            'client_id' => $this->sourceClient->id,
            'name' => 'Source Residency',
            'status' => 'active',
            'delivery_enabled' => true,
        ]);

        $this->projectA = Project::withoutGlobalScopes()->create([
            'client_id' => $this->clientA->id,
            'name' => 'Project Alpha',
            'status' => 'active',
            'delivery_enabled' => true,
        ]);

        $this->projectB = Project::withoutGlobalScopes()->create([
            'client_id' => $this->clientB->id,
            'name' => 'Project Beta',
            'status' => 'active',
            'delivery_enabled' => true,
        ]);

        $this->sourceCampaign = Campaign::withoutGlobalScopes()->create([
            'project_id' => $this->sourceProject->id,
            'name' => 'Source Campaign',
            'lead_source_id' => $this->leadSource->id,
            'status' => 'active',
        ]);

        $this->campaignA = Campaign::withoutGlobalScopes()->create([
            'project_id' => $this->projectA->id,
            'name' => 'Campaign Alpha',
            'lead_source_id' => $this->leadSource->id,
            'status' => 'active',
        ]);

        $this->campaignB = Campaign::withoutGlobalScopes()->create([
            'project_id' => $this->projectB->id,
            'name' => 'Campaign Beta',
            'lead_source_id' => $this->leadSource->id,
            'status' => 'active',
        ]);

        // Wallets with reconciled credit balances
        CreditWallet::create([
            'client_id' => $this->clientA->id,
            'balance' => 500.00,
            'opening_balance' => 500.00,
            'reconciled_at' => now(),
        ]);

        CreditWallet::create([
            'client_id' => $this->clientB->id,
            'balance' => 500.00,
            'opening_balance' => 500.00,
            'reconciled_at' => now(),
        ]);
    }

    private function createSourceLead(string $code = 'LP-2026-MULTI01'): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'client_id' => $this->sourceClient->id,
            'campaign_id' => $this->sourceCampaign->id,
            'lead_source_id' => $this->leadSource->id,
            'lead_code' => $code,
            'name' => 'Customer ' . $code,
            'mobile' => '9876543210',
            'status' => 'new',
            'current_stage' => 'new',
            'delivered_at' => null,
            'credit_delivery_confirmed' => false,
            'do_not_send_to_client' => false,
        ]);
    }

    public function test_multi_client_lead_distribution_creates_separate_items_without_duplicating_lead(): void
    {
        $this->actingAs($this->admin);
        $lead = $this->createSourceLead('LP-2026-00002368');

        $configs = [
            [
                'client_id' => $this->clientA->id,
                'project_id' => $this->projectA->id,
                'campaign_id' => $this->campaignA->id,
                'rate' => 100.00,
            ],
            [
                'client_id' => $this->clientB->id,
                'project_id' => $this->projectB->id,
                'campaign_id' => $this->campaignB->id,
                'rate' => 150.00,
            ],
        ];

        $service = app(LeadDistributionService::class);
        $results = $service->distributeLeadToMultipleClients($lead, $configs, $this->admin);

        // 1. Exactly 1 original Lead record exists (no duplication)
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('lead_code', 'LP-2026-00002368')->count());

        // 2. Both client distributions succeeded
        $this->assertSame('sent', $results[$this->clientA->id]['status']);
        $this->assertSame('sent', $results[$this->clientB->id]['status']);

        // 3. Two separate LeadDeliveryItem records exist
        $items = LeadDeliveryItem::where('lead_id', $lead->id)->get();
        $this->assertCount(2, $items);

        $itemA = $items->firstWhere('client_id', $this->clientA->id);
        $this->assertNotNull($itemA);
        $this->assertSame(100.00, (float)$itemA->rate_per_lead);
        $this->assertSame(100.00, (float)$itemA->billing_amount);
        $this->assertSame('sent', $itemA->status);
        $this->assertNotNull($itemA->sent_at);

        $itemB = $items->firstWhere('client_id', $this->clientB->id);
        $this->assertNotNull($itemB);
        $this->assertSame(150.00, (float)$itemB->rate_per_lead);
        $this->assertSame(150.00, (float)$itemB->billing_amount);
        $this->assertSame('sent', $itemB->status);
        $this->assertNotNull($itemB->sent_at);

        // 4. Per-client wallet deduction
        $walletA = CreditWallet::where('client_id', $this->clientA->id)->first();
        $this->assertSame(400.00, (float)$walletA->balance);

        $walletB = CreditWallet::where('client_id', $this->clientB->id)->first();
        $this->assertSame(350.00, (float)$walletB->balance);

        // 5. Separate CreditTransaction records
        $this->assertTrue(CreditTransaction::where('client_id', $this->clientA->id)->where('lead_id', $lead->id)->where('credit_used', 100)->exists());
        $this->assertTrue(CreditTransaction::where('client_id', $this->clientB->id)->where('lead_id', $lead->id)->where('credit_used', 150)->exists());
    }

    public function test_duplicate_delivery_to_same_client_is_skipped(): void
    {
        $this->actingAs($this->admin);
        $lead = $this->createSourceLead('LP-2026-DUP01');

        $configs = [
            [
                'client_id' => $this->clientA->id,
                'project_id' => $this->projectA->id,
                'campaign_id' => $this->campaignA->id,
                'rate' => 100.00,
            ],
        ];

        $service = app(LeadDistributionService::class);
        $res1 = $service->distributeLeadToMultipleClients($lead, $configs, $this->admin);
        $this->assertSame('sent', $res1[$this->clientA->id]['status']);

        // Attempting second delivery to Client A for the same lead
        $res2 = $service->distributeLeadToMultipleClients($lead, $configs, $this->admin);
        $this->assertSame('skipped', $res2[$this->clientA->id]['status']);
        $this->assertStringContainsString('already delivered', $res2[$this->clientA->id]['reason']);

        // Wallet was only charged once (500 - 100 = 400)
        $walletA = CreditWallet::where('client_id', $this->clientA->id)->first();
        $this->assertSame(400.00, (float)$walletA->balance);
    }

    public function test_revoking_delivery_for_one_client_refunds_only_that_client_and_preserves_other_client_delivery(): void
    {
        $this->actingAs($this->admin);
        $lead = $this->createSourceLead('LP-2026-REVOKE01');

        $configs = [
            [
                'client_id' => $this->clientA->id,
                'project_id' => $this->projectA->id,
                'campaign_id' => $this->campaignA->id,
                'rate' => 100.00,
            ],
            [
                'client_id' => $this->clientB->id,
                'project_id' => $this->projectB->id,
                'campaign_id' => $this->campaignB->id,
                'rate' => 150.00,
            ],
        ];

        $service = app(LeadDistributionService::class);
        $service->distributeLeadToMultipleClients($lead, $configs, $this->admin);

        $itemA = LeadDeliveryItem::where('lead_id', $lead->id)->where('client_id', $this->clientA->id)->first();
        $itemB = LeadDeliveryItem::where('lead_id', $lead->id)->where('client_id', $this->clientB->id)->first();

        // 1. Revoke delivery for Client A only
        $service->revokeClientDelivery($itemA, 'Customer not interested in Project A', $this->admin);

        $itemA->refresh();
        $itemB->refresh();
        $lead->refresh();

        // Client A item is revoked
        $this->assertSame('revoked', $itemA->status);
        $this->assertNotNull($itemA->revoked_at);
        $this->assertSame('Customer not interested in Project A', $itemA->revocation_reason);

        // Client A's wallet is refunded ₹100 (400 + 100 = 500)
        $walletA = CreditWallet::where('client_id', $this->clientA->id)->first();
        $this->assertSame(500.00, (float)$walletA->balance);

        // Client B's item remains active and billed
        $this->assertSame('sent', $itemB->status);
        $this->assertNull($itemB->revoked_at);

        // Client B's wallet is NOT refunded (remains 350)
        $walletB = CreditWallet::where('client_id', $this->clientB->id)->first();
        $this->assertSame(350.00, (float)$walletB->balance);

        // Lead remains delivered_at since Client B still has an active delivery
        $this->assertNotNull($lead->delivered_at);

        // 2. Now revoke Client B's delivery too
        $service->revokeClientDelivery($itemB, 'Client B requested cancellation', $this->admin);

        $itemB->refresh();
        $lead->refresh();

        // Client B is refunded ₹150 (350 + 150 = 500)
        $walletB = CreditWallet::where('client_id', $this->clientB->id)->first();
        $this->assertSame(500.00, (float)$walletB->balance);

        // Now that all client deliveries are revoked, master lead delivered_at is cleared
        $this->assertNull($lead->delivered_at);
        $this->assertTrue($lead->do_not_send_to_client);
    }

    public function test_delivery_management_actions_redeliver_and_exclude(): void
    {
        $this->actingAs($this->admin);
        $lead = $this->createSourceLead('LP-2026-ACTION01');

        $service = app(LeadDistributionService::class);
        $service->distributeLeadToMultipleClients($lead, [
            [
                'client_id' => $this->clientA->id,
                'project_id' => $this->projectA->id,
                'campaign_id' => $this->campaignA->id,
                'rate' => 100.00,
            ]
        ], $this->admin);

        $itemA = LeadDeliveryItem::where('lead_id', $lead->id)->where('client_id', $this->clientA->id)->first();

        // 1. Exclude from delivery (Do Not Send)
        $service->excludeClientDelivery($itemA, $this->admin);
        $this->assertSame('do_not_send', $itemA->fresh()->status);

        // 2. Revoke delivery
        $service->revokeClientDelivery($itemA, 'Return for testing re-deliver', $this->admin);
        $this->assertSame('revoked', $itemA->fresh()->status);

        // Client A was refunded back to 500
        $this->assertSame(500.00, (float)CreditWallet::where('client_id', $this->clientA->id)->value('balance'));

        // 3. Re-deliver to Client A
        $redelivered = $service->redeliverToClient($itemA, $this->admin, 100.00);
        $this->assertTrue($redelivered);

        $itemA->refresh();
        $this->assertSame('sent', $itemA->status);
        $this->assertNull($itemA->revoked_at);

        // Client A's wallet was charged again (500 - 100 = 400)
        $this->assertSame(400.00, (float)CreditWallet::where('client_id', $this->clientA->id)->value('balance'));
    }

    public function test_batch_distribution_of_multiple_leads_to_multiple_clients(): void
    {
        $this->actingAs($this->admin);
        $lead1 = $this->createSourceLead('LP-2026-BATCH01');
        $lead2 = $this->createSourceLead('LP-2026-BATCH02');
        $lead3 = $this->createSourceLead('LP-2026-BATCH03');

        $configs = [
            [
                'client_id' => $this->clientA->id,
                'project_id' => $this->projectA->id,
                'campaign_id' => $this->campaignA->id,
                'rate' => 100.00,
            ],
            [
                'client_id' => $this->clientB->id,
                'project_id' => $this->projectB->id,
                'campaign_id' => $this->campaignB->id,
                'rate' => 150.00,
            ],
        ];

        $service = app(LeadDistributionService::class);
        $summary = $service->distributeMultipleLeadsToMultipleClients(
            [$lead1->id, $lead2->id, $lead3->id],
            $configs,
            $this->admin
        );

        $this->assertSame(3, $summary['total_leads']);
        $this->assertSame(6, $summary['total_delivered']); // 3 leads * 2 clients
        $this->assertSame(750.00, $summary['total_billing']); // (3*100) + (3*150) = 750

        $this->assertSame(3, $summary['client_summaries'][$this->clientA->id]['delivered']);
        $this->assertSame(300.00, $summary['client_summaries'][$this->clientA->id]['total_amount']);

        $this->assertSame(3, $summary['client_summaries'][$this->clientB->id]['delivered']);
        $this->assertSame(450.00, $summary['client_summaries'][$this->clientB->id]['total_amount']);
    }

    public function test_lead_table_delivered_filters_and_bulk_actions(): void
    {
        $this->actingAs($this->admin);
        $lead1 = $this->createSourceLead('LP-2026-UI01');
        $lead2 = $this->createSourceLead('LP-2026-UI02');

        $service = app(LeadDistributionService::class);
        $service->distributeLeadToMultipleClients($lead1, [
            [
                'client_id' => $this->clientA->id,
                'project_id' => $this->projectA->id,
                'campaign_id' => $this->campaignA->id,
                'rate' => 100.00,
            ]
        ], $this->admin);

        $service->distributeLeadToMultipleClients($lead2, [
            [
                'client_id' => $this->clientB->id,
                'project_id' => $this->projectB->id,
                'campaign_id' => $this->campaignB->id,
                'rate' => 150.00,
            ]
        ], $this->admin);

        $item1 = LeadDeliveryItem::where('lead_id', $lead1->id)->where('client_id', $this->clientA->id)->first();
        $item2 = LeadDeliveryItem::where('lead_id', $lead2->id)->where('client_id', $this->clientB->id)->first();

        $component = Livewire::test(LeadKanban::class)
            ->set('statusFilter', 'delivered')
            ->assertSee('LP-2026-UI01')
            ->assertSee('LP-2026-UI02')
            // Test bulk Do Not Send on lead table
            ->set('selectedRows', [$lead1->id])
            ->call('bulkToggleDoNotSend', true)
            ->assertDispatched('toast');

        $this->assertTrue($lead1->fresh()->do_not_send_to_client);

        // Test bulk revoke on lead table
        $component->set('selectedRows', [$lead2->id])
            ->call('openRevokeDeliveryModal')
            ->assertSet('showRevokeDeliveryModal', true)
            ->set('revokeReason', 'Revoke via lead table bulk action')
            ->call('confirmRevokeDelivery')
            ->assertDispatched('toast');

        $this->assertSame('revoked', $item2->fresh()->status);
    }

    public function test_livewire_multi_client_modal_delivery_workflow(): void
    {
        $this->actingAs($this->admin);
        $lead = $this->createSourceLead('LP-2026-MODAL01');

        $component = Livewire::test(LeadKanban::class)
            ->set('selectedRows', [$lead->id])
            ->call('openMultiClientDeliveryModal')
            ->assertSet('showMultiClientModal', true)
            ->assertSet('multiClientLeadIds', [$lead->id])
            // Configure Client A
            ->set('multiClientConfigs.0.client_id', $this->clientA->id)
            ->set('multiClientConfigs.0.project_id', $this->projectA->id)
            ->set('multiClientConfigs.0.rate', 100.00)
            // Add Client B row
            ->call('addMultiClientRow')
            ->set('multiClientConfigs.1.client_id', $this->clientB->id)
            ->set('multiClientConfigs.1.project_id', $this->projectB->id)
            ->set('multiClientConfigs.1.rate', 150.00)
            // Confirm multi-client delivery
            ->call('confirmMultiClientDelivery')
            ->assertSet('showMultiClientModal', false)
            ->assertDispatched('toast');

        // Verify both deliveries created
        $this->assertSame(2, LeadDeliveryItem::where('lead_id', $lead->id)->count());
        $this->assertSame(400.00, (float)CreditWallet::where('client_id', $this->clientA->id)->value('balance'));
        $this->assertSame(350.00, (float)CreditWallet::where('client_id', $this->clientB->id)->value('balance'));
    }
}
