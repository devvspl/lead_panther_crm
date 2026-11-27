@if($showMultiClientModal)
    @php
        $selectedCount = count($multiClientLeadIds);
        $totalClients = count($multiClientConfigs);
        $totalAmount = $this->totalModalBilling;
    @endphp
    <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4"
         wire:keydown.escape="closeMultiClientDeliveryModal">
        <div class="bg-surface rounded-2xl border border-border shadow-2xl max-w-3xl w-full p-5 sm:p-6 space-y-4 max-h-[calc(100dvh-2rem)] flex flex-col animate-in fade-in zoom-in-95 duration-150">
            <!-- Modal Header -->
            <div class="flex items-start justify-between pb-3.5 border-b border-border shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-200/80 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-ink">Multi-Client Lead Distribution & Billing</h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                {{ $selectedCount }} Lead{{ $selectedCount > 1 ? 's' : '' }} Selected
                            </span>
                        </div>
                        <p class="text-xs text-muted mt-0.5">
                            Deliver leads to multiple clients with individual project mapping and per-client rates.
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="closeMultiClientDeliveryModal" class="text-muted hover:text-ink p-1 rounded-lg hover:bg-canvas transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Scrollable Content Body -->
            <div class="space-y-4 overflow-y-auto flex-1 pr-1">
                <!-- Info Banner -->
                <div class="bg-indigo-50/50 border border-indigo-200/70 rounded-xl p-3 flex items-start gap-2.5 text-xs text-indigo-950">
                    <svg class="w-4 h-4 text-indigo-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="leading-relaxed">
                        Each lead is shared across all selected clients without duplicating master records. Separate delivery items and wallet credit deductions are created independently per client.
                    </p>
                </div>

                <!-- Destination Clients Configuration List -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold text-ink uppercase tracking-wider">Destination Clients & Rates</h4>
                        <button type="button" wire:click="addMultiClientRow"
                            class="px-2.5 py-1 text-xs font-bold rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition cursor-pointer flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>Add Client</span>
                        </button>
                    </div>

                    @foreach($multiClientConfigs as $index => $cfg)
                        @php
                            $clientId = $cfg['client_id'] ?? null;
                            $projectId = $cfg['project_id'] ?? null;
                            $rate = (float)($cfg['rate'] ?? 0);
                            $subtotal = $selectedCount * $rate;
                            $clientProjects = $clientId && isset($clientProjectOptions[$clientId]) ? $clientProjectOptions[$clientId] : collect();
                            $projectCampaigns = $projectId && isset($projectCampaignOptions[$projectId]) ? $projectCampaignOptions[$projectId] : collect();
                        @endphp
                        <div class="rounded-xl border border-border bg-canvas p-3.5 space-y-3 transition hover:border-border/80">
                            <div class="flex items-center justify-between pb-2 border-b border-border/60">
                                <span class="text-xs font-bold text-ink flex items-center gap-1.5">
                                    <span class="w-5 h-5 rounded-full bg-primary/10 text-primary text-[10px] font-bold flex items-center justify-center">
                                        {{ $loop->iteration }}
                                    </span>
                                    <span>Client #{{ $loop->iteration }}</span>
                                </span>
                                @if(count($multiClientConfigs) > 1)
                                    <button type="button" wire:click="removeMultiClientRow({{ $index }})"
                                        class="text-xs font-medium text-rose-600 hover:text-rose-700 flex items-center gap-1 cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        <span>Remove</span>
                                    </button>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2.5 text-xs">
                                <div>
                                    <label class="block text-[11px] font-semibold text-muted mb-1">Destination Client <span class="text-rose-500">*</span></label>
                                    <x-ui.themed-select wire:model.live="multiClientConfigs.{{ $index }}.client_id"
                                        :options="$allRecipientClients->pluck('name','id')->all()"
                                        placeholder="Choose Client"
                                        :searchable="true"
                                        class="w-full text-xs" />
                                    @error('multiClientConfigs.'.$index.'.client_id')
                                        <p class="text-[10px] text-rose-600 mt-0.5">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-muted mb-1">CRM Project <span class="text-rose-500">*</span></label>
                                    <x-ui.themed-select wire:model.live="multiClientConfigs.{{ $index }}.project_id"
                                        :options="$clientProjects->pluck('name','id')->all()"
                                        placeholder="Choose Project"
                                        :searchable="true"
                                        class="w-full text-xs" />
                                    @error('multiClientConfigs.'.$index.'.project_id')
                                        <p class="text-[10px] text-rose-600 mt-0.5">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-muted mb-1">Campaign (Optional)</label>
                                    <x-ui.themed-select wire:model.live="multiClientConfigs.{{ $index }}.campaign_id"
                                        :options="['' => 'Project Default'] + $projectCampaigns->pluck('name','id')->all()"
                                        placeholder="Campaign"
                                        :searchable="true"
                                        class="w-full text-xs" />
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-muted mb-1">Rate / Lead (₹) <span class="text-rose-500">*</span></label>
                                    <x-text-input variant="compact" type="number" min="0" step="1"
                                        wire:model.live="multiClientConfigs.{{ $index }}.rate"
                                        placeholder="₹100"
                                        class="w-full text-xs font-semibold" />
                                    @error('multiClientConfigs.'.$index.'.rate')
                                        <p class="text-[10px] text-rose-600 mt-0.5">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Subtotal for this client -->
                            <div class="flex items-center justify-between pt-1.5 border-t border-border/40 text-[11px]">
                                <span class="text-muted">Client Billing Subtotal:</span>
                                <span class="font-bold text-ink">
                                    {{ $selectedCount }} leads × ₹{{ number_format($rate, 2) }} =
                                    <span class="text-primary font-mono text-xs">₹{{ number_format($subtotal, 2) }}</span>
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Per-Client Billing Summary Box -->
                <div class="bg-surface rounded-xl border border-border p-3.5 space-y-2 text-xs">
                    <h4 class="font-bold text-ink text-xs uppercase tracking-wider">Billing Calculation Summary</h4>
                    <div class="divide-y divide-border/60">
                        @foreach($multiClientConfigs as $index => $cfg)
                            @php
                                $cname = $allRecipientClients->firstWhere('id', $cfg['client_id'] ?? 0)?->name ?? 'Client #'.($index+1);
                                $rate = (float)($cfg['rate'] ?? 0);
                                $sub = $selectedCount * $rate;
                            @endphp
                            <div class="py-1.5 flex items-center justify-between text-[11px]">
                                <span class="text-ink font-medium">{{ $cname }}</span>
                                <span class="text-muted">
                                    {{ $selectedCount }} leads @ ₹{{ number_format($rate, 2) }} =
                                    <strong class="text-ink font-mono font-semibold">₹{{ number_format($sub, 2) }}</strong>
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="pt-2 border-t border-border flex items-center justify-between text-xs font-bold">
                        <span class="text-ink">Total Expected Deductions ({{ $totalClients }} client{{ $totalClients > 1 ? 's' : '' }}):</span>
                        <span class="text-sm font-mono text-primary font-black">₹{{ number_format($totalAmount, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-2.5 pt-3.5 border-t border-border shrink-0">
                <button type="button" wire:click="closeMultiClientDeliveryModal"
                    class="px-3.5 py-2 text-xs font-semibold rounded-xl border border-border text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                    Cancel
                </button>
                <button type="button" wire:click="confirmMultiClientDelivery" wire:loading.attr="disabled"
                    class="px-4 py-2 text-xs font-bold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition cursor-pointer shadow-sm flex items-center gap-2">
                    <span wire:loading.remove wire:target="confirmMultiClientDelivery">
                        Confirm Delivery (₹{{ number_format($totalAmount, 2) }})
                    </span>
                    <span wire:loading wire:target="confirmMultiClientDelivery" class="inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Processing Delivery...
                    </span>
                </button>
            </div>
        </div>
    </div>
@endif
