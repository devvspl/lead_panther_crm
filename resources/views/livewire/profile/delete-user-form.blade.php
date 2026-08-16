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

<div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
    <div>
        <h2 class="text-xs font-bold text-danger uppercase tracking-wider">
            Delete Account
        </h2>
        <p class="mt-1 text-xs text-muted">
            Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.
        </p>
    </div>

    <div class="pt-3 border-t border-border flex justify-end">
        <button 
            type="button"
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')" 
            class="bg-danger hover:bg-red-700 text-white font-medium py-2.5 px-5 rounded-lg text-xs transition shadow-sm"
        >
            Delete Account
        </button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6 space-y-4">
            <h2 class="text-sm font-bold text-ink">
                Are you sure you want to delete your account?
            </h2>

            <p class="text-xs text-muted leading-relaxed">
                Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.
            </p>

            <div>
                <label for="password" class="font-bold text-ink block mb-1 text-xs">Confirm Password</label>
                <input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Enter your password to confirm"
                    class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition"
                />
                <x-input-error :messages="$errors->get('password')" class="mt-1 text-xs text-danger" />
            </div>

            <div class="flex justify-end space-x-3 pt-3 border-t border-border">
                <button 
                    type="button" 
                    x-on:click="$dispatch('close')" 
                    class="px-4 py-2 text-xs font-medium text-muted hover:text-ink transition bg-canvas rounded-lg border border-border"
                >
                    Cancel
                </button>

                <button 
                    type="submit" 
                    class="px-4 py-2 text-xs font-medium text-white bg-danger hover:bg-red-700 rounded-lg transition"
                >
                    Delete Account
                </button>
            </div>
        </form>
    </x-modal>
</div>
