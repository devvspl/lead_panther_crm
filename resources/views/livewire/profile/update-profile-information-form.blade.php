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

<div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-5">
    <div class="flex items-start justify-between">
        <div class="space-y-1">
            <div class="flex items-center space-x-2">
                <div class="w-7 h-7 rounded-lg bg-ink/10 text-ink flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <h2 class="text-xs font-bold text-ink uppercase tracking-wider">
                    Profile Information
                </h2>
            </div>
            <p class="text-xs text-muted pl-9">
                Update your account's primary identification name and contact email address.
            </p>
        </div>
    </div>

    <form wire:submit="updateProfileInformation" class="space-y-4 text-xs">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="name" class="font-bold text-ink block mb-1">Full Name <span class="text-danger">*</span></label>
                <input 
                    wire:model="name" 
                    id="name" 
                    name="name" 
                    type="text" 
                    required 
                    autofocus 
                    autocomplete="name" 
                    placeholder="Enter your full name"
                    class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition font-medium placeholder:text-muted"
                >
                <x-input-error class="mt-1 text-xs text-danger" :messages="$errors->get('name')" />
            </div>

            <div>
                <label for="email" class="font-bold text-ink block mb-1">Email Address <span class="text-danger">*</span></label>
                <input 
                    wire:model="email" 
                    id="email" 
                    name="email" 
                    type="email" 
                    required 
                    autocomplete="username" 
                    placeholder="your.email@company.com"
                    class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition font-medium placeholder:text-muted"
                >
                <x-input-error class="mt-1 text-xs text-danger" :messages="$errors->get('email')" />
            </div>

            <div>
                <label class="font-bold text-ink block mb-1">Assigned Role</label>
                <input 
                    type="text" 
                    readonly 
                    disabled
                    value="{{ auth()->user()->primary_role_name ?? (auth()->user()->getRoleNames()->first() ?: 'User') }}"
                    class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas/60 text-muted text-xs font-medium cursor-not-allowed select-none"
                >
            </div>

            <div>
                <label class="font-bold text-ink block mb-1">Assigned Organization</label>
                <input 
                    type="text" 
                    readonly 
                    disabled
                    value="{{ auth()->user()->organization_name ?? (auth()->user()->organization?->name ?: 'Lead Panther CRM') }}"
                    class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas/60 text-muted text-xs font-medium cursor-not-allowed select-none"
                >
            </div>
        </div>

        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
            <div class="p-3.5 bg-amber-50/80 rounded-lg border border-amber-200/80 text-amber-900 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span class="text-xs font-medium">Your email address is unverified.</span>
                </div>
                <button wire:click.prevent="sendVerification" class="text-xs underline font-bold text-amber-950 hover:text-black transition cursor-pointer">
                    Re-send verification link
                </button>
            </div>
        @endif

        <div class="flex items-center justify-end pt-4 border-t border-border">
            <button 
                type="submit" 
                wire:loading.attr="disabled"
                class="px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition flex items-center gap-2 disabled:opacity-60 cursor-pointer shadow-xs"
            >
                <svg wire:loading wire:target="updateProfileInformation" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Save Changes</span>
            </button>
        </div>
    </form>
</div>
