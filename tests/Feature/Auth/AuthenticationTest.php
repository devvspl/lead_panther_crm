<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_account_cannot_login_even_with_correct_password(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);
        Volt::test('pages.auth.login')->set('form.email', $user->email)
            ->set('form.password', 'password')->call('login')->assertHasErrors('form.email');
        $this->assertGuest();
    }

    public function test_deactivated_account_loses_existing_session_access(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->update(['status' => 'inactive']);
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_account_is_blocked_from_authenticated_api(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);
        $this->actingAs($user)->getJson('/api/leads/999999')->assertForbidden();
        $this->assertGuest();
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    public function test_login_page_refreshes_do_not_trigger_password_attempt_lockout(): void
    {
        for ($i = 0; $i < 8; $i++) $this->get('/login')->assertOk();
    }

    public function test_failed_password_attempts_remain_limited_and_expire(): void
    {
        $user = User::factory()->create();
        $component = Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'wrong-password');
        for ($i = 0; $i < 5; $i++) $component->call('login')->assertHasErrors('form.email');
        $component->set('form.password', 'password')->call('login')->assertHasErrors('form.email');
        $this->assertGuest();
        // A locked-out password must not prevent opening the login screen.
        $this->get('/login')->assertOk();
        $this->travel(61)->seconds();
        $component->call('login')->assertHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('Lead Panther');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/');

        $this->assertGuest();
    }
}
