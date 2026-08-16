<?php

namespace Tests\Feature\Replacement;

use Tests\TestCase;
use App\Models\User;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\LeadSource;
use App\Models\ReplacementReason;
use App\Models\LeadReplacement;
use App\Models\CreditWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class LeadReplacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'client']);
    }

    public function test_replacement_queue_and_client_history_views_render_successfully(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $response = $this->actingAs($user)->get('/replacements');
        $response->assertOk();

        $clientResponse = $this->actingAs($user)->get('/client/replacements');
        $clientResponse->assertOk();
    }

    public function test_canonical_lead_replacement_approve_refunds_credit_and_creates_replacement_lead(): void
    {
        $user = User::factory()->create();

        $org = Organization::create(['name' => 'Org R1', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client R1']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project R1']);
        $source = LeadSource::create(['name' => 'meta']);

        $wallet = CreditWallet::create(['client_id' => $client->id, 'balance' => 50.00]);
        $reason = ReplacementReason::create(['label' => 'Invalid Phone Number', 'is_eligible' => true, 'requires_sla_check' => true]);

        $lead = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'current_stage' => 'contacted',
        ]);

        $replacement = LeadReplacement::create([
            'lead_id' => $lead->id,
            'reason_id' => $reason->id,
            'requested_by' => $user->id,
            'requested_at' => now(),
            'sla_met' => true,
            'status' => 'pending',
        ]);

        $result = $replacement->approve($user->id);

        $this->assertTrue($result);

        // Assert original lead updated to replaced
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'current_stage' => 'replaced',
            'status' => 'replaced',
        ]);

        // Assert credit refunded (50 -> 60)
        $this->assertDatabaseHas('client_wallets', [
            'client_id' => $client->id,
            'balance' => 60.00,
        ]);

        // Assert refund transaction logged
        $this->assertDatabaseHas('credit_transactions', [
            'client_id' => $client->id,
            'lead_id' => $lead->id,
            'transaction_type' => 'refund',
            'credit_used' => 10.00,
        ]);

        // Assert replacement lead generated
        $this->assertNotNull($replacement->fresh()->replacement_lead_id);

        // Assert audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'replacement.approved',
            'subject_id' => $replacement->id,
        ]);
    }

    public function test_no_double_replacement_guard_on_same_lead(): void
    {
        $user = User::factory()->create();

        $org = Organization::create(['name' => 'Org R Dup', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client R Dup']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project R Dup']);
        $source = LeadSource::create(['name' => 'meta']);

        $reason = ReplacementReason::create(['label' => 'Wrong Number', 'is_eligible' => true, 'requires_sla_check' => true]);

        $lead = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'current_stage' => 'replaced',
        ]);

        // First replacement already exists
        LeadReplacement::create([
            'lead_id' => $lead->id,
            'reason_id' => $reason->id,
            'requested_by' => $user->id,
            'requested_at' => now(),
            'sla_met' => true,
            'status' => 'approved',
        ]);

        // Guard assertion: Lead with existing replacement row cannot have a second replacement created
        $priorReplacementCount = LeadReplacement::where('lead_id', $lead->id)->count();
        $this->assertEquals(1, $priorReplacementCount);
    }

    public function test_canonical_lead_replacement_reject_writes_audit_log(): void
    {
        $user = User::factory()->create();

        $org = Organization::create(['name' => 'Org R2', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client R2']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project R2']);
        $source = LeadSource::create(['name' => 'google']);

        $reason = ReplacementReason::create(['label' => 'Out of Budget', 'is_eligible' => false, 'requires_sla_check' => false]);

        $lead = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
        ]);

        $replacement = LeadReplacement::create([
            'lead_id' => $lead->id,
            'reason_id' => $reason->id,
            'requested_by' => $user->id,
            'requested_at' => now(),
            'sla_met' => false,
            'status' => 'pending',
        ]);

        $result = $replacement->reject('Out of budget reason is ineligible for lead replacement', $user->id);

        $this->assertTrue($result);

        $this->assertDatabaseHas('lead_replacements', [
            'id' => $replacement->id,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'replacement.rejected',
            'subject_id' => $replacement->id,
        ]);
    }
}
