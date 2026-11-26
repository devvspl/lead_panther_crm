<?php

namespace Tests\Feature\Settings;

use App\Jobs\ProcessInboundLeadJob;
use App\Livewire\Settings\LeadFilteringRules;
use App\Models\{Campaign, Client, Lead, LeadFilterLog, LeadFilterRule, Organization, PortalAccount, Project, User, WebhookLog};
use App\Services\LeadFilterRuleEngine;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadFilteringRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $org;
    private User $builderOwner;
    private Project $project;
    private Campaign $campaign;
    private PortalAccount $portalAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);

        $this->org = Organization::create([
            'name' => 'Signature Builders',
            'status' => 'active',
        ]);

        $this->builderOwner = User::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Signature Builder Owner',
            'status' => 'active',
        ]);
        $this->builderOwner->assignRole('Builder/Developer');

        $this->project = Project::create([
            'name' => 'Studio Heights',
            'client_id' => $this->builderOwner->id,
            'status' => 'active',
            'delivery_enabled' => true,
        ]);

        $this->campaign = Campaign::create([
            'project_id' => $this->project->id,
            'name' => 'Studio Buy Campaign',
            'status' => 'active',
        ]);

        $this->portalAccount = PortalAccount::create([
            'type' => 'meta',
            'name' => 'Signature Meta Page',
            'status' => 'active',
        ]);
        $this->portalAccount->setCredential('page_id', 'page_123456');
        $this->portalAccount->setCredential('default_client_id', (string) $this->builderOwner->id);

        \App\Models\LeadFormMapping::create([
            'portal_account_id' => $this->portalAccount->id,
            'form_id' => 'form_123',
            'form_name' => 'Studio Buy Lead Form',
            'client_id' => $this->builderOwner->id,
            'project_id' => $this->project->id,
            'campaign_id' => $this->campaign->id,
            'status' => 'active',
        ]);
    }

    public function test_builder_can_create_edit_toggle_and_delete_filtering_rules(): void
    {
        $component = Livewire::test(LeadFilteringRules::class, ['organizationId' => $this->org->id])
            ->assertSee('Lead Filtering Rules')
            ->assertSee('Add Filtering Rule')
            ->call('openCreateRuleModal')
            ->set('ruleName', 'Studio Buyers Only')
            ->set('ruleAction', 'include')
            ->set('ruleGroupLogic', 'OR')
            ->set('conditionGroups', [
                [
                    'logic' => 'AND',
                    'conditions' => [
                        ['field' => 'want_to_buy_studio', 'operator' => 'equals', 'value' => 'buy'],
                        ['field' => 'free_site_visit', 'operator' => 'equals', 'value' => 'immediate'],
                    ],
                ],
            ])
            ->call('saveRule')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success');

        $rule = LeadFilterRule::where('name', 'Studio Buyers Only')->firstOrFail();
        $this->assertEquals($this->org->id, $rule->organization_id);
        $this->assertEquals('include', $rule->action);
        $this->assertTrue($rule->is_active);

        // Edit Rule
        $component->call('openEditRuleModal', $rule->id)
            ->set('ruleName', 'Studio Buyers & Investors Only')
            ->call('saveRule')
            ->assertHasNoErrors();

        $rule->refresh();
        $this->assertEquals('Studio Buyers & Investors Only', $rule->name);

        // Verify table renders CRM columns
        $component->assertSee('Studio Buyers & Investors Only')
            ->assertSee('All Campaigns')
            ->assertSee('Include Lead')
            ->assertSee('Super Admin');

        // Toggle Active
        $component->call('toggleRuleActive', $rule->id);
        $rule->refresh();
        $this->assertFalse($rule->is_active);

        $component->call('toggleRuleActive', $rule->id);
        $rule->refresh();
        $this->assertTrue($rule->is_active);

        // Delete Rule
        $component->call('deleteRule', $rule->id);
        $this->assertDatabaseMissing('lead_filter_rules', ['id' => $rule->id]);
    }

    public function test_builder_can_create_project_and_campaign_scoped_rules(): void
    {
        // 1. Create Project-scoped rule
        Livewire::test(LeadFilteringRules::class, ['organizationId' => $this->org->id])
            ->call('openCreateRuleModal')
            ->set('ruleName', 'Studio Heights Project Only')
            ->set('ruleScopeType', 'project')
            ->set('ruleProjectId', $this->project->id)
            ->set('ruleAction', 'include')
            ->set('conditionGroups', [
                [
                    'logic' => 'AND',
                    'conditions' => [
                        ['field' => 'city', 'operator' => 'equals', 'value' => 'Noida'],
                    ],
                ],
            ])
            ->call('saveRule')
            ->assertHasNoErrors();

        $projectRule = LeadFilterRule::where('name', 'Studio Heights Project Only')->firstOrFail();
        $this->assertEquals($this->project->id, $projectRule->project_id);
        $this->assertNull($projectRule->campaign_id);
        $this->assertEquals('Project: Studio Heights', $projectRule->scopeSummary());

        // 2. Create Campaign-scoped rule
        Livewire::test(LeadFilteringRules::class, ['organizationId' => $this->org->id])
            ->call('openCreateRuleModal')
            ->set('ruleName', 'Studio Buy Campaign Only')
            ->set('ruleScopeType', 'campaign')
            ->set('ruleCampaignId', $this->campaign->id)
            ->set('ruleAction', 'exclude')
            ->set('conditionGroups', [
                [
                    'logic' => 'AND',
                    'conditions' => [
                        ['field' => 'want_to_buy_studio', 'operator' => 'equals', 'value' => 'rent'],
                    ],
                ],
            ])
            ->call('saveRule')
            ->assertHasNoErrors();

        $campaignRule = LeadFilterRule::where('name', 'Studio Buy Campaign Only')->firstOrFail();
        $this->assertEquals($this->campaign->id, $campaignRule->campaign_id);
        $this->assertNull($campaignRule->project_id);
        $this->assertEquals('Campaign: Studio Buy Campaign', $campaignRule->scopeSummary());
    }

    public function test_rule_engine_evaluates_all_supported_operators_correctly(): void
    {
        $engine = app(LeadFilterRuleEngine::class);

        // 1. Equals
        $this->assertTrue($engine->evaluateOperator('buy', 'equals', 'buy'));
        $this->assertTrue($engine->evaluateOperator('Buy', 'equals', 'buy'));
        $this->assertFalse($engine->evaluateOperator('rent', 'equals', 'buy'));

        // 2. Not Equals
        $this->assertTrue($engine->evaluateOperator('rent', 'not_equals', 'buy'));
        $this->assertFalse($engine->evaluateOperator('buy', 'not_equals', 'buy'));

        // 3. Contains
        $this->assertTrue($engine->evaluateOperator('Studio Apartment Buy Option', 'contains', 'studio'));
        $this->assertFalse($engine->evaluateOperator('Villa 4BHK', 'contains', 'studio'));

        // 4. Does Not Contain
        $this->assertTrue($engine->evaluateOperator('Commercial Office', 'not_contains', 'studio'));
        $this->assertFalse($engine->evaluateOperator('Studio Space', 'not_contains', 'studio'));

        // 5. Is Empty
        $this->assertTrue($engine->evaluateOperator('', 'is_empty', null));
        $this->assertTrue($engine->evaluateOperator(null, 'is_empty', null));
        $this->assertFalse($engine->evaluateOperator('Gurgaon', 'is_empty', null));

        // 6. Is Not Empty
        $this->assertTrue($engine->evaluateOperator('Noida', 'is_not_empty', null));
        $this->assertFalse($engine->evaluateOperator('', 'is_not_empty', null));
        $this->assertFalse($engine->evaluateOperator(null, 'is_not_empty', null));
    }

    public function test_rule_engine_evaluates_complex_multi_group_and_or_logic(): void
    {
        $engine = app(LeadFilterRuleEngine::class);

        // Rule: Group 1 (want_to_buy_studio = buy AND free_site_visit = immediate)
        //       OR Group 2 (budget >= 1 Crore / contains 'crore')
        $rule = [
            'group_logic' => 'OR',
            'action' => 'include',
            'conditions' => [
                'groups' => [
                    [
                        'logic' => 'AND',
                        'conditions' => [
                            ['field' => 'want_to_buy_studio', 'operator' => 'equals', 'value' => 'buy'],
                            ['field' => 'free_site_visit', 'operator' => 'equals', 'value' => 'immediate'],
                        ],
                    ],
                    [
                        'logic' => 'AND',
                        'conditions' => [
                            ['field' => 'budget', 'operator' => 'contains', 'value' => 'crore'],
                        ],
                    ],
                ],
            ],
        ];

        // Case 1: Matches Group 1
        $lead1 = [
            'want_to_buy_studio' => 'buy',
            'free_site_visit' => 'immediate',
            'budget' => '50 Lakhs',
        ];
        $eval1 = $engine->evaluateRule($rule, $lead1);
        $this->assertTrue($eval1['matches']);

        // Case 2: Matches Group 2
        $lead2 = [
            'want_to_buy_studio' => 'rent',
            'free_site_visit' => 'later',
            'budget' => '1.5 Crore',
        ];
        $eval2 = $engine->evaluateRule($rule, $lead2);
        $this->assertTrue($eval2['matches']);

        // Case 3: Matches neither
        $lead3 = [
            'want_to_buy_studio' => 'rent',
            'free_site_visit' => 'later',
            'budget' => '30 Lakhs',
        ];
        $eval3 = $engine->evaluateRule($rule, $lead3);
        $this->assertFalse($eval3['matches']);
    }

    public function test_test_preview_modal_reports_correct_included_or_excluded_verdict(): void
    {
        LeadFilterRule::create([
            'organization_id' => $this->org->id,
            'name' => 'Exclude Rentees',
            'action' => 'exclude',
            'group_logic' => 'OR',
            'conditions' => [
                'groups' => [
                    [
                        'logic' => 'AND',
                        'conditions' => [
                            ['field' => 'want_to_buy_studio', 'operator' => 'equals', 'value' => 'rent'],
                        ],
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        Livewire::test(LeadFilteringRules::class, ['organizationId' => $this->org->id])
            ->call('openTestModal')
            ->set('testFields', [
                ['field' => 'want_to_buy_studio', 'value' => 'rent'],
            ])
            ->call('runTestPreview')
            ->assertSet('testResult.verdict', 'EXCLUDED / SKIPPED')
            ->assertSet('testResult.passed', false)
            ->set('testFields', [
                ['field' => 'want_to_buy_studio', 'value' => 'buy'],
            ])
            ->call('runTestPreview')
            ->assertSet('testResult.verdict', 'INCLUDED')
            ->assertSet('testResult.passed', true);
    }

    public function test_lead_ingestion_automatically_skips_leads_matching_exclude_rule(): void
    {
        // Setup rule: Exclude lead if want_to_buy_studio = rent
        LeadFilterRule::create([
            'organization_id' => $this->org->id,
            'name' => 'Exclude Studio Renters',
            'action' => 'exclude',
            'group_logic' => 'OR',
            'conditions' => [
                'groups' => [
                    [
                        'logic' => 'AND',
                        'conditions' => [
                            ['field' => 'want_to_buy_studio', 'operator' => 'equals', 'value' => 'rent'],
                        ],
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        // Payload with rent answer
        $payload = [
            'id' => 'meta_lead_exclude_101',
            'created_time' => now()->toIso8601String(),
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Renting Customer']],
                ['name' => 'phone_number', 'values' => ['+919988776655']],
                ['name' => 'email', 'values' => ['renter@example.com']],
                ['name' => 'want_to_buy_studio', 'values' => ['rent']],
            ],
            'campaign_id' => 'camp_meta_1',
            'campaign_name' => 'Studio Buy Campaign',
            'form_id' => 'form_123',
        ];

        $log = WebhookLog::create([
            'portal_account_id' => $this->portalAccount->id,
            'payload' => json_encode($payload),
            'received_at' => now(),
            'processed' => false,
        ]);

        // Process inbound lead
        (new ProcessInboundLeadJob($log))->handle();

        $log->refresh();
        $this->assertTrue((bool) $log->processed);
        $this->assertStringContainsString('Lead skipped by custom filter rule', (string) $log->error_message);

        // Verify lead was NOT inserted in leads table
        $this->assertDatabaseMissing('leads', ['mobile' => '919988776655']);

        // Verify entry in lead_filter_logs
        $filterLog = LeadFilterLog::where('organization_id', $this->org->id)->latest('id')->first();
        $this->assertNotNull($filterLog);
        $this->assertEquals('skipped', $filterLog->action);
        $this->assertStringContainsString('Exclude Studio Renters', $filterLog->reason);
    }

    public function test_lead_ingestion_allows_leads_matching_include_rule(): void
    {
        // Setup rule: Include lead if want_to_buy_studio = buy
        LeadFilterRule::create([
            'organization_id' => $this->org->id,
            'name' => 'Studio Buyers Ingestion',
            'action' => 'include',
            'group_logic' => 'OR',
            'conditions' => [
                'groups' => [
                    [
                        'logic' => 'AND',
                        'conditions' => [
                            ['field' => 'want_to_buy_studio', 'operator' => 'equals', 'value' => 'buy'],
                        ],
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $payload = [
            'id' => 'meta_lead_include_202',
            'created_time' => now()->toIso8601String(),
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Genuine Buyer']],
                ['name' => 'phone_number', 'values' => ['+919112233445']],
                ['name' => 'email', 'values' => ['buyer@example.com']],
                ['name' => 'want_to_buy_studio', 'values' => ['buy']],
            ],
            'campaign_id' => 'camp_meta_1',
            'campaign_name' => 'Studio Buy Campaign',
            'form_id' => 'form_123',
        ];

        $log = WebhookLog::create([
            'portal_account_id' => $this->portalAccount->id,
            'payload' => json_encode($payload),
            'received_at' => now(),
            'processed' => false,
        ]);

        (new ProcessInboundLeadJob($log))->handle();

        $log->refresh();
        $this->assertTrue((bool) $log->processed);
        $this->assertNull($log->error_message);

        // Verify lead was created in database
        $this->assertDatabaseHas('leads', ['mobile' => '919112233445']);
    }

    public function test_when_no_rules_configured_existing_lead_ingestion_behavior_is_preserved(): void
    {
        $this->assertEquals(0, LeadFilterRule::count());

        $payload = [
            'id' => 'meta_lead_default_303',
            'created_time' => now()->toIso8601String(),
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Default Lead Ingestion']],
                ['name' => 'phone_number', 'values' => ['+919334455667']],
                ['name' => 'email', 'values' => ['default@example.com']],
            ],
            'campaign_id' => 'camp_meta_1',
            'campaign_name' => 'Studio Buy Campaign',
            'form_id' => 'form_123',
        ];

        $log = WebhookLog::create([
            'portal_account_id' => $this->portalAccount->id,
            'payload' => json_encode($payload),
            'received_at' => now(),
            'processed' => false,
        ]);

        (new ProcessInboundLeadJob($log))->handle();

        $log->refresh();
        $this->assertTrue((bool) $log->processed);
        $this->assertNull($log->error_message);

        $this->assertDatabaseHas('leads', ['mobile' => '919334455667']);
    }
}
