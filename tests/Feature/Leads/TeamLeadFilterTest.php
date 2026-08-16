<?php

namespace Tests\Feature\Leads;

use Tests\TestCase;
use App\Models\User;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\LeadSource;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Livewire\Leads\LeadKanban;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class TeamLeadFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'channel-partner']);
        Role::create(['name' => 'sales-executive']);
    }

    public function test_super_admin_filtering_by_team_narrows_leads_to_team_members(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $org = Organization::create(['name' => 'Builder Org', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client A']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project A']);
        $source = LeadSource::create(['name' => 'Direct']);

        $teamA = SalesTeam::create(['ownable_type' => Organization::class, 'ownable_id' => $org->id, 'name' => 'Alpha Team']);
        $teamB = SalesTeam::create(['ownable_type' => Organization::class, 'ownable_id' => $org->id, 'name' => 'Beta Team']);

        $repA = User::factory()->create(['organization_id' => $org->id]);
        $repB = User::factory()->create(['organization_id' => $org->id]);

        SalesTeamMember::create(['sales_team_id' => $teamA->id, 'user_id' => $repA->id]);
        SalesTeamMember::create(['sales_team_id' => $teamB->id, 'user_id' => $repB->id]);

        $leadA = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'assigned_to' => $repA->id,
            'current_stage' => 'new',
            'name' => 'Lead Alpha Rep',
        ]);

        $leadB = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'assigned_to' => $repB->id,
            'current_stage' => 'new',
            'name' => 'Lead Beta Rep',
        ]);

        Livewire::actingAs($admin)
            ->test(LeadKanban::class)
            ->set('team', (string)$teamA->id)
            ->assertSee('Lead Alpha Rep')
            ->assertDontSee('Lead Beta Rep');
    }

    public function test_channel_partner_with_single_team_hides_redundant_filter(): void
    {
        $org = Organization::create(['name' => 'Partner Org', 'type' => 'channel_partner']);
        $cpUser = User::factory()->create(['organization_id' => $org->id]);
        $cpUser->assignRole('channel-partner');

        SalesTeam::create(['ownable_type' => Organization::class, 'ownable_id' => $org->id, 'name' => 'Solo Partner Team']);

        Livewire::actingAs($cpUser)
            ->test(LeadKanban::class)
            ->assertDontSee('select-team');
    }

    public function test_sales_executive_hides_team_filter(): void
    {
        $exec = User::factory()->create();
        $exec->assignRole('sales-executive');

        Livewire::actingAs($exec)
            ->test(LeadKanban::class)
            ->assertDontSee('select-team');
    }

    public function test_analytics_breakdown_by_team_aggregates_metrics(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $org = Organization::create(['name' => 'Builder Org', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client A']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project A']);
        $source = LeadSource::create(['name' => 'Direct']);

        $team = SalesTeam::create(['ownable_type' => Organization::class, 'ownable_id' => $org->id, 'name' => 'Top Sales Team']);
        $rep = User::factory()->create(['organization_id' => $org->id]);
        SalesTeamMember::create(['sales_team_id' => $team->id, 'user_id' => $rep->id]);

        Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'assigned_to' => $rep->id,
            'current_stage' => 'closed_won',
            'created_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(LeadKanban::class)
            ->set('breakdownTab', 'team')
            ->assertSee('Top Sales Team');
    }
}
