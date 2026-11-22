<?php

namespace Tests\Feature\Settings;

use App\Livewire\Admin\OrganizationDetail;
use App\Livewire\Settings\OrganizationProfile;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Organization;
use App\Models\Project;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Models\User;
use App\Services\LeadDistributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadAssignmentModeTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $superAdmin;
    protected User $builderUser;
    protected User $exec1;
    protected User $exec2;
    protected SalesTeam $team;
    protected Project $project;
    protected Campaign $campaign;
    protected LeadSource $leadSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(\Carbon\Carbon::parse('2026-11-17 10:00', 'UTC'));

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Builder/Developer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Builder', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Executive', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Sales Executive', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin User',
            'email' => 'admin@leadpanther.com',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->org = Organization::create([
            'name' => 'Apex Realty',
            'status' => 'active',
            'auto_assign_enabled' => true,
            'lead_assignment_mode' => 'automatic',
        ]);

        $this->builderUser = User::factory()->create([
            'name' => 'Apex Builder',
            'email' => 'builder@apex.com',
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);
        $this->builderUser->assignRole('Builder/Developer');

        $this->exec1 = User::factory()->create([
            'name' => 'Executive One',
            'email' => 'exec1@apex.com',
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);
        $this->exec1->assignRole('Sales Executive');

        $this->exec2 = User::factory()->create([
            'name' => 'Executive Two',
            'email' => 'exec2@apex.com',
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);
        $this->exec2->assignRole('Sales Executive');

        $this->team = SalesTeam::create([
            'name' => 'Apex Core Sales',
            'ownable_type' => Organization::class,
            'ownable_id' => $this->org->id,
        ]);

        SalesTeamMember::create([
            'sales_team_id' => $this->team->id,
            'user_id' => $this->exec1->id,
            'is_active' => true,
        ]);

        SalesTeamMember::create([
            'sales_team_id' => $this->team->id,
            'user_id' => $this->exec2->id,
            'is_active' => true,
        ]);

        $this->project = Project::create([
            'name' => 'Apex Horizon',
            'client_id' => $this->builderUser->id,
            'status' => 'active',
            'delivery_enabled' => true,
        ]);

        $this->leadSource = LeadSource::firstOrCreate(['name' => 'meta']);

        $this->campaign = Campaign::create([
            'name' => 'Apex Horizon Campaign',
            'project_id' => $this->project->id,
            'lead_source_id' => $this->leadSource->id,
            'status' => 'active',
        ]);
    }

    private function createDeliveredLead(array $attributes = []): Lead
    {
        $lead = Lead::create(array_merge([
            'lead_code' => 'LP-' . uniqid(),
            'client_id' => $this->builderUser->id,
            'campaign_id' => $this->campaign->id,
            'project_id' => $this->project->id,
            'lead_source_id' => $this->leadSource->id,
            'name' => 'Test Customer',
            'mobile' => '91' . rand(1000000000, 9999999999),
            'current_stage' => 'new',
            'status' => 'new',
        ], $attributes));

        $lead->update([
            'delivered_at' => now(),
            'delivery_pending' => false,
            'internal_processing_pending' => false,
        ]);

        return $lead->fresh();
    }

    public function test_default_lead_assignment_mode_is_automatic(): void
    {
        $newOrg = Organization::create([
            'name' => 'New Org',
            'status' => 'active',
        ]);

        $this->assertSame(Organization::ASSIGNMENT_MODE_AUTOMATIC, $newOrg->getLeadAssignmentMode());
        $this->assertTrue($newOrg->isAutomaticAssignment());
        $this->assertFalse($newOrg->isManualAssignment());
    }

    public function test_super_admin_can_update_lead_assignment_mode_via_organization_detail(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(OrganizationDetail::class, ['organization' => $this->org])
            ->call('setLeadAssignmentMode', Organization::ASSIGNMENT_MODE_MANUAL)
            ->assertDispatched('toast');

        $this->org->refresh();
        $this->assertSame(Organization::ASSIGNMENT_MODE_MANUAL, $this->org->getLeadAssignmentMode());
        $this->assertTrue($this->org->isManualAssignment());
        $this->assertFalse($this->org->auto_assign_enabled);

        // Switch back to automatic
        Livewire::test(OrganizationDetail::class, ['organization' => $this->org])
            ->call('setLeadAssignmentMode', Organization::ASSIGNMENT_MODE_AUTOMATIC)
            ->assertDispatched('toast');

        $this->org->refresh();
        $this->assertSame(Organization::ASSIGNMENT_MODE_AUTOMATIC, $this->org->getLeadAssignmentMode());
        $this->assertTrue($this->org->isAutomaticAssignment());
        $this->assertTrue($this->org->auto_assign_enabled);
    }

    public function test_builder_user_can_view_and_save_assignment_mode_for_own_organization(): void
    {
        $this->actingAs($this->builderUser);

        Livewire::test(OrganizationProfile::class)
            ->call('setLeadAssignmentMode', Organization::ASSIGNMENT_MODE_MANUAL)
            ->assertDispatched('toast');

        $this->org->refresh();
        $this->assertSame(Organization::ASSIGNMENT_MODE_MANUAL, $this->org->getLeadAssignmentMode());
        $this->assertTrue($this->org->isManualAssignment());
        $this->assertFalse($this->org->auto_assign_enabled);

        // Switch back to automatic
        Livewire::test(OrganizationProfile::class)
            ->call('setLeadAssignmentMode', Organization::ASSIGNMENT_MODE_AUTOMATIC)
            ->assertDispatched('toast');

        $this->org->refresh();
        $this->assertSame(Organization::ASSIGNMENT_MODE_AUTOMATIC, $this->org->getLeadAssignmentMode());
        $this->assertTrue($this->org->isAutomaticAssignment());
        $this->assertTrue($this->org->auto_assign_enabled);
    }

    public function test_sales_executive_cannot_configure_organization_assignment_mode(): void
    {
        $this->actingAs($this->exec1);

        Livewire::test(OrganizationProfile::class)
            ->call('setLeadAssignmentMode', Organization::ASSIGNMENT_MODE_MANUAL)
            ->assertForbidden();
    }

    public function test_automatic_mode_assigns_new_lead_to_sales_executive(): void
    {
        $this->org->update([
            'lead_assignment_mode' => Organization::ASSIGNMENT_MODE_AUTOMATIC,
            'auto_assign_enabled' => true,
        ]);

        $service = app(LeadDistributionService::class);
        $defaults = $service->assignmentDefaults($this->org);
        $defaults['team_id'] = $this->team->id;
        $defaults['enabled'] = true;
        $defaults['assignment_mode'] = Organization::ASSIGNMENT_MODE_AUTOMATIC;
        $defaults['timezone'] = 'UTC';
        $service->saveAssignmentConfiguration($this->org, $defaults, $this->superAdmin);

        $lead = $this->createDeliveredLead(['name' => 'Auto Customer']);

        $res = $service->distribute($lead);

        $this->assertSame('assigned', $res['status']);
        $this->assertNotNull($lead->fresh()->assigned_to);
        $this->assertContains($lead->fresh()->assigned_to, [$this->exec1->id, $this->exec2->id]);
    }

    public function test_manual_mode_routes_new_lead_to_builder_account_without_auto_assigning_executives(): void
    {
        $this->org->update([
            'lead_assignment_mode' => Organization::ASSIGNMENT_MODE_MANUAL,
            'auto_assign_enabled' => false,
        ]);

        $service = app(LeadDistributionService::class);
        $defaults = $service->assignmentDefaults($this->org);
        $defaults['team_id'] = $this->team->id;
        $defaults['enabled'] = false;
        $defaults['assignment_mode'] = Organization::ASSIGNMENT_MODE_MANUAL;
        $defaults['timezone'] = 'UTC';
        $service->saveAssignmentConfiguration($this->org, $defaults, $this->superAdmin);

        $lead = $this->createDeliveredLead(['name' => 'Manual Customer']);

        $res = $service->distribute($lead);

        $this->assertNull($res['assigned_user']);
        $this->assertNull($lead->fresh()->assigned_to);
        $this->assertSame($this->builderUser->id, $lead->fresh()->client_id);

        // Verify lead is visible to Builder
        $this->actingAs($this->builderUser);
        $this->assertTrue(Lead::panelQuery()->whereKey($lead->id)->exists());

        // Verify lead is NOT visible to Sales Executive
        $this->actingAs($this->exec1);
        $this->assertFalse(Lead::panelQuery()->whereKey($lead->id)->exists());
    }

    public function test_switching_modes_does_not_modify_existing_assigned_leads(): void
    {
        // 1. Create an already assigned lead
        $lead = $this->createDeliveredLead([
            'assigned_to' => $this->exec1->id,
            'current_stage' => 'contacted',
            'status' => 'contacted',
        ]);

        // 2. Switch mode to manual
        $this->actingAs($this->superAdmin);
        Livewire::test(OrganizationDetail::class, ['organization' => $this->org])
            ->call('setLeadAssignmentMode', Organization::ASSIGNMENT_MODE_MANUAL);

        // 3. Assert existing lead remained assigned to exec1
        $this->assertSame($this->exec1->id, $lead->fresh()->assigned_to);
        $this->assertSame('contacted', $lead->fresh()->current_stage);
    }
}
