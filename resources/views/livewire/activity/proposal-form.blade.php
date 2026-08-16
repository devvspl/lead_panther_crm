<div class="space-y-4">

    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Create Commercial Proposal for {{ $lead->name }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <div>
                <label class="font-bold text-ink">Project Unit</label>
                <livewire:shared.searchable-select 
                    :model="\App\Models\ProjectUnit::class"
                    placeholder="Select Unit"
                    wire:model="projectUnitId"
                    key="prop-unit"
                    class="w-full mt-1"
                />
            </div>

            <div>
                <label class="font-bold text-ink">Base Unit Price (₹)</label>
                <input type="number" wire:model="price" step="10000" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
            </div>

            <div>
                <label class="font-bold text-ink">Special Discount Amount (₹)</label>
                <input type="number" wire:model="discount" step="5000" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
            </div>

            <div>
                <label class="font-bold text-ink">Proposal Expiry Date</label>
                <input type="date" wire:model="validUntil" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
            </div>

            <div class="md:col-span-2">
                <label class="font-bold text-ink">Terms & Conditions</label>
                <textarea wire:model="terms" rows="3" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1"></textarea>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <x-ui.button wire:click="createProposal" variant="primary" class="text-xs">
                Generate & Email Proposal PDF Link
            </x-ui.button>
        </div>

        @if($generatedSignedUrl)
            <div class="p-4 bg-canvas rounded-card border border-border space-y-2 mt-4">
                <span class="text-xs font-bold text-ink">Client Signed Tracking Link:</span>
                <input type="text" readonly value="{{ $generatedSignedUrl }}" class="w-full text-xs font-mono p-2.5 rounded-lg border border-border bg-surface text-ink">
                <p class="text-[10px] text-muted">When the client opens this link, proposal viewed_at timestamp will be logged automatically.</p>
            </div>
        @endif
    </div>
</div>
