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

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_projects_and_wallets_exclude_team_members_and_project_rates_save(): void
    {
        $this->seed(DeltekSeeder::class);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');
        $owner = User::where('email', 'deltek@deltek.leadpanther.test')->firstOrFail();
        $org = Organization::findOrFail($owner->organization_id);
        $component = Livewire::actingAs($admin)->test(OrganizationDetail::class, ['organization' => $org]);
        $component->set('activeTab', 'activity')->assertSee('Performed By')->assertSee('IP Address')
            ->assertViewHas('auditLogs', fn ($logs) => $logs instanceof \Illuminate\Pagination\LengthAwarePaginator);
        $component->set('activeTab', 'clients')->assertSee('Project Mapping (0)')
            ->assertViewHas('clients', fn ($clients) => $clients->modelKeys() === [$owner->id])
            ->set('mappingProjectName', 'Mapped Project')
            ->call('saveProjectMapping')->assertHasNoErrors();
        $project = Project::withoutGlobalScopes()->where('name', 'Mapped Project')->firstOrFail();
        $this->assertEquals($owner->id, $project->client_id);
        $component->set('activeTab', 'credits')->assertSee('Project-wise Lead Pricing')
            ->set('projectRates.'.$project->id, '23.50')->call('saveProjectRate', $project->id)->assertHasErrors('projectRates.'.$project->id);
        $component->set('projectRates.'.$project->id, '23')->call('saveProjectRate', $project->id)->assertHasNoErrors();
        $this->assertEquals(23, $project->fresh()->lead_cost);
        $component->set('projectRates.'.$project->id, '')->call('saveProjectRate', $project->id)->assertHasNoErrors();
        $this->assertNull($project->fresh()->lead_cost);
        $otherOrg = Organization::create(['name' => 'Other Org']);
        $other = User::factory()->create(['organization_id' => $otherOrg->id]);
        $other->assignRole('Builder/Developer');
        $outside = Project::create(['client_id' => $other->id, 'name' => 'Outside Project']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $component->call('saveProjectRate', $outside->id);
    }

    public function test_project_mapping_automatically_provisions_billing_owner_client_when_none_exists(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        // Organization with NO pre-existing billing owner (e.g. only executive / team leader)
        $org = Organization::create(['name' => 'H&S By Investors Clinic', 'status' => 'active']);
        $executive = User::factory()->create(['organization_id' => $org->id, 'name' => 'H&S Executive 01']);
        $executive->assignRole('Executive');
        $teamLeader = User::factory()->create(['organization_id' => $org->id, 'name' => 'H&S Team Leader']);
        $teamLeader->assignRole('Team Leader');

        $component = Livewire::actingAs($admin)->test(OrganizationDetail::class, ['organization' => $org]);

        $component->set('activeTab', 'clients')
            ->assertSee('No Billing Owner Registered Yet')
            ->assertSee('Auto-Create Main User')
            ->assertSee('+ Add Client / Billing Owner')
            ->set('mappingProjectName', 'Boulevard walk by H&S')
            ->call('saveProjectMapping')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success', message: 'Project mapping saved.');

        $project = Project::withoutGlobalScopes()->where('name', 'Boulevard walk by H&S')->firstOrFail();
        $this->assertNotNull($project->client_id);

        $client = \App\Models\Client::withoutGlobalScopes()->findOrFail($project->client_id);
        $this->assertEquals($org->id, $client->organization_id);
        $this->assertEquals('H&S By Investors Clinic', $client->name);
        $this->assertTrue($client->hasRole('Builder/Developer'));

        // Verify credit wallet was automatically created with 0 balance
        $wallet = \App\Models\CreditWallet::where('client_id', $client->id)->firstOrFail();
        $this->assertEquals(0, $wallet->balance);
    }

    public function test_auto_create_main_user_button_provisions_billing_owner(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Prestige Heights', 'status' => 'active']);

        $component = Livewire::actingAs($admin)->test(OrganizationDetail::class, ['organization' => $org]);

        $component->set('activeTab', 'clients')
            ->assertSee('Auto-Create Main User')
            ->call('autoCreateMainUser')
            ->assertDispatched('toast', type: 'success');

        $client = \App\Models\Client::withoutGlobalScopes()->where('organization_id', $org->id)->firstOrFail();
        $this->assertEquals('Prestige Heights', $client->name);
        $this->assertTrue($client->hasRole('Builder/Developer'));
        $this->assertDatabaseHas('client_wallets', ['client_id' => $client->id, 'balance' => 0]);
    }
}
