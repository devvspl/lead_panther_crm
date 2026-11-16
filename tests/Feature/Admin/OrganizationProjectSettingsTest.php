<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, Organization, Project};
use App\Livewire\Admin\OrganizationDetail;
use Database\Seeders\DeltekSeeder;
use Livewire\Livewire;

class OrganizationProjectSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_and_wallets_exclude_team_members_and_project_rates_save(): void
    {
        $this->seed(DeltekSeeder::class);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');
        $owner = User::where('email', 'deltek@deltek.leadpanther.test')->firstOrFail();
        $org = Organization::findOrFail($owner->organization_id);
        $component = Livewire::actingAs($admin)->test(OrganizationDetail::class, ['organization' => $org]);
        $component->set('activeTab', 'clients')->assertSee('Project Mapping (0)')
            ->assertViewHas('clients', fn ($clients) => $clients->modelKeys() === [$owner->id])
            ->set('mappingProjectName', 'Mapped Project')->set('mappingClientId', $owner->id)
            ->call('saveProjectMapping')->assertHasNoErrors();
        $project = Project::withoutGlobalScopes()->where('name', 'Mapped Project')->firstOrFail();
        $component->set('activeTab', 'credits')->assertSee('Project-wise Lead Pricing')
            ->set('projectRates.'.$project->id, '23.50')->call('saveProjectRate', $project->id)->assertHasNoErrors();
        $this->assertEquals(23.5, $project->fresh()->lead_cost);
        $component->set('projectRates.'.$project->id, '')->call('saveProjectRate', $project->id)->assertHasNoErrors();
        $this->assertNull($project->fresh()->lead_cost);
        $otherOrg = Organization::create(['name' => 'Other Org']);
        $other = User::factory()->create(['organization_id' => $otherOrg->id]);
        $other->assignRole('Builder/Developer');
        $outside = Project::create(['client_id' => $other->id, 'name' => 'Outside Project']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $component->call('saveProjectRate', $outside->id);
    }
}
