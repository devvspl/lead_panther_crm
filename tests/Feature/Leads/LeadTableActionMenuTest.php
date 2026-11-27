<?php

namespace Tests\Feature\Leads;

use App\Livewire\Leads\LeadKanban;
use App\Models\{Lead, LeadSource, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadTableActionMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_toolbar_renders_three_dot_action_menu_with_all_options(): void
    {
        $role = Role::findOrCreate('Super Admin');
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $source = LeadSource::create(['name' => 'Web']);
        $lead1 = Lead::create([
            'lead_code' => 'LP-ACTION-001',
            'name' => 'Lead One',
            'mobile' => '9800000001',
            'lead_source_id' => $source->id,
            'client_id' => $user->id,
            'status' => 'new',
            'current_stage' => 'new',
            'delivery_pending' => false,
            'internal_processing_pending' => false,
        ]);
        $lead2 = Lead::create([
            'lead_code' => 'LP-ACTION-002',
            'name' => 'Lead Two',
            'mobile' => '9800000002',
            'lead_source_id' => $source->id,
            'client_id' => $user->id,
            'status' => 'new',
            'current_stage' => 'new',
            'delivery_pending' => false,
            'internal_processing_pending' => false,
        ]);

        // When 0 leads selected: actions menu renders, selection-based items are disabled
        $component = Livewire::test(LeadKanban::class)
            ->set('viewMode', 'table')
            ->assertSee('Actions')
            ->assertSee('Multi-Client Delivery')
            ->assertSee('Revoke Delivery')
            ->assertSee('Do Not Send')
            ->assertSee('Assign Selected')
            ->assertSee('Unassign Selected')
            ->assertSee('Export to Excel')
            ->assertDontSee('Multi-Client Delivery (2)');

        // When leads are selected: selected count badge is displayed on trigger and within action labels
        $component->set('selectedRows', [$lead1->id, $lead2->id])
            ->assertSee('Multi-Client Delivery')
            ->assertSee('Revoke Delivery')
            ->assertSee('Do Not Send')
            ->assertSee('Assign Selected')
            ->assertSee('Unassign Selected')
            ->assertSee('Export to Excel')
            ->assertSee('(2)');
    }

    public function test_action_menu_triggers_expected_modals_and_events(): void
    {
        $role = Role::findOrCreate('Super Admin');
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $source = LeadSource::create(['name' => 'Web']);
        $lead = Lead::create([
            'lead_code' => 'LP-ACTION-003',
            'name' => 'Lead Three',
            'mobile' => '9800000003',
            'lead_source_id' => $source->id,
            'client_id' => $user->id,
            'status' => 'new',
            'current_stage' => 'new',
            'delivery_pending' => false,
            'internal_processing_pending' => false,
        ]);

        Livewire::test(LeadKanban::class)
            ->set('selectedRows', [$lead->id])
            ->call('openMultiClientDeliveryModal')
            ->assertSet('showMultiClientModal', true)
            ->call('openRevokeDeliveryModal')
            ->assertSet('showRevokeDeliveryModal', true)
            ->call('openBulkAssignModal')
            ->assertSet('showAssignModal', true)
            ->call('openExportModal')
            ->assertSet('showExportModal', true);
    }
}
