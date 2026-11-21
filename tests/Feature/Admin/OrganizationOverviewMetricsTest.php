<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\OrganizationDetail;
use App\Models\{Client, CreditWallet, Lead, LeadSource, Organization, Project, User};
use App\Services\LeadArchiveService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationOverviewMetricsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create(['status'=>'active']);
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);
    }

    private function lead(User $owner, ?User $assignee = null, string $status = 'new'): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'lead_code'=>Lead::generateUniqueLeadCode(), 'client_id'=>$owner->id,
            'lead_source_id'=>LeadSource::firstOrCreate(['name'=>'Overview test source'])->id,
            'name'=>'Organization lead', 'mobile'=>'9000012345', 'status'=>$status,
            'current_stage'=>$status, 'assigned_to'=>$assignee?->id, 'delivery_pending'=>false,
            'internal_processing_pending'=>false,
        ]);
    }

    private function owner(string $name, Organization $org): Client
    {
        $owner = Client::create(['name'=>$name,'organization_id'=>$org->id]);
        User::findOrFail($owner->id)->assignRole('Builder/Developer');
        return $owner;
    }

    public function test_five_metrics_use_actual_organization_records_and_do_not_leak_into_empty_organizations(): void
    {
        $a = Organization::create(['name'=>'Client A', 'status'=>'active']);
        $b = Organization::create(['name'=>'Client B', 'status'=>'active']);
        $empty = Organization::create(['name'=>'Empty client', 'status'=>'active']);
        $ownerA = $this->owner('Owner A',$a);
        $ownerA2 = $this->owner('Second owner A',$a);
        $ownerB = $this->owner('Owner B',$b);
        $executive = User::factory()->create(['organization_id'=>$a->id,'status'=>'active']);
        $executive->assignRole(['Executive','Team Leader']); // Count a member once across multiple roles.
        $inactive = User::factory()->create(['organization_id'=>$a->id,'status'=>'inactive']);
        $inactive->assignRole('Executive');
        User::factory()->create(['organization_id'=>$a->id,'status'=>'active']); // No assigned role.
        Project::create(['name'=>'Active A','client_id'=>$ownerA->id,'status'=>'active']);
        Project::create(['name'=>'Second active A','client_id'=>$ownerA2->id,'status'=>'active']);
        Project::create(['name'=>'Inactive A','client_id'=>$ownerA->id,'status'=>'inactive']);
        Project::create(['name'=>'Active B','client_id'=>$ownerB->id,'status'=>'active']);
        CreditWallet::create(['client_id'=>$ownerA->id,'balance'=>40,'opening_balance'=>1000]);
        CreditWallet::create(['client_id'=>$ownerA2->id,'balance'=>10,'opening_balance'=>500]);
        CreditWallet::create(['client_id'=>$ownerB->id,'balance'=>7,'opening_balance'=>900]);
        $this->lead($ownerA,$executive);
        $this->lead($ownerA2);
        $this->lead($ownerA,$inactive); // Received but not assigned to an active member.
        $archived = $this->lead($ownerA,$executive);
        app(LeadArchiveService::class)->archive($archived->id,'Exclude archived lead');
        $this->lead($ownerA,$executive,'inactive');
        $this->lead($ownerA,$executive,'deleted');
        $this->lead($ownerB,$executive); // An A assignee must not change B ownership.

        $component = Livewire::test(OrganizationDetail::class,['organization'=>$a])
            ->assertViewHas('activeTeamMembersCount',3)
            ->assertViewHas('activeProjectsCount',2)
            ->assertViewHas('totalLeadsCount',3)
            ->assertViewHas('directlyAssignedLeadsCount',1)
            ->assertViewHas('totalWalletBalance',50);
        $kpis = explode('<!-- Tabbed Navigation Bar -->',explode('data-organization-kpis',$component->html(),2)[1],2)[0];
        foreach (['Team Members','Active Projects','Total Leads','Assigned Leads','Available Lead Credits','Sufficient'] as $label) $this->assertStringContainsString($label,$kpis);
        foreach (['<span>Clients</span>','Opening Balance','Projects & Rate'] as $label) $this->assertStringNotContainsString($label,$kpis);
        $this->assertSame(5,substr_count($kpis,'<svg'));
        $component->set('userSearch','No matching name')->assertViewHas('activeTeamMembersCount',3)->assertViewHas('totalLeadsCount',3);

        Livewire::test(OrganizationDetail::class,['organization'=>$b])
            ->assertViewHas('activeTeamMembersCount',1)->assertViewHas('activeProjectsCount',1)
            ->assertViewHas('totalLeadsCount',1)->assertViewHas('directlyAssignedLeadsCount',0)
            ->assertViewHas('totalWalletBalance',7)->assertSee('Low Credits');
        Livewire::test(OrganizationDetail::class,['organization'=>$empty])
            ->assertViewHas('activeTeamMembersCount',0)->assertViewHas('activeProjectsCount',0)
            ->assertViewHas('totalLeadsCount',0)->assertViewHas('directlyAssignedLeadsCount',0)
            ->assertViewHas('totalWalletBalance',0.0);
    }

    public function test_member_project_assignment_and_wallet_changes_are_reflected_on_refresh(): void
    {
        $org = Organization::create(['name'=>'Changing client','status'=>'active']);
        $owner = $this->owner('Owner',$org);
        $executive = User::factory()->create(['organization_id'=>$org->id,'status'=>'active']);
        $executive->assignRole('Executive');
        $project = Project::create(['name'=>'Project','client_id'=>$owner->id,'status'=>'active']);
        $wallet = CreditWallet::create(['client_id'=>$owner->id,'balance'=>50,'opening_balance'=>800]);
        $lead = $this->lead($owner,$executive);
        $component = Livewire::test(OrganizationDetail::class,['organization'=>$org])
            ->assertViewHas('activeTeamMembersCount',2)->assertViewHas('activeProjectsCount',1)
            ->assertViewHas('directlyAssignedLeadsCount',1)->assertViewHas('totalWalletBalance',50.0)->assertSee('Sufficient');
        $executive->update(['status'=>'inactive']);
        $project->update(['status'=>'inactive']);
        $wallet->update(['balance'=>23]);
        $component->call('$refresh')->assertViewHas('activeTeamMembersCount',1)->assertViewHas('activeProjectsCount',0)
            ->assertViewHas('totalLeadsCount',1)->assertViewHas('directlyAssignedLeadsCount',0)
            ->assertViewHas('totalWalletBalance',23)->assertSee('Low Credits');
        $lead->update(['assigned_to'=>null]);
        $component->call('$refresh')->assertViewHas('directlyAssignedLeadsCount',0);
    }
}
