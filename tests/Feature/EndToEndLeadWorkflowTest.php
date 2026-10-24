<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\PortalAccount;
use App\Models\CreditWallet;
use App\Models\WebhookLog;
use App\Models\Lead;
use App\Models\LeadCall;
use App\Models\LeadReplacement;
use App\Models\ReplacementReason;
use App\Models\LeadStatusHistory;
use App\Models\NotificationLog;
use App\Jobs\ProcessInboundLeadJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class EndToEndLeadWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected Client $client;
    protected Project $project;
    protected User $salesExecutive;
    protected CreditWallet $wallet;
    protected PortalAccount $metaAccount;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin']);
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'Sales Executive']);
        Role::firstOrCreate(['name' => 'sales-executive']);

        $this->org = Organization::create(['name' => 'Lodha Developers', 'status' => 'active', 'type' => 'builder']);
        $this->client = Client::create(['organization_id' => $this->org->id, 'name' => 'Lodha Real Estate']);
        $this->project = Project::create(['client_id' => $this->client->id, 'name' => 'Lodha Altamount']);
        $this->wallet = CreditWallet::create(['client_id' => $this->client->id, 'balance' => 50.00]);

        $this->salesExecutive = User::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Vikram Sharma',
            'email' => 'vikram@lodhagroup.com',
            'phone' => '9820098200',
            'status' => 'active',
        ]);
        $this->salesExecutive->assignRole('Sales Executive');

        $this->metaAccount = PortalAccount::create([
            'name' => 'Lodha Facebook Lead Ads',
            'type' => 'meta',
        ]);
    }

    public function test_full_lead_lifecycle_from_inbound_webhook_to_closed_won(): void
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'form_id' => '101928374',
                                'leadgen_id' => '9988776655',
                                'created_time' => time(),
                                'field_data' => [
                                    ['name' => 'full_name', 'values' => ['Rajesh Kumar']],
                                    ['name' => 'phone_number', 'values' => ['9820123456']],
                                    ['name' => 'email', 'values' => ['rajesh.kumar@example.com']],
                                    ['name' => 'city', 'values' => ['Mumbai']],
                                    ['name' => 'budget', 'values' => ['1.5 Cr']],
                                    ['name' => 'property_type', 'values' => ['3 BHK']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // 1. Ingestion: Webhook layer receives payload
        $response = $this->postJson("/api/webhooks/meta/{$this->metaAccount->id}", $payload);
        $response->assertOk();
        $logId = $response->json('log_id');
        $this->assertNotNull($logId);

        $log = WebhookLog::find($logId);
        $this->assertNotNull($log);

        // 2. Lead Ingestion Engine: ProcessInboundLeadJob executes
        ProcessInboundLeadJob::dispatchSync($log);

        // 3. Normalization & Lead Master record creation
        $lead = Lead::where('mobile', '9820123456')->first();
        $this->assertNotNull($lead);
        $this->assertStringStartsWith('LP-', $lead->lead_code);
        $this->assertEquals('Rajesh Kumar', $lead->name);
        $this->assertEquals($this->client->id, $lead->client_id);

        // 4. Credit Check & Reservation: 10 credits deducted (50 -> 40)
        $this->wallet->refresh();
        $this->assertEquals(40.00, (float) $this->wallet->balance);
        $this->assertDatabaseHas('credit_transactions', [
            'client_id' => $this->client->id,
            'lead_id' => $lead->id,
            'transaction_type' => 'reserve',
            'credit_used' => 10.00,
            'credit_after' => 40.00,
        ]);

        // 5. Sales Distribution: Automatically assigned to Sales Executive
        $lead->refresh();
        $this->assertEquals($this->salesExecutive->id, $lead->assigned_to);
        $this->assertEquals('assigned', $lead->current_stage);
        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $this->salesExecutive->id,
        ]);

        // 6. Deduplication Engine: Re-posting duplicate within 90 days
        $dupResponse = $this->postJson("/api/webhooks/meta/{$this->metaAccount->id}", $payload);
        $dupResponse->assertOk();
        $dupLog = WebhookLog::find($dupResponse->json('log_id'));
        ProcessInboundLeadJob::dispatchSync($dupLog);

        // Assert no duplicate Lead created and no extra credits deducted
        $this->assertEquals(1, Lead::where('mobile', '9820123456')->count());
        $this->wallet->refresh();
        $this->assertEquals(40.00, (float) $this->wallet->balance);
        $this->assertDatabaseHas('lead_metadata', [
            'lead_id' => $lead->id,
            'key' => 'duplicate_utm_source',
        ]);

        // 7. SLA First Response Tracking: Sales Executive logs outbound call
        $this->assertNull($lead->first_response_at);
        LeadCall::create([
            'lead_id' => $lead->id,
            'user_id' => $this->salesExecutive->id,
            'duration_seconds' => 180,
            'outcome' => 'connected',
            'called_at' => now(),
        ]);

        $lead->refresh();
        $this->assertNotNull($lead->first_response_at);
        $this->assertEquals('SLA Met', $lead->sla_badge);

        // 8. Lead Journey: Progress through pipeline stages
        $stages = [
            'contacted',
            'interested',
            'follow-up',
            'meeting',
            'site_visit',
            'negotiation',
            'proposal',
            'booking',
            'payment',
            'closed_won',
        ];

        foreach ($stages as $stage) {
            $fromStage = $lead->current_stage;
            $lead->update([
                'current_stage' => $stage,
                'status' => $stage,
            ]);

            LeadStatusHistory::create([
                'lead_id' => $lead->id,
                'from_status' => $fromStage,
                'to_status' => $stage,
                'changed_by' => $this->salesExecutive->id,
                'changed_at' => now(),
            ]);
        }

        $lead->refresh();
        $this->assertEquals('closed_won', $lead->current_stage);
        $this->assertGreaterThanOrEqual(count($stages), LeadStatusHistory::where('lead_id', $lead->id)->count());
    }

    public function test_lead_inbound_with_zero_credits_pauses_campaign_and_notifies_client(): void
    {
        // Set wallet balance to zero
        $this->wallet->update(['balance' => 0.00]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'form_id' => '101928374',
                                'leadgen_id' => '1122334455',
                                'created_time' => time(),
                                'field_data' => [
                                    ['name' => 'full_name', 'values' => ['Pooja Mehta']],
                                    ['name' => 'phone_number', 'values' => ['9833445566']],
                                    ['name' => 'email', 'values' => ['pooja@example.com']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson("/api/webhooks/meta/{$this->metaAccount->id}", $payload);
        $response->assertOk();
        $log = WebhookLog::find($response->json('log_id'));
        ProcessInboundLeadJob::dispatchSync($log);

        $lead = Lead::where('mobile', '9833445566')->first();
        $this->assertNotNull($lead);
        $this->assertEquals('pending_credit', $lead->current_stage);

        // Client wallet remains 0
        $this->assertEquals(0.00, (float) $this->wallet->fresh()->balance);

        // In-app CRM notification sent to client
        $this->assertDatabaseHas('notification_logs', [
            'notifiable_id' => $this->client->id,
            'channel' => 'crm',
            'status' => 'sent',
        ]);
    }

    public function test_replacement_guarantee_workflow_with_sla_met_refunds_credits(): void
    {
        $reason = ReplacementReason::create([
            'label' => 'Wrong Contact / Invalid Number',
            'is_eligible' => true,
            'requires_sla_check' => true,
        ]);

        $source = \App\Models\LeadSource::firstOrCreate(['name' => 'direct']);

        $lead = Lead::create([
            'lead_code' => Lead::generateUniqueLeadCode(),
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'lead_source_id' => $source->id,
            'name' => 'Invalid Test Lead',
            'mobile' => '9111222333',
            'email' => 'invalid@example.com',
            'current_stage' => 'contacted',
            'status' => 'contacted',
            'created_at' => now()->subMinutes(10),
            'first_response_at' => now()->subMinutes(5), // Contacted within 5 min => SLA Met!
        ]);

        $replacement = LeadReplacement::create([
            'lead_id' => $lead->id,
            'reason_id' => $reason->id,
            'requested_by' => $this->salesExecutive->id,
            'requested_at' => now(),
            'sla_met' => true,
            'status' => 'pending',
        ]);

        $replacement->approve($this->salesExecutive->id);

        $this->wallet->refresh();
        // 50 + 10 = 60 credits
        $this->assertEquals(60.00, (float) $this->wallet->balance);

        // Original lead updated to replaced
        $this->assertEquals('replaced', $lead->fresh()->current_stage);

        // Replacement lead generated with LP-REP code
        $replacementLead = Lead::find($replacement->fresh()->replacement_lead_id);
        $this->assertNotNull($replacementLead);
        $this->assertStringStartsWith('LP-REP-', $replacementLead->lead_code);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'replacement.approved',
            'subject_id' => $replacement->id,
        ]);
    }
}
