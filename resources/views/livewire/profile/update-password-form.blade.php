<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
        $this->dispatch('toast', type: 'success', message: 'Password updated successfully.');
    }
}; ?>

<div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-5">
    <div class="flex items-start justify-between">
        <div class="space-y-1">
            <div class="flex items-center space-x-2">
                <div class="w-7 h-7 rounded-lg bg-ink/10 text-ink flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h2 class="text-xs font-bold text-ink uppercase tracking-wider">
                    Update Password
                </h2>
            </div>
            <p class="text-xs text-muted pl-9">
                Ensure your account is using a long, random password to maintain optimal security.
            </p>
        </div>
    </div>

    <form wire:submit="updatePassword" class="space-y-4 text-xs">
        <div>
            <label for="update_password_current_password" class="font-bold text-ink block mb-1">Current Password <span class="text-danger">*</span></label>
            <input 
                wire:model="current_password" 
                id="update_password_current_password" 
                name="current_password" 
                type="password" 
                autocomplete="current-password" 
                placeholder="Enter current password"
                class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition font-medium placeholder:text-muted"
            >
            <x-input-error :messages="$errors->get('current_password')" class="mt-1 text-xs text-danger" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 gap-4 pt-1">
            <div>
                <label for="update_password_password" class="font-bold text-ink block mb-1">New Password <span class="text-danger">*</span></label>
                <input 
                    wire:model="password" 
                    id="update_password_password" 
                    name="password" 
                    type="password" 
                    autocomplete="new-password" 
                    placeholder="Minimum 8 characters"
                    class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition font-medium placeholder:text-muted"
                >
                <x-input-error :messages="$errors->get('password')" class="mt-1 text-xs text-danger" />
            </div>

            <div>
                <label for="update_password_password_confirmation" class="font-bold text-ink block mb-1">Confirm New Password <span class="text-danger">*</span></label>
                <input 
                    wire:model="password_confirmation" 
                    id="update_password_password_confirmation" 
                    name="password_confirmation" 
                    type="password" 
                    autocomplete="new-password" 
                    placeholder="Repeat new password"
                    class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition font-medium placeholder:text-muted"
                >
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1 text-xs text-danger" />
            </div>
        </div>

        <div class="flex items-center justify-end pt-4 border-t border-border">
            <button 
                type="submit" 
                wire:loading.attr="disabled"
                class="px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition flex items-center gap-2 disabled:opacity-60 cursor-pointer shadow-xs"
            >
                <svg wire:loading wire:target="updatePassword" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Update Password</span>
            </button>
        </div>
    </form>
</div>
