<?php

namespace Tests\Feature\Team;

use App\Livewire\Leads\LeadKanban;
use App\Livewire\Reports\ReportsContainer;
use App\Livewire\Reports\SourcePerformance;
use App\Models\{Lead, LeadSource, User};
use App\Support\{LeadPresenter, LeadSourceVisibility};
use Database\Seeders\DeltekSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadSourceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_monetary_data_is_super_admin_only_while_integer_credits_remain_visible(): void
    {
        $org = \App\Models\Organization::create(['name' => 'Financial privacy', 'status' => 'active']);
        $source = LeadSource::create(['name' => 'Private source']);
        foreach (['Builder/Developer', 'Client', 'Team Leader', 'Sales Executive'] as $role) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role]);
            $user = User::factory()->create(['organization_id' => $org->id]); $user->assignRole($role);
            $lead = Lead::withoutEvents(fn () => Lead::create(['lead_code' => Lead::generateUniqueLeadCode(), 'client_id' => $user->id, 'lead_source_id' => $source->id,
                'name' => 'Private financial lead', 'mobile' => '9000000101', 'budget' => 8999999, 'lead_cost' => 3, 'status' => 'new', 'current_stage' => 'new',
                'requirement' => "Budget: INR 8999999\nNeeds central location", 'delivery_pending' => false, 'delivered_at' => now()]));
            $this->actingAs($user);
            $this->assertArrayNotHasKey('budget', $lead->toArray());
            $this->assertArrayNotHasKey('budget', LeadPresenter::present($lead, $user));
            $this->assertSame(3, $lead->toArray()['lead_cost']);
            $this->assertStringNotContainsString('8999999', $lead->toArray()['requirement']);
            $this->assertStringContainsString('central location', $lead->toArray()['requirement']);
            $export = new \App\Exports\BaseStyledExport(collect([$lead]), 'Leads', '', ['Lead ID','Budget','Lead Credits'], ['lead_code','budget','lead_cost']);
            $this->assertSame(['Lead ID','Lead Credits'], $export->headings());
            $this->assertNotContains(8999999, $export->map($lead));
            $restricted = new \App\Exports\BaseStyledExport(collect([['budget' => 8999999]]), 'Private', '', ['Budget'], ['budget']);
            $this->assertSame([], $restricted->map(['budget' => 8999999]));
            if ($role === 'Builder/Developer') {
                Livewire::test(\App\Livewire\Leads\LeadDetail::class)->call('openLead', $lead->id)
                    ->assertDontSee('8999999')->assertDontSee('₹')->assertSee('central location');
            }
            Livewire::test(\App\Livewire\Reports\RevenueAndBookings::class)->assertForbidden();
        }
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin']);
        $admin = User::factory()->create(); $admin->assignRole('Super Admin'); $this->actingAs($admin);
        $this->assertEquals(8999999, $lead->fresh()->toArray()['budget']);
        Livewire::test(\App\Livewire\Reports\RevenueAndBookings::class)->assertOk();
    }

    public function test_all_client_roles_hide_source_and_use_client_friendly_labels_without_changing_storage(): void
    {
        $org = \App\Models\Organization::create(['name' => 'Client wording', 'status' => 'active']);
        $source = LeadSource::create(['name' => 'Private source sentinel']);
        foreach (['Builder/Developer', 'Client', 'client', 'Broker', 'Channel Partner', 'Team Leader', 'Sales Executive'] as $role) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            $user = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']); $user->assignRole($role);
            $lead = Lead::create(['lead_code' => Lead::generateUniqueLeadCode(), 'lead_source_id' => $source->id,
                'client_id' => $user->id, 'assigned_to' => $user->id, 'name' => 'Wording customer', 'mobile' => '9000000011',
                'status' => 'assigned', 'current_stage' => 'assigned', 'requirement' => 'Manual lead entry via Lead Manager', 'delivery_pending' => false]);
            $this->actingAs($user);
            $this->assertFalse(LeadSourceVisibility::allowed(), $role);
            $directory = Livewire::test(LeadKanban::class)->assertDontSee('Private source sentinel');
            $this->assertNotContains('lead_source_name', array_column($directory->instance()->tableColumns(), 'key'));
            Livewire::test(SourcePerformance::class)->assertForbidden();
            $this->assertSame('On Demand Assignment', \App\Support\ClientFacingText::label('Manual Assignment'));
            $this->assertSame('Credit Adjustment', \App\Support\ClientFacingText::label('Manual adjustment'));
            $this->assertArrayNotHasKey('lead_source_id', $lead->toArray());
            $lead->setRelation('campaign', new \App\Models\Campaign(['name' => 'Assigned campaign', 'lead_source_id' => $source->id]));
            $this->assertArrayNotHasKey('lead_source_id', $lead->toArray()['campaign']);
            $this->assertSame('Assigned campaign', $lead->toArray()['campaign']['name']);
            $this->assertStringNotContainsString('Manual', LeadPresenter::present($lead, $user)['requirement']);
            $this->assertSame('Manual lead entry via Lead Manager', $lead->fresh()->getRawOriginal('requirement'));
            $payload = \App\Support\ClientFacingText::activity(json_encode(['lead_source_id' => $source->id, 'values' => ['source_name' => $source->name, 'method' => 'manual']]));
            $this->assertStringNotContainsString('lead_source', $payload);
            $this->assertStringNotContainsString('Private source sentinel', $payload);
            $this->assertStringNotContainsString('manual', $payload);
        }
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => 'active']); $admin->assignRole('Super Admin'); $this->actingAs($admin);
        $this->assertTrue(LeadSourceVisibility::allowed());
        $this->assertSame('Manual Assignment', \App\Support\ClientFacingText::label('Manual Assignment'));
        $this->assertSame($source->id, $lead->fresh()->lead_source_id);
    }

    public function test_client_assignment_labels_are_readable_while_enum_values_are_unchanged(): void
    {
        $org = \App\Models\Organization::create(['name' => 'Client assignment wording', 'status' => 'active',
            'auto_assign_enabled' => false, 'assignment_config' => ['method' => 'manual']]);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Builder/Developer', 'guard_name' => 'web']);
        $owner = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']); $owner->assignRole('Builder/Developer');
        $component = Livewire::actingAs($owner)->test(\App\Livewire\Settings\LeadAssignmentConfiguration::class, ['organizationId' => $org->id])
            ->assertSee('On Demand');
        $this->assertDoesNotMatchRegularExpression('/manual/i', html_entity_decode(strip_tags($component->html())));
        $this->assertSame('manual', $org->fresh()->assignment_config['method']);
    }

    public function test_source_is_hidden_for_owner_leader_and_executive_but_preserved_for_admin(): void
    {
        $this->seed(DeltekSeeder::class);
        $owner = User::where('email', 'deltek@deltek.leadpanther.test')->firstOrFail();
        $executive = User::where('email', 'se1@deltek.leadpanther.test')->firstOrFail();
        $source = LeadSource::firstOrCreate(['name' => 'Private origin sentinel']);
        $lead = Lead::withoutGlobalScopes()->create([
            'lead_code' => 'LP-SOURCE-PRIVATE', 'lead_source_id' => $source->id,
            'client_id' => $owner->id, 'assigned_to' => $executive->id,
            'name' => 'Visibility test', 'mobile' => '9000000000',
            'status' => 'assigned', 'current_stage' => 'assigned', 'delivery_pending' => false,
        ]);

        foreach (['deltek', 'brijesh', 'se1'] as $account) {
            $user = User::where('email', $account.'@deltek.leadpanther.test')->firstOrFail();
            $this->actingAs($user);
            $this->assertFalse(LeadSourceVisibility::allowed());
            $directory = Livewire::test(LeadKanban::class)->assertDontSee('Private origin sentinel');
            $this->assertStringNotContainsString('>Lead Source</label>', $directory->html());
            $this->assertNotContains('lead_source_name', array_column($directory->instance()->tableColumns(), 'key'));
            $directory->set('source', (string) $source->id)->assertSet('source', '');
            Livewire::test(\App\Livewire\Leads\LeadDetail::class)->call('openLead', $lead->id)
                ->assertDontSee('Private origin sentinel')->assertDontSee('Lead Source &amp; Ingestion');
            $sheets = (new \App\Exports\DashboardReportExport(leads: collect([$lead])))->sheets();
            $this->assertNotContains('Source', end($sheets)->headings());
            Livewire::test(ReportsContainer::class)->assertSet('activeTab', 'sla')->assertDontSee('Source Performance')
                ->call('setTab', 'source')->assertForbidden();
            Livewire::test(SourcePerformance::class)->assertForbidden();
            $this->assertArrayNotHasKey('source_name', LeadPresenter::present($lead, $user));
            $serialized = $lead->load('leadSource')->toArray();
            $this->assertArrayNotHasKey('lead_source_id', $serialized);
            $this->assertArrayNotHasKey('lead_source', $serialized);
        }

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);
        $this->assertTrue(LeadSourceVisibility::allowed());
        Livewire::test(ReportsContainer::class)->assertSet('activeTab', 'source')->assertSee('Source Performance');
        $this->assertSame($source->id, $lead->fresh()->lead_source_id);
        $this->assertArrayHasKey('source_name', LeadPresenter::present($lead, $admin));
        $this->assertArrayHasKey('lead_source_id', $lead->toArray());
    }
}
