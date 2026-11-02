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

    public function test_meta_webhook_get_without_params_returns_online_status(): void
    {
        $account = PortalAccount::create([
            'name' => 'Meta Live Account',
            'type' => 'meta',
            'status' => 'active',
        ]);

        $response = $this->get("/api/webhooks/meta/{$account->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'online',
            'portal_account_id' => $account->id,
        ]);
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

    public function test_sync_meta_leads_artisan_command_syncs_forms_and_leads(): void
    {
        $org = Organization::create(['name' => 'Brigade HQ', 'status' => 'active']);
        $client = Client::create(['name' => 'Brigade Group', 'organization_id' => $org->id]);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Brigade El Dorado']);

        $account = PortalAccount::create([
            'name' => 'Meta Automated Sync Account',
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
            'encrypted_value' => 'mock_sync_access_token',
        ]);

        Http::fake([
            'https://graph.facebook.com/v19.0/109283746501928/leadgen_forms*' => Http::response([
                'data' => [
                    ['id' => 'form_auto_7788', 'name' => 'Brigade Lead Form 1'],
                ]
            ], 200),
            'https://graph.facebook.com/v19.0/form_auto_7788/leads*' => Http::response([
                'data' => [
                    [
                        'id' => 'leadgen_7788_1',
                        'created_time' => '2026-08-17T12:00:00+0000',
                        'field_data' => [
                            ['name' => 'full_name', 'values' => ['Virat Kohli']],
                            ['name' => 'phone_number', 'values' => ['+919811122233']],
                            ['name' => 'email', 'values' => ['virat@example.com']],
                            ['name' => 'city', 'values' => ['Bengaluru']],
                        ]
                    ]
                ]
            ], 200),
        ]);

        $this->artisan('meta:sync-leads', ['--account' => $account->id])
            ->expectsOutputToContain('Meta sync complete: 1 new leads imported')
            ->assertExitCode(0);

        $lead = \App\Models\Lead::where('mobile', '919811122233')->first();
        $this->assertNotNull($lead);
        $this->assertEquals('Virat Kohli', $lead->name);
        $this->assertEquals('virat@example.com', $lead->email);
    }

    public function test_meta_pull_captures_all_campaign_ad_and_custom_question_metadata(): void
    {
        $org = Organization::create(['name' => 'Prestige HQ', 'type' => 'builder', 'status' => 'active']);
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole('Super Admin');

        $client = Client::create(['name' => 'Prestige Estates', 'organization_id' => $org->id]);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Prestige Highrise']);

        $account = PortalAccount::create([
            'name' => 'Meta Comprehensive Account',
            'type' => 'meta',
            'status' => 'active',
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'page_id',
            'encrypted_value' => '99887766554433',
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'access_token',
            'encrypted_value' => 'mock_full_meta_token',
        ]);

        Http::fake([
            'https://graph.facebook.com/v19.0/form_meta_full/leads*' => Http::response([
                'data' => [
                    [
                        'id' => 'leadgen_comp_9999',
                        'created_time' => '2026-10-29T14:30:00+0000',
                        'campaign_name' => 'Prestige Festive Sky Villas',
                        'campaign_id' => 'meta_camp_8899',
                        'adset_name' => 'HNI Highrise Target',
                        'adset_id' => 'meta_adset_77',
                        'ad_name' => 'Sunset Penthouse Video',
                        'ad_id' => 'meta_ad_66',
                        'form_id' => 'form_meta_full',
                        'field_data' => [
                            ['name' => 'full_name', 'values' => ['Deepika Padukone']],
                            ['name' => 'phone_number', 'values' => ['+919877788899']],
                            ['name' => 'email', 'values' => ['deepika@example.com']],
                            ['name' => 'city', 'values' => ['Bengaluru']],
                            ['name' => 'location', 'values' => ['Whitefield']],
                            ['name' => 'budget', 'values' => ['₹2.5 Cr - ₹3.5 Cr']],
                            ['name' => 'property_type', 'values' => ['4 BHK Penthouse']],
                            ['name' => 'when_planning_to_buy', 'values' => ['Immediate within 30 days']],
                        ]
                    ]
                ]
            ], 200),
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Leads\LeadKanban::class)
            ->set('selectedPortalAccountId', $account->id)
            ->set('customFormId', 'form_meta_full')
            ->set('syncProjectId', $project->id)
            ->call('pullMetaLeads')
            ->assertDispatched('toast');

        // Verify lead is created
        $lead = \App\Models\Lead::where('mobile', '919877788899')->first();
        $this->assertNotNull($lead);
        $this->assertEquals('Deepika Padukone', $lead->name);
        $this->assertEquals('deepika@example.com', $lead->email);
        $this->assertEquals('Bengaluru', $lead->city);
        $this->assertEquals('Whitefield', $lead->location);
        $this->assertEquals(25000000.00, (float) $lead->budget);
        $this->assertEquals('4 BHK Penthouse', $lead->property_type);

        // Verify Campaign auto-created and linked
        $campaign = \App\Models\Campaign::where('name', 'Prestige Festive Sky Villas')->first();
        $this->assertNotNull($campaign);
        $this->assertEquals($campaign->id, $lead->campaign_id);

        // Verify requirement contains custom answers
        $this->assertStringContainsString('Immediate within 30 days', $lead->requirement);
        $this->assertStringContainsString('Sunset Penthouse Video', $lead->requirement);

        // Verify LeadMetadata records
        $this->assertEquals('leadgen_comp_9999', $lead->getMetaAttribute('meta_leadgen_id'));
        $this->assertEquals('Prestige Festive Sky Villas', $lead->getMetaAttribute('meta_campaign_name'));
        $this->assertEquals('HNI Highrise Target', $lead->getMetaAttribute('meta_adset_name'));
        $this->assertEquals('Sunset Penthouse Video', $lead->getMetaAttribute('meta_ad_name'));
        $this->assertNotNull($lead->getMetaAttribute('meta_form_answers'));
        $this->assertNotNull($lead->getMetaAttribute('raw_json'));
    }

    public function test_lead_kanban_modal_contains_only_meta_ads_sync_and_removes_other_tabs(): void
    {
        $org = Organization::create(['name' => 'Tata HQ', 'type' => 'builder', 'status' => 'active']);
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Leads\LeadKanban::class)
            ->assertSee('+ Pull Meta Leads')
            ->assertSee('Meta Ads Sync')
            ->assertDontSee('Single Manual Lead')
            ->assertDontSee('Bulk CSV Upload');
    }

    public function test_fetch_lead_forms_follows_cursor_pagination_to_retrieve_all_forms(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $account = PortalAccount::create([
            'name' => 'High Volume Meta Account',
            'type' => 'meta',
            'status' => 'active',
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'page_id',
            'encrypted_value' => '9988112233',
        ]);
        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'access_token',
            'encrypted_value' => 'mock_token_multi_page',
        ]);

        Http::fake([
            'https://graph.facebook.com/v19.0/9988112233/leadgen_forms*' => Http::sequence()
                ->push([
                    'data' => array_map(fn($i) => ['id' => "form_page1_{$i}", 'name' => "Form Page 1 - #{$i}", 'status' => 'ACTIVE'], range(1, 100)),
                    'paging' => [
                        'cursors' => [
                            'before' => 'cursor_before_1',
                            'after' => 'cursor_after_page_1',
                        ]
                    ]
                ], 200)
                ->push([
                    'data' => [
                        ['id' => 'form_page2_01', 'name' => 'Form Page Two - Alpha', 'status' => 'ACTIVE'],
                        ['id' => 'form_page2_02', 'name' => 'Form Page Two - Beta', 'status' => 'ACTIVE'],
                    ],
                    'paging' => []
                ], 200),
        ]);

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->call('fetchLeadForms', $account->id)
            ->assertCount('availableForms', 102);

        $this->assertDatabaseHas('lead_form_mappings', ['form_id' => 'form_page1_1']);
        $this->assertDatabaseHas('lead_form_mappings', ['form_id' => 'form_page1_100']);
        $this->assertDatabaseHas('lead_form_mappings', ['form_id' => 'form_page2_01']);
        $this->assertDatabaseHas('lead_form_mappings', ['form_id' => 'form_page2_02']);
    }

    public function test_forms_table_has_sr_no_pagination_and_export(): void
    {
        \Maatwebsite\Excel\Facades\Excel::fake();

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $account = PortalAccount::create([
            'name' => 'Meta Ads Main Account',
            'type' => 'meta',
            'status' => 'active',
        ]);

        $forms = [];
        for ($i = 1; $i <= 35; $i++) {
            $forms[] = [
                'id' => "form_{$i}",
                'name' => "Property Tower Phase {$i}",
                'status' => 'ACTIVE',
            ];
        }

        $component = Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->set('availableForms', $forms);

        // Verify column layout: Sr No -> Campaign (Left) -> Form Name (Right) -> Client -> Sync (Project column removed)
        $columns = $component->instance()->availableFormsTableColumns();
        $colKeys = array_column($columns, 'key');
        $this->assertEquals(['sr_no', 'campaign', 'form_name', 'client', 'action'], $colKeys);

        // Verify pagination partitions into pages (page 1 has 15 items, total 35)
        $paginated = $component->instance()->filteredAvailableForms;
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $paginated);
        $this->assertEquals(35, $paginated->total());
        $this->assertEquals(15, $paginated->count());
        $this->assertEquals(1, $paginated->first()['_sr_no']);

        // Verify exportForms downloads Excel
        $component->call('exportForms');
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded("meta_lead_forms_" . now()->format('Y-m-d_His') . ".xlsx");
    }

    public function test_campaign_and_project_names_are_identical_to_meta_form_name_without_alteration(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $account = PortalAccount::create([
            'name' => 'Meta Exact Name Test Account',
            'type' => 'meta',
            'status' => 'active',
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'page_id',
            'encrypted_value' => '1419517495829988',
        ]);
        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'access_token',
            'encrypted_value' => 'mock_token_exact_names',
        ]);

        Http::fake([
            'https://graph.facebook.com/v19.0/1419517495829988/leadgen_forms*' => Http::response([
                'data' => [
                    ['id' => '1419517495829988', 'name' => 'Saya South X', 'status' => 'ACTIVE'],
                    ['id' => '1314553981741865', 'name' => 'Dholers Anjali Vatika-copy (v1)', 'status' => 'ACTIVE'],
                ],
                'paging' => []
            ], 200),
        ]);

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->call('fetchLeadForms', $account->id);

        // Verify Project name is EXACTLY identical to Meta form name
        $project1 = Project::where('name', 'Saya South X')->first();
        $this->assertNotNull($project1);

        $project2 = Project::where('name', 'Dholers Anjali Vatika-copy (v1)')->first();
        $this->assertNotNull($project2);

        // Verify fake duplicate campaigns are NOT created with the form's name
        $this->assertNull(Campaign::where('name', 'Saya South X')->first());
        $this->assertNull(Campaign::where('name', 'Dholers Anjali Vatika-copy (v1)')->first());

        // Verify lead sync stores the ACTUAL Meta campaign name
        Http::fake([
            'https://graph.facebook.com/v19.0/1419517495829988/leads*' => Http::response([
                'data' => [
                    [
                        'id' => 'lead_actual_camp_1',
                        'created_time' => '2026-11-02T16:00:00+0000',
                        'campaign_name' => 'Dubai Form Harshit 01-Top Investors',
                        'campaign_id' => 'camp_meta_dubai_99',
                        'field_data' => [
                            ['name' => 'full_name', 'values' => ['Harshit Mehta']],
                            ['name' => 'phone_number', 'values' => ['+919877712345']],
                        ]
                    ]
                ]
            ], 200),
        ]);

        $syncService = app(\App\Services\MetaLeadSyncService::class);
        $syncService->sync($account, '1419517495829988');

        // Verify the actual campaign from Meta Ads Manager is created and mapped
        $actualCamp = Campaign::where('name', 'Dubai Form Harshit 01-Top Investors')->first();
        $this->assertNotNull($actualCamp);
        $this->assertEquals('Dubai Form Harshit 01-Top Investors', $actualCamp->name);

        $mapping = LeadFormMapping::where('form_id', '1419517495829988')->first();
        $this->assertEquals($actualCamp->id, $mapping->campaign_id);
    }

    public function test_meta_lead_mapper_cleans_modern_form_structure_choices_and_questions(): void
    {
        $mapper = new \App\Support\LeadMappers\MetaLeadMapper();

        $rawLead = [
            'id' => 'leadgen_modern_7788',
            'created_time' => '2026-11-02T16:00:00+0000',
            'campaign_name' => 'Panchsheel Greens 2 Campaign',
            'adset_name' => 'Noida Extension Adset',
            'ad_name' => 'Green Vista 3BHK Video',
            'form_id' => '1144292554640400',
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Vikramaditya Rao']],
                ['name' => 'phone_number', 'values' => ['+919812345678']],
                ['name' => 'email', 'values' => ['vikram.rao@example.com']],
                ['name' => 'city', 'values' => ['Greater Noida West']],
                ['name' => 'preferred_location', 'values' => ['noida_expressway']],
                ['name' => 'what_is_your_preferred_configuration?', 'values' => ['3_bhk_']],
                ['name' => 'what_is_your_budget?', 'values' => ['1_cr_-_1.5_cr']],
                ['name' => 'when_are_you_planning_to_purchase?', 'values' => ['3–6_months']],
                ['name' => 'suitable_time_for_call', 'values' => ['☎️_12_noon_to_4pm']],
                ['name' => 'site_visit', 'values' => ['yes,_i_want_a_site_visit']],
                ['name' => 'whatsapp_preference', 'values' => ['only_want_details_on_whatsapp']],
            ]
        ];

        $mapped = $mapper->map($rawLead);

        $this->assertEquals('Vikramaditya Rao', $mapped['name']);
        $this->assertEquals('919812345678', $mapped['mobile']);
        $this->assertEquals('vikram.rao@example.com', $mapped['email']);
        $this->assertEquals('Greater Noida West', $mapped['city']);
        $this->assertEquals('Noida Expressway', $mapped['location']);
        $this->assertEquals('3 BHK', $mapped['property_type']);
        $this->assertEquals('1 Cr - 1.5 Cr', $mapped['budget']);

        // Verify budget parsing handles the format
        $budgetNum = \App\Services\MetaLeadSyncService::parseBudget($mapped['budget']);
        $this->assertEquals(10000000.00, $budgetNum);

        // Verify requirement contains clean question names and formatted answers without raw underscores
        $req = $mapped['requirement'];
        $this->assertStringContainsString('Property: 3 BHK', $req);
        $this->assertStringContainsString('Budget: 1 Cr - 1.5 Cr', $req);
        $this->assertStringContainsString('Location: Noida Expressway', $req);
        $this->assertStringContainsString('When Are You Planning To Purchase: 3–6 Months', $req);
        $this->assertStringContainsString('Suitable Time For Call: ☎️ 12 Noon To 4 PM', $req);
        $this->assertStringContainsString('Site Visit: Yes, I Want A Site Visit', $req);
        $this->assertStringContainsString('Whatsapp Preference: Only Want Details On WhatsApp', $req);
    }

    public function test_sync_meta_leads_filters_archived_forms_and_prevents_504_timeout(): void
    {
        $org = Organization::create(['name' => 'DLF Group', 'type' => 'builder', 'status' => 'active']);
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole('Super Admin');

        $account = PortalAccount::create([
            'name' => 'Active Only Sync Test Account',
            'type' => 'meta',
            'status' => 'active',
        ]);

        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'page_id',
            'encrypted_value' => 'page_active_filter_test',
        ]);
        IntegrationCredential::create([
            'portal_account_id' => $account->id,
            'key_name' => 'access_token',
            'encrypted_value' => 'mock_token_active_filter',
        ]);

        // Mock 1 ACTIVE form and 2 ARCHIVED forms
        Http::fake([
            'https://graph.facebook.com/v19.0/page_active_filter_test/leadgen_forms*' => Http::response([
                'data' => [
                    ['id' => 'form_active_101', 'name' => 'Active New Launch 2026', 'status' => 'ACTIVE'],
                    ['id' => 'form_old_archived_1', 'name' => 'Burari July form-March 2022', 'status' => 'ARCHIVED'],
                    ['id' => 'form_old_archived_2', 'name' => 'Website-10th Feb 2022', 'status' => 'ARCHIVED'],
                ],
                'paging' => []
            ], 200),
            'https://graph.facebook.com/v19.0/form_active_101/leads*' => Http::response([
                'data' => [
                    [
                        'id' => 'lead_active_1',
                        'created_time' => '2026-11-02T16:00:00+0000',
                        'field_data' => [
                            ['name' => 'full_name', 'values' => ['Kavita Sharma']],
                            ['name' => 'phone_number', 'values' => ['+919988771122']],
                            ['name' => 'email', 'values' => ['kavita@example.com']],
                        ]
                    ]
                ]
            ], 200),
        ]);

        $syncService = app(\App\Services\MetaLeadSyncService::class);
        $result = $syncService->sync($account);

        $this->assertTrue($result['success']);
        // Verify only the 1 ACTIVE form was synced, archived forms were bypassed
        $this->assertEquals(1, $result['total_forms']);
        $this->assertEquals(1, $result['total_created']);
        $this->assertCount(1, $result['forms']);
        $this->assertEquals('form_active_101', $result['forms'][0]['form_id']);
    }

    public function test_meta_lead_mapper_handles_user_exact_dholera_samples(): void
    {
        $mapper = new \App\Support\LeadMappers\MetaLeadMapper();

        // Sample 1: Jagbir Singh Mehla with all choices, Hindi/WhatsApp, 12_noon_to_4pm, Remarks
        $sample1 = [
            'id' => 'lead_sample_1',
            'created_time' => '10/31/26',
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Jagbir Singh Mehla']],
                ['name' => 'phone_number', 'values' => ['919878000231']],
                ['name' => 'would_you_like_to_schedule_a_site_visit_to_dholera?', 'values' => ['only_want_details_on_whatsapp']],
                ['name' => 'when_are_you_planning_to_purchase?', 'values' => ['immediately_(0–30_days)']],
                ['name' => 'suitable_time_for_call_☎️_', 'values' => ['12_noon_to_4pm']],
                ['name' => 'Remarks', 'values' => ['call not received']],
            ]
        ];

        $mapped1 = $mapper->map($sample1);
        $this->assertEquals('Jagbir Singh Mehla', $mapped1['name']);
        $this->assertEquals('919878000231', $mapped1['mobile']);
        $this->assertNull($mapped1['location']);
        $this->assertStringContainsString('Would You Like To Schedule A Site Visit To Dholera: Only Want Details On WhatsApp', $mapped1['requirement']);
        $this->assertStringContainsString('When Are You Planning To Purchase: Immediately (0–30 Days)', $mapped1['requirement']);
        $this->assertStringContainsString('Suitable Time For Call ☎️: 12 Noon To 4 PM', $mapped1['requirement']);
        $this->assertStringContainsString('Remarks: Call Not Received', $mapped1['requirement']);

        // Sample 2: दलवीर मुजरी दलवीरआदिवासी मुजरा (Hindi Unicode name preserved + before_9am)
        $sample2 = [
            'id' => 'lead_sample_2',
            'created_time' => '10/31/26',
            'field_data' => [
                ['name' => 'full_name', 'values' => ['दलवीर मुजरी दलवीरआदिवासी मुजरा']],
                ['name' => 'phone_number', 'values' => ['919664020086']],
                ['name' => 'would_you_like_to_schedule_a_site_visit_to_dholera?', 'values' => ['yes,_i_want_a_site_visit']],
                ['name' => 'when_are_you_planning_to_purchase?', 'values' => ['immediately_(0–30_days)']],
                ['name' => 'suitable_time_for_call_☎️_', 'values' => ['before_9am']],
            ]
        ];

        $mapped2 = $mapper->map($sample2);
        $this->assertEquals('दलवीर मुजरी दलवीरआदिवासी मुजरा', $mapped2['name']);
        $this->assertEquals('919664020086', $mapped2['mobile']);
        $this->assertStringContainsString('Would You Like To Schedule A Site Visit To Dholera: Yes, I Want A Site Visit', $mapped2['requirement']);
        $this->assertStringContainsString('Suitable Time For Call ☎️: Before 9 AM', $mapped2['requirement']);

        // Sample 3: Yadvendra Pratap Singhll with trailing underscore in 9am_to_11am_
        $sample3 = [
            'id' => 'lead_sample_3',
            'created_time' => '10/31/26',
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Yadvendra Pratap Singhll']],
                ['name' => 'phone_number', 'values' => ['919336774627']],
                ['name' => 'suitable_time_for_call_☎️_', 'values' => ['9am_to_11am_']],
            ]
        ];

        $mapped3 = $mapper->map($sample3);
        $this->assertEquals('Yadvendra Pratap Singhll', $mapped3['name']);
        $this->assertStringContainsString('Suitable Time For Call ☎️: 9 AM To 11 AM', $mapped3['requirement']);

        // Sample 4: Purushottam Ganga with just_exploring and 4pm_to_6pm
        $sample4 = [
            'id' => 'lead_sample_4',
            'created_time' => '10/31/26',
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Purushottam Ganga']],
                ['name' => 'phone_number', 'values' => ['917070848787']],
                ['name' => 'when_are_you_planning_to_purchase?', 'values' => ['just_exploring']],
                ['name' => 'suitable_time_for_call_☎️_', 'values' => ['4pm_to_6pm']],
                ['name' => 'Remarks', 'values' => ['call him at monday']],
            ]
        ];

        $mapped4 = $mapper->map($sample4);
        $this->assertStringContainsString('When Are You Planning To Purchase: Just Exploring', $mapped4['requirement']);
        $this->assertStringContainsString('Suitable Time For Call ☎️: 4 PM To 6 PM', $mapped4['requirement']);
        $this->assertStringContainsString('Remarks: Call Him At Monday', $mapped4['requirement']);
    }
}
