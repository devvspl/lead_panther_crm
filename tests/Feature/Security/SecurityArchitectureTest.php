<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\PortalAccount;
use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class SecurityArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Builder']);
        Role::create(['name' => 'Channel Partner']);
        Role::create(['name' => 'Account Manager']);
    }

    public function test_security_headers_middleware_appends_headers(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
    }

    public function test_client_scoped_global_scope_isolates_leads(): void
    {
        $org1 = Organization::create(['name' => 'Org 1', 'type' => 'builder']);
        $client1 = Client::create(['organization_id' => $org1->id, 'name' => 'Client 1']);
        $project1 = Project::create(['client_id' => $client1->id, 'name' => 'Project 1']);

        $org2 = Organization::create(['name' => 'Org 2', 'type' => 'builder']);
        $client2 = Client::create(['organization_id' => $org2->id, 'name' => 'Client 2']);
        $project2 = Project::create(['client_id' => $client2->id, 'name' => 'Project 2']);

        $source = LeadSource::create(['name' => 'meta']);

        $user1 = User::factory()->create(['organization_id' => $org1->id]);
        $user1->assignRole('Account Manager');

        $lead1 = Lead::factory()->create(['client_id' => $client1->id, 'project_id' => $project1->id, 'lead_source_id' => $source->id]);
        $lead2 = Lead::factory()->create(['client_id' => $client2->id, 'project_id' => $project2->id, 'lead_source_id' => $source->id]);

        // Querying Lead::all() as User 1 must only return tenant 1's lead
        $this->actingAs($user1);
        $retrievedLeads = Lead::all();

        $this->assertCount(1, $retrievedLeads);
        $this->assertEquals($lead1->id, $retrievedLeads->first()->id);
    }

    public function test_super_admin_bypasses_client_scoped_global_scope(): void
    {
        $org1 = Organization::create(['name' => 'Org A', 'type' => 'builder']);
        $client1 = Client::create(['organization_id' => $org1->id, 'name' => 'Client A']);
        $project1 = Project::create(['client_id' => $client1->id, 'name' => 'Project A']);

        $org2 = Organization::create(['name' => 'Org B', 'type' => 'builder']);
        $client2 = Client::create(['organization_id' => $org2->id, 'name' => 'Client B']);
        $project2 = Project::create(['client_id' => $client2->id, 'name' => 'Project B']);

        $source = LeadSource::create(['name' => 'google']);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Lead::factory()->create(['client_id' => $client1->id, 'project_id' => $project1->id, 'lead_source_id' => $source->id]);
        Lead::factory()->create(['client_id' => $client2->id, 'project_id' => $project2->id, 'lead_source_id' => $source->id]);

        $this->actingAs($admin);
        $allLeads = Lead::all();

        $this->assertCount(2, $allLeads);
    }

    public function test_webhook_logs_redacts_sensitive_keys(): void
    {
        $org = Organization::create(['name' => 'Org Sec Log', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Sec Log']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Sec Log']);
        $source = LeadSource::create(['name' => 'meta']);

        $portalAccount = PortalAccount::create(['name' => 'Meta Test Acc', 'type' => 'meta']);

        $response = $this->postJson("/api/webhooks/meta/{$portalAccount->id}", [
            'name' => 'John Doe',
            'mobile' => '9876543210',
            'access_token' => 'mock_secret_token_12345',
            'app_secret' => 'super_secret_app_key',
        ]);

        $response->assertOk();

        $log = WebhookLog::where('portal_account_id', $portalAccount->id)->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('mock_secret_token_12345', $log->payload);
        $this->assertStringContainsString('[REDACTED_SECRET]', $log->payload);
    }

    public function test_unauthorized_role_gets_403_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Channel Partner');

        $response = $this->actingAs($user)->get('/admin/dashboard');
        $response->assertStatus(403);
    }
}
