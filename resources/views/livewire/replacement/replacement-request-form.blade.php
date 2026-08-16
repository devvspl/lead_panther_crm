<div>
    @if($isOpen && $lead)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-surface rounded-card border border-border p-6 max-w-md w-full shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-bold text-ink">Claim Lead Replacement</h3>
                    <button wire:click="closeForm" class="text-muted hover:text-ink">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-3">
                    <div class="p-3 bg-canvas rounded-lg text-xs space-y-1">
                        <div class="font-bold text-ink">{{ $lead->name }} ({{ $lead->lead_code }})</div>
                        <div class="text-muted">Created: {{ \Carbon\Carbon::parse($lead->created_at)->format('M d, H:i') }}</div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-ink">Select Replacement Reason</label>
                        <livewire:shared.searchable-select 
                            :model="\App\Models\ReplacementReason::class"
                            displayColumn="label"
                            :searchable="true"
                            placeholder="Select Replacement Reason"
                            wire:model="reasonId"
                            key="repl-reason"
                        />
                    </div>

                    <div>
                        <label class="text-xs font-bold text-ink">Free-text Note / Details</label>
                        <textarea wire:model="note" placeholder="Provide extra details for review..." class="w-full text-xs p-2 rounded-lg border border-border bg-surface text-ink mt-1 h-20"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-border">
                    <x-ui.button wire:click="closeForm" variant="secondary" class="text-xs">Cancel</x-ui.button>
                    <x-ui.button wire:click="submitRequest" variant="primary" class="text-xs">Submit Claim</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
