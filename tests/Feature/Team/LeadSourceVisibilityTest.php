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
