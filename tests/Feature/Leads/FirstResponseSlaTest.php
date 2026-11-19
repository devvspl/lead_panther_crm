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

    private function executiveFixture(int $minutes = 15): array
    {
        $org = Organization::create(['name' => 'Response client', 'status' => 'active', 'executive_response_minutes' => $minutes]);
        $owner = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $exec = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $exec->assignRole('Sales Executive');
        $source = LeadSource::create(['name' => 'Response tests']);
        $lead = Lead::create(['client_id' => $owner->id, 'assigned_to' => $exec->id, 'lead_source_id' => $source->id,
            'lead_code' => Lead::generateUniqueLeadCode(), 'name' => 'Response customer', 'mobile' => '9000012345',
            'status' => 'assigned', 'current_stage' => 'assigned', 'delivered_at' => now(), 'delivery_pending' => false]);
        return [$org, $owner, $exec, $lead->fresh()];
    }

    public function test_client_specific_deadline_and_response_history_survive_reassignment(): void
    {
        $this->travelTo(now()->startOfMinute());
        [$org, $owner, $exec, $lead] = $this->executiveFixture(30);
        $this->assertEquals(30, $lead->executive_assigned_at->diffInMinutes($lead->executive_response_due_at));
        $this->assertSame('SLA Pending', $lead->sla_badge);
        $other = User::factory()->create(['organization_id' => $org->id]);
        LeadCall::create(['lead_id' => $lead->id, 'user_id' => $other->id, 'duration_seconds' => 1, 'outcome' => 'connected', 'called_at' => now()]);
        $this->assertNull($lead->fresh()->executive_first_response_at);
        $this->travel(10)->minutes();
        LeadCall::create(['lead_id' => $lead->id, 'user_id' => $exec->id, 'duration_seconds' => 1, 'outcome' => 'connected', 'called_at' => now()]);
        $this->assertSame('SLA Met', $lead->fresh()->sla_badge);
        $this->assertEquals(10, $lead->fresh()->executive_response_time);
        $previous = \App\Models\LeadAssignment::where('lead_id', $lead->id)->firstOrFail();
        $lead->refresh()->update(['assigned_to' => $other->id]);
        $this->assertNull($lead->fresh()->executive_first_response_at);
        $this->assertNotNull($previous->fresh()->first_response_at);
        $this->assertNotNull($previous->fresh()->unassigned_at);
        $this->travel(31)->minutes();
        $this->assertSame('SLA Breached', $lead->fresh()->sla_badge);
        LeadCall::create(['lead_id' => $lead->id, 'user_id' => $other->id, 'duration_seconds' => 1, 'outcome' => 'connected', 'called_at' => now()]);
        $this->assertEquals(31, $lead->fresh()->executive_response_time);
        $this->assertSame('SLA Breached', $lead->fresh()->sla_badge);
        $this->assertSame(2, \App\Models\LeadAssignment::where('lead_id', $lead->id)->whereNotNull('first_response_at')->count());
    }

    public function test_approaching_and_breached_notifications_are_deduplicated_and_stop_after_response(): void
    {
        [$org, $owner, $exec, $lead] = $this->executiveFixture();
        $this->travel(12)->minutes();
        $this->artisan('notifications:check-operational')->assertExitCode(0);
        $this->artisan('notifications:check-operational')->assertExitCode(0);
        $this->assertSame(1, $exec->notifications()->where('data->type', 'sla_approaching')->count());
        $this->travel(4)->minutes();
        $this->artisan('notifications:check-operational')->assertExitCode(0);
        $this->artisan('notifications:check-operational')->assertExitCode(0);
        $this->assertSame(1, $exec->notifications()->where('data->type', 'sla_breached')->count());
        LeadCall::create(['lead_id' => $lead->id, 'user_id' => $exec->id, 'duration_seconds' => 1, 'outcome' => 'connected', 'called_at' => now()]);
        $this->travel(1)->hours();
        $this->artisan('notifications:check-operational')->assertExitCode(0);
        $this->assertSame(1, $exec->notifications()->where('data->type', 'sla_breached')->count());
    }

    public function test_clients_have_independent_targets_and_reports_use_assignment_deadlines(): void
    {
        $this->travelTo(now()->startOfMinute());
        [$slowOrg, $owner, $slowExec, $slowLead] = $this->executiveFixture(30);
        [$fastOrg, $fastOwner, $fastExec, $fastLead] = $this->executiveFixture(5);
        $this->travel(6)->minutes();
        $this->assertSame('SLA Pending', $slowLead->fresh()->sla_badge);
        $this->assertSame('SLA Breached', $fastLead->fresh()->sla_badge);
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => 'active']); $admin->assignRole('Super Admin');
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Reports\SlaDashboard::class)
            ->assertViewHas('buckets', fn ($b) => $b['breached'] === 1 && $b['acceptable'] === 1);
        LeadCall::create(['lead_id' => $slowLead->id, 'user_id' => $slowExec->id, 'duration_seconds' => 1, 'outcome' => 'connected', 'called_at' => now()]);
        \Livewire\Livewire::test(\App\Livewire\Reports\SlaDashboard::class)
            ->assertViewHas('buckets', fn ($b) => $b['excellent'] === 1 && $b['breached'] === 1 && $b['acceptable'] === 0);
    }

    public function test_only_super_admin_can_save_response_minutes_and_delivery_settings_remain_separate(): void
    {
        [$org, $owner, $exec, $lead] = $this->executiveFixture();
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['status' => 'active']); $admin->assignRole('Super Admin');
        $component = \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\OrganizationDetail::class, ['organization' => $org]);
        $component->set('executiveResponseMinutes', 0)->call('saveExecutiveResponseTime')->assertHasErrors('executiveResponseMinutes');
        $component->set('executiveResponseMinutes', 45)->call('saveExecutiveResponseTime')->assertHasNoErrors();
        $this->assertEquals(45, $org->fresh()->executive_response_minutes);
        $this->assertEquals(15, $lead->executive_assigned_at->diffInMinutes($lead->fresh()->executive_response_due_at));
        \Livewire\Livewire::actingAs($exec)->test(\App\Livewire\Admin\OrganizationDetail::class, ['organization' => $org])
            ->assertForbidden();
        $this->assertEquals(45, $org->fresh()->executive_response_minutes);
    }

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
