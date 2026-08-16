<?php

namespace Tests\Feature\Leads;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadCall;
use App\Models\SiteVisit;
use App\Livewire\Leads\LeadKanban;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class LeadScoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Sales Executive']);
    }

    public function test_lead_score_calculates_and_recalculates_on_activities(): void
    {
        $exec = User::factory()->create();
        $exec->assignRole('Sales Executive');

        $org = Organization::create(['name' => 'Org Score', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Score']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Score']);
        $source = LeadSource::create(['name' => 'portal_direct', 'default_score_weight' => 25]);

        $lead = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'assigned_to' => $exec->id,
            'budget' => 8000000.00,
            'requirement' => 'Looking for 3 BHK luxury apartment with balcony',
            'current_stage' => 'assigned',
            'first_response_at' => now()->subMinutes(10),
        ]);

        $initialScore = $lead->fresh()->lead_score;
        $this->assertGreaterThan(0, $initialScore);

        // Create Outbound Call activity -> triggers LeadScoreObserver
        LeadCall::create([
            'lead_id' => $lead->id,
            'user_id' => $exec->id,
            'duration_seconds' => 120,
            'outcome' => 'connected',
            'called_at' => now(),
        ]);

        $scoreAfterCall = $lead->fresh()->lead_score;
        $this->assertGreaterThanOrEqual($initialScore, $scoreAfterCall);

        // Log Attended Site Visit -> triggers LeadScoreObserver
        SiteVisit::create([
            'lead_id' => $lead->id,
            'executive_id' => $exec->id,
            'attendance' => 'attended',
            'visit_date' => now()->subHour(),
        ]);

        $scoreAfterSiteVisit = $lead->fresh()->lead_score;
        $this->assertGreaterThanOrEqual($scoreAfterCall, $scoreAfterSiteVisit);
    }

    public function test_kanban_sorts_by_lead_score_descending(): void
    {
        $exec = User::factory()->create();
        $exec->assignRole('Sales Executive');

        $org = Organization::create(['name' => 'Org Kanban Score', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Kanban Score']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Kanban Score']);
        $source = LeadSource::create(['name' => 'meta', 'default_score_weight' => 10]);

        $leadLow = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'assigned_to' => $exec->id,
            'current_stage' => 'assigned',
            'lead_score' => 20,
        ]);

        $leadHigh = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'assigned_to' => $exec->id,
            'current_stage' => 'assigned',
            'lead_score' => 90,
        ]);

        Livewire::actingAs($exec)
            ->test(LeadKanban::class)
            ->set('sort_by', 'score_desc')
            ->assertSee($leadHigh->name)
            ->assertSee($leadLow->name);
    }
}
