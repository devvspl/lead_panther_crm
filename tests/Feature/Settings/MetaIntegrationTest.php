<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\PortalAccount;
use App\Models\IntegrationCredential;
use App\Models\Project;
use App\Models\Campaign;
use App\Models\LeadFormMapping;
use App\Livewire\Settings\IntegrationsManager;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

class MetaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Super Admin']);
        Role::firstOrCreate(['name' => 'Builder']);
    }

    public function test_meta_integration_view_renders_meta_fields(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->assertSee('Webhook Integrations')
            ->assertSee('Facebook Page ID')
            ->assertSee('Meta App ID')
            ->assertSee('Meta App Secret')
            ->assertSee('Page Access Token')
            ->assertSee('Webhook Verify Token')
            ->assertSee('/api/webhooks/meta/');
    }

    public function test_saves_all_five_meta_credentials_encrypted_at_rest(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $pageId = '109283746501928';
        $appId = '998877665544332';
        $appSecret = 'sec_meta_secret_hash_9988';
        $token = 'mock_system_user_token_long_lived';
        $verifyToken = 'custom_verify_token_32chars';

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->set('portalType', 'meta')
            ->set('accountName', 'Luxury Tower Meta Ads')
            ->set('metaPageId', $pageId)
            ->set('metaAppId', $appId)
            ->set('metaAppSecret', $appSecret)
            ->set('metaAccessToken', $token)
            ->set('metaVerifyToken', $verifyToken)
            ->call('saveAccount')
            ->assertDispatched('toast');

        $account = PortalAccount::where('name', 'Luxury Tower Meta Ads')->first();
        $this->assertNotNull($account);
        $this->assertEquals('meta', $account->type);

        $this->assertEquals($pageId, $account->getCredential('page_id'));
        $this->assertEquals($appId, $account->getCredential('app_id'));
        $this->assertEquals($appSecret, $account->getCredential('app_secret'));
        $this->assertEquals($token, $account->getCredential('access_token'));
        $this->assertEquals($verifyToken, $account->getCredential('verify_token'));

        // Verify raw database values are encrypted
        $rawSecret = \DB::table('integration_credentials')
            ->where('portal_account_id', $account->id)
            ->where('key_name', 'app_secret')
            ->value('encrypted_value');

        $this->assertNotEquals($appSecret, $rawSecret);
    }

    public function test_test_connection_successful_meta_graph_api(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $pageId = '109283746501928';
        $token = 'mock_valid_access_token';

        Http::fake([
            "https://graph.facebook.com/v19.0/{$pageId}*" => Http::response([
                'id' => $pageId,
                'name' => 'Prestige Horizon Luxury Residences',
                'access_token' => $token,
            ], 200),
        ]);

        $account = PortalAccount::create([
            'name' => 'Prestige Meta Account',
            'type' => 'meta',
            'status' => 'active',
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'page_id',
            'encrypted_value' => $pageId,
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'access_token',
            'encrypted_value' => $token,
        ]);

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->call('testConnection', $account->id)
            ->assertDispatched('toast');

        $account->refresh();
        $this->assertEquals('healthy', $account->health_status);
        $this->assertStringContainsString('Prestige Horizon Luxury Residences', $account->health_message);
    }

    public function test_test_connection_reports_meta_graph_api_error(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $pageId = 'invalid_page_123';
        $token = 'expired_token_xyz';

        Http::fake([
            "https://graph.facebook.com/v19.0/{$pageId}*" => Http::response([
                'error' => [
                    'message' => 'Error validating access token: Session has expired.',
                    'type' => 'OAuthException',
                    'code' => 190,
                ]
            ], 400),
        ]);

        $account = PortalAccount::create([
            'name' => 'Expired Meta Account',
            'type' => 'meta',
            'status' => 'active',
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'page_id',
            'encrypted_value' => $pageId,
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'access_token',
            'encrypted_value' => $token,
        ]);

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->call('testConnection', $account->id);

        $account->refresh();
        $this->assertEquals('error', $account->health_status);
        $this->assertStringContainsString('Error validating access token: Session has expired', $account->health_message);
    }

    public function test_lead_form_mapping_assignment(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Lodha Org', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Lodha Group']);
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Lodha Park',
            'location' => 'Worli',
            'status' => 'active',
        ]);
        $leadSource = \App\Models\LeadSource::firstOrCreate(['name' => 'meta']);
        $campaign = Campaign::create([
            'project_id' => $project->id,
            'lead_source_id' => $leadSource->id,
            'name' => 'Diwali 2026 Special',
            'status' => 'active',
        ]);

        $account = PortalAccount::create([
            'name' => 'Lodha Meta Ads',
            'type' => 'meta',
            'status' => 'active',
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'page_id',
            'encrypted_value' => '1029384756',
        ]);

        Http::fake([
            'https://graph.facebook.com/v19.0/1029384756/leadgen_forms*' => Http::response([
                'data' => [
                    [
                        'id' => 'form_12345678',
                        'name' => 'Lodha Park 3BHK Brochure Download Form',
                        'status' => 'ACTIVE',
                    ]
                ]
            ], 200),
        ]);

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->call('fetchLeadForms', $account->id)
            ->set('formProjectMap.form_12345678', $project->id)
            ->set('formCampaignMap.form_12345678', $campaign->id)
            ->call('saveFormMapping', 'form_12345678', 'Lodha Park 3BHK Brochure Download Form');

        $mapping = LeadFormMapping::where('portal_account_id', $account->id)
            ->where('form_id', 'form_12345678')
            ->first();

        $this->assertNotNull($mapping);
        $this->assertEquals($project->id, $mapping->project_id);
        $this->assertEquals($campaign->id, $mapping->campaign_id);
    }

    public function test_saves_meta_credentials_with_page_id_and_token_only(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->set('portalType', 'meta')
            ->set('accountName', 'Page Explorer Connection')
            ->set('metaPageId', '109283746501928')
            ->set('metaAccessToken', 'mock_page_token_abc_1234567890')
            ->set('metaVerifyToken', 'mock_verify_token_dummy_xyz123')
            ->call('saveAccount')
            ->assertDispatched('toast');

        $account = PortalAccount::where('name', 'Page Explorer Connection')->first();
        $this->assertNotNull($account);
        $this->assertEquals('109283746501928', $account->getCredential('page_id'));
        $this->assertEquals('mock_verify_token_dummy_xyz123', $account->getCredential('verify_token'));
    }

    public function test_meta_webhook_get_verification_successful(): void
    {
        $account = PortalAccount::create([
            'name' => 'Meta Live Account',
            'type' => 'meta',
            'status' => 'active',
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'verify_token',
            'encrypted_value' => 'my_secret_verify_token_123',
        ]);

        $response = $this->get("/api/webhooks/meta/{$account->id}?hub.mode=subscribe&hub.verify_token=my_secret_verify_token_123&hub.challenge=1122334455");

        $response->assertStatus(200);
        $this->assertEquals('1122334455', $response->getContent());
    }

    public function test_meta_webhook_get_verification_fails_with_invalid_token(): void
    {
        $account = PortalAccount::create([
            'name' => 'Meta Live Account',
            'type' => 'meta',
            'status' => 'active',
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'verify_token',
            'encrypted_value' => 'my_secret_verify_token_123',
        ]);

        $response = $this->get("/api/webhooks/meta/{$account->id}?hub.mode=subscribe&hub.verify_token=WRONG_TOKEN&hub.challenge=1122334455");

        $response->assertStatus(403);
    }

    public function test_meta_webhook_post_lead_event_success(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $account = PortalAccount::create([
            'name' => 'Meta Live Account',
            'type' => 'meta',
            'status' => 'active',
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'app_secret',
            'encrypted_value' => 'meta_app_secret_hash',
        ]);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => '109283746501928',
                    'time' => 1718000000,
                    'changes' => [
                        [
                            'field' => 'leadgen',
                            'value' => [
                                'leadgen_id' => 'lead_99887766',
                                'page_id' => '109283746501928',
                                'form_id' => 'form_12345678',
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postJson("/api/webhooks/meta/{$account->id}", $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'queued']);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ProcessInboundLeadJob::class);
    }

    public function test_privacy_policy_page_is_publicly_accessible(): void
    {
        $response = $this->get('/privacy-policy');

        $response->assertStatus(200);
        $response->assertSee('Privacy Policy');
        $response->assertSee('Lead Panther CRM');
        $response->assertSee('Meta (Facebook', false);
        $response->assertSee('User Data Deletion Instructions');
    }

    public function test_lead_kanban_manual_pull_meta_leads(): void
    {
        $org = Organization::create(['name' => 'Lodha HQ', 'type' => 'builder', 'status' => 'active']);
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole('Super Admin');

        $client = Client::create(['name' => 'Lodha Group', 'organization_id' => $org->id]);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Lodha Crown']);

        $account = PortalAccount::create([
            'name' => 'Meta Main Ads',
            'type' => 'meta',
            'status' => 'active',
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'page_id',
            'encrypted_value' => '109283746501928',
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'access_token',
            'encrypted_value' => 'mock_test_access_token',
        ]);

        Http::fake([
            'https://graph.facebook.com/v19.0/form_meta_9988/leads*' => Http::response([
                'data' => [
                    [
                        'id' => 'leadgen_1001',
                        'created_time' => '2026-08-17T12:00:00+0000',
                        'field_data' => [
                            ['name' => 'full_name', 'values' => ['Karan Johar']],
                            ['name' => 'phone_number', 'values' => ['+919876543210']],
                            ['name' => 'email', 'values' => ['karan@example.com']],
                            ['name' => 'city', 'values' => ['Mumbai']],
                        ]
                    ]
                ]
            ], 200),
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Leads\LeadKanban::class)
            ->set('selectedPortalAccountId', $account->id)
            ->set('customFormId', 'form_meta_9988')
            ->set('syncProjectId', $project->id)
            ->call('pullMetaLeads')
            ->assertDispatched('toast');

        $lead = \App\Models\Lead::where('mobile', '919876543210')->first();
        $this->assertNotNull($lead);
        $this->assertEquals('Karan Johar', $lead->name);
        $this->assertEquals($project->id, $lead->project_id);
    }

    public function test_lead_kanban_creates_single_manual_lead(): void
    {
        $org = Organization::create(['name' => 'Godrej HQ', 'type' => 'builder', 'status' => 'active']);
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole('Super Admin');

        $client = Client::create(['name' => 'Godrej Properties', 'organization_id' => $org->id]);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Godrej Woods']);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Leads\LeadKanban::class)
            ->set('manualName', 'Rohit Sharma')
            ->set('manualMobile', '9988776655')
            ->set('manualEmail', 'rohit@hitman.com')
            ->set('manualProjectId', $project->id)
            ->set('manualPropertyType', '3 BHK')
            ->set('manualBudget', '₹1.5Cr')
            ->call('createManualLead')
            ->assertDispatched('toast');

        $lead = \App\Models\Lead::where('mobile', '9988776655')->first();
        $this->assertNotNull($lead);
        $this->assertEquals('Rohit Sharma', $lead->name);
        $this->assertEquals($project->id, $lead->project_id);
    }
}
