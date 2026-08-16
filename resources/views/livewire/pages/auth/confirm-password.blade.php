<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="w-full max-w-md space-y-6">
    <div class="bg-surface rounded-card border border-border p-8 shadow-sm">
        <div class="text-center space-y-2 mb-6">
            <div class="inline-flex h-12 w-12 rounded-2xl bg-ink text-white items-center justify-center font-bold text-xl shadow-sm mb-2">
                LP
            </div>
            <h1 class="text-2xl font-bold text-ink tracking-tight">Security Check</h1>
            <p class="text-xs text-muted leading-relaxed">
                This is a secure area of the application. Please confirm your password before continuing.
            </p>
        </div>

        <form wire:submit="confirmPassword" class="space-y-5">
            <div>
                <label for="password" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-2">Password</label>
                <input 
                    wire:model="password"
                    id="password"
                    type="password"
                    name="password"
                    required 
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="w-full px-3.5 py-2.5 bg-surface text-ink text-sm rounded-lg border border-border focus:ring-2 focus:ring-ink focus:border-ink placeholder:text-muted transition"
                >
                <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-xs text-danger" />
            </div>

            <button 
                type="submit" 
                class="w-full py-3 px-4 bg-accent hover:bg-black text-white text-sm font-semibold rounded-lg shadow-sm transition duration-150 ease-in-out"
            >
                Confirm Password
            </button>
        </form>
    </div>
</div>
