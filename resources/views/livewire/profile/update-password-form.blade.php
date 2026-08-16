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

<div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
    <div>
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">
            Update Password
        </h2>
        <p class="mt-1 text-xs text-muted">
            Ensure your account is using a long, random password to stay secure.
        </p>
    </div>

    <form wire:submit="updatePassword" class="space-y-4 text-xs">
        <div>
            <label for="update_password_current_password" class="font-bold text-ink block mb-1">Current Password</label>
            <input 
                wire:model="current_password" 
                id="update_password_current_password" 
                name="current_password" 
                type="password" 
                autocomplete="current-password" 
                class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition"
            >
            <x-input-error :messages="$errors->get('current_password')" class="mt-1 text-xs text-danger" />
        </div>

        <div>
            <label for="update_password_password" class="font-bold text-ink block mb-1">New Password</label>
            <input 
                wire:model="password" 
                id="update_password_password" 
                name="password" 
                type="password" 
                autocomplete="new-password" 
                class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition"
            >
            <x-input-error :messages="$errors->get('password')" class="mt-1 text-xs text-danger" />
        </div>

        <div>
            <label for="update_password_password_confirmation" class="font-bold text-ink block mb-1">Confirm Password</label>
            <input 
                wire:model="password_confirmation" 
                id="update_password_password_confirmation" 
                name="password_confirmation" 
                type="password" 
                autocomplete="new-password" 
                class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition"
            >
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1 text-xs text-danger" />
        </div>

        <div class="flex items-center justify-end pt-3 border-t border-border">
            <button type="submit" class="bg-accent hover:bg-black text-white font-medium py-2.5 px-5 rounded-lg text-xs transition">
                Update Password
            </button>
        </div>
    </form>
</div>
