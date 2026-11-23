<?php

namespace Tests\Feature\Leads;

use App\Livewire\Leads\SuperAdminLeadModal;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadMetadata;
use App\Models\LeadSource;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuperAdminLeadModalTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $salesExecutive;
    private Organization $organization;
    private Client $client;
    private Project $project;
    private Campaign $campaign;
    private LeadSource $source;
    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $this->superAdmin->assignRole('Super Admin');

        $this->salesExecutive = User::factory()->create(['status' => 'active']);
        $this->salesExecutive->assignRole('sales-executive');

        $this->organization = Organization::create([
            'name' => 'Super Admin Test Org',
            'status' => 'active',
            'auto_assign_enabled' => false,
        ]);

        $this->client = Client::create([
            'name' => 'Test Builder Owner',
            'organization_id' => $this->organization->id,
            'status' => 'active',
        ]);

        $this->project = Project::create([
            'client_id' => $this->client->id,
            'name' => 'Elan Boulevard Test',
            'status' => 'active',
        ]);

        $this->source = LeadSource::firstOrCreate(['name' => 'Meta Ads']);

        $this->campaign = Campaign::create([
            'project_id' => $this->project->id,
            'name' => 'Elan Retail Campaign',
            'lead_source_id' => $this->source->id,
            'status' => 'active',
        ]);

        $this->lead = Lead::create([
            'lead_code' => 'LP-TEST-0001',
            'client_id' => $this->client->id,
            'campaign_id' => $this->campaign->id,
            'lead_source_id' => $this->source->id,
            'name' => 'Rahul Sharma',
            'mobile' => '9811122233',
            'email' => 'rahul@example.com',
            'city' => 'Gurgaon',
            'location' => 'Sector 106',
            'property_type' => '3 BHK Luxury',
            'budget' => 15000000,
            'requirement' => 'Looking for corner unit facing park',
            'status' => 'active',
            'current_stage' => 'new',
            'lead_score' => 75,
            'assigned_to' => $this->salesExecutive->id,
            'lead_cost' => 1.0,
        ]);

        LeadMetadata::create([
            'lead_id' => $this->lead->id,
            'key' => 'meta_form_answers',
            'value' => json_encode([
                'what_configuration_are_you_looking_for?' => '3 BHK',
                'preferred_location' => 'Dwarka Expressway',
                'investment_budget' => '1.5 Cr',
            ]),
        ]);

        LeadMetadata::create([
            'lead_id' => $this->lead->id,
            'key' => 'meta_ad_name',
            'value' => 'Elan Retail Ad 01',
        ]);

        $this->lead->refresh();
    }

    public function test_super_admin_can_open_modal_and_see_all_lead_fields(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SuperAdminLeadModal::class)
            ->dispatch('open-super-admin-lead-modal', id: $this->lead->id)
            ->assertSet('isOpen', true)
            ->assertSet('leadId', $this->lead->id)
            ->assertSet('name', 'Rahul Sharma')
            ->assertSet('mobile', '9811122233')
            ->assertSet('city', 'Gurgaon')
            ->assertSet('location', 'Sector 106')
            ->assertSet('property_type', '3 BHK Luxury')
            ->assertSet('budget', 15000000.0)
            ->assertSet('current_stage', 'new')
            ->assertSet('lead_score', $this->lead->lead_score)
            ->assertSee('Super Admin Master Inspector & Editor')
            ->assertSee('Rahul Sharma')
            ->assertSee('9811122233')
            ->assertSee('what_configuration_are_you_looking_for?')
            ->assertSee('3 BHK')
            ->assertSee('Dwarka Expressway');
    }

    public function test_super_admin_can_edit_and_save_lead_details(): void
    {
        $this->actingAs($this->superAdmin);

        $newProject = Project::create([
            'client_id' => $this->client->id,
            'name' => 'Boulevard Walk Phase 2',
            'status' => 'active',
        ]);

        Livewire::test(SuperAdminLeadModal::class)
            ->dispatch('open-super-admin-lead-modal', id: $this->lead->id)
            ->set('name', 'Mohammad Faisal')
            ->set('mobile', '9899001122')
            ->set('city', 'Noida')
            ->set('location', 'Sector 62')
            ->set('property_type', 'Commercial Retail')
            ->set('budget', 25000000)
            ->set('current_stage', 'interested')
            ->set('lead_score', 90)
            ->set('project_id', $newProject->id)
            ->call('saveLead')
            ->assertDispatched('toast')
            ->assertDispatched('lead-updated')
            ->assertSet('isOpen', false);

        $this->lead->refresh();
        $this->assertEquals('Mohammad Faisal', $this->lead->name);
        $this->assertEquals('9899001122', $this->lead->mobile);
        $this->assertEquals('Noida', $this->lead->city);
        $this->assertEquals('Sector 62', $this->lead->location);
        $this->assertEquals('Commercial Retail', $this->lead->property_type);
        $this->assertEquals(25000000, $this->lead->budget);
        $this->assertEquals('interested', $this->lead->current_stage);
        $this->assertGreaterThanOrEqual(45, $this->lead->lead_score);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'super_admin_lead_master_update',
            'subject_type' => Lead::class,
            'subject_id' => $this->lead->id,
        ]);
    }

    public function test_super_admin_can_unassign_executive_and_save(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SuperAdminLeadModal::class)
            ->dispatch('open-super-admin-lead-modal', id: $this->lead->id)
            ->assertSet('assigned_to', $this->salesExecutive->id)
            ->call('unassignExecutive')
            ->assertSet('assigned_to', null)
            ->call('saveLead');

        $this->lead->refresh();
        $this->assertNull($this->lead->assigned_to);
    }

    public function test_super_admin_can_add_and_delete_custom_metadata(): void
    {
        $this->actingAs($this->superAdmin);

        $component = Livewire::test(SuperAdminLeadModal::class)
            ->dispatch('open-super-admin-lead-modal', id: $this->lead->id)
            ->set('newMetaKey', 'custom_investment_timeline')
            ->set('newMetaValue', 'immediate_within_15_days')
            ->call('addMetadataKey')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('lead_metadata', [
            'lead_id' => $this->lead->id,
            'key' => 'custom_investment_timeline',
            'value' => 'immediate_within_15_days',
        ]);

        $createdMeta = LeadMetadata::where('lead_id', $this->lead->id)->where('key', 'custom_investment_timeline')->first();

        $component->call('deleteMetadataKey', $createdMeta->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('lead_metadata', [
            'id' => $createdMeta->id,
        ]);
    }

    public function test_modal_renders_theme_selects_and_responsive_mobile_layout(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SuperAdminLeadModal::class)
            ->dispatch('open-super-admin-lead-modal', id: $this->lead->id)
            ->assertSee('lp-select-trigger')
            ->assertSee('h-[100dvh]')
            ->assertSee('sm:max-h-[92vh]')
            ->assertSee('flex-col-reverse')
            ->set('project_id', $this->project->id)
            ->set('campaign_id', $this->campaign->id)
            ->assertSet('project_id', $this->project->id)
            ->assertSet('campaign_id', $this->campaign->id);
    }

    public function test_non_super_admin_is_denied_access(): void
    {
        $this->actingAs($this->salesExecutive);

        Livewire::test(SuperAdminLeadModal::class)
            ->dispatch('open-super-admin-lead-modal', id: $this->lead->id)
            ->assertForbidden();
    }
}
