<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1">
                <span>Leads &amp; Quality</span>
                <span>/</span>
                <span class="text-ink font-semibold">Replacement Claims</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                <svg class="w-6 h-6 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span>Lead Replacement Queue</span>
            </h1>
            <p class="text-xs text-muted">Review, approve, or reject lead replacement claims with audited SLA tracking.</p>
        </div>
    </div>

    <!-- Advanced Table Component -->
    <x-ui.advanced-table 
        :columns="$this->tableColumns()"
        :rows="$replacements"
        :quickFilters="$this->quickFilters()"
        :activeStatus="$statusFilter"
        :visibleColumns="$visibleColumns"
        :sortField="$sortField"
        :sortDirection="$sortDirection"
        :filterCount="$this->activeFilterCount"
        searchPlaceholder="Search by lead code, contact name, or reason..."
        emptyTitle="No Replacement Claims"
        emptyMessage="No replacement requests match your current filters."
    >
        <!-- Filter Dropdown Slot -->
        <x-slot:filters>
            <div class="space-y-3">
                <div>
                    <label class="text-[11px] font-bold text-ink block mb-1">Filter Client</label>
                    <select wire:model.live="filterClient" class="w-full h-8 px-2.5 rounded-lg border border-border bg-canvas text-ink text-xs">
                        <option value="">All Clients</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-[11px] font-bold text-ink block mb-1">Filter Project</label>
                    <select wire:model.live="filterProject" class="w-full h-8 px-2.5 rounded-lg border border-border bg-canvas text-ink text-xs">
                        <option value="">All Projects</option>
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-slot:filters>

        <!-- Primary Action Slot -->
        <x-slot:action>
            <x-ui.export-button target="exportExcel" class="text-xs" />
        </x-slot:action>
    </x-ui.advanced-table>

    <!-- Rejection Note Modal -->
    @if($showRejectModal)
        <div class="fixed inset-0 bg-black/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-surface rounded-card border border-border p-6 max-w-md w-full shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-bold text-ink">Reject Replacement Claim</h3>
                    <button type="button" wire:click="closeRejectModal" class="text-muted hover:text-ink cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div>
                    <label class="text-xs font-bold text-ink">Rejection Reason / Note <span class="text-danger">*</span></label>
                    <textarea wire:model="rejectionNote" placeholder="Explain why this claim is rejected (required)..." class="w-full text-xs p-3 rounded-lg border border-border bg-canvas text-ink mt-1 h-24 focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500"></textarea>
                    @error('rejectionNote') <span class="text-[10px] text-danger mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-border">
                    <x-ui.button wire:click="closeRejectModal" variant="secondary" class="text-xs">Cancel</x-ui.button>
                    <x-ui.button wire:click="confirmReject" variant="danger" class="text-xs">Confirm Rejection</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
