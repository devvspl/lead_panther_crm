<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\OrganizationDetail;
use App\Models\{Campaign, Client, MetaAdAccount, MetaAdRecord, Organization, PortalAccount, Project, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationCampaignMappingManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $deltek;
    private User $deltekOwner;
    private Project $deltekProject;
    private Organization $otherOrg;
    private User $otherOwner;
    private Project $otherProject;
    private MetaAdAccount $adAccount;
    private MetaAdRecord $deltekMetaCampaign;
    private MetaAdRecord $otherMetaCampaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);

        // 1. Deltek Organization Setup
        $this->deltek = Organization::create(['name' => 'Deltek', 'status' => 'active']);
        $this->deltekOwner = User::factory()->create(['organization_id' => $this->deltek->id, 'name' => 'Deltek Owner']);
        $this->deltekOwner->assignRole('Builder');
        $this->deltekProject = Project::create([
            'client_id' => $this->deltekOwner->id,
            'name' => 'General',
            'status' => 'active',
            'delivery_enabled' => true,
        ]);

        // 2. Other Organization Setup
        $this->otherOrg = Organization::create(['name' => 'Acme Builders', 'status' => 'active']);
        $this->otherOwner = User::factory()->create(['organization_id' => $this->otherOrg->id, 'name' => 'Acme Owner']);
        $this->otherOwner->assignRole('Builder');
        $this->otherProject = Project::create([
            'client_id' => $this->otherOwner->id,
            'name' => 'Skyline',
            'status' => 'active',
            'delivery_enabled' => true,
        ]);

        // 3. Meta Ad Account & Campaign Records
        $page = PortalAccount::create([
            'type' => 'meta',
            'name' => 'Lead Panther Main Ads',
            'status' => 'active',
        ]);
        $page->setCredential('page_id', '123456');
        $this->adAccount = MetaAdAccount::create([
            'portal_account_id' => $page->id,
            'ad_account_id' => 'act_1001',
            'name' => 'Main Ads Portfolio',
            'status' => 'connected',
        ]);
        $this->adAccount->pages()->attach($page->id);

        // Meta Campaign mapped to Deltek
        $this->deltekMetaCampaign = MetaAdRecord::create([
            'meta_ad_account_id' => $this->adAccount->id,
            'ad_account_id' => 'act_1001',
            'kind' => 'campaign',
            'remote_id' => 'meta_camp_deltek_1',
            'name' => 'SIR Inside Plots 1 - Palacio',
            'client_id' => $this->deltekOwner->id,
            'project_id' => $this->deltekProject->id,
            'status' => 'ACTIVE',
            'data' => [],
            'synced_at' => now(),
        ]);

        // Meta Campaign mapped to Other Org
        $this->otherMetaCampaign = MetaAdRecord::create([
            'meta_ad_account_id' => $this->adAccount->id,
            'ad_account_id' => 'act_1001',
            'kind' => 'campaign',
            'remote_id' => 'meta_camp_other_1',
            'name' => 'Acme Towers Summer Campaign',
            'client_id' => $this->otherOwner->id,
            'project_id' => $this->otherProject->id,
            'status' => 'ACTIVE',
            'data' => [],
            'synced_at' => now(),
        ]);
    }

    public function test_deltek_campaign_details_tab_displays_mapped_meta_campaign(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OrganizationDetail::class, ['organization' => $this->deltek])
            ->set('activeTab', 'campaigns')
            ->assertSee('General')
            ->assertSee('SIR Inside Plots 1 - Palacio')
            ->assertSee('Meta ID: meta_camp_deltek_1')
            ->assertDontSee('Acme Towers Summer Campaign')
            ->assertDontSee('meta_camp_other_1');
    }

    public function test_add_campaign_mapping_creates_mapping_and_links_crm_campaign(): void
    {
        $newMetaCamp = MetaAdRecord::create([
            'meta_ad_account_id' => $this->adAccount->id,
            'ad_account_id' => 'act_1001',
            'kind' => 'campaign',
            'remote_id' => 'meta_camp_deltek_2',
            'name' => 'SIR Inside Plots 2 - Palacio',
            'status' => 'ACTIVE',
            'data' => [],
            'synced_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(OrganizationDetail::class, ['organization' => $this->deltek])
            ->call('openAddCampaignMapping')
            ->assertSet('mappingSourceType', 'campaign')
            ->assertSet('mappingOrganizationId', $this->deltek->id)
            ->assertSet('mappingClientId', $this->deltekOwner->id)
            ->assertDontSee('Delivery Configuration')
            ->assertDontSee('Use Project Default')
            ->assertDontSee('Preselected')
            ->set('mappingAccountId', $this->adAccount->id)
            ->set('mappingRemoteId', 'meta_camp_deltek_2')
            ->set('mappingProjectId', $this->deltekProject->id)
            ->set('mappingDailyLimit', 25)
            ->set('mappingInheritLimit', false)
            ->set('mappingDeliveryInterval', 15)
            ->set('mappingInheritInterval', false)
            ->call('saveCampaignMapping')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success');

        $newMetaCamp->refresh();
        $this->assertSame((int) $this->deltekOwner->id, (int) $newMetaCamp->client_id);
        $this->assertSame((int) $this->deltekProject->id, (int) $newMetaCamp->project_id);
        $this->assertNotNull($newMetaCamp->crm_campaign_id);

        $crmCamp = Campaign::find($newMetaCamp->crm_campaign_id);
        $this->assertSame('SIR Inside Plots 2 - Palacio', $crmCamp->name);
        $this->assertSame(25, $crmCamp->delivery_daily_limit);
        $this->assertSame(15, $crmCamp->delivery_interval_minutes);
    }

    public function test_edit_campaign_mapping_updates_existing_record_and_keeps_screens_synchronized(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(OrganizationDetail::class, ['organization' => $this->deltek])
            ->call('editCampaignMapping', $this->deltekMetaCampaign->id, 'campaign')
            ->assertSet('mappingRemoteId', 'meta_camp_deltek_1')
            ->assertSet('mappingProjectId', $this->deltekProject->id)
            ->set('mappingDailyLimit', 100)
            ->set('mappingInheritLimit', false)
            ->set('mappingDeliveryInterval', 30)
            ->set('mappingInheritInterval', false)
            ->call('saveCampaignMapping')
            ->assertHasNoErrors();

        $this->deltekMetaCampaign->refresh();
        $this->assertSame((int) $this->deltekOwner->id, (int) $this->deltekMetaCampaign->client_id);
        $this->assertSame((int) $this->deltekProject->id, (int) $this->deltekMetaCampaign->project_id);

        $crmCamp = Campaign::find($this->deltekMetaCampaign->crm_campaign_id);
        $this->assertSame(100, $crmCamp->delivery_daily_limit);
        $this->assertSame(30, $crmCamp->delivery_interval_minutes);
    }

    public function test_cannot_map_project_belonging_to_another_organization(): void
    {
        $newMetaCamp = MetaAdRecord::create([
            'meta_ad_account_id' => $this->adAccount->id,
            'ad_account_id' => 'act_1001',
            'kind' => 'campaign',
            'remote_id' => 'meta_camp_hack',
            'name' => 'Hack Campaign',
            'status' => 'ACTIVE',
            'data' => [],
            'synced_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(OrganizationDetail::class, ['organization' => $this->deltek])
            ->call('openAddCampaignMapping')
            ->set('mappingRemoteId', 'meta_camp_hack')
            ->set('mappingProjectId', $this->otherProject->id) // Project belonging to Acme, not Deltek
            ->call('saveCampaignMapping')
            ->assertStatus(422);
    }
}
