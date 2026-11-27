<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MultiIdentifierLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_user_can_login_using_email(): void
    {
        $user = User::factory()->create([
            'email' => 'agent@prestige.test',
            'password' => bcrypt('Secret123!'),
            'status' => 'active',
        ]);

        Volt::test('pages.auth.login')
            ->set('form.email', 'agent@prestige.test')
            ->set('form.password', 'Secret123!')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_using_user_code(): void
    {
        $user = User::factory()->create([
            'email' => 'exec@prestige.test',
            'user_code' => 'LP00099',
            'password' => bcrypt('Secret123!'),
            'status' => 'active',
        ]);

        // Login with exact case
        Volt::test('pages.auth.login')
            ->set('form.email', 'LP00099')
            ->set('form.password', 'Secret123!')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->assertGuest();

        // Login with lowercase user code
        Volt::test('pages.auth.login')
            ->set('form.email', 'lp00099')
            ->set('form.password', 'Secret123!')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_using_mobile_number(): void
    {
        $user = User::factory()->create([
            'email' => 'mobileuser@prestige.test',
            'phone' => '+919876543210',
            'password' => bcrypt('Secret123!'),
            'status' => 'active',
        ]);

        // 1. Login with 10 digits without country code
        Volt::test('pages.auth.login')
            ->set('form.email', '9876543210')
            ->set('form.password', 'Secret123!')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);

        auth()->logout();

        // 2. Login with formatted phone (+91 98765-43210)
        Volt::test('pages.auth.login')
            ->set('form.email', '+91 98765-43210')
            ->set('form.password', 'Secret123!')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_suspended_user_cannot_login_with_any_identifier(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@prestige.test',
            'user_code' => 'LP-000888',
            'phone' => '+919811122299',
            'password' => bcrypt('Secret123!'),
            'status' => 'suspended',
        ]);

        // By email
        Volt::test('pages.auth.login')
            ->set('form.email', 'suspended@prestige.test')
            ->set('form.password', 'Secret123!')
            ->call('login')
            ->assertHasErrors('form.email');
        $this->assertGuest();

        // By User Code
        Volt::test('pages.auth.login')
            ->set('form.email', 'LP-000888')
            ->set('form.password', 'Secret123!')
            ->call('login')
            ->assertHasErrors('form.email');
        $this->assertGuest();

        // By phone
        Volt::test('pages.auth.login')
            ->set('form.email', '9811122299')
            ->set('form.password', 'Secret123!')
            ->call('login')
            ->assertHasErrors('form.email');
        $this->assertGuest();
    }

    public function test_ambiguous_phone_number_rejects_with_specific_error(): void
    {
        // Two users sharing same phone number
        User::factory()->create([
            'email' => 'user1@test.com',
            'phone' => '9888877777',
            'password' => bcrypt('Secret123!'),
            'status' => 'active',
        ]);

        User::factory()->create([
            'email' => 'user2@test.com',
            'phone' => '9888877777',
            'password' => bcrypt('Secret123!'),
            'status' => 'active',
        ]);

        Volt::test('pages.auth.login')
            ->set('form.email', '9888877777')
            ->set('form.password', 'Secret123!')
            ->call('login')
            ->assertHasErrors(['form.email']);

        $this->assertGuest();
    }
}
