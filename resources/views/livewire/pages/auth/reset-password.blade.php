<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));
            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="w-full max-w-md space-y-6">
    <div class="bg-surface rounded-card border border-border p-8 shadow-sm">
        <div class="text-center space-y-2 mb-6">
            <div class="inline-flex h-12 w-12 rounded-2xl bg-ink text-white items-center justify-center font-bold text-xl shadow-sm mb-2">
                LP
            </div>
            <h1 class="text-2xl font-bold text-ink tracking-tight">Create new password</h1>
            <p class="text-xs text-muted">Set a strong password for your Lead Panther account</p>
        </div>

        <form wire:submit="resetPassword" class="space-y-5">
            <!-- Email Address -->
            <div>
                <label for="email" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-2">Email Address</label>
                <input
                    wire:model="email"
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    autocomplete="username"
                    class="w-full px-3.5 py-2.5 bg-surface text-ink text-sm rounded-lg border border-border focus:ring-2 focus:ring-ink focus:border-ink placeholder:text-muted transition"
                >
                <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-xs text-danger" />
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-2">New Password</label>
                <input
                    wire:model="password"
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="w-full px-3.5 py-2.5 bg-surface text-ink text-sm rounded-lg border border-border focus:ring-2 focus:ring-ink focus:border-ink placeholder:text-muted transition"
                >
                <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-xs text-danger" />
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="password_confirmation" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-2">Confirm New Password</label>
                <input
                    wire:model="password_confirmation"
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="w-full px-3.5 py-2.5 bg-surface text-ink text-sm rounded-lg border border-border focus:ring-2 focus:ring-ink focus:border-ink placeholder:text-muted transition"
                >
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5 text-xs text-danger" />
            </div>

            <button
                type="submit"
                class="w-full py-3 px-4 bg-accent hover:bg-black text-white text-sm font-semibold rounded-lg shadow-sm transition duration-150 ease-in-out"
            >
                Reset Password
            </button>
        </form>
    </div>
</div>
