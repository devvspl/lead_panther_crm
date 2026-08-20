<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <button 
        type="button"
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')" 
        class="px-3.5 py-2 border border-danger/40 bg-danger/5 hover:bg-danger text-danger hover:text-white text-xs font-semibold rounded-lg transition-all duration-150 flex items-center gap-1.5 cursor-pointer shadow-2xs group shrink-0"
        title="Permanently Delete Account"
    >
        <svg class="w-3.5 h-3.5 text-danger group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        <span>Delete Account</span>
    </button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6 space-y-5">
            <div class="flex items-start space-x-3.5">
                <div class="w-10 h-10 rounded-full bg-danger/10 text-danger flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-ink">
                        Are you sure you want to delete your account?
                    </h3>
                    <p class="text-xs text-muted leading-relaxed">
                        Once your account is deleted, all associated data, settings, and sessions will be permanently purged. Please enter your account password below to confirm deletion.
                    </p>
                </div>
            </div>

            <div>
                <label for="password" class="font-bold text-ink block mb-1 text-xs">Confirm Password <span class="text-danger">*</span></label>
                <input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Enter your password to confirm"
                    class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition font-medium placeholder:text-muted mt-1"
                />
                <x-input-error :messages="$errors->get('password')" class="mt-1 text-xs text-danger" />
            </div>

            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-border">
                <button 
                    type="button" 
                    x-on:click="$dispatch('close')" 
                    class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer shadow-xs"
                >
                    Cancel
                </button>

                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    class="px-4 py-2 bg-danger text-white text-xs font-semibold rounded-lg hover:bg-red-700 transition flex items-center gap-2 disabled:opacity-60 cursor-pointer shadow-xs"
                >
                    <svg wire:loading wire:target="deleteUser" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Permanently Delete</span>
                </button>
            </div>
        </form>
    </x-modal>
</div>
