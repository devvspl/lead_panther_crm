<?php

namespace Tests\Feature\Distribution;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\DistributionRule;
use App\Models\CreditTransaction;
use App\Events\CreditReserved;
use App\Events\LeadAssigned;
use App\Listeners\AssignLeadAfterCreditReserved;
use App\Livewire\Distribution\DistributionRuleForm;
use App\Livewire\Distribution\TeamAvailability;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

class LeadDistributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'sales-executive']);
    }

    public function test_distribution_rule_form_renders_and_saves_rule(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Builder Org', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Builder Client']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Grand Residency']);

        $response = $this->actingAs($user)->get("/projects/{$project->id}/distribution");
        $response->assertOk();

        Livewire::actingAs($user)
            ->test(DistributionRuleForm::class, ['project' => $project])
            ->set('ruleType', 'round_robin')
            ->call('saveRule');

        $this->assertDatabaseHas('distribution_rules', [
            'project_id' => $project->id,
            'rule_type' => 'round_robin',
        ]);
    }

    public function test_pick_assignee_round_robin_strategy(): void
    {
        $exec = User::factory()->create(['name' => 'Exec One']);
        $exec->assignRole('sales-executive');

        $org = Organization::create(['name' => 'Builder Org 2', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Builder Client 2']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project RR']);
        $source = LeadSource::create(['name' => 'meta']);

        DistributionRule::create([
            'project_id' => $project->id,
            'rule_type' => 'round_robin',
            'is_active' => true,
        ]);

        $lead = Lead::factory()->create(['project_id' => $project->id, 'client_id' => $client->id, 'lead_source_id' => $source->id]);

        $assignee = DistributionRule::pickAssignee($lead);

        $this->assertNotNull($assignee);
        $this->assertEquals($exec->id, $assignee->id);
    }

    public function test_assign_lead_after_credit_reserved_listener_creates_assignment_row(): void
    {
        Event::fake([LeadAssigned::class]);

        $exec = User::factory()->create(['name' => 'Exec Two']);
        $exec->assignRole('sales-executive');

        $org = Organization::create(['name' => 'Builder Org 3', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Builder Client 3']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Assign']);
        $source = LeadSource::create(['name' => 'google']);

        $lead = Lead::factory()->create(['project_id' => $project->id, 'client_id' => $client->id, 'lead_source_id' => $source->id]);
        $tx = CreditTransaction::create([
            'client_id' => $client->id,
            'lead_id' => $lead->id,
            'credit_before' => 100,
            'credit_used' => 10,
            'credit_after' => 90,
            'transaction_type' => 'reserve',
            'created_at' => now(),
        ]);

        $listener = new AssignLeadAfterCreditReserved();
        $listener->handle(new CreditReserved($lead, $tx));

        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id,
            'assigned_to' => $exec->id,
        ]);

        Event::assertDispatched(LeadAssigned::class);
    }

    public function test_team_availability_toggle_and_auto_offline_command(): void
    {
        $exec = User::factory()->create(['status' => 'active']);
        $exec->assignRole('sales-executive');

        Livewire::test(TeamAvailability::class)
            ->call('toggleStatus', $exec->id);

        $this->assertEquals('inactive', strtolower($exec->fresh()->status));

        // Re-activate and run command
        $exec->update(['status' => 'active']);

        $this->artisan('teams:auto-offline')
            ->assertExitCode(0);

        $this->assertEquals('inactive', strtolower($exec->fresh()->status));
    }
}
