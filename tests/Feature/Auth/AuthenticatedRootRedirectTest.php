<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedRootRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_visiting_root_sees_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertViewIs('pages.landing');
    }

    public function test_authenticated_super_admin_visiting_root_redirects_to_admin_dashboard(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)->get('/');

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_authenticated_sales_executive_visiting_root_redirects_to_sales_dashboard(): void
    {
        $executive = User::factory()->create(['status' => 'active']);
        $executive->assignRole('sales-executive');

        $response = $this->actingAs($executive)->get('/');

        $response->assertRedirect(route('sales.dashboard'));
    }

    public function test_authenticated_builder_visiting_root_redirects_to_builder_dashboard(): void
    {
        $builder = User::factory()->create(['status' => 'active']);
        $builder->assignRole('Builder');

        $response = $this->actingAs($builder)->get('/');

        $response->assertRedirect(route('builder.dashboard'));
    }

    public function test_authenticated_team_leader_visiting_root_redirects_to_team_leader_dashboard(): void
    {
        $leader = User::factory()->create(['status' => 'active']);
        $leader->assignRole('Team Leader');

        $response = $this->actingAs($leader)->get('/');

        $response->assertRedirect(route('team-leader.dashboard'));
    }

    public function test_authenticated_user_with_inactive_organization_redirects_to_pending_approval(): void
    {
        $org = Organization::create([
            'name' => 'Pending Org',
            'status' => 'pending',
        ]);

        $user = User::factory()->create([
            'organization_id' => $org->id,
            'status' => 'active',
        ]);
        $user->assignRole('Builder');

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('auth.pending-approval'));
    }

    public function test_authenticated_user_visiting_login_redirects_to_dashboard(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)->get('/login');

        $response->assertRedirect(route('admin.dashboard'));
    }
}
