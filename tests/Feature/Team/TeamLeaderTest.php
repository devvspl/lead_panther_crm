<?php
namespace Tests\Feature\Team;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Auth};
use App\Models\{User, Organization, SalesTeam, SalesTeamMember, Lead, LeadSource, Followup};
use App\Support\{TeamAccess, NavigationConfig};
use App\Livewire\Team\BuilderTeamManager;
use App\Livewire\Leads\LeadKanban;
use Livewire\{Livewire, Volt\Volt};
use Database\Seeders\{DeltekSeeder, TeamLeaderRoleSeeder};

class TeamLeaderTest extends TestCase {
    use RefreshDatabase;
    private User $builder; private User $leader; private User $exec; private User $outside;
    private SalesTeam $team;
    protected function setUp(): void {
        parent::setUp(); $this->seed(DeltekSeeder::class);
        $this->builder=User::where('email','deltek@deltek.leadpanther.test')->firstOrFail();
        $this->leader=User::where('email','brijesh@deltek.leadpanther.test')->firstOrFail();
        $this->exec=User::where('email','se1@deltek.leadpanther.test')->firstOrFail();
        $this->team=SalesTeam::where('leader_id',$this->leader->id)->firstOrFail();
        $this->outside=User::factory()->create(['organization_id'=>$this->builder->organization_id,'status'=>'active','name'=>'Other team executive']); $this->outside->assignRole('Executive');
        $other=SalesTeam::create(['ownable_type'=>Organization::class,'ownable_id'=>$this->builder->organization_id,'name'=>'Other private team']);
        SalesTeamMember::create(['sales_team_id'=>$other->id,'user_id'=>$this->outside->id,'role'=>'Executive','is_active'=>true]);
    }
    private function lead(User $assignee, string $code): Lead {
        return Lead::withoutGlobalScopes()->create(['lead_code'=>$code,'lead_source_id'=>LeadSource::firstOrCreate(['name'=>'Team test source'])->id,'client_id'=>$this->builder->id,'assigned_to'=>$assignee->id,'name'=>$code,'mobile'=>'9000000000','status'=>'assigned','current_stage'=>'assigned','delivery_pending'=>false]);
    }
    public function test_leader_can_use_the_actual_livewire_update_route(): void {
        $this->actingAs($this->leader);
        $this->get('/leads')->assertOk();
        $component = Livewire::test(LeadKanban::class);
        $snapshot = $component->snapshot;
        $response = $this->postJson(route('default.livewire.update'), [
            '_token' => csrf_token(),
            'components' => [[
                'snapshot' => json_encode($snapshot),
                'updates' => ['search' => 'team filter'],
                'calls' => [],
            ]],
        ]);
        $response->assertOk()->assertJsonStructure(['components']);
        $this->get('/admin/users')->assertForbidden();
    }
    public function test_demo_seeding_is_idempotent_and_preserves_passwords_and_existing_roles(): void {
        $this->leader->update(['password'=>Hash::make('changed-password')]); $hash=$this->leader->fresh()->password;
        $admin=User::factory()->create(); $admin->assignRole('Super Admin');
        $count=User::count(); $members=SalesTeamMember::count();
        $this->seed(DeltekSeeder::class);
        $this->assertSame($count,User::count()); $this->assertSame($members,SalesTeamMember::count());
        $this->assertSame($hash,$this->leader->fresh()->password); $this->assertTrue($admin->fresh()->hasRole('Super Admin'));
        $this->assertSame(10,TeamAccess::executives($this->leader)->count());
    }
    public function test_all_twelve_accounts_login_and_open_role_dashboards(): void {
        foreach(User::where('email','like','%@deltek.leadpanther.test')->get() as $user) {
            Auth::logout();
            $route=match(NavigationConfig::getRoleKey($user)) {'builder'=>'builder.dashboard','team-leader'=>'team-leader.dashboard',default=>'sales.dashboard'};
            Volt::test('pages.auth.login')->set('form.email',$user->email)->set('form.password','password')->call('login')->assertHasNoErrors()->assertRedirect(route($route,absolute:false));
            $this->assertAuthenticatedAs($user); $this->get(route($route))->assertOk();
        }
    }
    public function test_leader_leads_followups_reports_and_members_are_team_scoped(): void {
        $ours=$this->lead($this->exec,'LP-TEAM-OWN'); $other=$this->lead($this->outside,'LP-TEAM-OTHER');
        Followup::create(['lead_id'=>$ours->id,'created_by'=>$this->exec->id,'due_at'=>now(),'status'=>'pending']);
        Followup::create(['lead_id'=>$other->id,'created_by'=>$this->outside->id,'due_at'=>now(),'status'=>'pending']);
        $this->actingAs($this->leader);
        $this->assertSame([$ours->id],Lead::panelQuery()->pluck('id')->all()); $this->assertSame([$ours->id],Lead::query()->pluck('id')->all());
        Livewire::test(LeadKanban::class)->assertSee('LP-TEAM-OWN')->assertDontSee('LP-TEAM-OTHER');
        Livewire::test(BuilderTeamManager::class)->assertSee('SE1')->assertDontSee('Other team executive');
        Livewire::test(\App\Livewire\Activity\FollowupList::class)->assertSee('LP-TEAM-OWN')->assertDontSee('LP-TEAM-OTHER');
        Livewire::test(\App\Livewire\Reports\SalesExecutivePerformance::class)->assertSee('SE1')->assertDontSee('Other team executive');
        $this->get('/team-leader/dashboard')->assertSee('Team Leader Dashboard')->assertDontSee('LP-TEAM-OTHER');
    }
    public function test_leader_reassigns_only_within_own_team(): void {
        $lead=$this->lead($this->exec,'LP-TEAM-ASSIGN');
        $second=User::where('email','se2@deltek.leadpanther.test')->firstOrFail();
        Livewire::actingAs($this->leader)->test(LeadKanban::class)->set('selectedLeadIds',[$lead->id])->set('assignUserId',$second->id)->call('executeAssignment')->assertHasNoErrors();
        $this->assertEquals($second->id,$lead->fresh()->assigned_to);
        Livewire::test(LeadKanban::class)->set('selectedLeadIds',[$lead->id])->set('assignUserId',$this->outside->id)->call('executeAssignment')->assertForbidden();
    }

