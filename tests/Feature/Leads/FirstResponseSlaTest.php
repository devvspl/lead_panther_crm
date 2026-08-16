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
use App\Events\FirstResponseRecorded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class FirstResponseSlaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Sales Executive']);
    }

    public function test_first_call_sets_first_response_at_and_second_call_does_not_override_it(): void
    {
        Event::fake([FirstResponseRecorded::class]);

        $exec = User::factory()->create();
        $exec->assignRole('Sales Executive');

        $org = Organization::create(['name' => 'Org SLA', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client SLA']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project SLA']);
        $source = LeadSource::create(['name' => 'meta']);

        $lead = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'assigned_to' => $exec->id,
            'current_stage' => 'assigned',
            'status' => 'assigned',
            'first_response_at' => null,
        ]);

        // Clear first_response_at to null cleanly
        DB::table('leads')->where('id', $lead->id)->update(['first_response_at' => null]);
        $lead->refresh();

        // 1. Assert initial state is null
        $this->assertNull($lead->first_response_at);

        $firstResponseTimestamp = null;

        // 2. Log first call
        LeadCall::create([
            'lead_id' => $lead->id,
            'user_id' => $exec->id,
            'duration_seconds' => 120,
            'outcome' => 'connected',
            'called_at' => now(),
        ]);

        $lead->refresh();
        $firstResponseTimestamp = (string) $lead->first_response_at;

        $this->assertNotNull($firstResponseTimestamp);
        Event::assertDispatched(FirstResponseRecorded::class);

        // Sleep 1 second simulation
        sleep(1);

        // 3. Log second call
        LeadCall::create([
            'lead_id' => $lead->id,
            'user_id' => $exec->id,
            'duration_seconds' => 60,
            'outcome' => 'followup_scheduled',
            'called_at' => now(),
        ]);

        $lead->refresh();

        // 4. Assert first_response_at did NOT change
        $this->assertEquals($firstResponseTimestamp, $lead->first_response_at);
    }

    public function test_backfill_artisan_command_infers_historical_first_response(): void
    {
        $exec = User::factory()->create();

        $org = Organization::create(['name' => 'Org Backfill', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Backfill']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Backfill']);
        $source = LeadSource::create(['name' => 'google']);

        $lead = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'current_stage' => 'assigned',
            'status' => 'assigned',
            'first_response_at' => null,
        ]);

        // Explicitly clear first_response_at to null for backfill simulation
        DB::table('leads')->where('id', $lead->id)->update(['first_response_at' => null]);

        // Create historical LeadCall via raw DB insert to bypass Eloquent observers
        DB::table('lead_calls')->insert([
            'lead_id' => $lead->id,
            'user_id' => $exec->id,
            'duration_seconds' => 180,
            'outcome' => 'connected',
            'called_at' => now()->subDays(5),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $this->assertNull($lead->fresh()->first_response_at);

        $this->artisan('leads:backfill-first-response')
            ->assertExitCode(0);

        $this->assertNotNull($lead->fresh()->first_response_at);
    }
}
