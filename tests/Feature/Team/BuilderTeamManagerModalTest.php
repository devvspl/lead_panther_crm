<?php

namespace Tests\Feature\Team;

use App\Livewire\Team\BuilderTeamManager;
use App\Models\Client;
use App\Models\Organization;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BuilderTeamManagerModalTest extends TestCase
{
    use RefreshDatabase;

    protected User $builder;
    protected Organization $org;
    protected SalesTeam $team;
    protected User $orgMember;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin']);
        Role::firstOrCreate(['name' => 'Builder/Developer']);
        Role::firstOrCreate(['name' => 'Team Leader']);
        Role::firstOrCreate(['name' => 'Executive']);
        Role::firstOrCreate(['name' => 'Channel Partner']);

        $this->org = Organization::create([
            'name' => 'Apex Realty Developers',
            'status' => 'active',
        ]);

        $client = Client::create([
            'organization_id' => $this->org->id,
            'name' => 'Apex Builder',
            'email' => 'builder@apex.local',
            'status' => 'active',
        ]);
        $this->builder = User::find($client->id);
        $this->builder->assignRole('Builder/Developer');

        $this->team = SalesTeam::create([
            'ownable_type' => Organization::class,
            'ownable_id' => $this->org->id,
            'name' => 'Apex Core Sales Team',
        ]);

        $this->orgMember = User::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Existing Org Colleague',
            'email' => 'colleague@apex.local',
            'status' => 'active',
        ]);
        $this->orgMember->assignRole('Executive');
    }

    public function test_main_page_does_not_contain_inline_role_assignment_section(): void
    {
        $this->actingAs($this->builder);

        Livewire::test(BuilderTeamManager::class)
            ->set('teamId', $this->team->id)
            ->assertDontSee('Update Role and Add to Team')
            ->assertSee('Add Team Member')
            ->assertSee('Apex Core Sales Team');
    }

    public function test_modal_allows_selecting_existing_member_and_assigning_role(): void
    {
        $this->actingAs($this->builder);

        Livewire::test(BuilderTeamManager::class)
            ->set('teamId', $this->team->id)
            ->set('existingMemberId', $this->orgMember->id)
            ->set('existingMemberRole', 'Team Leader')
            ->call('assignExistingMember')
            ->assertHasNoErrors()
            ->assertDispatched('close-modal', 'add-team-member');

        $this->assertTrue($this->orgMember->fresh()->hasRole('Team Leader'));
        $this->assertDatabaseHas('sales_team_members', [
            'sales_team_id' => $this->team->id,
            'user_id' => $this->orgMember->id,
            'role' => 'Team Leader',
            'is_active' => true,
        ]);
        $this->assertEquals($this->orgMember->id, $this->team->fresh()->leader_id);
    }

    public function test_modal_allows_adding_new_team_member_with_role(): void
    {
        $this->actingAs($this->builder);

        Livewire::test(BuilderTeamManager::class)
            ->set('teamId', $this->team->id)
            ->set('memberName', 'New Sales Rep')
            ->set('memberEmail', 'rep@apex.local')
            ->set('roleName', 'Executive')
            ->call('addMember')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'New Sales Rep',
            'email' => 'rep@apex.local',
            'organization_id' => $this->org->id,
        ]);

        $newUser = User::where('email', 'rep@apex.local')->first();
        $this->assertTrue($newUser->hasRole('Executive'));
        $this->assertDatabaseHas('sales_team_members', [
            'sales_team_id' => $this->team->id,
            'user_id' => $newUser->id,
            'role' => 'Executive',
            'is_active' => true,
        ]);
    }
}
