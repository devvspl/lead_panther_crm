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
        $token = 'EAABw_system_user_token_long_lived';
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
        $token = 'EAABw_valid_token';

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
}
