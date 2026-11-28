<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\OrganizationDetail;
use App\Livewire\Credits\Reconciliation;
use App\Models\{Client, CreditTransaction, CreditWallet, Lead, LeadSource, Organization, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationCreditReconciliationModalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $org;
    private Client $client;
    private CreditWallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);

        $this->org = Organization::create([
            'name' => 'Apex Realty Infra',
            'status' => 'active',
        ]);

        $this->client = Client::create([
            'organization_id' => $this->org->id,
            'name' => 'Apex Billing Owner',
            'status' => 'active',
        ]);
        User::findOrFail($this->client->id)->assignRole('Builder/Developer');

        $this->wallet = CreditWallet::create([
            'client_id' => $this->client->id,
            'balance' => 150,
            'opening_balance' => 200,
            'reconciled_at' => now(),
        ]);
    }

    public function test_reconcile_button_is_rendered_and_large_form_is_replaced(): void
    {
        // 1. On OrganizationDetail credits tab, verify the Reconcile button exists and old details element is gone
        $orgDetail = Livewire::test(OrganizationDetail::class, ['organization' => $this->org])
            ->call('setTab', 'credits')
            ->assertSee('Lead Credits Summary')
            ->assertSee('Reconcile / Manage Credits')
            ->assertSee('Opening Credits')
            ->assertSee('Available Lead Credits')
            ->assertDontSee('Confirm usable credits, correct dummy entries');

        // 2. On Reconciliation component, verify the clean button and modal structure
        $recon = Livewire::test(Reconciliation::class, ['organizationId' => $this->org->id])
            ->assertSee('Reconcile / Manage Credits')
            ->assertSet('showModal', false)
            ->assertSet('activeSection', 'reconcile');
    }

    public function test_modal_opens_and_allows_tab_switching_between_three_sections(): void
    {
        $component = Livewire::test(Reconciliation::class, ['organizationId' => $this->org->id])
            ->call('openModal')
            ->assertSet('showModal', true)
            ->assertDispatched('open-modal', 'reconcile-manage-credits-modal');

        // Tab 1: Reconcile Credits
        $component->call('setSection', 'reconcile')
            ->assertSet('activeSection', 'reconcile')
            ->assertSee('Verified remaining lead credits')
            ->assertSee('Confirm Reconciliation');

        // Tab 2: Mark as Consumed
        $component->call('setSection', 'consumed')
            ->assertSet('activeSection', 'consumed')
            ->assertSee('Mark Legacy Delivery as Consumption')
            ->assertSee('Credits consumed');

        // Tab 3: Raise Dispute
        $component->call('setSection', 'dispute')
            ->assertSet('activeSection', 'dispute')
            ->assertSee('Ledger entry to dispute')
            ->assertSee('Record Dispute');
    }

    public function test_reconcile_credits_workflow_validates_updates_wallet_and_closes_modal(): void
    {
        Livewire::test(Reconciliation::class, ['organizationId' => $this->org->id])
            ->call('openModal')
            ->assertSet('showModal', true)
            // Fails without confirmation checkbox
            ->set('remaining', 180)
            ->set('reason', 'Corrected wallet allocation')
            ->set('confirmed', false)
            ->call('reconcile')
            ->assertHasErrors(['confirmed'])
            // Succeeds with confirmation
            ->set('confirmed', true)
            ->call('reconcile')
            ->assertHasNoErrors()
            ->assertSet('showModal', false)
            ->assertDispatched('close-modal', 'reconcile-manage-credits-modal')
            ->assertDispatched('lead-credits-updated')
            ->assertDispatched('toast');

        $this->assertEquals(180, $this->wallet->fresh()->balance);
    }

    public function test_mark_legacy_consumption_workflow_deducts_credits_and_closes_modal(): void
    {
        $source = LeadSource::create(['name' => 'Legacy Source']);
        $lead = Lead::factory()->create([
            'lead_source_id' => $source->id,
            'client_id' => $this->client->id,
            'delivered_at' => now(),
            'delivery_pending' => false,
        ]);

        $legacyTx = CreditTransaction::create([
            'client_id' => $this->client->id,
            'lead_id' => $lead->id,
            'transaction_type' => 'lead_consumption',
            'is_legacy' => true,
            'legacy_credit_used' => 10,
            'credit_before' => 0,
            'credit_used' => 0,
            'credit_after' => 0,
            'created_at' => now(),
        ]);

        Livewire::test(Reconciliation::class, ['organizationId' => $this->org->id])
            ->call('openModal')
            ->call('setSection', 'consumed')
            ->set('transactionId', $legacyTx->id)
            ->set('legacyQuantity', 5)
            ->set('legacyReason', 'Verified past delivered lead consumption')
            ->set('legacyConfirmed', true)
            ->call('markLegacyConsumption')
            ->assertHasNoErrors()
            ->assertSet('showModal', false)
            ->assertDispatched('close-modal', 'reconcile-manage-credits-modal')
            ->assertDispatched('lead-credits-updated')
            ->assertDispatched('toast');

        $this->assertEquals(145, $this->wallet->fresh()->balance);
    }

    public function test_raise_dispute_workflow_records_dispute_and_closes_modal(): void
    {
        $tx = CreditTransaction::create([
            'client_id' => $this->client->id,
            'transaction_type' => 'recharge',
            'credit_before' => 100,
            'credit_used' => 50,
            'credit_after' => 150,
            'is_legacy' => false,
            'created_at' => now(),
        ]);

        Livewire::test(Reconciliation::class, ['organizationId' => $this->org->id])
            ->call('openModal')
            ->call('setSection', 'dispute')
            ->set('transactionId', $tx->id)
            ->set('disputeReason', 'Billing statement discrepancy claimed by client')
            ->call('openDispute')
            ->assertHasNoErrors()
            ->assertSet('showModal', false)
            ->assertDispatched('close-modal', 'reconcile-manage-credits-modal')
            ->assertDispatched('lead-credits-updated')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('credit_disputes', [
            'credit_transaction_id' => $tx->id,
            'status' => 'open',
            'reason' => 'Billing statement discrepancy claimed by client',
        ]);
    }

    public function test_credit_ledger_search_filters_transactions_by_lead_code_note_or_id(): void
    {
        $source = \App\Models\LeadSource::create(['name' => 'SearchTest']);
        $leadA = \App\Models\Lead::factory()->create([
            'client_id' => $this->client->id,
            'lead_source_id' => $source->id,
            'lead_code' => 'LP-2026-00000741',
            'name' => 'John Panther',
        ]);
        $leadB = \App\Models\Lead::factory()->create([
            'client_id' => $this->client->id,
            'lead_source_id' => $source->id,
            'lead_code' => 'LP-2026-00000888',
            'name' => 'Sara Smith',
        ]);

        $txA = CreditTransaction::create([
            'client_id' => $this->client->id,
            'lead_id' => $leadA->id,
            'transaction_type' => 'lead_consumption',
            'description' => 'Deltek allocation reconciliation: delivered lead LP-2026-00000741',
            'credit_before' => 100,
            'credit_used' => 1,
            'credit_after' => 99,
            'is_legacy' => false,
            'created_at' => now()->subDay(),
        ]);

        $txB = CreditTransaction::create([
            'client_id' => $this->client->id,
            'lead_id' => $leadB->id,
            'transaction_type' => 'lead_consumption',
            'description' => 'Deltek normal delivery: delivered lead LP-2026-00000888',
            'credit_before' => 99,
            'credit_used' => 1,
            'credit_after' => 98,
            'is_legacy' => false,
            'created_at' => now(),
        ]);

        Livewire::test(OrganizationDetail::class, ['organization' => $this->org])
            ->call('setTab', 'credits')
            ->assertSee('LP-2026-00000741')
            ->assertSee('LP-2026-00000888')
            ->set('creditSearch', '00000741')
            ->assertSee('LP-2026-00000741')
            ->assertDontSee('LP-2026-00000888')
            ->set('creditSearch', 'nonexistent-xyz-999')
            ->assertSee('No credit transactions match your search or filter criteria')
            ->assertSee('Reset Filters')
            ->set('creditSearch', '')
            ->assertSee('LP-2026-00000741')
            ->assertSee('LP-2026-00000888');
    }
}
