<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
        $this->dispatch('toast', type: 'info', message: 'Verification link sent to your email.');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="w-full max-w-md space-y-6">
    <div class="bg-surface rounded-card border border-border p-8 shadow-sm">
        <div class="text-center space-y-2 mb-6">
            <div class="inline-flex h-12 w-12 rounded-2xl bg-ink text-white items-center justify-center font-bold text-xl shadow-sm mb-2">
                LP
            </div>
            <h1 class="text-2xl font-bold text-ink tracking-tight">Verify Email</h1>
            <p class="text-xs text-muted leading-relaxed">
                Thanks for signing up! Before getting started, please verify your email address by clicking on the link we sent to your inbox.
            </p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="p-3 bg-emerald-50 text-success text-xs font-semibold rounded-lg border border-emerald-200 mb-4">
                A new verification link has been sent to the email address you provided during registration.
            </div>
        @endif

        <div class="space-y-4">
            <button 
                wire:click="sendVerification" 
                type="button" 
                class="w-full py-3 px-4 bg-accent hover:bg-black text-white text-sm font-semibold rounded-lg shadow-sm transition duration-150 ease-in-out"
            >
                Resend Verification Email
            </button>

            <button 
                wire:click="logout" 
                type="button" 
                class="w-full py-2.5 px-4 bg-canvas hover:bg-surface text-ink text-xs font-semibold rounded-lg border border-border transition"
            >
                Log Out
            </button>
        </div>
    </div>
</div>
