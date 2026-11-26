<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\OrganizationDetail;
use App\Models\{Campaign, LeadFormMapping, LeadSource, Organization, PortalAccount, Project, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationCampaignDeliveryModalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $org;
    private User $owner;
    private Project $project;
    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);

        $this->org = Organization::create([
            'name' => 'Brigade Enterprises',
            'status' => 'active',
        ]);

        $this->owner = User::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Brigade Owner',
            'status' => 'active',
        ]);
        $this->owner->assignRole('Builder/Developer');

        $this->project = Project::create([
            'name' => 'Brigade Meadows',
            'client_id' => $this->owner->id,
            'status' => 'active',
            'delivery_enabled' => true,
            'delivery_daily_limit' => 10,
            'delivery_interval_minutes' => 15,
        ]);

        $source = LeadSource::firstOrCreate(['name' => 'Meta Ads']);

        $this->campaign = Campaign::create([
            'project_id' => $this->project->id,
            'lead_source_id' => $source->id,
            'name' => 'Meadows Phase 2 Launch',
            'status' => 'active',
            'delivery_daily_limit' => null,
            'delivery_interval_minutes' => null,
        ]);

        $account = PortalAccount::create([
            'name' => 'Brigade Meta Portal',
            'type' => 'meta',
            'status' => 'active',
        ]);

        LeadFormMapping::create([
            'portal_account_id' => $account->id,
            'form_id' => 'form-brigade-meadows',
            'form_name' => 'Brigade Meadows Lead Form',
            'client_id' => $this->owner->id,
            'project_id' => $this->project->id,
            'campaign_id' => $this->campaign->id,
            'status' => 'active',
        ]);
    }

    public function test_advanced_table_and_summary_stats_render_with_campaign_details(): void
    {
        Livewire::test(OrganizationDetail::class, ['organization' => $this->org])
            ->call('setTab', 'campaigns')
            ->assertSee('Total Campaigns')
            ->assertSee('Mapped Campaigns')
            ->assertSee('Delivered Today')
            ->assertSee('Pending Leads')
            ->assertSee('Brigade Meadows')
            ->assertSee('Meadows Phase 2 Launch')
            ->assertSee('Brigade Enterprises')
            ->assertSee('Edit Mapping')
            ->assertDontSee('Configure Settings')
            ->assertDontSee('Configure Delivery');
    }

    public function test_unmapped_campaign_displays_not_mapped_and_allows_editing_mapping(): void
    {
        $unmappedCampaign = Campaign::create([
            'project_id' => $this->project->id,
            'name' => 'Unmapped Local Banner Campaign',
            'status' => 'active',
        ]);

        Livewire::test(OrganizationDetail::class, ['organization' => $this->org])
            ->call('setTab', 'campaigns')
            ->assertSee('Unmapped Local Banner Campaign')
            ->assertSee('Not Mapped')
            ->call('editCampaignMapping', $unmappedCampaign->id, 'crm_campaign')
            ->assertSet('mappingProjectId', $this->project->id)
            ->assertDispatched('open-modal', 'organization-campaign-mapping');
    }

    public function test_project_delivery_settings_modal_updates_limit_interval_and_status(): void
    {
        $projectKey = 'project_'.$this->project->id;

        Livewire::test(OrganizationDetail::class, ['organization' => $this->org])
            ->call('setTab', 'campaigns')
            ->set("deliverySettings.$projectKey.limit", 25)
            ->set("deliverySettings.$projectKey.interval", 45)
            ->set("deliverySettings.$projectKey.enabled", 0)
            ->call('saveDeliverySettings', 'project', $this->project->id)
            ->assertHasNoErrors()
            ->assertDispatched('close-modal', 'project-delivery-settings-'.$this->project->id)
            ->assertDispatched('toast', type: 'success');

        $this->project->refresh();
        $this->assertEquals(25, $this->project->delivery_daily_limit);
        $this->assertEquals(45, $this->project->delivery_interval_minutes);
        $this->assertFalse((bool) $this->project->delivery_enabled);
    }

    public function test_campaign_modal_updates_custom_limits_inheritance_and_campaign_status(): void
    {
        $campaignKey = 'campaign_'.$this->campaign->id;

        Livewire::test(OrganizationDetail::class, ['organization' => $this->org])
            ->call('setTab', 'campaigns')
            // Set custom limit & interval and pause the campaign
            ->set("deliverySettings.$campaignKey.inherit_limit", false)
            ->set("deliverySettings.$campaignKey.limit", 5)
            ->set("deliverySettings.$campaignKey.inherit_interval", false)
            ->set("deliverySettings.$campaignKey.interval", 20)
            ->set("deliverySettings.$campaignKey.status", 'paused')
            ->call('saveDeliverySettings', 'campaign', $this->campaign->id)
            ->assertHasNoErrors()
            ->assertDispatched('close-modal', 'campaign-delivery-settings-'.$this->campaign->id)
            ->assertDispatched('toast', type: 'success');

        $this->campaign->refresh();
        $this->assertEquals(5, $this->campaign->delivery_daily_limit);
        $this->assertEquals(20, $this->campaign->delivery_interval_minutes);
        $this->assertEquals('paused', $this->campaign->status);

        // Switch back to inherit project defaults
        Livewire::test(OrganizationDetail::class, ['organization' => $this->org])
            ->call('setTab', 'campaigns')
            ->set("deliverySettings.$campaignKey.inherit_limit", true)
            ->set("deliverySettings.$campaignKey.inherit_interval", true)
            ->set("deliverySettings.$campaignKey.status", 'active')
            ->call('saveDeliverySettings', 'campaign', $this->campaign->id)
            ->assertHasNoErrors()
            ->assertDispatched('close-modal', 'campaign-delivery-settings-'.$this->campaign->id);

        $this->campaign->refresh();
        $this->assertNull($this->campaign->delivery_daily_limit);
        $this->assertNull($this->campaign->delivery_interval_minutes);
        $this->assertEquals('active', $this->campaign->status);
    }
}
