<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
        $this->dispatch('toast', type: 'success', message: 'Profile information updated successfully.');
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
        $this->dispatch('toast', type: 'info', message: 'A new verification link has been sent to your email address.');
    }
}; ?>

<div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
    <div>
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">
            Profile Information
        </h2>
        <p class="mt-1 text-xs text-muted">
            Update your account's profile name and email address.
        </p>
    </div>

    <form wire:submit="updateProfileInformation" class="space-y-4 text-xs">
        <div>
            <label for="name" class="font-bold text-ink block mb-1">Full Name</label>
            <input 
                wire:model="name" 
                id="name" 
                name="name" 
                type="text" 
                required 
                autofocus 
                autocomplete="name" 
                class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition"
            >
            <x-input-error class="mt-1 text-xs text-danger" :messages="$errors->get('name')" />
        </div>

        <div>
            <label for="email" class="font-bold text-ink block mb-1">Email Address</label>
            <input 
                wire:model="email" 
                id="email" 
                name="email" 
                type="email" 
                required 
                autocomplete="username" 
                class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition"
            >
            <x-input-error class="mt-1 text-xs text-danger" :messages="$errors->get('email')" />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div class="mt-2 p-3 bg-amber-50 rounded-lg border border-amber-200">
                    <p class="text-xs text-amber-800 font-medium">
                        Your email address is unverified.
                        <button wire:click.prevent="sendVerification" class="underline font-bold text-amber-900 hover:text-black ml-1">
                            Click here to re-send verification email.
                        </button>
                    </p>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-end pt-3 border-t border-border">
            <button type="submit" class="bg-accent hover:bg-black text-white font-medium py-2.5 px-5 rounded-lg text-xs transition">
                Save Changes
            </button>
        </div>
    </form>
</div>
