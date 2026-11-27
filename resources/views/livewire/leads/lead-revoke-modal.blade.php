@if($showRevokeDeliveryModal)
    @php
        $targetIds = !empty($revokeLeadIds) ? $revokeLeadIds : ($revokeLeadId ? [$revokeLeadId] : []);
        $targetCount = count($targetIds);
        $revokingLead = $targetCount === 1 ? \App\Models\Lead::withoutGlobalScopes()->with(['client.organization', 'campaign.project', 'leadDeliveryItem.client.organization', 'leadDeliveryItem.project'])->find($targetIds[0]) : null;
        $deliveryItem = $revokingLead?->leadDeliveryItem;
        $destClient = $deliveryItem?->client ?? $revokingLead?->client;
        $destProject = $deliveryItem?->project ?? $revokingLead?->campaign?->project;
        $deliveredAtDate = $deliveryItem?->sent_at ?? $revokingLead?->delivered_at;
    @endphp
    <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4"
         wire:keydown.escape="closeRevokeDeliveryModal">
        <div class="bg-surface rounded-2xl border border-border shadow-2xl max-w-lg w-full p-5 sm:p-6 space-y-4 max-h-[calc(100dvh-2rem)] flex flex-col animate-in fade-in zoom-in-95 duration-150">
            <!-- Modal Header -->
            <div class="flex items-start justify-between pb-3.5 border-b border-border shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 border border-rose-200/80 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-ink">
                                {{ $targetCount > 1 ? "Revoke {$targetCount} Delivered Leads" : 'Revoke Delivered Lead' }}
                            </h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                {{ $targetCount > 1 ? 'Batch Return' : 'Lead Return' }}
                            </span>
                        </div>
                        <p class="text-xs text-muted mt-0.5">
                            Return {{ $targetCount > 1 ? 'the selected delivered leads' : 'this delivered lead' }} to the CRM's available lead pool.
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="closeRevokeDeliveryModal" class="text-muted hover:text-ink p-1 rounded-lg hover:bg-canvas transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Lead Information Details -->
            @if($targetCount === 1 && $revokingLead)
                <div class="bg-canvas rounded-xl border border-border p-3.5 space-y-2 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <span class="text-muted block text-[11px] font-medium">Lead ID</span>
                            <span class="font-mono font-bold text-ink text-sm">#{{ $revokingLead->lead_code ?? $revokingLead->id }}</span>
                            @if($revokingLead->name)
                                <span class="text-muted block text-[11px] truncate">{{ $revokingLead->name }}</span>
                            @endif
                        </div>
                        <div>
                            <span class="text-muted block text-[11px] font-medium">Delivered Date</span>
                            <span class="font-semibold text-ink">
                                {{ $deliveredAtDate ? $deliveredAtDate->format('d M Y, h:i A') : 'N/A' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-muted block text-[11px] font-medium">Client / Organization</span>
                            <span class="font-semibold text-ink truncate block">
                                {{ $destClient?->organization?->name ?? $destClient?->name ?? 'N/A' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-muted block text-[11px] font-medium">Project</span>
                            <span class="font-semibold text-ink truncate block">
                                {{ $destProject?->name ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-canvas rounded-xl border border-border p-3.5 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-ink">Selected Leads for Revocation</span>
                        <span class="px-2 py-0.5 rounded font-bold bg-primary/10 text-primary text-xs">
                            {{ $targetCount }} Leads Selected
                        </span>
                    </div>
                    <p class="text-muted text-[11px]">
                        All {{ $targetCount }} selected leads will be returned to the CRM pool, unassigned from client executives, and marked as excluded from automatic future client delivery.
                    </p>
                </div>
            @endif

            <!-- Return Reason Input -->
            <div class="space-y-1.5">
                <label for="revoke-reason-input" class="block text-xs font-semibold text-ink">
                    Return Reason <span class="text-rose-500">*</span>
                </label>
                <textarea id="revoke-reason-input"
                          wire:model="revokeReason"
                          rows="3"
                          placeholder="Provide the reason for returning {{ $targetCount > 1 ? 'these leads' : 'this lead' }} to the CRM..."
                          class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-xs text-ink placeholder:text-muted focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none transition"></textarea>
                @error('revokeReason')
                    <p class="text-xs text-rose-600 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-xl bg-amber-50 border border-amber-200/80 p-3 text-[11px] text-amber-800 space-y-1">
                <div class="font-semibold flex items-center gap-1.5 text-amber-900">
                    <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Delivery Revocation Notice</span>
                </div>
                <p>
                    Revoking will unassign client executives, restore the original source organization, and mark {{ $targetCount > 1 ? 'these leads' : 'this lead' }} as excluded from automatic client delivery until explicitly pushed.
                </p>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-2.5 pt-3.5 border-t border-border shrink-0">
                <button type="button"
                        wire:click="closeRevokeDeliveryModal"
                        class="px-3.5 py-2 text-xs font-semibold rounded-xl border border-border text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                    Cancel
                </button>
                <button type="button"
                        wire:click="confirmRevokeDelivery"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 text-xs font-bold rounded-xl bg-rose-600 text-white hover:bg-rose-700 active:bg-rose-800 transition cursor-pointer shadow-sm flex items-center gap-2">
                    <span wire:loading.remove wire:target="confirmRevokeDelivery">
                        {{ $targetCount > 1 ? "Confirm Revoke ({$targetCount})" : 'Confirm Revoke' }}
                    </span>
                    <span wire:loading wire:target="confirmRevokeDelivery" class="inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Revoking...
                    </span>
                </button>
            </div>
        </div>
    </div>
@endif