    public function test_leader_member_actions_are_scoped_and_preserve_user_accounts(): void {
        $foreignTeamId=SalesTeamMember::where('user_id',$this->outside->id)->value('sales_team_id');
        Livewire::actingAs($this->leader)->test(BuilderTeamManager::class)->call('editTeamMember',$this->exec->id)
            ->set('moveToTeamId',$foreignTeamId)->call('saveTeamMember')->assertForbidden();
        $second = SalesTeam::create(['name'=>'Second managed team','ownable_type'=>Organization::class,'ownable_id'=>$this->builder->organization_id,'leader_id'=>$this->leader->id]);
        $manager = Livewire::actingAs($this->leader)->test(BuilderTeamManager::class)->assertSee('Edit / Reassign')->assertSee('Membership')
            ->call('editTeamMember',$this->exec->id)->set('editMemberName','Updated executive')->set('moveToTeamId',$second->id)
            ->call('saveTeamMember')->assertHasNoErrors();
        $this->assertSame('Updated executive',$this->exec->fresh()->name);
        $this->assertDatabaseHas('sales_team_members',['sales_team_id'=>$second->id,'user_id'=>$this->exec->id,'is_active'=>true]);
        $manager->set('teamId',$second->id)->call('toggleMember',$this->exec->id);
        $this->assertDatabaseHas('sales_team_members',['sales_team_id'=>$second->id,'user_id'=>$this->exec->id,'is_active'=>false]);
        $manager->call('toggleMember',$this->exec->id)->call('removeTeamMember',$this->exec->id)->assertHasNoErrors();
        $this->assertDatabaseHas('users',['id'=>$this->exec->id]);
        $this->assertDatabaseMissing('sales_team_members',['sales_team_id'=>$second->id,'user_id'=>$this->exec->id]);
        $manager->call('editTeamMember',$this->outside->id)->assertForbidden();
    }

