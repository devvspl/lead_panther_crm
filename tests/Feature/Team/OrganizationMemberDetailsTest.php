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
            ->call('open', $member->id)->set('editing', true)->assertSee('Set Team Leader')->assertSee('Active')
            ->set('leadershipTeamId', $team->id)->call('assignTeamLeader')->assertHasNoErrors();
        $this->assertSame($member->id, $team->fresh()->leader_id);
        $this->assertTrue($member->fresh()->hasRole('Team Leader'));
        $this->assertDatabaseHas('sales_team_members', ['sales_team_id' => $team->id, 'user_id' => $member->id, 'role' => 'Team Leader', 'is_active' => true]);
        $other = Organization::create(['name' => 'Other client']);
        $foreign = \App\Models\SalesTeam::create(['name' => 'Private team', 'ownable_type' => Organization::class, 'ownable_id' => $other->id]);
        $component->set('leadershipTeamId', $foreign->id)->call('assignTeamLeader')->assertNotFound();
        $this->assertNull($foreign->fresh()->leader_id);
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
}
