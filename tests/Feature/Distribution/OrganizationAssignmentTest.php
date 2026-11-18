<?php
namespace Tests\Feature\Distribution;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Organization,User,Client,Project,Campaign,LeadSource,Lead,LeadAssignment,SalesTeam,SalesTeamMember,CreditWallet,AuditLog};
use App\Services\LeadDistributionService;
use App\Livewire\Settings\LeadAssignmentConfiguration;
use Livewire\Livewire;

class OrganizationAssignmentTest extends TestCase
{
    use RefreshDatabase;
    private Organization $org;
    private User $admin;
    private Client $owner;
    private Campaign $campaign;
    private SalesTeam $team;
    private array $agents;
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-17 10:00','UTC'));
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->admin=User::factory()->create(['status'=>'active']); $this->admin->assignRole('Super Admin'); $this->actingAs($this->admin);
        $this->org=Organization::create(['name'=>'Assignment Org','status'=>'active']);
        $this->owner=Client::create(['name'=>'Builder','organization_id'=>$this->org->id,'status'=>'active']);
        User::find($this->owner->id)->assignRole('Builder/Developer');
        $project=Project::create(['client_id'=>$this->owner->id,'name'=>'Project','status'=>'active','delivery_enabled'=>true]);
        $source=LeadSource::create(['name'=>'Import']);
        $this->campaign=Campaign::create(['name'=>'Campaign','project_id'=>$project->id,'lead_source_id'=>$source->id,'status'=>'active']);
        CreditWallet::create(['client_id'=>$this->owner->id,'balance'=>10000,'opening_balance'=>10000]);
        $this->team=SalesTeam::create(['name'=>'Sales','ownable_type'=>Organization::class,'ownable_id'=>$this->org->id]);
        $this->agents=[];
        for($i=0;$i<3;$i++) {
            $user=User::factory()->create(['organization_id'=>$this->org->id,'status'=>'active']); $user->assignRole('Executive');
            SalesTeamMember::create(['sales_team_id'=>$this->team->id,'user_id'=>$user->id,'is_active'=>true,'role'=>'Executive']);
            $this->agents[]=$user;
        }
    }
    private function service(): LeadDistributionService { return app(LeadDistributionService::class); }
    private function configure(array $overrides=[]): void
    {
        $settings=array_replace($this->service()->assignmentDefaults($this->org->fresh()),['enabled'=>true,'timezone'=>'UTC'],$overrides);
        $this->service()->saveAssignmentConfiguration($this->org,$settings,$this->admin);
    }
    private function lead(bool $delivered=true): Lead
    {
        $lead=Lead::create(['lead_source_id'=>$this->campaign->lead_source_id,'lead_code'=>Lead::generateUniqueLeadCode(),'client_id'=>$this->owner->id,'campaign_id'=>$this->campaign->id,'name'=>'Customer','mobile'=>'9'.str_pad((string)(Lead::withoutGlobalScopes()->count()+1),9,'0',STR_PAD_LEFT),'status'=>'new','current_stage'=>'new']);
        if($delivered) $lead->update(['delivered_at'=>now(),'delivery_pending'=>false]);
        return $lead->fresh();
    }
    public function test_round_robin_daily_limits_duplicate_prevention_and_timezone_reset(): void
    {
        $this->configure(['daily_limit'=>1,'timezone'=>'Asia/Kolkata']);
        foreach($this->agents as $agent) $this->assertSame($agent->id,$this->service()->distribute($this->lead())['assigned_user']->id);
        $pending=$this->lead(); $this->assertNull($this->service()->distribute($pending)['assigned_user']);
        $assigned=Lead::withoutGlobalScopes()->whereNotNull('assigned_to')->first();
        $this->service()->distribute($assigned); $this->assertSame(3,LeadAssignment::count());
        $this->travelTo(\Carbon\Carbon::parse('2026-11-17 18:31','UTC'));
        $this->assertNotNull($this->service()->distribute($pending)['assigned_user']);
        $this->assertSame(4,AuditLog::where('action','lead.auto_assigned')->count());
    }
    public function test_priority_skips_inactive_unavailable_and_inactive_memberships(): void
    {
        $this->configure(['method'=>'priority','members'=>[$this->agents[0]->id=>['priority'=>1,'available'=>false],$this->agents[1]->id=>['priority'=>2,'available'=>true],$this->agents[2]->id=>['priority'=>3,'available'=>true]]]);
        $this->agents[1]->update(['status'=>'inactive']);
        $this->assertSame($this->agents[2]->id,$this->service()->distribute($this->lead())['assigned_user']->id);
        SalesTeamMember::where('user_id',$this->agents[2]->id)->update(['is_active'=>false]);
        $this->assertNull($this->service()->distribute($this->lead())['assigned_user']);
    }
    public function test_equal_distribution_balances_current_total_and_batch_size(): void
    {
        $this->configure(['method'=>'equal_distribution','batch_size'=>2]);
        $this->service()->assignLeadToUser($this->lead(),$this->agents[0],'manual');
        $this->lead(); $this->lead(); $this->lead();
        $this->assertSame(2,$this->service()->processOrganizationAssignments($this->org->id));
        foreach($this->agents as $agent) $this->assertSame(1,Lead::withoutGlobalScopes()->where('assigned_to',$agent->id)->count());
        $this->assertSame(1,$this->service()->assignmentPendingLeads($this->org->id)->count());
    }
    public function test_working_window_manual_disabled_project_and_campaign_filters(): void
    {
        $lead=$this->lead();
        foreach([['enabled'=>false],['method'=>'manual'],['working_days'=>[0]],['start_time'=>'11:00'],['end_time'=>'09:00']] as $settings) {
            $this->configure(array_replace(['enabled'=>true,'method'=>'round_robin','working_days'=>[0,1,2,3,4,5,6],'start_time'=>'00:00','end_time'=>'23:59'],$settings));
            $this->assertNull($this->service()->distribute($lead)['assigned_user']);
        }
        $otherProject=Project::create(['client_id'=>$this->owner->id,'name'=>'Other']);
        $this->configure(['start_time'=>'00:00','end_time'=>'23:59','project_ids'=>[$otherProject->id]]);
        $this->assertNull($this->service()->distribute($lead)['assigned_user']);
        $this->configure(['project_ids'=>[$this->campaign->project_id],'campaign_ids'=>[$this->campaign->id]]);
        $this->assertNotNull($this->service()->distribute($lead)['assigned_user']);
    }
    public function test_delivered_lead_is_automatically_assigned_only_after_release_and_isolated_in_panels(): void
    {
        $this->configure(); $lead=$this->lead(false);
        $this->assertNull($this->service()->distribute($lead)['assigned_user']);
        $this->assertTrue($this->service()->releaseDelivery($lead->id));
        $this->assertSame($this->agents[0]->id,$lead->fresh()->assigned_to);
        $this->actingAs($this->agents[0]); $this->assertTrue(Lead::panelQuery()->whereKey($lead->id)->exists());
        $this->actingAs($this->agents[1]); $this->assertFalse(Lead::panelQuery()->whereKey($lead->id)->exists());
        $this->actingAs($this->admin);
        $this->service()->assignLeadToUser($lead,$this->agents[1],'manual');
        $this->assertSame(2,LeadAssignment::where('lead_id',$lead->id)->count());
        $this->assertSame(1,LeadAssignment::where('lead_id',$lead->id)->whereNull('unassigned_at')->count());
    }
    public function test_configuration_ui_validation_and_permissions(): void
    {
        Livewire::test(LeadAssignmentConfiguration::class,['organizationId'=>$this->org->id])->assertSee('Lead Assignment Configuration')
            ->set('settings.enabled',true)->set('settings.method','equal_distribution')->call('save')->assertHasNoErrors();
        $this->assertSame('equal_distribution',$this->org->fresh()->assignment_config['method']);
        $this->assertNotNull(AuditLog::where('action','assignment.configuration_updated')->first()->to_value);
        Livewire::actingAs(User::find($this->owner->id))->test(LeadAssignmentConfiguration::class,['organizationId'=>$this->org->id])->assertSet('settings',[])->call('save')->assertForbidden();
        Livewire::actingAs($this->agents[0])->test(LeadAssignmentConfiguration::class,['organizationId'=>$this->org->id])->assertForbidden();
        $other=Organization::create(['name'=>'Other org']);
        Livewire::actingAs(User::find($this->owner->id))->test(LeadAssignmentConfiguration::class,['organizationId'=>$other->id])->assertForbidden();
    }
    public function test_team_leader_manages_only_the_configured_team_pending_pool_and_reassignment(): void
    {
        $leader=User::factory()->create(['organization_id'=>$this->org->id,'status'=>'active']); $leader->assignRole('Team Leader');
        $this->team->update(['leader_id'=>$leader->id]);
        SalesTeamMember::create(['sales_team_id'=>$this->team->id,'user_id'=>$leader->id,'is_active'=>true,'role'=>'Team Leader']);
        $otherLeader=User::factory()->create(['organization_id'=>$this->org->id,'status'=>'active']); $otherLeader->assignRole('Team Leader');
        $otherTeam=SalesTeam::create(['name'=>'Other Sales','ownable_type'=>Organization::class,'ownable_id'=>$this->org->id,'leader_id'=>$otherLeader->id]);
        SalesTeamMember::create(['sales_team_id'=>$otherTeam->id,'user_id'=>$otherLeader->id,'is_active'=>true,'role'=>'Team Leader']);
        $this->configure(['method'=>'manual','team_id'=>$this->team->id]);
        $lead=$this->lead();
        $this->actingAs($otherLeader); $this->assertFalse(Lead::panelQuery()->whereKey($lead->id)->exists());
        $this->actingAs($leader); $this->assertTrue(Lead::panelQuery()->whereKey($lead->id)->exists());
        Livewire::test(\App\Livewire\Leads\LeadKanban::class)->set('selectedLeadIds',[$lead->id])->set('assignUserId',$this->agents[0]->id)
            ->call('executeAssignment')->assertHasNoErrors();
        $this->assertSame($this->agents[0]->id,$lead->fresh()->assigned_to);
        Livewire::test(\App\Livewire\Leads\LeadKanban::class)->set('selectedLeadIds',[$lead->id])->set('assignUserId',$this->agents[1]->id)
            ->call('executeAssignment')->assertHasNoErrors();
        $this->assertSame($this->agents[1]->id,$lead->fresh()->assigned_to);
        $this->assertSame(1,LeadAssignment::where('lead_id',$lead->id)->whereNull('unassigned_at')->count());
    }

    public function test_background_job_respects_batch_size_and_daily_limits_without_login(): void
    {
        $this->configure(['batch_size'=>2,'daily_limit'=>1]);
        for($i=0;$i<4;$i++) $this->lead();
        auth()->logout();
        $job=new \App\Jobs\AssignOrganizationLeads($this->org->id);
        $job->handle($this->service());
        $this->assertSame(2,LeadAssignment::count());
        $job->handle($this->service());
        $job->handle($this->service());
        $this->assertSame(3,LeadAssignment::count());
        $this->assertSame(1,$this->service()->assignmentPendingLeads($this->org->id)->count());
        foreach($this->service()->assignmentStatistics($this->org->fresh()) as $row) {
            $this->assertSame(1,$row['today']); $this->assertSame(0,$row['remaining']);
        }
        $this->assertNull(AuditLog::where('action','lead.auto_assigned')->first()->user_id);
    }

    public function test_foreign_configuration_ids_are_rejected_and_pending_filters_do_not_starve_eligible_leads(): void
    {
        $first=$this->lead();
        $project=Project::create(['client_id'=>$this->owner->id,'name'=>'Eligible']);
        $campaign=Campaign::create(['project_id'=>$project->id,'lead_source_id'=>$this->campaign->lead_source_id,'name'=>'Eligible campaign','status'=>'active']);
        $this->configure(['project_ids'=>[$project->id],'campaign_ids'=>[$campaign->id],'batch_size'=>1]);
        $second=$this->lead(); $second->update(['campaign_id'=>$campaign->id]);
        $this->assertSame(1,$this->service()->processOrganizationAssignments($this->org->id));
        $this->assertNull($first->fresh()->assigned_to); $this->assertNotNull($second->fresh()->assigned_to);
        $foreign=User::factory()->create();
        $settings=$this->service()->assignmentDefaults($this->org->fresh());
        $settings['members'][$foreign->id]=['available'=>true,'priority'=>1];
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service()->saveAssignmentConfiguration($this->org,$settings,$this->admin);
    }
}

