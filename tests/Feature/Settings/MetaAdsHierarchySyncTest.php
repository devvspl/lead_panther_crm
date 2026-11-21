<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\MetaAdAccounts;
use App\Models\{LeadFormMapping, MetaAdAccount, MetaAdRecord, PortalAccount, User};
use App\Services\MetaAdAccountService;
use App\Services\MetaFormSyncService;
use App\Services\MetaLeadSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MetaAdsHierarchySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_hierarchy_sync_fetches_campaigns_adsets_ads_and_insights(): void
    {
        $page = PortalAccount::create([
            'name' => 'Test Page',
            'type' => 'meta',
            'status' => 'active',
        ]);
        $page->setCredential('page_id', '123456');
        $page->setCredential('access_token', 'page-token');

        // Pre-create a lead form mapping
        LeadFormMapping::create([
            'portal_account_id' => $page->id,
            'form_id' => 'form_777',
            'form_name' => 'Luxury Villa Form',
            'status' => 'ACTIVE',
        ]);

        $account = MetaAdAccount::create([
            'portal_account_id' => $page->id,
            'name' => 'Main Ads',
            'ad_account_id' => '987654',
            'access_token' => 'act-token',
            'status' => 'connected',
        ]);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/promote_pages')) {
                return Http::response(['data' => [['id' => '123456', 'name' => 'Test Page']]]);
            }
            if (str_contains($url, '/campaigns')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'camp_101',
                            'name' => 'Q4 Festive Launch',
                            'status' => 'ACTIVE',
                            'effective_status' => 'ACTIVE',
                            'objective' => 'OUTCOME_LEADS',
                            'buying_type' => 'AUCTION',
                            'daily_budget' => '500000',
                        ],
                    ],
                ]);
            }
            if (str_contains($url, '/adsets')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'adset_201',
                            'campaign_id' => 'camp_101',
                            'name' => 'HNI Investors 35-55',
                            'status' => 'ACTIVE',
                            'effective_status' => 'ACTIVE',
                            'optimization_goal' => 'LEAD_GENERATION',
                            'billing_event' => 'IMPRESSIONS',
                            'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP',
                            'daily_budget' => '300000',
                        ],
                    ],
                ]);
            }
            if (str_contains($url, '/ads')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'ad_301',
                            'campaign_id' => 'camp_101',
                            'adset_id' => 'adset_201',
                            'name' => 'Villa Video Showcase',
                            'status' => 'ACTIVE',
                            'effective_status' => 'ACTIVE',
                            'creative' => [
                                'id' => 'cr_401',
                                'name' => 'Villa Showcase Creative',
                                'title' => 'Own a Luxury Villa',
                                'body' => 'Limited 4 BHK residences starting ₹3.5 Cr',
                                'thumbnail_url' => 'https://example.com/thumb.jpg',
                                'asset_feed_spec' => [
                                    'link_urls' => [
                                        ['lead_gen_form_id' => 'form_777'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);
            }
            if (str_contains($url, '/insights')) {
                return Http::response([
                    'data' => [
                        [
                            'account_id' => '987654',
                            'campaign_id' => 'camp_101',
                            'campaign_name' => 'Q4 Festive Launch',
                            'date_start' => '2026-11-20',
                            'date_stop' => '2026-11-20',
                            'impressions' => '15400',
                            'reach' => '12100',
                            'clicks' => '420',
                            'inline_link_clicks' => '380',
                            'spend' => '3500.50',
                            'cpm' => '227.30',
                            'cpc' => '8.33',
                            'ctr' => '2.73',
                            'frequency' => '1.27',
                            'actions' => [
                                ['action_type' => 'lead', 'value' => '24'],
                            ],
                            'cost_per_action_type' => [
                                ['action_type' => 'lead', 'value' => '145.85'],
                            ],
                        ],
                    ],
                ]);
            }
            return Http::response(['data' => []]);
        });

        $service = app(MetaAdAccountService::class);
        $service->sync($account);

        // Verify all 4 edges stored
        $campaignRecord = MetaAdRecord::where('meta_ad_account_id', $account->id)->where('kind', 'campaign')->first();
        $this->assertNotNull($campaignRecord);
        $this->assertEquals('camp_101', $campaignRecord->remote_id);
        $this->assertEquals('OUTCOME_LEADS', $campaignRecord->objective);
        $this->assertEquals('Daily: ₹5,000.00', $campaignRecord->budget_display);

        $adsetRecord = MetaAdRecord::where('meta_ad_account_id', $account->id)->where('kind', 'adset')->first();
        $this->assertNotNull($adsetRecord);
        $this->assertEquals('adset_201', $adsetRecord->remote_id);
        $this->assertEquals('camp_101', $adsetRecord->campaign_id);

        $adRecord = MetaAdRecord::where('meta_ad_account_id', $account->id)->where('kind', 'ad')->first();
        $this->assertNotNull($adRecord);
        $this->assertEquals('ad_301', $adRecord->remote_id);
        $this->assertEquals('form_777', $adRecord->lead_form_id);
        $this->assertEquals('cr_401', $adRecord->creative_id);
        $this->assertEquals('https://example.com/thumb.jpg', $adRecord->creative_thumbnail);
        $this->assertEquals('Own a Luxury Villa', $adRecord->creative_title);
        $this->assertEquals('form_777', $adRecord->leadForm?->form_id);

        $insightRecord = MetaAdRecord::where('meta_ad_account_id', $account->id)->where('kind', 'insight')->first();
        $this->assertNotNull($insightRecord);
        $this->assertEquals(15400, $insightRecord->impressions);
        $this->assertEquals(12100, $insightRecord->reach);
        $this->assertEquals(420, $insightRecord->clicks);
        $this->assertEquals(380, $insightRecord->link_clicks);
        $this->assertEquals(3500.50, $insightRecord->spend);
        $this->assertEquals(24, $insightRecord->leads_count);
        $this->assertEquals(145.85, $insightRecord->cost_per_lead);
    }

    public function test_form_discovery_captures_form_questions_and_locale(): void
    {
        $page = PortalAccount::create([
            'name' => 'Page with Questions',
            'type' => 'meta',
            'status' => 'active',
        ]);
        $page->setCredential('page_id', '998877');
        $page->setCredential('access_token', 'page-token');

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/leadgen_forms')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'form_with_q',
                            'name' => 'Luxury Homes Registration',
                            'status' => 'ACTIVE',
                            'locale' => 'en_US',
                            'leads_count' => 54,
                            'questions' => [
                                ['key' => 'full_name', 'label' => 'Full Name', 'type' => 'FULL_NAME'],
                                ['key' => 'phone_number', 'label' => 'Phone Number', 'type' => 'PHONE'],
                                ['key' => 'budget', 'label' => 'Preferred Budget', 'type' => 'CUSTOM', 'options' => [
                                    ['key' => '1cr_2cr', 'value' => '1 Cr - 2 Cr'],
                                    ['key' => '2cr_plus', 'value' => '2 Cr+'],
                                ]],
                            ],
                        ],
                    ],
                ]);
            }
            return Http::response(['data' => []]);
        });

        $service = app(MetaFormSyncService::class);
        $result = $service->fetch($page);

        $this->assertEquals('completed', $result['status']);
        $mapping = LeadFormMapping::where('portal_account_id', $page->id)->where('form_id', 'form_with_q')->first();
        $this->assertNotNull($mapping);
        $this->assertIsArray($mapping->questions);
        $this->assertCount(3, $mapping->questions);
        $this->assertEquals('Preferred Budget', $mapping->questions[2]['label']);
    }

    public function test_livewire_meta_ad_accounts_component_filters_and_synchronizes_insights(): void
    {
        Role::firstOrCreate(['name' => 'Super Admin']);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $page = PortalAccount::create(['name' => 'Livewire Page', 'type' => 'meta']);
        $account = MetaAdAccount::create([
            'portal_account_id' => $page->id,
            'name' => 'Livewire Ads',
            'ad_account_id' => '112233',
            'status' => 'connected',
            'access_token' => 'livewire-token',
        ]);

        // Create campaign, adset, ad, and insight records
        MetaAdRecord::create([
            'meta_ad_account_id' => $account->id,
            'ad_account_id' => '112233',
            'kind' => 'campaign',
            'remote_id' => 'c_1',
            'name' => 'Searchable Campaign',
            'status' => 'ACTIVE',
            'effective_status' => 'ACTIVE',
            'data' => ['objective' => 'LEAD_GENERATION'],
            'synced_at' => now(),
        ]);

        MetaAdRecord::create([
            'meta_ad_account_id' => $account->id,
            'ad_account_id' => '112233',
            'kind' => 'adset',
            'remote_id' => 'as_1',
            'name' => 'Target Set 1',
            'campaign_id' => 'c_1',
            'status' => 'ACTIVE',
            'effective_status' => 'ACTIVE',
            'data' => ['optimization_goal' => 'LEAD_GENERATION'],
            'synced_at' => now(),
        ]);

        MetaAdRecord::create([
            'meta_ad_account_id' => $account->id,
            'ad_account_id' => '112233',
            'kind' => 'ad',
            'remote_id' => 'ad_1',
            'name' => 'Creative Ad 1',
            'campaign_id' => 'c_1',
            'adset_id' => 'as_1',
            'status' => 'PAUSED',
            'effective_status' => 'PAUSED',
            'lead_form_id' => 'form_888',
            'data' => ['creative' => ['title' => 'Ad Title 1']],
            'synced_at' => now(),
        ]);

        MetaAdRecord::create([
            'meta_ad_account_id' => $account->id,
            'ad_account_id' => '112233',
            'kind' => 'insight',
            'insight_level' => 'campaign',
            'remote_id' => 'c_1',
            'name' => 'Searchable Campaign',
            'report_date' => now()->toDateString(),
            'data' => ['impressions' => 5000, 'spend' => 120.50, 'parsed_leads' => 10],
            'synced_at' => now(),
        ]);

        // Test Livewire component interactions
        Livewire::actingAs($admin)
            ->test(MetaAdAccounts::class, ['integrationId' => $page->id])
            ->call('showRecords', $account->id)
            ->assertSee('Searchable Campaign')
            ->set('kind', 'ad')
            ->assertSee('Creative Ad 1')
            ->assertSee('Form: form_888')
            ->set('statusFilter', 'ACTIVE')
            ->assertDontSee('Creative Ad 1') // Ad is PAUSED
            ->set('statusFilter', 'PAUSED')
            ->assertSee('Creative Ad 1')
            ->set('kind', 'insight')
            ->assertSee('5,000 imp')
            ->assertSee('10 Leads');
    }

    public function test_leads_are_passed_according_to_form_client_mapping_without_crm_campaign_override(): void
    {
        $org = \App\Models\Organization::create(['name' => 'Elite Realty Org', 'status' => 'active']);
        $clientA = \App\Models\Client::create(['name' => 'Mapped Client Alpha', 'organization_id' => $org->id]);
        $clientB = \App\Models\Client::create(['name' => 'Unrelated Client Beta', 'organization_id' => $org->id]);

        $projectA = \App\Models\Project::create(['client_id' => $clientA->id, 'name' => 'Skyline Heights']);
        $projectB = \App\Models\Project::create(['client_id' => $clientB->id, 'name' => 'Ocean Vista']);

        $page = PortalAccount::create([
            'name' => 'Client Routing Page',
            'type' => 'meta',
            'status' => 'active',
        ]);
        $page->setCredential('page_id', '889900');
        $page->setCredential('access_token', 'page-routing-token');

        // Form is mapped directly to Client A
        $formMapping = LeadFormMapping::create([
            'portal_account_id' => $page->id,
            'form_id' => 'form_mapped_to_a',
            'form_name' => 'Skyline Inquiries',
            'client_id' => $clientA->id,
            'status' => 'active',
        ]);

        Http::fake([
            '*/889900/leadgen_forms*' => Http::response([
                'data' => [
                    ['id' => 'form_mapped_to_a', 'name' => 'Skyline Inquiries', 'status' => 'ACTIVE'],
                ],
            ]),
            '*/form_mapped_to_a/leads*' => Http::response([
                'data' => [
                    [
                        'id' => 'lead_meta_routed_1',
                        'created_time' => '2026-11-21T10:00:00+0000',
                        'campaign_name' => 'Prestige Luxury Festival Campaign',
                        'campaign_id' => 'meta_camp_999',
                        'field_data' => [
                            ['name' => 'full_name', 'values' => ['Vikramaditya Roy']],
                            ['name' => 'phone_number', 'values' => ['+919876543210']],
                            ['name' => 'email', 'values' => ['vikram@example.com']],
                        ],
                    ],
                ],
            ]),
        ]);

        $syncService = app(MetaLeadSyncService::class);
        $result = $syncService->sync($page, 'form_mapped_to_a');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['total_created']);

        $lead = \App\Models\Lead::where('mobile', '919876543210')->first();
        $this->assertNotNull($lead);

        // Crucial: The lead MUST be passed to Client A as mapped on the form!
        $this->assertEquals($clientA->id, $lead->client_id);
        $this->assertNotEquals($clientB->id, $lead->client_id);

        // Verify Campaign was created under Client A's project
        $this->assertEquals($projectA->id, $lead->campaign->project_id);
        $this->assertEquals('Prestige Luxury Festival Campaign', $lead->campaign->name);
    }

    public function test_forms_table_searches_by_meta_campaign_and_assigned_client_name(): void
    {
        $org = \App\Models\Organization::create(['name' => 'Deltek Org', 'type' => 'builder', 'status' => 'active']);
        $deltekClient = \App\Models\Client::create(['name' => 'Deltek Client', 'organization_id' => $org->id, 'status' => 'active']);
        \Spatie\Permission\Models\Role::findOrCreate('Super Admin', 'web');
        $admin = \App\Models\User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole('Super Admin');

        $account = PortalAccount::create([
            'name' => 'Meta Main Account',
            'type' => 'meta',
            'status' => 'active',
        ]);

        $metaAdAccount = MetaAdAccount::create([
            'portal_account_id' => $account->id,
            'ad_account_id' => '172678813',
            'name' => 'Lead Panther Main Ads',
            'status' => 'active',
        ]);

        MetaAdRecord::create([
            'meta_ad_account_id' => $metaAdAccount->id,
            'ad_account_id' => '172678813',
            'kind' => 'campaign',
            'remote_id' => '52584064756875',
            'name' => 'DHOLERA EMI_01',
            'status' => 'ACTIVE',
            'data' => ['objective' => 'LEAD_GENERATION'],
            'synced_at' => now(),
        ]);

        MetaAdRecord::create([
            'meta_ad_account_id' => $metaAdAccount->id,
            'ad_account_id' => '172678813',
            'kind' => 'ad',
            'remote_id' => '52584064756675',
            'name' => 'DHOLERA EMI_01 Ad',
            'campaign_id' => '52584064756875',
            'lead_form_id' => '1971751056790364',
            'status' => 'ACTIVE',
            'data' => [],
            'synced_at' => now(),
        ]);

        $mapping = LeadFormMapping::create([
            'portal_account_id' => $account->id,
            'form_id' => '1971751056790364',
            'form_name' => 'DHOLERA EMI_01',
            'client_id' => $deltekClient->id,
            'status' => 'active',
        ]);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Settings\IntegrationsManager::class)
            ->set('search', 'Deltek')
            ->assertSee('DHOLERA EMI_01')
            ->set('search', 'DHOLERA')
            ->assertSee('DHOLERA EMI_01')
            ->set('search', 'NonExistentXYZ')
            ->assertDontSee('1971751056790364');
    }
    public function test_optional_metadata_denial_does_not_block_campaign_connection(): void
    {
        $page = PortalAccount::create(['name' => 'Page', 'type' => 'meta', 'status' => 'active']);
        $account = MetaAdAccount::create(['portal_account_id' => $page->id, 'name' => 'Ads', 'ad_account_id' => '123', 'access_token' => 'secret', 'status' => 'error']);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/campaigns')) return Http::response(['data' => []]);
            if (($request['fields'] ?? '') === 'id,account_id,name,account_status') return Http::response(['account_id' => '123']);
            return Http::response(['error' => ['code' => 200]], 403);
        });
        app(MetaAdAccountService::class)->test($account);
        $this->assertSame('connected', $account->fresh()->status);
        $this->assertNull($account->fresh()->last_error);
    }

    public function test_optional_ad_fields_fallback_and_insight_denial_preserve_core_import(): void
    {
        $page = PortalAccount::create(['name' => 'Page', 'type' => 'meta', 'status' => 'active']);
        $account = MetaAdAccount::create(['portal_account_id' => $page->id, 'name' => 'Ads', 'ad_account_id' => '123', 'access_token' => 'secret', 'status' => 'connected', 'last_error' => 'Old permission error']);
        Http::fake(function ($request) use ($account) {
            $this->assertNull($account->fresh()->last_error);
            if (str_contains($request->url(), '/insights')) return Http::response(['error' => ['code' => 200]], 403);
            if (str_contains($request->url(), '/ads?')) {
                if (str_contains($request['fields'], 'asset_feed_spec')) return Http::response(['error' => ['code' => 100]], 400);
                return Http::response(['data' => [['id' => 'ad1', 'account_id' => '123', 'name' => 'Ad', 'creative' => ['id' => 'creative1', 'object_story_spec' => ['link_data' => ['call_to_action' => ['value' => ['lead_gen_form_id' => 'form1']]]]]]]]);
            }
            return Http::response(['data' => []]);
        });
        app(MetaAdAccountService::class)->sync($account);
        $account->refresh();
        $this->assertSame('connected', $account->status);
        $this->assertNull($account->sync_state);
        $this->assertStringContainsString('Insights unavailable', $account->last_error);
        $this->assertSame('form1', $account->records()->where('remote_id', 'ad1')->firstOrFail()->lead_form_id);
    }

    public function test_real_campaign_denial_reports_stage_without_syncing_label(): void
    {
        $page = PortalAccount::create(['name' => 'Page', 'type' => 'meta', 'status' => 'active']);
        $account = MetaAdAccount::create(['portal_account_id' => $page->id, 'name' => 'Ads', 'ad_account_id' => '123', 'access_token' => 'secret', 'status' => 'connected', 'sync_state' => ['edge' => 0, 'after' => null, 'page_errors' => 0]]);
        Http::fake(['*' => Http::response(['error' => ['code' => 200]], 403)]);
        try {
            app(MetaAdAccountService::class)->sync($account);
            $this->fail('Access denial should fail the core synchronization.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Campaigns:', $e->getMessage());
            $this->assertStringContainsString('Meta code 200', $e->getMessage());
        }
        $this->assertSame('error', $account->fresh()->status);
        $this->assertSame('Error', $account->fresh()->sync_stage_label);
        $this->assertStringNotContainsString('secret', $account->fresh()->last_error);
    }
}
