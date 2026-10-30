<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')]
    class extends Component {
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $user = auth()->user();

        // Check if Clients is inactive (Pending Approval)
        if (isset($user->organization) && !$user->organization->is_active) {
            $this->redirect(route('auth.pending-approval'), navigate: true);
            return;
        }

        // Role-based Spatie permission redirect logic
        $targetRoute = match (true) {
            $user->hasRole('Super Admin') => route('admin.dashboard', absolute: false),
            $user->hasRole('Builder/Developer') || $user->hasRole('Builder') => route('builder.dashboard', absolute: false),
            $user->hasRole('Broker') => route('broker.dashboard', absolute: false),
            $user->hasRole('Channel Partner') => route('partner.dashboard', absolute: false),
            $user->hasRole('Executive') || $user->hasRole('Sales Executive') => route('sales.dashboard', absolute: false),
            $user->hasRole('Client') => route('client.dashboard', absolute: false),
            default => route('dashboard', absolute: false),
        };

        $this->redirectIntended(default: $targetRoute, navigate: true);
    }
}; ?>

<div class="w-full max-w-md space-y-6">
    <!-- Main Login Card -->
    <div class="bg-surface rounded-card border border-border p-8 shadow-sm">
        <!-- Logo & Header -->
        <div class="text-center space-y-2 mb-8">
            <div
                class="inline-flex h-12 w-12 rounded-2xl bg-ink text-white items-center justify-center font-bold text-xl shadow-sm mb-2">
                LP
            </div>
            <h1 class="text-2xl font-bold text-ink tracking-tight">Welcome back</h1>
            <p class="text-xs font-medium text-muted">Log in to your Lead Panther account</p>
        </div>

        <!-- Session Status Notification -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form wire:submit="login" class="space-y-5">
            <!-- Email Address Field -->
            <div>
                <label for="email" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-2">Email
                    Address</label>
                <input wire:model="form.email" id="email" type="email" name="email" required autofocus
                    autocomplete="username" placeholder="name@company.com"
                    class="w-full px-3.5 py-2.5 bg-surface text-ink text-sm rounded-lg border border-border focus:ring-2 focus:ring-ink focus:border-ink placeholder:text-muted transition">
                <x-input-error :messages="$errors->get('form.email')" class="mt-1.5 text-xs text-danger" />
            </div>

            <!-- Password Field -->
            <div x-data="{ showPassword: false }">
                <div class="flex items-center justify-between mb-2">
                    <label for="password"
                        class="block text-xs font-semibold text-ink uppercase tracking-wider">Password</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" wire:navigate
                            class="text-xs font-medium text-muted hover:text-ink transition">
                            Forgot password?
                        </a>
                    @endif
                </div>
                <div class="relative">
                    <input wire:model="form.password" id="password" :type="showPassword ? 'text' : 'password'"
                        name="password" required autocomplete="current-password" placeholder="••••••••"
                        class="w-full px-3.5 py-2.5 pr-10 bg-surface text-ink text-sm rounded-lg border border-border focus:ring-2 focus:ring-ink focus:border-ink placeholder:text-muted transition">
                    <button type="button" @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted hover:text-ink transition cursor-pointer focus:outline-none"
                        tabindex="-1" title="Toggle password visibility" aria-label="Toggle password visibility">
                        <svg x-show="!showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg x-show="showPassword" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                        </svg>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('form.password')" class="mt-1.5 text-xs text-danger" />
            </div>

            <!-- Remember Me Checkbox -->
            <div class="flex items-center justify-between pt-1">
                <label for="remember" class="inline-flex items-center cursor-pointer">
                    <input wire:model="form.remember" id="remember" type="checkbox"
                        class="h-4 w-4 rounded border-border text-ink focus:ring-ink" name="remember">
                    <span class="ms-2 text-xs font-medium text-muted">Remember this device</span>
                </label>
            </div>

            <!-- Primary Action Button -->
            <button type="submit"
                class="w-full py-3 px-4 bg-accent hover:bg-black text-white text-sm font-semibold rounded-lg shadow-sm transition duration-150 ease-in-out mt-4">
                Log in
            </button>
        </form>
    </div>

    <!-- Below Card Notice (Invite-Only B2B CRM) -->
    <div class="text-center">
        <p class="text-xs text-muted">
            Don't have an account? <span class="font-semibold text-ink">Contact your clients administrator.</span>
        </p>
    </div>
</div>