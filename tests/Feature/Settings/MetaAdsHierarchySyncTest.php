<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\MetaAdAccounts;
use App\Models\{LeadFormMapping, MetaAdAccount, MetaAdRecord, PortalAccount, User};
use App\Services\MetaAdAccountService;
use App\Services\MetaFormSyncService;
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
}
