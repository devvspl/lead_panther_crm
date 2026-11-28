<?php

namespace Tests\Feature\Campaign;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadFilterRule;
use App\Models\LeadMetadata;
use App\Models\LeadSource;
use App\Models\MetaAdAccount;
use App\Models\MetaAdRecord;
use App\Models\Organization;
use App\Models\PortalAccount;
use App\Models\Project;
use App\Models\User;
use App\Services\LeadFilterRuleEngine;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TransferGulmoharStudioBuyLeadsToTruegrowthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioLeadTransferAndFilteringTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $sourceOrg;
    private User $sourceClient;
    private Project $sourceProject;
    private Campaign $sourceCampaign;
    private Organization $targetOrg;
    private User $targetClient;
    private MetaAdAccount $adAccount;
    private MetaAdRecord $metaRecord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);

        // 1. Source Organization: Gulmohar Studio 135
        $this->sourceOrg = Organization::create([
            'name' => 'Gulmohar Studio 135',
            'status' => 'active',
        ]);
        $this->sourceClient = User::factory()->create([
            'organization_id' => $this->sourceOrg->id,
            'name' => 'Gulmohar Client Owner',
        ]);
        $this->sourceClient->assignRole('Builder/Developer');

        $this->sourceProject = Project::create([
            'client_id' => $this->sourceClient->id,
            'name' => 'General',
            'status' => 'active',
        ]);

        $this->sourceCampaign = Campaign::create([
            'project_id' => $this->sourceProject->id,
            'name' => 'Studio Noida Exp 50 – 2 gulmohar',
            'status' => 'active',
        ]);

        // 2. Target Organization: Truegrowth Realty
        $this->targetOrg = Organization::create([
            'name' => 'Truegrowth Realty',
            'status' => 'active',
        ]);
        $this->targetClient = User::factory()->create([
            'organization_id' => $this->targetOrg->id,
            'name' => 'Truegrowth Client Owner',
        ]);
        $this->targetClient->assignRole('Builder/Developer');

        // 3. Meta Ad Account & Campaign Record
        $page = PortalAccount::create([
            'type' => 'meta',
            'name' => 'Studio Leads Page',
            'status' => 'active',
        ]);
        $page->setCredential('page_id', '777888');

        $this->adAccount = MetaAdAccount::create([
            'portal_account_id' => $page->id,
            'ad_account_id' => 'act_studio_50',
            'name' => 'Studio Portfolio',
            'status' => 'connected',
        ]);
        $this->adAccount->pages()->attach($page->id);

        $this->metaRecord = MetaAdRecord::create([
            'meta_ad_account_id' => $this->adAccount->id,
            'ad_account_id' => 'act_studio_50',
            'kind' => 'campaign',
            'remote_id' => '52591282486875',
            'name' => 'Studio Noida Exp 50 – 2 gulmohar',
            'client_id' => $this->sourceClient->id,
            'project_id' => $this->sourceProject->id,
            'crm_campaign_id' => $this->sourceCampaign->id,
            'status' => 'ACTIVE',
            'data' => [],
            'synced_at' => now(),
        ]);
    }

    public function test_buy_leads_only_filter_rule_evaluation(): void
    {
        // Configure Buy Leads Only rule
        $rule = LeadFilterRule::create([
            'organization_id' => $this->sourceOrg->id,
            'name' => 'Buy Leads Only',
            'action' => 'include',
            'group_logic' => 'OR',
            'conditions' => [
                'group_logic' => 'OR',
                'groups' => [
                    [
                        'logic' => 'AND',
                        'conditions' => [
                            [
                                'field' => 'want_to_buy_studio',
                                'operator' => 'equals',
                                'value' => 'buy',
                            ],
                        ],
                    ],
                ],
            ],
            'is_active' => true,
            'priority' => 100,
        ]);

        $engine = app(LeadFilterRuleEngine::class);

        // 1. Buy lead - should be allowed (included)
        $buyPayload = [
            'name' => 'Rajesh Sharma',
            'mobile' => '+919876543210',
            'meta_form_answers' => [
                'want_to_buy_studio' => 'buy',
                'full_name' => 'Rajesh Sharma',
            ],
        ];
        $evalBuy = $engine->evaluateLead($buyPayload, $this->sourceOrg->id);
        $this->assertTrue($evalBuy->passed, 'Buy lead must be allowed when include rule matches.');
        $this->assertSame('include', $evalBuy->action);

        // 2. Rent lead - should be blocked (excluded)
        $rentPayload = [
            'name' => 'Suresh Kumar',
            'mobile' => '+919876543211',
            'meta_form_answers' => [
                'want_to_buy_studio' => 'rent',
                'full_name' => 'Suresh Kumar',
            ],
        ];
        $evalRent = $engine->evaluateLead($rentPayload, $this->sourceOrg->id);
        $this->assertFalse($evalRent->passed, 'Rent lead must not be allowed when include rule requires buy.');

        // 3. Lead without answer - should be blocked
        $emptyPayload = [
            'name' => 'Amit Patel',
            'mobile' => '+919876543212',
            'meta_form_answers' => [],
        ];
        $evalEmpty = $engine->evaluateLead($emptyPayload, $this->sourceOrg->id);
        $this->assertFalse($evalEmpty->passed, 'Lead without want_to_buy_studio answer must not be allowed.');
    }

    public function test_seeder_transfers_only_buy_leads_and_is_idempotent(): void
    {
        $source = LeadSource::firstOrCreate(['name' => 'Meta Ads']);

        // Create 3 sample leads under source client
        // Lead 1: Buy lead (eligible)
        $buyLead = Lead::create([
            'lead_code' => 'LP-2026-TEST0001',
            'client_id' => $this->sourceClient->id,
            'campaign_id' => $this->sourceCampaign->id,
            'lead_source_id' => $source->id,
            'name' => 'Eligible Buyer',
            'mobile' => '+919999900001',
            'email' => 'buyer@example.com',
            'status' => 'new',
            'current_stage' => 'new',
            'created_at' => '2026-11-26 10:00:00',
        ]);
        LeadMetadata::create([
            'lead_id' => $buyLead->id,
            'key' => 'meta_form_answers',
            'value' => json_encode(['want_to_buy_studio' => 'buy']),
        ]);

        // Lead 2: Rent lead (ineligible)
        $rentLead = Lead::create([
            'lead_code' => 'LP-2026-TEST0002',
            'client_id' => $this->sourceClient->id,
            'campaign_id' => $this->sourceCampaign->id,
            'lead_source_id' => $source->id,
            'name' => 'Ineligible Renter',
            'mobile' => '+919999900002',
            'email' => 'renter@example.com',
            'status' => 'new',
            'current_stage' => 'new',
            'created_at' => '2026-11-26 10:00:00',
        ]);
        LeadMetadata::create([
            'lead_id' => $rentLead->id,
            'key' => 'meta_form_answers',
            'value' => json_encode(['want_to_buy_studio' => 'rent']),
        ]);

        // Lead 3: Null answer lead (ineligible)
        $nullLead = Lead::create([
            'lead_code' => 'LP-2026-TEST0003',
            'client_id' => $this->sourceClient->id,
            'campaign_id' => $this->sourceCampaign->id,
            'lead_source_id' => $source->id,
            'name' => 'Ineligible Blank',
            'mobile' => '+919999900003',
            'email' => 'blank@example.com',
            'status' => 'new',
            'current_stage' => 'new',
            'created_at' => '2026-11-26 10:00:00',
        ]);

        // Run the transfer seeder
        $seeder = new TransferGulmoharStudioBuyLeadsToTruegrowthSeeder();
        $seeder->run();

        // 1. Verify Target Project and Campaign were created
        $targetProject = Project::withoutGlobalScopes()
            ->where('client_id', $this->targetClient->id)
            ->where('name', 'Studio Buy Leads')
            ->first();
        $this->assertNotNull($targetProject, 'Target project Studio Buy Leads must be created.');

        $targetCampaign = Campaign::withoutGlobalScopes()
            ->where('project_id', $targetProject->id)
            ->where('name', 'Studio Noida Exp 50 – 2 gulmohar')
            ->first();
        $this->assertNotNull($targetCampaign, 'Target campaign must be created under target project.');

        // 2. Verify MetaAdRecord was mapped to Target Project
        $this->metaRecord->refresh();
        $this->assertSame((int)$this->targetClient->id, (int)$this->metaRecord->client_id);
        $this->assertSame((int)$targetProject->id, (int)$this->metaRecord->project_id);
        $this->assertSame((int)$targetCampaign->id, (int)$this->metaRecord->crm_campaign_id);

        // 3. Verify Buy Lead was transferred to Target Client and Campaign
        $buyLead->refresh();
        $this->assertSame((int)$this->targetClient->id, (int)$buyLead->client_id, 'Buy lead client_id must be updated to target client.');
        $this->assertSame((int)$targetCampaign->id, (int)$buyLead->campaign_id, 'Buy lead campaign_id must be updated to target campaign.');

        // Verify audit trail metadata
        $this->assertSame('Gulmohar Studio 135', $buyLead->metadata->firstWhere('key', 'transferred_from_organization')?->value);
        $this->assertSame('Truegrowth Realty', $buyLead->metadata->firstWhere('key', 'transferred_to_organization')?->value);
        $this->assertNotNull($buyLead->metadata->firstWhere('key', 'transferred_at')?->value);

        // 4. Verify Rent and Null leads were NOT transferred
        $rentLead->refresh();
        $this->assertSame((int)$this->sourceClient->id, (int)$rentLead->client_id, 'Rent lead client_id must remain with source.');
        $this->assertSame((int)$this->sourceCampaign->id, $rentLead->campaign_id);

        $nullLead->refresh();
        $this->assertSame((int)$this->sourceClient->id, (int)$nullLead->client_id, 'Null lead client_id must remain with source.');
        $this->assertSame((int)$this->sourceCampaign->id, $nullLead->campaign_id);

        // 5. Verify Filtering Rule exists under both organizations
        $this->assertTrue(LeadFilterRule::where('organization_id', $this->sourceOrg->id)->where('name', 'Buy Leads Only')->exists());
        $this->assertTrue(LeadFilterRule::where('organization_id', $this->targetOrg->id)->where('name', 'Buy Leads Only')->exists());

        // 6. Test Idempotency: Running seeder again should make no duplicate transfers or errors
        $seeder->run();

        $buyLead->refresh();
        $this->assertSame((int)$this->targetClient->id, (int)$buyLead->client_id);
        $this->assertSame((int)$targetCampaign->id, (int)$buyLead->campaign_id);
        $this->assertSame(3, Lead::count(), 'Total leads must remain exactly 3 without duplication.');
    }
}
