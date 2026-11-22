<?php

namespace Tests\Feature\Leads;

use App\Livewire\Leads\LeadDetail;
use App\Livewire\Leads\LeadDisplayConfigModal;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadDisplayConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;
    protected Organization $orgB;
    protected User $superAdmin;
    protected User $clientAdminA;
    protected User $clientAdminB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Client', 'guard_name' => 'web']);

        $this->orgA = Organization::create([
            'name' => 'Organization Alpha',
            'status' => 'active',
        ]);

        $this->orgB = Organization::create([
            'name' => 'Organization Beta',
            'status' => 'active',
        ]);

        $this->superAdmin = User::factory()->create([
            'organization_id' => null,
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->clientAdminA = User::factory()->create([
            'organization_id' => $this->orgA->id,
        ]);
        $this->clientAdminA->assignRole('Client');

        $this->clientAdminB = User::factory()->create([
            'organization_id' => $this->orgB->id,
        ]);
        $this->clientAdminB->assignRole('Client');
    }

    public function test_default_lead_display_config_enables_all_nine_fields(): void
    {
        $defaults = Organization::defaultLeadDisplayConfig();

        $this->assertCount(9, $defaults);
        $this->assertTrue($defaults['phone']);
        $this->assertTrue($defaults['email']);
        $this->assertTrue($defaults['city']);
        $this->assertTrue($defaults['project']);
        $this->assertTrue($defaults['property_type']);
        $this->assertTrue($defaults['budget']);
        $this->assertTrue($defaults['requirement']);
        $this->assertTrue($defaults['meta_submission']);
        $this->assertTrue($defaults['meta_context']);
    }

    public function test_saving_display_preferences_persists_per_organization(): void
    {
        $this->actingAs($this->clientAdminA);

        $customConfig = [
            'phone' => true,
            'email' => false,
            'city' => true,
            'project' => true,
            'property_type' => false,
            'budget' => false,
            'requirement' => true,
            'meta_submission' => true,
            'meta_context' => false,
        ];

        Livewire::test(LeadDisplayConfigModal::class)
            ->dispatch('open-display-config', organizationId: $this->orgA->id)
            ->set('config', $customConfig)
            ->call('save')
            ->assertDispatched('lead-display-config-updated')
            ->assertSet('isOpen', false);

        $this->orgA->refresh();
        $this->orgB->refresh();

        // Org A has customized config
        $this->assertFalse($this->orgA->getLeadDisplayConfig()['budget']);
        $this->assertFalse($this->orgA->getLeadDisplayConfig()['email']);
        $this->assertFalse($this->orgA->getLeadDisplayConfig()['meta_context']);
        $this->assertTrue($this->orgA->getLeadDisplayConfig()['phone']);

        // Org B remains completely unaffected (strict data isolation)
        $this->assertTrue($this->orgB->getLeadDisplayConfig()['budget']);
        $this->assertTrue($this->orgB->getLeadDisplayConfig()['email']);
        $this->assertTrue($this->orgB->getLeadDisplayConfig()['meta_context']);
    }

    public function test_client_admin_cannot_modify_another_organizations_preferences(): void
    {
        $this->actingAs($this->clientAdminA);

        Livewire::test(LeadDisplayConfigModal::class)
            ->dispatch('open-display-config', organizationId: $this->orgB->id)
            ->set('config.budget', false)
            ->call('save');

        $this->orgB->refresh();
        // Org B must not have been modified by Client Admin A
        $this->assertTrue($this->orgB->getLeadDisplayConfig()['budget']);
    }

    public function test_super_admin_can_modify_any_organizations_preferences(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(LeadDisplayConfigModal::class)
            ->dispatch('open-display-config', organizationId: $this->orgB->id)
            ->set('config.budget', false)
            ->call('save')
            ->assertDispatched('lead-display-config-updated');

        $this->orgB->refresh();
        $this->assertFalse($this->orgB->getLeadDisplayConfig()['budget']);
    }

    public function test_lead_details_drawer_respects_organization_display_preferences(): void
    {
        $source = \App\Models\LeadSource::firstOrCreate(['name' => 'direct']);

        $projectA = Project::create([
            'client_id' => $this->clientAdminA->id,
            'name' => 'Project Alpha',
        ]);

        $lead = Lead::factory()->create([
            'lead_source_id' => $source->id,
            'client_id' => $this->clientAdminA->id,
            'project_id' => $projectA->id,
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'mobile' => '9876543210',
            'city' => 'Ahmedabad',
            'property_type' => 'Villa',
            'budget' => 5000000, // 50 Lakhs
        ]);

        $this->actingAs($this->clientAdminA);

        // Initially with default config, all fields are visible
        Livewire::test(LeadDetail::class)
            ->set('isOpen', true)
            ->set('leadId', $lead->id)
            ->assertSee('john.doe@example.com')
            ->assertSee('Ahmedabad')
            ->assertSee('Villa');

        // Turn off email and city for Org A
        $this->orgA->update([
            'lead_display_config' => array_merge(Organization::defaultLeadDisplayConfig(), [
                'email' => false,
                'city' => false,
            ]),
        ]);

        // In the drawer, email and city should now be hidden
        Livewire::test(LeadDetail::class)
            ->set('isOpen', true)
            ->set('leadId', $lead->id)
            ->assertDontSee('john.doe@example.com')
            ->assertDontSee('Ahmedabad')
            ->assertSee('Villa');
    }

    public function test_select_all_and_deselect_all_in_modal(): void
    {
        $this->actingAs($this->clientAdminA);

        $test = Livewire::test(LeadDisplayConfigModal::class)
            ->dispatch('open-display-config', organizationId: $this->orgA->id)
            ->call('deselectAll');

        foreach (array_keys(Organization::defaultLeadDisplayConfig()) as $k) {
            $this->assertFalse($test->get("config.{$k}"));
        }

        $test->call('selectAll');

        foreach (array_keys(Organization::defaultLeadDisplayConfig()) as $k) {
            $this->assertTrue($test->get("config.{$k}"));
        }
    }

    public function test_advanced_table_renders_phone_tel_and_whatsapp_links(): void
    {
        $this->actingAs($this->clientAdminA);

        $columns = [
            ['key' => 'mobile', 'label' => 'Mobile', 'type' => 'phone'],
        ];

        $rows = [
            (object) ['id' => 1, 'mobile' => '9876543210'],
        ];

        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-ui.advanced-table :columns="$columns" :rows="$rows" />',
            ['columns' => $columns, 'rows' => $rows]
        );

        $this->assertStringContainsString('tel:9876543210', $html);
        $this->assertStringContainsString('https://wa.me/919876543210', $html);
        $this->assertStringContainsString('Chat on WhatsApp', $html);
    }

    public function test_advanced_table_handles_missing_phone_gracefully(): void
    {
        $this->actingAs($this->clientAdminA);

        $columns = [
            ['key' => 'mobile', 'label' => 'Mobile', 'type' => 'phone'],
        ];

        $rows = [
            (object) ['id' => 1, 'mobile' => null],
            (object) ['id' => 2, 'mobile' => ''],
        ];

        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-ui.advanced-table :columns="$columns" :rows="$rows" />',
            ['columns' => $columns, 'rows' => $rows]
        );

        $this->assertStringNotContainsString('tel:', $html);
        $this->assertStringNotContainsString('https://wa.me/', $html);
        $this->assertStringContainsString('—', $html);
    }

    public function test_advanced_table_renders_configure_display_button_in_lead_actions_for_authorized_users(): void
    {
        $this->actingAs($this->clientAdminA);

        $columns = [
            ['key' => 'actions', 'label' => 'Actions', 'type' => 'lead_actions'],
        ];

        $rows = [
            (object) ['id' => 42],
        ];

        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-ui.advanced-table :columns="$columns" :rows="$rows" />',
            ['columns' => $columns, 'rows' => $rows]
        );

        $this->assertStringContainsString('Configure Display', $html);
        $this->assertStringContainsString('open-display-config', $html);
        $this->assertStringContainsString('leadId: 42', $html);
    }
}
