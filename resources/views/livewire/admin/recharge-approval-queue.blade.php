<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1">
                <span>Lead Credits</span>
                <span>/</span>
                <span class="text-ink font-semibold">Recharge Requests</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                <svg class="w-6 h-6 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <span>Credit Request Approvals</span>
            </h1>
            <p class="text-xs text-muted">Review quantities and approve lead credit requests.</p>
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
        searchPlaceholder="Search client, credit quantity or reference..."
        emptyTitle="No Recharge Requests Found"
        emptyMessage="No wallet recharge requests match your current filters."
    />

    <!-- APPROVE CONFIRMATION MODAL -->
    @if($showApproveModal)
        @php
            $selectedReq = $selectedRequestId ? \App\Models\RechargeRequest::with(['client.wallet', 'client.organization', 'package'])->find($selectedRequestId) : null;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4 overflow-y-auto overscroll-contain">
            <div class="bg-surface rounded-card max-w-md w-full p-6 shadow-2xl border border-border space-y-4 max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-bold text-ink">Approve Credit Recharge Request</h3>
                    <button type="button" wire:click="$set('showApproveModal', false)" class="text-muted hover:text-ink cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="text-xs text-muted">Confirming this request will immediately credit the client's wallet with the approved lead credit quantity.</p>

                <!-- Request Summary Details -->
                @if($selectedReq)
                    <div class="p-3 bg-canvas rounded-lg border border-border space-y-1.5 text-xs">
                        <div class="flex justify-between">
                            <span class="text-muted">Client / Org:</span>
                            <strong class="text-ink">{{ $selectedReq->client_name }}</strong>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">Current Balance:</span>
                            <span class="font-mono font-bold text-ink">{{ number_format($selectedReq->current_balance) }} Credits</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">Requested Credits:</span>
                            <span class="font-mono font-bold text-primary">{{ number_format($selectedReq->amount ?? 0) }} Credits</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">Requested Date:</span>
                            <span class="text-muted font-mono">{{ $selectedReq->created_at?->format('M d, Y H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">Status:</span>
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-pill bg-amber-50 text-amber-700 border border-amber-200">Pending Approval</span>
                        </div>
                        @if($selectedReq->reference_note)
                            <div class="flex justify-between">
                                <span class="text-muted">Client Note:</span>
                                <span class="text-ink italic">{{ $selectedReq->reference_note }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                <div><label class="block text-xs font-semibold text-ink mb-1">Approved Lead Credits</label><input type="number" min="1" step="1" wire:model="approvalQuantity" class="w-full text-xs rounded-lg border border-border p-2 bg-canvas text-ink font-mono" /></div>
                <div>
                    <label class="block text-xs font-semibold text-ink mb-1">Approval note (optional)</label>
                    <input type="text" wire:model="referenceNote" placeholder="Reason or allocation reference" class="w-full text-xs rounded-lg border border-border p-2 bg-canvas text-ink focus:ring-2 focus:ring-primary/20 focus:border-primary">
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
        @php
            $selectedReq = $selectedRequestId ? \App\Models\RechargeRequest::with(['client.wallet', 'client.organization', 'package'])->find($selectedRequestId) : null;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4 overflow-y-auto overscroll-contain">
            <div class="bg-surface rounded-card max-w-md w-full p-6 shadow-2xl border border-border space-y-4 max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-bold text-ink">Decline Credit Recharge Request</h3>
                    <button type="button" wire:click="$set('showRejectModal', false)" class="text-muted hover:text-ink cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="text-xs text-muted">Decline this lead credit request. No credits will be added to the client's balance.</p>

                <!-- Request Summary Details -->
                @if($selectedReq)
                    <div class="p-3 bg-canvas rounded-lg border border-border space-y-1.5 text-xs">
                        <div class="flex justify-between">
                            <span class="text-muted">Client / Org:</span>
                            <strong class="text-ink">{{ $selectedReq->client_name }}</strong>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">Current Balance:</span>
                            <span class="font-mono font-bold text-ink">{{ number_format($selectedReq->current_balance) }} Credits</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">Requested Credits:</span>
                            <span class="font-mono font-bold text-rose-600">{{ number_format($selectedReq->amount ?? 0) }} Credits</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">Requested Date:</span>
                            <span class="text-muted font-mono">{{ $selectedReq->created_at?->format('M d, Y H:i') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted">Status:</span>
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-pill bg-amber-50 text-amber-700 border border-amber-200">Pending Approval</span>
                        </div>
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-semibold text-ink mb-1">Rejection Reason (Optional)</label>
                    <textarea wire:model="rejectionReason" rows="3" placeholder="Optional explanation for declining the request." class="w-full text-xs rounded-lg border border-border p-2.5 bg-canvas text-ink focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500"></textarea>
                </div>

                <div class="flex justify-end space-x-2 pt-2 border-t border-border">
                    <button type="button" wire:click="$set('showRejectModal', false)" class="px-3 py-1.5 text-xs text-muted hover:text-ink font-semibold cursor-pointer">Cancel</button>
                    <button type="button" wire:click="rejectRequest" class="px-4 py-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-2xs cursor-pointer">Decline Request ✕</button>
                </div>
            </div>
        </div>
    @endif
</div>
