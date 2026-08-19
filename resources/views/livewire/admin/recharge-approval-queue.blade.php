<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1">
                <span>Billing &amp; Credits</span>
                <span>/</span>
                <span class="text-ink font-semibold">Recharge Requests</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                <svg class="w-6 h-6 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <span>Recharge Approval Queue</span>
            </h1>
            <p class="text-xs text-muted">Review, verify payment references, and approve wallet recharge requests.</p>
        </div>
    </div>

    <!-- Advanced Table Component -->
    <x-ui.advanced-table 
        :columns="$this->tableColumns()"
        :rows="$requests"
        :quickFilters="$this->quickFilters()"
        :activeStatus="$statusFilter"
        :visibleColumns="$visibleColumns"
        :sortField="$sortField"
        :sortDirection="$sortDirection"
        :filterCount="$this->activeFilterCount"
        searchPlaceholder="Search client name, amount, or reference UTR..."
        emptyTitle="No Recharge Requests Found"
        emptyMessage="No wallet recharge requests match your current filters."
    />

    <!-- APPROVE CONFIRMATION MODAL -->
    @if($showApproveModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
            <div class="bg-surface rounded-card max-w-md w-full p-6 shadow-2xl border border-border space-y-4">
                <h3 class="text-base font-bold text-ink">Approve Credit Recharge Request</h3>
                <p class="text-xs text-muted">Confirming this request will immediately credit the client's wallet with the requested credit balance.</p>

                <div>
                    <label class="block text-xs font-semibold text-ink mb-1">Payment Reference / Note (Optional)</label>
                    <input type="text" wire:model="referenceNote" placeholder="e.g. Bank Transfer Ref #12345 or Cash Payment" class="w-full text-xs rounded-lg border border-border p-2 bg-canvas text-ink focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>

                <div class="flex justify-end space-x-2 pt-2 border-t border-border">
                    <button type="button" wire:click="$set('showApproveModal', false)" class="px-3 py-1.5 text-xs text-muted hover:text-ink font-semibold cursor-pointer">Cancel</button>
                    <button type="button" wire:click="approveRequest" class="px-4 py-1.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-2xs cursor-pointer">Confirm Approval ✓</button>
                </div>
            </div>
        </div>
    @endif

    <!-- REJECT MODAL -->
    @if($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4">
            <div class="bg-surface rounded-card max-w-md w-full p-6 shadow-2xl border border-border space-y-4">
                <h3 class="text-base font-bold text-ink">Decline Credit Recharge Request</h3>
                <p class="text-xs text-muted">Please specify a reason for rejecting this recharge request. This will be sent to the client.</p>

                <div>
                    <label class="block text-xs font-semibold text-ink mb-1">Rejection Reason <span class="text-rose-500">*</span></label>
                    <textarea wire:model="rejectionReason" rows="3" placeholder="e.g. Payment receipt not verified or incorrect amount." class="w-full text-xs rounded-lg border border-border p-2.5 bg-canvas text-ink focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500"></textarea>
                    @error('rejectionReason') <span class="text-rose-500 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end space-x-2 pt-2 border-t border-border">
                    <button type="button" wire:click="$set('showRejectModal', false)" class="px-3 py-1.5 text-xs text-muted hover:text-ink font-semibold cursor-pointer">Cancel</button>
                    <button type="button" wire:click="rejectRequest" class="px-4 py-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-2xs cursor-pointer">Decline Request ✕</button>
                </div>
            </div>
        </div>
    @endif
</div>
