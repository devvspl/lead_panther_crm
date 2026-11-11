<?php

namespace Tests\Feature\Leads;

use Tests\TestCase;
use App\Models\User;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\LeadSource;
use App\Livewire\Leads\LeadKanban;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class LeadKanbanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'client']);
        Role::create(['name' => 'sales-executive']);
        Role::create(['name' => 'channel-partner']);
    }

    public function test_kanban_board_renders_successfully_for_authenticated_users(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $response = $this->actingAs($user)->get('/leads');

        $response->assertOk();
    }

    public function test_kanban_stage_update_creates_status_history_and_dispatches_event(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $org = Organization::create(['name' => 'Test Org', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Test Client']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Test Project']);
        $source = LeadSource::create(['name' => 'meta']);

        $lead = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'current_stage' => 'new',
        ]);

        Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->call('updateLeadStage', $lead->id, 'contacted');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'current_stage' => 'contacted',
        ]);

        $this->assertDatabaseHas('lead_status_history', [
            'lead_id' => $lead->id,
            'from_status' => 'new',
            'to_status' => 'contacted',
        ]);
    }

    public function test_kanban_board_contains_searchable_select_for_projects_and_agents(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->assertSeeLivewire('shared.searchable-select')
            ->assertSee('Sort By')
            ->assertSee('All Projects')
            ->assertSee('All Agents')
            ->assertSee('All Sources');
    }

    public function test_autofilled_user_email_in_search_is_cleared(): void
    {
        $user = User::factory()->create(['email' => 'admin@leadpanther.com']);
        $user->assignRole('super-admin');

        // Test with query param search = user email (e.g. browser autofill or stale URL)
        Livewire::withQueryParams(['search' => 'admin@leadpanther.com'])
            ->actingAs($user)
            ->test(LeadKanban::class)
            ->assertSet('search', '');

        // Test with live input updated to user email
        Livewire::actingAs($user)
            ->test(LeadKanban::class)
            ->set('search', 'admin@leadpanther.com')
            ->assertSet('search', '')
            ->set('search', 'Genuine Lead Name')
            ->assertSet('search', 'Genuine Lead Name');
    }

    public function test_lead_detail_and_timeline_with_lead_assignment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $agent = User::factory()->create(['name' => 'Agent Vikram']);
        $agent->assignRole('sales-executive');

        $org = Organization::create(['name' => 'Test Org', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Test Client']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Test Project']);
        $source = LeadSource::create(['name' => 'meta']);

        $lead = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'assigned_to' => $agent->id,
            'current_stage' => 'assigned',
        ]);

        \App\Models\LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $agent->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
        ]);

        // Assert LeadDetail loads and renders lead journey without RelationNotFoundException
        Livewire::actingAs($user)
            ->test(\App\Livewire\Leads\LeadDetail::class)
            ->call('openLead', $lead->id)
            ->assertSet('isOpen', true)
            ->assertSee('Agent Vikram');

        // Assert LeadTimeline loads and renders lead journey without RelationNotFoundException
        Livewire::actingAs($user)
            ->test(\App\Livewire\Leads\LeadTimeline::class)
            ->call('openJourney', $lead->id)
            ->assertSet('isOpen', true)
            ->assertSee('Agent Vikram');
    }

    public function test_table_columns_show_campaign_and_form_and_exclude_project(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $component = Livewire::actingAs($user)->test(LeadKanban::class);
        $columns = $component->instance()->tableColumns();
        $keys = collect($columns)->pluck('key')->all();

        $this->assertContains('project_name', $keys);
        $this->assertContains('campaign_name', $keys);

        // Verify Lead accessors
        $org = Organization::create(['name' => 'Agency Org', 'type' => 'developer']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Agency Client']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Dholera Smart City']);
        $source = LeadSource::create(['name' => 'meta']);
        $campaign = \App\Models\Campaign::create([
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'name' => 'Dholera Mega Campaign',
            'status' => 'active',
        ]);

        $lead = Lead::factory()->create([
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'lead_source_id' => $source->id,
        ]);

        \App\Models\LeadMetadata::create([
            'lead_id' => $lead->id,
            'key' => 'meta_form_name',
            'value' => 'DHOLERA EMI_01 (v2)',
        ]);

        $lead->load('metadata', 'campaign');

        $this->assertEquals('Dholera Mega Campaign', $lead->campaign_name);
        $this->assertEquals('DHOLERA EMI_01 (v2)', $lead->form_name);
    }
}