    public function test_deltek_repair_restores_confirmed_inactive_leader_link_without_reassigning_leads(): void {
        $lead = $this->lead($this->exec, 'LP-RESTORED-DATA');
        SalesTeamMember::where('sales_team_id', $this->team->id)->where('user_id', $this->leader->id)->update(['is_active'=>false]);
        $this->actingAs($this->leader);
        $this->assertSame(0, TeamAccess::teams($this->leader)->count());
        auth()->logout();
        $this->seed(\Database\Seeders\DeltekTeamAccessRepairSeeder::class);
        $this->seed(\Database\Seeders\DeltekTeamAccessRepairSeeder::class);
        $this->actingAs($this->leader->fresh());
        $this->assertSame(10, TeamAccess::executives(auth()->user())->count());
        $this->assertTrue(Lead::panelQuery()->whereKey($lead->id)->exists());
        $this->assertSame($this->exec->id, $lead->fresh()->assigned_to);
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'organization.leader_membership_confirmed')->count());
    }

    public function test_legacy_role_permission_repair_restores_team_members_and_leads(): void {
        $own = $this->lead($this->exec, 'LP-ROLE-REPAIR');
        $outside = $this->lead($this->outside, 'LP-ROLE-OTHER');
        $role = \Spatie\Permission\Models\Role::findByName('Team Leader', 'web');
        $role->revokePermissionTo('view-team-leads');
        $this->actingAs($this->leader->fresh());
        $this->assertSame(0, TeamAccess::executives(auth()->user())->count());
        $migration = require database_path('migrations/2026_11_18_000005_repair_team_leader_permissions.php');
        $migration->up(); $migration->up();
        $this->actingAs($this->leader->fresh());
        $this->assertSame(10, TeamAccess::executives(auth()->user())->count());
        $this->assertTrue(Lead::panelQuery()->whereKey($own->id)->exists());
        $this->assertFalse(Lead::panelQuery()->whereKey($outside->id)->exists());
        Livewire::test(LeadKanban::class)->assertSee('SE1')->assertSee('LP-ROLE-REPAIR')->assertDontSee('Other team executive');
    }

    public function test_imported_team_without_leader_pivot_keeps_team_leads_visible(): void {
        $own = $this->lead($this->exec, 'LP-IMPORTED-TEAM');
        $other = $this->lead($this->outside, 'LP-OTHER-TEAM');
        SalesTeamMember::where('sales_team_id', $this->team->id)->where('user_id', $this->leader->id)->delete();
        $this->actingAs($this->leader);
        $this->assertTrue(Lead::panelQuery()->whereKey($own->id)->exists());
        $this->assertFalse(Lead::panelQuery()->whereKey($other->id)->exists());
        $this->get('/team-leader/dashboard')->assertOk()->assertSee('LP-IMPORTED-TEAM')->assertDontSee('LP-OTHER-TEAM');
        SalesTeamMember::create(['sales_team_id'=>$this->team->id, 'user_id'=>$this->leader->id, 'role'=>'Team Leader', 'is_active'=>false]);
        $this->assertFalse(Lead::panelQuery()->whereKey($own->id)->exists());
    }

    public function test_inactive_executive_existing_leads_remain_visible_for_leader_reassignment(): void {
        $own = $this->lead($this->exec, 'LP-INACTIVE-EXEC');
        $personal = $this->lead($this->leader, 'LP-LEADER-ASSIGNED');
        $this->exec->update(['status'=>'inactive']);
        $this->actingAs($this->leader);
        $this->assertFalse(TeamAccess::executives($this->leader)->whereKey($this->exec->id)->exists());
        $this->assertTrue(Lead::panelQuery()->whereKey($own->id)->exists());
        $this->assertTrue(Lead::panelQuery()->whereKey($personal->id)->exists());
        Livewire::test(LeadKanban::class)->assertSee('LP-INACTIVE-EXEC');
    }
    public function test_owner_can_assign_leadership_and_leader_cannot_promote_users(): void {
        $replacement=User::factory()->create(['organization_id'=>$this->builder->organization_id,'status'=>'active']); $replacement->assignRole('Executive');
        Livewire::actingAs($this->builder)->test(BuilderTeamManager::class)->set('teamId',$this->team->id)->set('existingMemberId',$replacement->id)->set('existingMemberRole','Team Leader')->call('assignExistingMember')->assertHasNoErrors();
        $this->assertEquals($replacement->id,$this->team->fresh()->leader_id); $this->assertTrue($replacement->fresh()->hasRole('Team Leader'));
        Livewire::actingAs($replacement)->test(BuilderTeamManager::class)->set('existingMemberId',$this->exec->id)->set('existingMemberRole','Team Leader')->call('assignExistingMember')->assertForbidden();
    }
    public function test_revoked_membership_and_cross_organization_leads_are_hidden(): void {
        $own=$this->lead($this->exec,'LP-REVOKED');
        $org=Organization::create(['name'=>'Separate organization']); $owner=User::factory()->create(['organization_id'=>$org->id]);
        $cross=$this->lead($this->exec,'LP-CROSS-ORG'); $cross->update(['client_id'=>$owner->id]);
        $this->actingAs($this->leader); $this->assertFalse(Lead::panelQuery()->whereKey($cross->id)->exists());
        SalesTeamMember::where('sales_team_id',$this->team->id)->where('user_id',$this->exec->id)->update(['is_active'=>false]);
        $this->assertFalse(Lead::panelQuery()->whereKey($own->id)->exists());
        $this->get('/admin/users')->assertForbidden(); $this->get('/builder/team')->assertForbidden(); $this->get('/settings/users')->assertForbidden();
    }
    public function test_super_admin_can_create_and_edit_a_team_leader_in_existing_user_management(): void {
        $admin=User::factory()->create(['status'=>'active']); $admin->assignRole('Super Admin');
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\UserManager::class)->call('openCreateUserModal')
            ->set('newUserName','Additional Team Leader')->set('newUserEmail','leader.additional@example.test')
            ->set('newUserOrganizationId',$this->builder->organization_id)->set('newUserRole','Team Leader')->set('newUserPassword','password')->call('createUser')->assertHasNoErrors();
        $user=User::where('email','leader.additional@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('Team Leader')); $this->assertTrue(Hash::check('password',$user->password));
        $hash=$user->password;
        Livewire::test(\App\Livewire\Admin\UserManager::class)->call('openUserDetailsModal',$user->id)->set('editRole','Executive')->call('updateUser')->assertHasNoErrors();
        Livewire::test(\App\Livewire\Admin\UserManager::class)->call('openUserDetailsModal',$user->id)->set('editRole','Team Leader')->call('updateUser')->assertHasNoErrors();
        $this->assertTrue($user->fresh()->hasRole('Team Leader')); $this->assertSame($hash,$user->fresh()->password);
        $this->assertContains('Team Leader',\Spatie\Permission\Models\Role::pluck('name')->all());
    }
    public function test_project_and_campaign_lookups_only_expose_the_leaders_team(): void {
        $visible=$this->lead($this->exec,'LP-PROJECT-OWN'); $hidden=$this->lead($this->outside,'LP-PROJECT-OTHER');
        foreach([$visible,$hidden] as $lead) {
            $project=\App\Models\Project::create(['client_id'=>$this->builder->id,'name'=>$lead->name.' project']);
            $campaign=\App\Models\Campaign::create(['project_id'=>$project->id,'name'=>$lead->name.' campaign','lead_source_id'=>$lead->lead_source_id]);
            $lead->update(['campaign_id'=>$campaign->id]);
        }
        $this->actingAs($this->leader);
        $this->assertSame([$visible->campaign_id],\App\Models\Campaign::pluck('id')->all());
        $this->assertSame(1,\App\Models\Project::count());
    }
}
