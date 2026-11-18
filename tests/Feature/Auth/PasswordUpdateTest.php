<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-password-form')
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_generation_fills_both_new_fields_without_changing_account_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $original = $user->password;
        $component = Volt::test('profile.update-password-form')->assertSee('Show')->assertSee('Generate Strong Password')
            ->call('generatePassword')->assertDispatched('profile-password-generated')->assertSet('current_password', '');
        $password = $component->get('password');
        $this->assertSame(14, strlen($password));
        $this->assertMatchesRegularExpression('/[a-z]/', $password);
        $this->assertMatchesRegularExpression('/[A-Z]/', $password);
        $this->assertMatchesRegularExpression('/[0-9]/', $password);
        $this->assertMatchesRegularExpression('/[^a-zA-Z0-9]/', $password);
        $component->assertSet('password_confirmation', $password);
        $this->assertSame($original, $user->fresh()->password);
        $component->set('current_password', 'password')->call('updatePassword')->assertHasNoErrors()
            ->assertSet('password', '')->assertSet('password_confirmation', '');
        $this->assertTrue(Hash::check($password, $user->fresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-password-form')
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $component
            ->assertHasErrors(['current_password'])
            ->assertNoRedirect();
    }
}
