<?php
namespace Tests\Feature\Team;

use App\Livewire\Admin\OrganizationMemberDetails;
use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash};
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationMemberDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_modal_displays_current_status_and_assigns_only_a_client_owned_team(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $org = Organization::create(['name' => 'Client team']);
        $admin = User::factory()->create(['status' => 'active']); $admin->assignRole('Super Admin');
        $member = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']); $member->assignRole('Executive');
        $team = \App\Models\SalesTeam::create(['name' => 'Client sales', 'ownable_type' => Organization::class, 'ownable_id' => $org->id]);
        $component = Livewire::actingAs($admin)->test(OrganizationMemberDetails::class, ['organizationId' => $org->id])
            ->call('open', $member->id)->set('editing', true)->assertSee('Team Leader')->assertSee('Active')
            ->set('leadershipTeamId', $team->id)->call('assignTeamLeader')->assertHasNoErrors();
        $this->assertSame($member->id, $team->fresh()->leader_id);
        $this->assertTrue($member->fresh()->hasRole('Team Leader'));
        $this->assertDatabaseHas('sales_team_members', ['sales_team_id' => $team->id, 'user_id' => $member->id, 'role' => 'Team Leader', 'is_active' => true]);
        $other = Organization::create(['name' => 'Other client']);
        $foreign = \App\Models\SalesTeam::create(['name' => 'Private team', 'ownable_type' => Organization::class, 'ownable_id' => $other->id]);
        $component->set('leadershipTeamId', $foreign->id)->call('assignTeamLeader')->assertNotFound();
        $this->assertNull($foreign->fresh()->leader_id);
    }

    public function test_executive_selects_brijesh_by_name_without_replacing_the_team_leader(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $org = Organization::create(['name' => 'Deltek', 'status' => 'active']);
        $admin = User::factory()->create(['status'=>'active']); $admin->assignRole('Super Admin');
        $leader = User::factory()->create(['name'=>'Brijesh', 'organization_id'=>$org->id, 'status'=>'active']); $leader->assignRole('Team Leader');
        $member = User::factory()->create(['organization_id'=>$org->id, 'status'=>'active', 'phone'=>'919123456789']); $member->assignRole('Executive');
        $team = \App\Models\SalesTeam::create(['name'=>'Deltek Sales Team', 'ownable_type'=>Organization::class, 'ownable_id'=>$org->id, 'leader_id'=>$leader->id]);
        \App\Models\SalesTeamMember::create(['sales_team_id'=>$team->id, 'user_id'=>$leader->id, 'role'=>'Team Leader', 'is_active'=>false]);
        Livewire::actingAs($admin)->test(OrganizationMemberDetails::class, ['organizationId'=>$org->id])
            ->call('open', $member->id)->assertSee('Brijesh')->assertDontSee('Deltek Sales Team')
            ->set('leadershipTeamId', $team->id)->call('assignMemberToTeam')->assertHasNoErrors()
            ->call('open', $member->id)->assertSet('leadershipTeamId', $team->id);
        $this->assertSame($leader->id, $team->fresh()->leader_id);
        $this->assertTrue($member->fresh()->hasRole('Executive'));
        $this->assertFalse($member->fresh()->hasRole('Team Leader'));
        $this->assertDatabaseHas('sales_team_members', ['user_id'=>$member->id, 'sales_team_id'=>$team->id, 'is_active'=>true]);
        $this->assertDatabaseHas('sales_team_members', ['user_id'=>$leader->id, 'sales_team_id'=>$team->id, 'is_active'=>true]);
        $this->actingAs($leader->fresh());
        $this->assertTrue(\App\Support\TeamAccess::executives(auth()->user())->whereKey($member->id)->exists());
        Livewire::test(\App\Livewire\Team\BuilderTeamManager::class)->assertSee($member->name)->assertSee('Deltek Sales Team');
        Livewire::test(\App\Livewire\Settings\LeadAssignmentConfiguration::class, ['organizationId'=>$org->id])->assertSee($member->name);
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\OrganizationDetail::class, ['organization'=>$org])
            ->set('activeTab', 'team')->assertSee('Phone')->assertSee('Team Leader')->assertSee('Deltek Sales Team')->assertSee('Brijesh')->assertSee($member->phone);
    }

    public function test_admin_can_edit_and_change_password_only_for_this_organization(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $org = Organization::create(['name' => 'Selected client']);
        $admin = User::factory()->create(['status' => 'active']); $admin->assignRole('Super Admin');
        $member = User::factory()->create(['organization_id' => $org->id]); $member->assignRole('Executive');
        $other = User::factory()->create();
        $component = Livewire::actingAs($admin)->test(OrganizationMemberDetails::class, ['organizationId' => $org->id])
            ->dispatch('open-org-member', memberId: $member->id)->assertSee($member->email)->assertSee('Executive')
            ->set('editing', true)->set('name', 'Updated executive')->call('saveDetails')->assertHasNoErrors();
        $this->assertSame('Updated executive', $member->fresh()->name);
        $original = $member->password;
        $component->set('password', 'StrongNewPass!123')->set('password_confirmation', 'different')
            ->call('changePassword')->assertHasErrors('password');
        $this->assertSame($original, $member->fresh()->password);
        $component->set('password_confirmation', 'StrongNewPass!123')->call('changePassword')->assertHasNoErrors()
            ->assertSet('password', '')->assertSet('password_confirmation', '');
        $this->assertTrue(Hash::check('StrongNewPass!123', $member->fresh()->password));
        $this->assertStringNotContainsString('StrongNewPass', json_encode(DB::table('audit_logs')->get()));
        $component->call('open', $other->id)->assertNotFound();
        Livewire::actingAs($member)->test(OrganizationMemberDetails::class, ['organizationId' => $org->id])->assertForbidden();
    }

    public function test_team_leader_without_preexisting_sales_team_automatically_appears_in_dropdown_and_can_be_assigned(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $org = Organization::create(['name' => 'H&S Investors', 'status' => 'active']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        // Create Team Leader WITHOUT creating a SalesTeam
        $leader = User::factory()->create([
            'name' => 'H&S Team Leader',
            'organization_id' => $org->id,
            'status' => 'active',
        ]);
        $leader->assignRole('Team Leader');

        // Create Executive
        $executive = User::factory()->create([
            'name' => 'H&S Executive 01',
            'organization_id' => $org->id,
            'status' => 'active',
        ]);
        $executive->assignRole('Executive');

        // Ensure initially no sales teams exist
        $this->assertSame(0, \App\Models\SalesTeam::where('ownable_id', $org->id)->count());

        // Open modal for Executive - Leader must appear in the options!
        $component = Livewire::actingAs($admin)->test(OrganizationMemberDetails::class, ['organizationId' => $org->id])
            ->call('open', $executive->id)
            ->assertSee('H&S Team Leader')
            ->assertDontSee('Add an active Team Leader to this organization first.');

        $team = \App\Models\SalesTeam::where('ownable_id', $org->id)->where('leader_id', $leader->id)->firstOrFail();
        $this->assertSame($leader->id, $team->leader_id);

        // Assign executive to the team leader
        $component->set('leadershipTeamId', $team->id)
            ->call('assignMemberToTeam')
            ->assertHasNoErrors();

        // Check memberships
        $this->assertDatabaseHas('sales_team_members', [
            'sales_team_id' => $team->id,
            'user_id' => $executive->id,
            'role' => 'Executive',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('sales_team_members', [
            'sales_team_id' => $team->id,
            'user_id' => $leader->id,
            'role' => 'Team Leader',
            'is_active' => true,
        ]);

        // Verify leader access
        $this->assertTrue(\App\Support\TeamAccess::executives($leader->fresh())->whereKey($executive->id)->exists());

        // Verify organization detail team tab shows the leader and team
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\OrganizationDetail::class, ['organization' => $org])
            ->set('activeTab', 'team')
            ->assertSee('H&S Team Leader')
            ->assertSee('H&S Executive 01');
    }
}
