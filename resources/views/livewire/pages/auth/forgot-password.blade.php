<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));
            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div class="w-full max-w-md space-y-6">
    <div class="bg-surface rounded-card border border-border p-8 shadow-sm">
        <div class="text-center space-y-2 mb-6">
            <div class="inline-flex h-12 w-12 rounded-2xl bg-ink text-white items-center justify-center font-bold text-xl shadow-sm mb-2">
                LP
            </div>
            <h1 class="text-2xl font-bold text-ink tracking-tight">Reset password</h1>
            <p class="text-xs text-muted leading-relaxed">
                Enter your work email address and we'll send you a password reset link.
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form wire:submit="sendPasswordResetLink" class="space-y-5">
            <div>
                <label for="email" class="block text-xs font-semibold text-ink uppercase tracking-wider mb-2">Email Address</label>
                <input
                    wire:model="email"
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    placeholder="name@company.com"
                    class="w-full px-3.5 py-2.5 bg-surface text-ink text-sm rounded-lg border border-border focus:ring-2 focus:ring-ink focus:border-ink placeholder:text-muted transition"
                >
                <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-xs text-danger" />
            </div>

            <button
                type="submit"
                class="w-full py-3 px-4 bg-accent hover:bg-black text-white text-sm font-semibold rounded-lg shadow-sm transition duration-150 ease-in-out"
            >
                Send Password Reset Link
            </button>
        </form>

        <div class="mt-6 pt-6 border-t border-border text-center">
            <a href="{{ route('login') }}" wire:navigate class="text-xs font-semibold text-ink hover:text-muted transition">
                &larr; Back to login
            </a>
        </div>
    </div>
</div>
