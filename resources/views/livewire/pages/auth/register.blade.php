<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="w-full max-w-md space-y-6">
    <div class="bg-surface rounded-card border border-border p-8 shadow-sm">
        <div class="text-center space-y-2 mb-6">
            <div class="inline-flex h-12 w-12 rounded-2xl bg-ink text-white items-center justify-center font-bold text-xl shadow-sm mb-2">
                LP
            </div>
            <h1 class="text-2xl font-bold text-ink tracking-tight">Create an account</h1>
            <p class="text-xs text-muted">Register your account with Lead Panther</p>
        </div>

        <form wire:submit="register" class="space-y-4">
            <div>
                <label for="name" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-2">Full Name</label>
                <input wire:model="name" id="name" type="text" name="name" required autofocus placeholder="John Doe" class="w-full px-3.5 py-2.5 bg-surface text-ink text-sm rounded-lg border border-border focus:ring-2 focus:ring-ink focus:border-ink placeholder:text-muted transition">
                <x-input-error :messages="$errors->get('name')" class="mt-1 text-xs text-danger" />
            </div>

            <div>
                <label for="email" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-2">Email Address</label>
                <input wire:model="email" id="email" type="email" name="email" required placeholder="name@company.com" class="w-full px-3.5 py-2.5 bg-surface text-ink text-sm rounded-lg border border-border focus:ring-2 focus:ring-ink focus:border-ink placeholder:text-muted transition">
                <x-input-error :messages="$errors->get('email')" class="mt-1 text-xs text-danger" />
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-2">Password</label>
                <input wire:model="password" id="password" type="password" name="password" required autocomplete="new-password" placeholder="••••••••" class="w-full px-3.5 py-2.5 bg-surface text-ink text-sm rounded-lg border border-border focus:ring-2 focus:ring-ink focus:border-ink placeholder:text-muted transition">
                <x-input-error :messages="$errors->get('password')" class="mt-1 text-xs text-danger" />
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-2">Confirm Password</label>
                <input wire:model="password_confirmation" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••" class="w-full px-3.5 py-2.5 bg-surface text-ink text-sm rounded-lg border border-border focus:ring-2 focus:ring-ink focus:border-ink placeholder:text-muted transition">
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1 text-xs text-danger" />
            </div>

            <button type="submit" class="w-full py-3 px-4 bg-accent hover:bg-black text-white text-sm font-semibold rounded-lg shadow-sm transition duration-150 ease-in-out mt-4">
                Register
            </button>
        </form>

        <div class="mt-6 pt-6 border-t border-border text-center">
            <a href="{{ route('login') }}" wire:navigate class="text-xs font-semibold text-ink hover:text-muted transition">
                Already registered? Log in
            </a>
        </div>
    </div>
</div>
