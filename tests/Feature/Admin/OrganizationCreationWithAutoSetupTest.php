<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\OrganizationManager;
use App\Models\Organization;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserCodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationCreationWithAutoSetupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);
    }

    public function test_create_organization_without_auto_setup_works_as_before(): void
    {
        Livewire::test(OrganizationManager::class)
            ->set('name', 'Apex Developers')
            ->set('status', 'active')
            ->set('billingEmail', 'billing@apexdev.test')
            ->set('autoSetup', false)
            ->call('createOrganization')
            ->assertHasNoErrors();

        $org = Organization::where('name', 'Apex Developers')->first();
        $this->assertNotNull($org);
        $this->assertSame('active', $org->status);

        // Client user created for billing
        $this->assertDatabaseHas('users', [
            'organization_id' => $org->id,
            'name' => 'Apex Developers',
            'email' => 'billing@apexdev.test',
        ]);

        // Only the billing client user created by existing logic, no teams created
        $this->assertSame(1, User::where('organization_id', $org->id)->count());
        $this->assertSame(0, SalesTeam::where('ownable_id', $org->id)->count());
    }

    public function test_create_organization_with_automatic_account_and_team_setup(): void
    {
        Livewire::test(OrganizationManager::class)
            ->set('name', 'Skyline Infra')
            ->set('status', 'active')
            ->set('billingEmail', 'billing@skyline.test')
            ->set('autoSetup', true)
            ->set('mainAccountName', 'Skyline Owner')
            ->set('mainAccountEmail', 'owner@skyline.test')
            ->set('mainAccountPhone', '+91 9811122233')
            ->set('mainAccountPassword', 'SecretPass123!')
            ->set('teamName', 'North Region Sales')
            ->set('teamLeaderName', 'Vikram TL')
            ->set('teamLeaderEmail', 'vikram@skyline.test')
            ->set('teamLeaderPhone', '+91 9811122244')
            ->set('teamLeaderPassword', 'SecretPass123!')
            ->set('salesExecutives', [
                [
                    'name' => 'Anil Exec',
                    'email' => 'anil@skyline.test',
                    'phone' => '+91 9811122255',
                    'password' => 'SecretPass123!',
                ],
                [
                    'name' => 'Pooja Exec',
                    'email' => 'pooja@skyline.test',
                    'phone' => '+91 9811122266',
                    'password' => 'SecretPass123!',
                ],
            ])
            ->call('createOrganization')
            ->assertHasNoErrors();

        // 1. Organization created
        $org = Organization::where('name', 'Skyline Infra')->first();
        $this->assertNotNull($org);

        // 2. Main Account created
        $mainUser = User::where('email', 'owner@skyline.test')->first();
        $this->assertNotNull($mainUser);
        $this->assertSame($org->id, $mainUser->organization_id);
        $this->assertTrue($mainUser->hasRole(['Builder/Developer', 'Builder']));
        $this->assertNotEmpty($mainUser->user_code);
        $this->assertMatchesRegularExpression('/^LP\d{5,}$/', $mainUser->user_code);

        // 3. Team Leader created
        $leader = User::where('email', 'vikram@skyline.test')->first();
        $this->assertNotNull($leader);
        $this->assertSame($org->id, $leader->organization_id);
        $this->assertTrue($leader->hasRole('Team Leader'));
        $this->assertNotEmpty($leader->user_code);
        $this->assertNotSame($mainUser->user_code, $leader->user_code);

        // 4. Sales Team created
        $team = SalesTeam::where('ownable_id', $org->id)->first();
        $this->assertNotNull($team);
        $this->assertSame('North Region Sales', $team->name);
        $this->assertSame($leader->id, $team->leader_id);

        // Team Leader membership verified
        $leaderMembership = SalesTeamMember::where('sales_team_id', $team->id)
            ->where('user_id', $leader->id)
            ->first();
        $this->assertNotNull($leaderMembership);
        $this->assertSame('Team Leader', $leaderMembership->role);
        $this->assertTrue((bool) $leaderMembership->is_active);

        // 5. Sales Executives created and assigned
        $exec1 = User::where('email', 'anil@skyline.test')->first();
        $this->assertNotNull($exec1);
        $this->assertSame($org->id, $exec1->organization_id);
        $this->assertTrue($exec1->hasRole(['Executive', 'Sales Executive']));
        $this->assertNotEmpty($exec1->user_code);

        $exec2 = User::where('email', 'pooja@skyline.test')->first();
        $this->assertNotNull($exec2);
        $this->assertSame($org->id, $exec2->organization_id);
        $this->assertTrue($exec2->hasRole(['Executive', 'Sales Executive']));
        $this->assertNotEmpty($exec2->user_code);

        $this->assertDatabaseHas('sales_team_members', [
            'sales_team_id' => $team->id,
            'user_id' => $exec1->id,
            'role' => 'Executive',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('sales_team_members', [
            'sales_team_id' => $team->id,
            'user_id' => $exec2->id,
            'role' => 'Executive',
            'is_active' => 1,
        ]);
    }

    public function test_transaction_rolls_back_everything_if_any_step_fails(): void
    {
        // Existing user with conflicting email
        User::factory()->create(['email' => 'conflict@existing.test']);

        Livewire::test(OrganizationManager::class)
            ->set('name', 'Rollback Builders')
            ->set('status', 'active')
            ->set('autoSetup', true)
            ->set('mainAccountEmail', 'owner@rollback.test')
            ->set('teamName', 'Rollback Team')
            ->set('teamLeaderEmail', 'conflict@existing.test') // Will fail email unique validation
            ->set('teamLeaderName', 'Fail TL')
            ->call('createOrganization')
            ->assertHasErrors(['teamLeaderEmail']);

        // Assert organization was NOT created
        $this->assertDatabaseMissing('organizations', ['name' => 'Rollback Builders']);
        $this->assertDatabaseMissing('users', ['email' => 'owner@rollback.test']);
        $this->assertDatabaseMissing('sales_teams', ['name' => 'Rollback Team']);
    }

    public function test_user_code_is_automatically_generated_and_unique(): void
    {
        $u1 = User::create([
            'name' => 'User One',
            'email' => 'u1@test.com',
            'password' => bcrypt('password'),
        ]);

        $u2 = User::create([
            'name' => 'User Two',
            'email' => 'u2@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->assertNotNull($u1->user_code);
        $this->assertNotNull($u2->user_code);
        $this->assertMatchesRegularExpression('/^LP\d{5,}$/', $u1->user_code);
        $this->assertMatchesRegularExpression('/^LP\d{5,}$/', $u2->user_code);
        $this->assertNotSame($u1->user_code, $u2->user_code);
    }

    public function test_user_code_seeder_assigns_codes_and_is_idempotent(): void
    {
        // Create user with null user_code by temporarily bypassing model creating hook
        $id = \DB::table('users')->insertGetId([
            'name' => 'Legacy User',
            'email' => 'legacy@test.com',
            'password' => bcrypt('password'),
            'user_code' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('users', ['id' => $id, 'user_code' => null]);

        // Run seeder once
        $seeder = new UserCodeSeeder();
        $seeder->run();

        $legacyUser = User::find($id);
        $this->assertNotNull($legacyUser->user_code);
        $assignedCode = $legacyUser->user_code;
        $this->assertMatchesRegularExpression('/^LP\d{5,}$/', $assignedCode);

        // Run seeder second time
        $seeder->run();

        // Code must be preserved without change
        $legacyUser->refresh();
        $this->assertSame($assignedCode, $legacyUser->user_code);
    }
}
