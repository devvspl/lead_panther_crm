<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_session_lifetime_configuration_is_set_to_7_days(): void
    {
        // 7 days = 7 * 24 * 60 = 10,080 minutes
        $this->assertSame(10080, config('session.lifetime'));
        $this->assertFalse(config('session.expire_on_close'));
    }

    public function test_authenticated_user_session_remains_active_across_multiple_days(): void
    {
        $org = Organization::create(['name' => 'Session Test Org', 'status' => 'active']);
        $user = User::factory()->create([
            'status' => 'active',
            'organization_id' => $org->id,
        ]);
        $user->assignRole('Super Admin');

        $this->actingAs($user);

        // Verify initial access
        $response = $this->get(route('admin.dashboard'));
        $response->assertOk();
        $this->assertAuthenticatedAs($user);

        // Travel 6 days into the future (within the 7-day / 10,080 minute idle window)
        $this->travel(6)->days();

        // User should still be authenticated
        $responseAfter6Days = $this->get(route('admin.dashboard'));
        $responseAfter6Days->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_manually_logout_before_session_expiry(): void
    {
        $org = Organization::create(['name' => 'Session Test Org 2', 'status' => 'active']);
        $user = User::factory()->create([
            'status' => 'active',
            'organization_id' => $org->id,
        ]);

        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        $logoutResponse = $this->post('/logout');
        $logoutResponse->assertRedirect('/');
        $this->assertGuest();
    }
}
