<?php

namespace Tests\Feature\Replacement;

use Tests\TestCase;
use App\Models\User;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\Campaign;
use App\Models\LeadSource;
use App\Models\ReplacementReason;
use App\Models\LeadReplacement;
use App\Models\CreditWallet;
use App\Models\CreditTransaction;
use App\Livewire\Leads\LeadDetail;
use App\Livewire\Replacement\ReplacementQueue;
use App\Livewire\Replacement\ClientReplacementHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class LeadReplacementEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $builder;
    protected User $executive;
    protected Organization $org;
    protected Client $client;
    protected Project $project;
    protected Campaign $campaign;
    protected LeadSource $source;
    protected CreditWallet $wallet;
    protected ReplacementReason $reason;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin']);
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'Builder/Developer']);
        Role::firstOrCreate(['name' => 'Executive']);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('Super Admin');

        $this->org = Organization::create(['name' => 'H&S By Investors Clinic', 'status' => 'active']);

        $client = Client::create([
            'organization_id' => $this->org->id,
            'name' => 'H&S Builder',
            'email' => 'builder_test@example.com',
            'status' => 'active',
        ]);
        $this->builder = User::find($client->id);
        $this->builder->assignRole('Builder/Developer');

        $this->executive = User::factory()->create(['organization_id' => $this->org->id, 'status' => 'active', 'name' => 'Aman']);
        $this->executive->assignRole('Executive');

        $this->project = Project::create([
            'client_id' => $this->builder->id,
            'name' => 'Boulevard walk by H&S',
            'status' => 'active',
        ]);

        $this->campaign = Campaign::create([
            'project_id' => $this->project->id,
            'name' => 'Meta - 3BHK Boulevard Walk',
            'status' => 'active',
        ]);

        $this->source = LeadSource::create(['name' => 'Meta Ads']);

        $this->wallet = CreditWallet::create([
            'client_id' => $this->builder->id,
            'balance' => 196,
            'reconciled_at' => now(),
        ]);

        $this->reason = ReplacementReason::create([
            'label' => 'Invalid Phone Number',
            'is_eligible' => true,
            'requires_sla_check' => true,
        ]);
    }

    public function test_sales_executive_cannot_submit_replacement_claim(): void
    {
        $lead = Lead::factory()->create([
            'client_id' => $this->builder->id,
            'campaign_id' => $this->campaign->id,
            'lead_source_id' => $this->source->id,
            'current_stage' => 'contacted',
            'status' => 'contacted',
            'assigned_to' => $this->executive->id,
        ]);
        $lead->update([
            'delivery_pending' => false,
            'delivered_at' => now(),
            'credit_delivery_confirmed' => true,
            'internal_processing_pending' => false,
        ]);

        Livewire::actingAs($this->executive)
            ->test(LeadDetail::class)
            ->call('openLead', $lead->id)
            ->set('replacementReasonId', $this->reason->id)
            ->set('replacementRemarks', 'Customer says wrong number')
            ->call('requestReplacement')
            ->assertForbidden();

        $this->assertEquals(0, LeadReplacement::where('lead_id', $lead->id)->count());
    }

    public function test_builder_can_submit_replacement_claim_and_super_admin_can_approve(): void
    {
        $lead = Lead::factory()->create([
            'client_id' => $this->builder->id,
            'campaign_id' => $this->campaign->id,
            'lead_source_id' => $this->source->id,
            'current_stage' => 'contacted',
            'status' => 'contacted',
            'assigned_to' => $this->executive->id,
        ]);
        $lead->update([
            'delivery_pending' => false,
            'delivered_at' => now(),
            'credit_delivery_confirmed' => true,
            'internal_processing_pending' => false,
        ]);

        // Prior consumption of 1 credit
        CreditTransaction::create([
            'client_id' => $this->builder->id,
            'lead_id' => $lead->id,
            'transaction_type' => 'lead_consumption',
            'credit_before' => 197,
            'credit_used' => 1,
            'credit_after' => 196,
            'created_at' => now(),
        ]);

        // 1. Builder requests replacement
        Livewire::actingAs($this->builder)
            ->test(LeadDetail::class)
            ->call('openLead', $lead->id)
            ->assertSet('leadId', $lead->id)
            ->set('replacementReasonId', $this->reason->id)
            ->set('replacementRemarks', 'Unreachable invalid number verified')
            ->call('requestReplacement')
            ->assertDispatched('toast', type: 'success');

        $this->assertEquals('pending', $lead->fresh()->replacement_status);
        $claim = LeadReplacement::where('lead_id', $lead->id)->first();
        $this->assertNotNull($claim);
        $this->assertEquals('pending', $claim->status);

        // 2. Super Admin views queue and approves
        Livewire::actingAs($this->admin)
            ->test(ReplacementQueue::class)
            ->assertSee('Approve &amp; Replace', false)
            ->call('approveReplacement', $claim->id)
            ->assertDispatched('toast', type: 'success');

        $claim->refresh();
        $this->assertEquals('approved', $claim->status);
        $this->assertEquals('replaced', $lead->fresh()->status);
        $this->assertEquals('replaced', $lead->fresh()->current_stage);

        // 3. Verify credit refund (196 -> 197)
        $this->assertEquals(197, (int) $this->wallet->fresh()->balance);
        $this->assertDatabaseHas('credit_transactions', [
            'client_id' => $this->builder->id,
            'lead_id' => $lead->id,
            'transaction_type' => 'refund',
            'credit_used' => 1,
        ]);

        // 4. Verify replacement lead created
        $this->assertNotNull($claim->replacement_lead_id);
        $repLead = Lead::find($claim->replacement_lead_id);
        $this->assertNotNull($repLead);
        $this->assertEquals($this->campaign->id, $repLead->campaign_id);

        // 5. Client replacement history renders with replacement lead link
        Livewire::actingAs($this->builder)
            ->test(ClientReplacementHistory::class)
            ->assertOk()
            ->assertSee($lead->lead_code)
            ->assertSee('Invalid Phone Number')
            ->assertSee($repLead->lead_code);
    }

    public function test_super_admin_can_reject_replacement_with_note(): void
    {
        $lead = Lead::factory()->create([
            'client_id' => $this->builder->id,
            'campaign_id' => $this->campaign->id,
            'lead_source_id' => $this->source->id,
            'current_stage' => 'contacted',
            'status' => 'contacted',
            'assigned_to' => $this->executive->id,
        ]);
        $lead->update([
            'delivery_pending' => false,
            'delivered_at' => now(),
            'credit_delivery_confirmed' => true,
            'internal_processing_pending' => false,
        ]);

        Livewire::actingAs($this->builder)
            ->test(LeadDetail::class)
            ->call('openLead', $lead->id)
            ->set('replacementReasonId', $this->reason->id)
            ->set('replacementRemarks', 'Claiming invalid number')
            ->call('requestReplacement');

        $claim = LeadReplacement::where('lead_id', $lead->id)->first();

        // Super Admin rejects
        Livewire::actingAs($this->admin)
            ->test(ReplacementQueue::class)
            ->call('openRejectModal', $claim->id)
            ->set('rejectionNote', 'Call recording proves customer answered and talked')
            ->call('confirmReject')
            ->assertDispatched('toast', type: 'success');

        $claim->refresh();
        $this->assertEquals('rejected', $claim->status);
        $this->assertEquals('rejected', $lead->fresh()->replacement_status);

        // Wallet balance untouched
        $this->assertEquals(196, (int) $this->wallet->fresh()->balance);

        // Client history displays rejection outcome
        Livewire::actingAs($this->builder)
            ->test(ClientReplacementHistory::class)
            ->assertOk()
            ->assertSee('Ineligible per SLA policy');
    }
}
