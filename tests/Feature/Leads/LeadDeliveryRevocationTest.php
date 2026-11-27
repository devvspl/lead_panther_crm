<?php

namespace Tests\Feature\Leads;

use App\Livewire\Leads\{ClientDeliveryCenter, LeadKanban};
use App\Models\{Campaign, CreditPackage, CreditTransaction, CreditWallet, Lead, LeadDeliveryItem, LeadDeliveryPlan, LeadDeliveryRevocation, LeadSource, Organization, Project, SalesTeam, User};
use App\Services\LeadDistributionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadDeliveryRevocationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $sourceClient;
    private User $destClient;
    private Organization $sourceOrg;
    private Organization $destOrg;
    private Project $sourceProject;
    private Project $destProject;
    private LeadSource $leadSource;
    private Campaign $sourceCampaign;
    private Campaign $destCampaign;
    private LeadDeliveryPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->leadSource = LeadSource::create(['name' => 'direct']);

        $this->admin = User::factory()->create(['status' => 'active', 'email' => 'admin@leadpanther.com']);
        $this->admin->assignRole('Super Admin');

        $this->sourceOrg = Organization::create([
            'name' => 'Source Real Estate',
            'status' => 'active',
            'delivery_timezone' => 'Asia/Kolkata',
        ]);

        $this->destOrg = Organization::create([
            'name' => 'Destination Realty',
            'status' => 'active',
            'delivery_timezone' => 'Asia/Kolkata',
        ]);

        $this->sourceClient = User::factory()->create([
            'organization_id' => $this->sourceOrg->id,
            'status' => 'active',
            'email' => 'source@leadpanther.com',
        ]);
        $this->sourceClient->assignRole('Builder');

        $this->destClient = User::factory()->create([
            'organization_id' => $this->destOrg->id,
            'status' => 'active',
            'email' => 'dest@leadpanther.com',
        ]);
        $this->destClient->assignRole('Builder');

        $this->sourceProject = Project::withoutGlobalScopes()->create([
            'client_id' => $this->sourceClient->id,
            'name' => 'Source Residency',
            'status' => 'active',
            'delivery_enabled' => true,
        ]);

        $this->destProject = Project::withoutGlobalScopes()->create([
            'client_id' => $this->destClient->id,
            'name' => 'Destination Heights',
            'status' => 'active',
            'delivery_enabled' => true,
        ]);

        $this->sourceCampaign = Campaign::withoutGlobalScopes()->create([
            'project_id' => $this->sourceProject->id,
            'name' => 'Source Campaign',
            'lead_source_id' => $this->leadSource->id,
            'status' => 'active',
        ]);

        $this->destCampaign = Campaign::withoutGlobalScopes()->create([
            'project_id' => $this->destProject->id,
            'name' => 'Destination Campaign',
            'lead_source_id' => $this->leadSource->id,
            'status' => 'active',
        ]);

        $this->plan = LeadDeliveryPlan::create([
            'organization_id' => $this->sourceOrg->id,
            'created_by' => $this->admin->id,
            'client_id' => $this->destClient->id,
            'project_id' => $this->destProject->id,
            'campaign_id' => $this->destCampaign->id,
            'state' => 'active',
            'settings' => [
                'frequency' => 'immediate',
                'quantity_mode' => 'fixed',
                'quantity' => 10,
                'automatic' => true,
                'timezone' => 'Asia/Kolkata',
                'window_start' => '00:00',
                'window_end' => '23:59',
                'working_days' => range(0, 6),
                'daily_enabled' => false,
            ],
        ]);
    }

    private function createDeliveredLead(string $code = 'LP-TEST-001'): array
    {
        $lead = Lead::withoutGlobalScopes()->create([
            'client_id' => $this->destClient->id,
            'campaign_id' => $this->destCampaign->id,
            'lead_source_id' => $this->leadSource->id,
            'lead_code' => $code,
            'name' => 'Customer ' . $code,
            'mobile' => '9876543210',
            'status' => 'new',
            'current_stage' => 'new',
            'delivered_at' => now()->subHours(2),
            'credit_delivery_confirmed' => true,
            'do_not_send_to_client' => false,
        ]);

        $item = LeadDeliveryItem::create([
            'plan_id' => $this->plan->id,
            'lead_id' => $lead->id,
            'source_client_id' => $this->sourceClient->id,
            'source_campaign_id' => $this->sourceCampaign->id,
            'client_id' => $this->destClient->id,
            'project_id' => $this->destProject->id,
            'campaign_id' => $this->destCampaign->id,
            'requested_by' => $this->admin->id,
            'status' => 'sent',
            'sent_at' => now()->subHours(2),
            'attempts' => 1,
        ]);

        return [$lead, $item];
    }

    public function test_single_delivered_lead_revocation_via_service(): void
    {
        $this->actingAs($this->admin);
        [$lead, $item] = $this->createDeliveredLead('LP-REV-001');

        $service = app(LeadDistributionService::class);
        $revocation = $service->revokeClientDelivery($item, 'Customer cancelled transaction', $this->admin);

        $this->assertInstanceOf(LeadDeliveryRevocation::class, $revocation);
        $this->assertSame($lead->id, $revocation->lead_id);
        $this->assertSame($this->destClient->id, $revocation->client_id);
        $this->assertSame($this->destProject->id, $revocation->project_id);
        $this->assertSame('Customer cancelled transaction', $revocation->return_reason);
        $this->assertSame('sent', $revocation->previous_status);
        $this->assertSame('revoked', $revocation->new_status);
        $this->assertSame($this->admin->id, $revocation->revoked_by);

        $lead->refresh();
        $this->assertNull($lead->delivered_at);
        $this->assertFalse($lead->delivery_pending);
        $this->assertTrue($lead->do_not_send_to_client);
        $this->assertSame($this->sourceClient->id, $lead->client_id);
        $this->assertSame($this->sourceCampaign->id, $lead->campaign_id);
        $this->assertNotNull($lead->delivery_revoked_at);
        $this->assertSame('Customer cancelled transaction', $lead->delivery_revoke_reason);

        $item->refresh();
        $this->assertSame('revoked', $item->status);
        $this->assertStringContainsString('Customer cancelled transaction', $item->failure_reason);
    }

    public function test_single_delivered_lead_revocation_via_lead_kanban_modal(): void
    {
        $this->actingAs($this->admin);
        [$lead, $item] = $this->createDeliveredLead('LP-REV-002');

        Livewire::test(LeadKanban::class)
            ->call('openRevokeDeliveryModal', $lead->id)
            ->assertSet('showRevokeDeliveryModal', true)
            ->assertSet('revokeLeadId', $lead->id)
            ->set('revokeReason', 'Wrong project delivery')
            ->call('confirmRevokeDelivery')
            ->assertSet('showRevokeDeliveryModal', false)
            ->assertDispatched('toast');

        $lead->refresh();
        $this->assertNull($lead->delivered_at);
        $this->assertTrue($lead->do_not_send_to_client);
        $this->assertSame($this->sourceClient->id, $lead->client_id);

        $this->assertDatabaseHas('lead_delivery_revocations', [
            'lead_id' => $lead->id,
            'return_reason' => 'Wrong project delivery',
            'new_status' => 'revoked',
        ]);
    }

    public function test_do_not_send_to_client_toggle_and_candidate_exclusion(): void
    {
        $this->actingAs($this->admin);
        $lead = Lead::withoutGlobalScopes()->create([
            'client_id' => $this->sourceClient->id,
            'campaign_id' => $this->sourceCampaign->id,
            'lead_source_id' => $this->leadSource->id,
            'lead_code' => 'LP-EXCL-001',
            'name' => 'Excluded Customer',
            'mobile' => '9988776655',
            'status' => 'new',
            'current_stage' => 'new',
            'do_not_send_to_client' => false,
        ]);

        $service = app(LeadDistributionService::class);
        $this->assertTrue($service->eligibleClientDeliveryLeads($this->sourceOrg->id)->where('id', $lead->id)->exists());

        Livewire::test(LeadKanban::class)
            ->call('toggleDoNotSendToClient', $lead->id, true)
            ->assertDispatched('toast');

        $lead->refresh();
        $this->assertTrue($lead->do_not_send_to_client);

        // Excluded from automatic delivery candidates
        $this->assertFalse($service->eligibleClientDeliveryLeads($this->sourceOrg->id)->where('id', $lead->id)->exists());

        // Toggle back to allowed
        Livewire::test(LeadKanban::class)
            ->call('toggleDoNotSendToClient', $lead->id, false)
            ->assertDispatched('toast');

        $lead->refresh();
        $this->assertFalse($lead->do_not_send_to_client);
        $this->assertTrue($service->eligibleClientDeliveryLeads($this->sourceOrg->id)->where('id', $lead->id)->exists());
    }

    public function test_delivered_filter_and_batch_revoke_on_lead_table(): void
    {
        $this->actingAs($this->admin);
        [$lead1, $item1] = $this->createDeliveredLead('LP-BATCH-001');
        [$lead2, $item2] = $this->createDeliveredLead('LP-BATCH-002');
        [$lead3, $item3] = $this->createDeliveredLead('LP-BATCH-003');

        $component = Livewire::test(LeadKanban::class)
            ->set('statusFilter', 'delivered')
            ->assertSee('LP-BATCH-001')
            ->assertSee('LP-BATCH-002')
            ->assertSee('LP-BATCH-003')
            ->set('selectedRows', [$lead1->id, $lead2->id])
            ->call('openRevokeDeliveryModal')
            ->assertSet('showRevokeDeliveryModal', true)
            ->set('revokeReason', 'Batch return for review')
            ->call('confirmRevokeDelivery')
            ->assertSet('showRevokeDeliveryModal', false)
            ->assertDispatched('toast');

        $this->assertSame('revoked', $item1->fresh()->status);
        $this->assertSame('revoked', $item2->fresh()->status);
        $this->assertSame('sent', $item3->fresh()->status);

        $this->assertNull($lead1->fresh()->delivered_at);
        $this->assertNull($lead2->fresh()->delivered_at);
        $this->assertNotNull($lead3->fresh()->delivered_at);

        $this->assertSame(2, LeadDeliveryRevocation::whereIn('lead_id', [$lead1->id, $lead2->id])->count());
    }

    public function test_bulk_revoke_with_table_selection(): void
    {
        $this->actingAs($this->admin);
        [$lead1, $item1] = $this->createDeliveredLead('LP-ALL-001');
        [$lead2, $item2] = $this->createDeliveredLead('LP-ALL-002');

        Livewire::test(LeadKanban::class)
            ->set('selectedRows', [$lead1->id, $lead2->id])
            ->call('openRevokeDeliveryModal')
            ->assertSet('showRevokeDeliveryModal', true)
            ->set('revokeReason', 'Revoke all selected leads')
            ->call('confirmRevokeDelivery')
            ->assertDispatched('toast');

        $this->assertSame('revoked', $item1->fresh()->status);
        $this->assertSame('revoked', $item2->fresh()->status);
    }

    public function test_non_admin_cannot_revoke_delivery(): void
    {
        $salesUser = User::factory()->create([
            'organization_id' => $this->sourceOrg->id,
            'status' => 'active',
        ]);
        $salesUser->assignRole('Executive');

        $this->actingAs($salesUser);
        [$lead, $item] = $this->createDeliveredLead('LP-AUTH-001');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(LeadDistributionService::class)->revokeClientDelivery($item, 'Unauthorized attempt', $salesUser);
    }

    public function test_revoking_refunds_wallet_if_credit_was_consumed(): void
    {
        $this->actingAs($this->admin);
        [$lead, $item] = $this->createDeliveredLead('LP-REFUND-001');

        // Create reconciled wallet for destClient
        $wallet = CreditWallet::create([
            'client_id' => $this->destClient->id,
            'balance' => 50,
            'reconciled_at' => now(),
        ]);

        CreditTransaction::create([
            'client_id' => $this->destClient->id,
            'lead_id' => $lead->id,
            'credit_before' => 55,
            'credit_used' => 5,
            'credit_after' => 50,
            'transaction_type' => 'lead_consumption',
            'is_legacy' => false,
            'created_at' => now(),
        ]);

        $service = app(LeadDistributionService::class);
        $service->revokeClientDelivery($item, 'Refund verified lead', $this->admin);

        $wallet->refresh();
        $this->assertSame(55, $wallet->balance);

        $this->assertDatabaseHas('credit_transactions', [
            'client_id' => $this->destClient->id,
            'lead_id' => $lead->id,
            'transaction_type' => 'refund',
            'credit_used' => 5,
        ]);
    }

    public function test_cannot_revoke_non_delivered_lead(): void
    {
        $this->actingAs($this->admin);
        $lead = Lead::withoutGlobalScopes()->create([
            'client_id' => $this->sourceClient->id,
            'campaign_id' => $this->sourceCampaign->id,
            'lead_source_id' => $this->leadSource->id,
            'lead_code' => 'LP-NONDELIV-001',
            'name' => 'Non-delivered Customer',
            'mobile' => '9111223344',
            'status' => 'new',
            'current_stage' => 'new',
            'delivered_at' => null,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(LeadDistributionService::class)->revokeClientDelivery($lead, 'Cannot revoke', $this->admin);
    }

    public function test_re_enqueuing_revoked_lead_via_explicit_selection_resets_exclusion(): void
    {
        $this->actingAs($this->admin);
        [$lead, $item] = $this->createDeliveredLead('LP-REENQ-001');

        $service = app(LeadDistributionService::class);
        $service->revokeClientDelivery($item, 'Initial return', $this->admin);

        $lead->refresh();
        $this->assertTrue($lead->do_not_send_to_client);
        $this->assertSame('revoked', $item->fresh()->status);

        // Explicitly pushing the lead again re-arms it and resets do_not_send_to_client
        $res = $service->enqueueClientLeads($this->plan, [$lead->id], true, $this->admin);

        $this->assertSame(1, $res['queued']);
        $lead->refresh();
        $this->assertFalse($lead->do_not_send_to_client);
        $this->assertTrue($lead->delivery_pending);
        $this->assertSame('pending', $item->fresh()->status);
    }
}
