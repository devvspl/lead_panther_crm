<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\Lead;
use App\Models\LeadSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class RoleScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Builder']);
        Role::create(['name' => 'Channel Partner']);
        Role::create(['name' => 'Account Manager']);
        Role::create(['name' => 'Sales Executive']);
    }

    public function test_super_admin_sees_all_tenant_leads(): void
    {
        $org1 = Organization::create(['name' => 'Org Scope 1', 'type' => 'builder']);
        $client1 = Client::create(['organization_id' => $org1->id, 'name' => 'Client Scope 1']);
        $project1 = Project::create(['client_id' => $client1->id, 'name' => 'Project Scope 1']);

        $org2 = Organization::create(['name' => 'Org Scope 2', 'type' => 'builder']);
        $client2 = Client::create(['organization_id' => $org2->id, 'name' => 'Client Scope 2']);
        $project2 = Project::create(['client_id' => $client2->id, 'name' => 'Project Scope 2']);

        $source = LeadSource::create(['name' => 'meta']);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Lead::factory()->create(['client_id' => $client1->id, 'project_id' => $project1->id, 'lead_source_id' => $source->id]);
        Lead::factory()->create(['client_id' => $client2->id, 'project_id' => $project2->id, 'lead_source_id' => $source->id]);

        $this->actingAs($admin);
        $this->assertCount(2, Lead::all());
    }

    public function test_builder_admin_sees_only_own_organization_leads(): void
    {
        $org1 = Organization::create(['name' => 'Builder Org 1', 'type' => 'builder']);
        $client1 = Client::create(['organization_id' => $org1->id, 'name' => 'Builder Client 1']);
        $project1 = Project::create(['client_id' => $client1->id, 'name' => 'Project 1']);

        $org2 = Organization::create(['name' => 'Builder Org 2', 'type' => 'builder']);
        $client2 = Client::create(['organization_id' => $org2->id, 'name' => 'Builder Client 2']);
        $project2 = Project::create(['client_id' => $client2->id, 'name' => 'Project 2']);

        $source = LeadSource::create(['name' => 'google']);

        $builderUser = User::factory()->create(['organization_id' => $org1->id]);
        $builderUser->assignRole('Builder');

        $lead1 = Lead::factory()->create(['client_id' => $client1->id, 'project_id' => $project1->id, 'lead_source_id' => $source->id]);
        $lead2 = Lead::factory()->create(['client_id' => $client2->id, 'project_id' => $project2->id, 'lead_source_id' => $source->id]);

        $this->actingAs($builderUser);
        $leads = Lead::all();

        $this->assertCount(1, $leads);
        $this->assertEquals($lead1->id, $leads->first()->id);
    }

    public function test_channel_partner_sees_only_assigned_organization_leads(): void
    {
        $org1 = Organization::create(['name' => 'CP Org 1', 'type' => 'channel_partner']);
        $client1 = Client::create(['organization_id' => $org1->id, 'name' => 'CP Client 1']);
        $project1 = Project::create(['client_id' => $client1->id, 'name' => 'CP Project 1']);

        $org2 = Organization::create(['name' => 'CP Org 2', 'type' => 'channel_partner']);
        $client2 = Client::create(['organization_id' => $org2->id, 'name' => 'CP Client 2']);
        $project2 = Project::create(['client_id' => $client2->id, 'name' => 'CP Project 2']);

        $source = LeadSource::create(['name' => 'portal']);

        $cpUser = User::factory()->create(['organization_id' => $org1->id]);
        $cpUser->assignRole('Channel Partner');

        $lead1 = Lead::factory()->create(['client_id' => $client1->id, 'project_id' => $project1->id, 'lead_source_id' => $source->id]);
        Lead::factory()->create(['client_id' => $client2->id, 'project_id' => $project2->id, 'lead_source_id' => $source->id]);

        $this->actingAs($cpUser);
        $leads = Lead::all();

        $this->assertCount(1, $leads);
        $this->assertEquals($lead1->id, $leads->first()->id);
    }

    public function test_builder_user_visiting_leads_kanban_renders_without_sql_error_and_scopes_campaigns_and_leads(): void
    {
        $org1 = Organization::create(['name' => 'Builder Org A', 'type' => 'builder']);
        $client1 = Client::create(['organization_id' => $org1->id, 'name' => 'Builder Client A']);
        $project1 = Project::create(['client_id' => $client1->id, 'name' => 'Project A']);

        $org2 = Organization::create(['name' => 'Builder Org B', 'type' => 'builder']);
        $client2 = Client::create(['organization_id' => $org2->id, 'name' => 'Builder Client B']);
        $project2 = Project::create(['client_id' => $client2->id, 'name' => 'Project B']);

        $source = LeadSource::create(['name' => 'meta_ads']);

        $campaign1 = \App\Models\Campaign::create(['project_id' => $project1->id, 'lead_source_id' => $source->id, 'name' => 'Campaign A']);
        $campaign2 = \App\Models\Campaign::create(['project_id' => $project2->id, 'lead_source_id' => $source->id, 'name' => 'Campaign B']);

        $lead1 = Lead::factory()->create(['client_id' => $client1->id, 'project_id' => $project1->id, 'campaign_id' => $campaign1->id, 'lead_source_id' => $source->id]);
        $lead2 = Lead::factory()->create(['client_id' => $client2->id, 'project_id' => $project2->id, 'campaign_id' => $campaign2->id, 'lead_source_id' => $source->id]);

        $builderUser = User::factory()->create(['organization_id' => $org1->id]);
        $builderUser->assignRole('Builder');

        $this->actingAs($builderUser);

        $response = $this->get(route('leads.index'));
        $response->assertStatus(200);

        $scopedCampaigns = \App\Models\Campaign::all();
        $this->assertCount(1, $scopedCampaigns);
        $this->assertEquals($campaign1->id, $scopedCampaigns->first()->id);

        $scopedLeads = Lead::all();
        $this->assertCount(1, $scopedLeads);
        $this->assertEquals($lead1->id, $scopedLeads->first()->id);
    }

    public function test_sidebar_navigation_renders_only_allowed_nav_items_per_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $builder = User::factory()->create();
        $builder->assignRole('Builder');

        $adminNav = \App\Support\NavigationConfig::getNavItemsForUser($admin);
        $this->assertNotEmpty($adminNav['database']);
        $adminDbKeys = collect($adminNav['database'])->pluck('key')->toArray();
        $this->assertContains('organizations', $adminDbKeys);
        $this->assertContains('users', $adminDbKeys);

        $builderNav = \App\Support\NavigationConfig::getNavItemsForUser($builder);
        $this->assertEmpty($builderNav['database']);
        $builderPrimaryKeys = collect($builderNav['primary'])->pluck('key')->toArray();
        $this->assertContains('dashboard', $builderPrimaryKeys);
        $this->assertContains('leads', $builderPrimaryKeys);
        $this->assertContains('bulk-import', $builderPrimaryKeys);
        $this->assertNotContains('organizations', $builderPrimaryKeys);
    }
}
