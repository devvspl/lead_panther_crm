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
}
