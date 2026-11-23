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

    public function test_can_remove_project_with_zero_leads(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Zero Leads Org', 'status' => 'active']);
        $client = User::factory()->create(['organization_id' => $org->id, 'name' => 'Zero Leads Owner']);
        $client->assignRole('Builder/Developer');
        $project = Project::create(['client_id' => $client->id, 'name' => 'Empty Project', 'status' => 'active']);

        $component = Livewire::actingAs($admin)->test(OrganizationDetail::class, ['organization' => $org]);

        $component->set('activeTab', 'clients')
            ->assertSee('Empty Project')
            ->assertSee('Remove')
            ->call('openRemoveProjectModal', $project->id)
            ->assertSet('showRemoveProjectModal', true)
            ->assertSet('projectAssignedLeadsCount', 0)
            ->call('confirmRemoveProject')
            ->assertSet('showRemoveProjectModal', false)
            ->assertDispatched('toast', type: 'success');

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_removing_project_with_assigned_leads_requires_destination_project_and_moves_leads(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Multi Project Org', 'status' => 'active']);
        $client = User::factory()->create(['organization_id' => $org->id, 'name' => 'Multi Project Owner']);
        $client->assignRole('Builder/Developer');

        $projectA = Project::create(['client_id' => $client->id, 'name' => 'Boulevard walk by H&S', 'status' => 'active']);
        $projectB = Project::create(['client_id' => $client->id, 'name' => 'Elan Boulevard', 'status' => 'active']);

        $source = \App\Models\LeadSource::create(['name' => 'meta_ads']);
        $campaignA = \App\Models\Campaign::create(['project_id' => $projectA->id, 'name' => 'Campaign A', 'lead_source_id' => $source->id, 'status' => 'active']);
        $campaignB = \App\Models\Campaign::create(['project_id' => $projectB->id, 'name' => 'Campaign B', 'lead_source_id' => $source->id, 'status' => 'active']);

        $lead1 = \App\Models\Lead::create([
            'lead_code' => 'LP-TEST-001',
            'client_id' => $client->id,
            'campaign_id' => $campaignA->id,
            'lead_source_id' => $source->id,
            'name' => 'Lead One',
            'mobile' => '9876543210',
            'status' => 'new',
        ]);
        $lead2 = \App\Models\Lead::create([
            'lead_code' => 'LP-TEST-002',
            'client_id' => $client->id,
            'campaign_id' => $campaignA->id,
            'lead_source_id' => $source->id,
            'name' => 'Lead Two',
            'mobile' => '9876543211',
            'status' => 'new',
        ]);

        $component = Livewire::actingAs($admin)->test(OrganizationDetail::class, ['organization' => $org]);

        $component->set('activeTab', 'clients')
            ->call('openRemoveProjectModal', $projectA->id)
            ->assertSet('showRemoveProjectModal', true)
            ->assertSet('projectAssignedLeadsCount', 2)
            ->assertSee('2 Lead(s) Assigned')
            ->assertSee('Move Leads to Project')
            // Attempt to confirm without selecting destination project
            ->call('confirmRemoveProject')
            ->assertHasErrors('destinationProjectId')
            // Now select project B as destination
            ->set('destinationProjectId', $projectB->id)
            ->call('confirmRemoveProject')
            ->assertHasNoErrors()
            ->assertSet('showRemoveProjectModal', false)
            ->assertDispatched('toast', type: 'success');

        // Project A is deleted
        $this->assertDatabaseMissing('projects', ['id' => $projectA->id]);
        // Project B still exists
        $this->assertDatabaseHas('projects', ['id' => $projectB->id]);

        // Leads are moved to project B (their campaign is under project B)
        $this->assertEquals($projectB->id, $lead1->fresh()->project->id);
        $this->assertEquals($projectB->id, $lead2->fresh()->project->id);

        // Audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'project.removed',
            'subject_id' => $projectA->id,
        ]);
    }

    public function test_cannot_remove_only_project_if_it_has_assigned_leads(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Single Project Org', 'status' => 'active']);
        $client = User::factory()->create(['organization_id' => $org->id, 'name' => 'Single Owner']);
        $client->assignRole('Builder/Developer');

        $project = Project::create(['client_id' => $client->id, 'name' => 'Only Project', 'status' => 'active']);
        $source = \App\Models\LeadSource::create(['name' => 'direct']);
        $campaign = \App\Models\Campaign::create(['project_id' => $project->id, 'name' => 'Only Campaign', 'lead_source_id' => $source->id, 'status' => 'active']);

        \App\Models\Lead::create([
            'lead_code' => 'LP-TEST-003',
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'lead_source_id' => $source->id,
            'name' => 'Solo Lead',
            'mobile' => '9876543212',
            'status' => 'new',
        ]);

        $component = Livewire::actingAs($admin)->test(OrganizationDetail::class, ['organization' => $org]);

        $component->set('activeTab', 'clients')
            ->call('openRemoveProjectModal', $project->id)
            ->assertSet('showRemoveProjectModal', true)
            ->assertSet('projectAssignedLeadsCount', 1)
            ->assertSee('There are no other projects mapped to this organization')
            ->call('confirmRemoveProject')
            ->assertHasErrors('destinationProjectId');

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_can_edit_project_by_clicking_project_name(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Edit Project Org', 'status' => 'active']);
        $client = User::factory()->create(['organization_id' => $org->id, 'name' => 'Edit Project Owner']);
        $client->assignRole('Builder/Developer');

        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Original Project Name',
            'status' => 'active',
            'lead_cost' => 5,
        ]);

        $component = Livewire::actingAs($admin)->test(OrganizationDetail::class, ['organization' => $org]);

        $component->set('activeTab', 'clients')
            ->assertSee('Original Project Name')
            ->assertSee('Edit')
            // Click the project name / edit button
            ->call('openEditProjectModal', $project->id)
            ->assertSet('showEditProjectModal', true)
            ->assertDispatched('open-modal', 'edit-org-project-modal')
            ->assertSet('editingProjectId', $project->id)
            ->assertSet('editingProjectName', 'Original Project Name')
            ->assertSet('editingProjectStatus', 'active')
            ->assertSet('editingProjectLeadCost', 5)
            // Edit project name and status
            ->set('editingProjectName', 'Updated Boulevard Walk')
            ->set('editingProjectStatus', 'inactive')
            ->set('editingProjectLeadCost', 8)
            ->call('updateProject')
            ->assertHasNoErrors()
            ->assertSet('showEditProjectModal', false)
            ->assertDispatched('close-modal', 'edit-org-project-modal')
            ->assertDispatched('toast', type: 'success');

        $fresh = $project->fresh();
        $this->assertEquals('Updated Boulevard Walk', $fresh->name);
        $this->assertEquals('inactive', $fresh->status);
        $this->assertEquals(8, $fresh->lead_cost);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'action' => 'project.updated',
            'subject_id' => $project->id,
        ]);
    }
}
