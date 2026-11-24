<?php

namespace Tests\Feature\Followup;

use App\Livewire\Activity\FollowupList;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FollowupActionsMenuTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $builder;
    protected User $teamLeader;
    protected User $executive;
    protected Organization $org;
    protected Project $project;
    protected Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin']);
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'Builder/Developer']);
        Role::firstOrCreate(['name' => 'Team Leader']);
        Role::firstOrCreate(['name' => 'Executive']);

        $this->org = Organization::create([
            'name' => 'H&S By Investors Clinic',
            'status' => 'active',
        ]);

        $client = Client::create([
            'organization_id' => $this->org->id,
            'name' => 'H&S Builder',
            'email' => 'builder@example.com',
            'status' => 'active',
        ]);
        $this->builder = User::find($client->id);
        $this->builder->assignRole('Builder/Developer');

        $this->executive = User::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Aman Exec',
            'email' => 'aman@example.com',
            'status' => 'active',
        ]);
        $this->executive->assignRole('Executive');

        $this->project = Project::create([
            'client_id' => $this->builder->id,
            'name' => 'Boulevard walk by H&S',
            'status' => 'active',
        ]);

        $source = LeadSource::firstOrCreate(['name' => 'Meta Ads']);
        $campaign = Campaign::create([
            'project_id' => $this->project->id,
            'lead_source_id' => $source->id,
            'name' => 'BW_01',
            'status' => 'active',
        ]);

        $this->lead = Lead::create([
            'client_id' => $this->builder->id,
            'campaign_id' => $campaign->id,
            'lead_source_id' => $source->id,
            'name' => 'Mohinder Singh',
            'mobile' => '9876543210',
            'email' => 'mohinder@example.com',
            'lead_code' => 'LP-2026-0000741',
            'current_stage' => 'interested',
            'status' => 'in_progress',
            'assigned_to' => $this->executive->id,
            'delivery_pending' => false,
            'internal_processing_pending' => false,
            'delivered_at' => now(),
            'executive_assigned_at' => now(),
        ]);
    }

    public function test_completed_tab_renders_three_dot_action_menu(): void
    {
        $fu = Followup::create([
            'lead_id' => $this->lead->id,
            'created_by' => $this->executive->id,
            'assigned_to' => $this->executive->id,
            'due_at' => now()->subDays(2),
            'status' => 'completed',
            'type' => 'call',
            'note' => 'Customer follow-up call',
            'outcome' => 'connected',
            'outcome_notes' => 'Discussed unit layout',
            'completed_at' => now()->subDay(),
            'completed_by' => $this->executive->id,
        ]);

        $this->actingAs($this->executive);

        Livewire::test(FollowupList::class)
            ->call('setTab', 'completed')
            ->assertSee('Mohinder Singh')
            ->assertSee('Customer follow-up call')
            ->assertSee('title="Follow-up options"', false)
            ->assertSee('View Lead Details')
            ->assertSee('Open in Pipeline')
            ->assertSee('Call (9876543210)')
            ->assertSee('WhatsApp Chat')
            ->assertSee('Schedule Follow-up');
    }

    public function test_pending_tabs_render_three_dot_action_menu_with_complete_reschedule_cancel(): void
    {
        $fu = Followup::create([
            'lead_id' => $this->lead->id,
            'created_by' => $this->executive->id,
            'assigned_to' => $this->executive->id,
            'due_at' => now()->startOfDay()->addHours(12),
            'status' => 'pending',
            'type' => 'call',
            'note' => 'Today follow-up discussion',
        ]);

        $this->actingAs($this->executive);

        Livewire::test(FollowupList::class)
            ->call('setTab', 'today')
            ->assertSee('Mohinder Singh')
            ->assertSee('Today follow-up discussion')
            ->assertSee('title="Follow-up options"', false)
            ->assertSee('View Lead Details')
            ->assertSee('Complete Follow-up')
            ->assertSee('Reschedule')
            ->assertSee('Reassign')
            ->assertSee('Cancel Follow-up');
    }

    public function test_requested_followup_renders_accept_request_option(): void
    {
        $requestFu = Followup::create([
            'lead_id' => $this->lead->id,
            'created_by' => $this->builder->id,
            'assigned_to' => $this->executive->id,
            'assigned_by' => $this->builder->id,
            'requested_by' => $this->builder->id,
            'due_at' => now()->addDay(),
            'status' => 'pending',
            'type' => 'call',
            'note' => 'Offer special festival discount',
            'request_status' => Followup::REQUEST_PENDING,
        ]);

        $this->actingAs($this->executive);

        Livewire::test(FollowupList::class)
            ->call('setTab', 'requests')
            ->assertSee('Mohinder Singh')
            ->assertSee('Offer special festival discount')
            ->assertSee('Accept Request')
            ->assertSee('Complete Follow-up');
    }
}
