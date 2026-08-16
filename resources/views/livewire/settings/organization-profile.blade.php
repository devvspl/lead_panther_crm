<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Organization Profile</h1>
            <p class="text-xs text-muted">Manage company branding, billing contact details, and organization metadata.</p>
        </div>
    </div>

    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4 max-w-2xl">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Company Details</h2>

        <div class="space-y-4 text-xs">
            <div>
                <label class="font-bold text-ink">Organization Legal Name</label>
                <input type="text" wire:model="name" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1">
            </div>

            <div>
                <label class="font-bold text-ink">Billing Email Address</label>
                <input type="email" wire:model="billingEmail" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1">
            </div>

            <div>
                <label class="font-bold text-ink">Support Contact Phone</label>
                <input type="text" wire:model="phone" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1">
            </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-border">
            <x-ui.button wire:click="save" variant="primary" class="text-xs">
                Save Organization Profile
            </x-ui.button>
        </div>
    </div>
</div>
