<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\PortalAccount;
use App\Models\Lead;
use App\Models\Client;
use App\Models\Project;
use App\Models\CreditWallet;
use App\Models\WebhookLog;
use App\Jobs\ProcessInboundLeadJob;
use App\Livewire\Admin\WebhookLogs;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class InboundLeadPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'super-admin']);
    }

    public function test_valid_meta_webhook_creates_lead_and_logs_payload(): void
    {
        $org = Organization::create(['name' => 'Org 1', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Test Client']);
        Project::create(['client_id' => $client->id, 'name' => 'Test Project']);
        CreditWallet::create(['client_id' => $client->id, 'balance' => 100.00]);

        $account = PortalAccount::create(['name' => 'Meta Test Account', 'type' => 'meta']);

        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/webhooks/meta.json')), true);

        $response = $this->postJson("/api/webhooks/meta/{$account->id}", $fixture);

        $response->assertOk();
        $response->assertJsonStructure(['status', 'log_id']);

        $logId = $response->json('log_id');
        $log = WebhookLog::find($logId);
        $this->assertNotNull($log);

        // Process job
        ProcessInboundLeadJob::dispatchSync($log);

        $this->assertDatabaseHas('leads', [
            'mobile' => '9811223344',
            'client_id' => $client->id,
        ]);
    }

    public function test_duplicate_lead_within_90_days_does_not_create_new_lead_row(): void
    {
        $org = Organization::create(['name' => 'Org 2', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Test Client Dup']);
        Project::create(['client_id' => $client->id, 'name' => 'Test Project Dup']);
        CreditWallet::create(['client_id' => $client->id, 'balance' => 100.00]);

        $account = PortalAccount::create(['name' => 'Google Test Account', 'type' => 'google']);
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/webhooks/google.json')), true);

        // First ingestion
        $res1 = $this->postJson("/api/webhooks/google/{$account->id}", $fixture);
        $res1->assertOk();
        $log1 = WebhookLog::find($res1->json('log_id'));
        $this->assertNotNull($log1);
        ProcessInboundLeadJob::dispatchSync($log1);

        $leadCountBefore = Lead::where('mobile', '9822334455')->count();
        $this->assertEquals(1, $leadCountBefore);

        // Duplicate ingestion (same mobile)
        $res2 = $this->postJson("/api/webhooks/google/{$account->id}", $fixture);
        $res2->assertOk();
        $log2 = WebhookLog::find($res2->json('log_id'));
        $this->assertNotNull($log2);
        ProcessInboundLeadJob::dispatchSync($log2);

        $leadCountAfter = Lead::where('mobile', '9822334455')->count();
        $this->assertEquals(1, $leadCountAfter); // Count remains 1!
    }

    public function test_invalid_signature_returns_401_and_still_logs_webhook(): void
    {
        $account = PortalAccount::create(['name' => 'Portal Account Invalid', 'type' => 'portal']);
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/webhooks/portal.json')), true);

        $response = $this->withHeaders(['X-Portal-API-Key' => 'invalid_key'])
            ->postJson("/api/webhooks/portal/{$account->id}", $fixture);

        $response->assertStatus(401);
        $this->assertDatabaseHas('webhook_logs', [
            'portal_account_id' => $account->id,
        ]);
    }

    public function test_admin_webhook_logs_view_renders_and_retries_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Org 3', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Test Client Retries']);
        Project::create(['client_id' => $client->id, 'name' => 'Test Project Retries']);

        $account = PortalAccount::create(['name' => 'Owned Account', 'type' => 'owned']);
        $log = WebhookLog::create([
            'portal_account_id' => $account->id,
            'payload' => file_get_contents(base_path('tests/Fixtures/webhooks/owned.json')),
            'received_at' => now(),
            'processed' => false,
        ]);

        $response = $this->actingAs($admin)->get('/admin/webhook-logs');
        $response->assertOk();

        Livewire::actingAs($admin)
            ->test(WebhookLogs::class)
            ->call('retryLog', $log->id);

        $this->assertEquals(1, (int) $log->fresh()->processed);
    }
}
