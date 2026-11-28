<div class="space-y-6">
    @if($wallet && !$wallet->reconciled_at)
        <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 text-xs text-amber-900 flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span>Lead credits await Super Admin reconciliation. Existing transaction history is preserved.</span>
        </div>
    @endif

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-200/60 text-indigo-600 flex items-center justify-center shrink-0 shadow-2xs">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-3.866 0-7-1.343-7-3s3.134-3 7-3 7 1.343 7 3-3.134 3-7 3z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5v6c0 1.657 3.134 3 7 3s7-1.343 7-3V5" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11v6c0 1.657 3.134 3 7 3s7-1.343 7-3v-6" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink">Lead Credits</h1>
                <p class="text-xs text-muted mt-0.5">Manage and track your lead delivery credits.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" wire:click="openRechargeModal"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-xs transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Request Credits</span>
            </button>
        </div>
    </div>

    <!-- Credit Metric Cards (Clean 3-Card Strip matching screenshot) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- Card 1: Available Credits (Gradient Card with 3D Stacked Coins) -->
        <div class="bg-gradient-to-br from-indigo-600 via-indigo-600 to-indigo-700 text-white rounded-2xl p-5 shadow-xs relative overflow-hidden flex flex-col justify-between min-h-[140px]">
            <div>
                <div class="text-xs tracking-wide">
                    <span class="font-bold text-white">Available</span> <span class="font-normal text-indigo-100">Credits</span>
                </div>
                <span class="sr-only">Available Lead Credits</span>
            </div>
            
            <div class="flex items-end justify-between mt-3">
                <div class="text-4xl sm:text-5xl font-black text-white tracking-tight font-mono">
                    {{ number_format($wallet->balance ?? 0) }}
                </div>
                <!-- 3D Stacked Coins Illustration on Right -->
                <div class="opacity-25 pointer-events-none -mr-1 -mb-1 text-white">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-3.866 0-7-1.343-7-3s3.134-3 7-3 7 1.343 7 3-3.134 3-7 3z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 5v6c0 1.657 3.134 3 7 3s7-1.343 7-3V5" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 11v6c0 1.657 3.134 3 7 3s7-1.343 7-3v-6" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Card 2: Total Credits (Baseline) -->
        <div class="bg-surface rounded-2xl border border-border p-5 shadow-2xs flex flex-col justify-between min-h-[140px]">
            <div class="flex items-center justify-between">
                <div class="text-xs text-ink">
                    <span class="font-bold">Total Credits</span> <span class="text-muted">(Baseline)</span>
                </div>
                <div class="w-7 h-7 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9" stroke-width="2" />
                        <circle cx="12" cy="12" r="5" stroke-width="2" />
                        <circle cx="12" cy="12" r="1.5" fill="currentColor" />
                    </svg>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-3xl sm:text-4xl font-extrabold text-ink tracking-tight font-mono">
                    {{ number_format($wallet->total_credits ?? 0) }}
                </div>
            </div>
        </div>

        <!-- Card 3: Billing & Rate -->
        <div class="bg-surface rounded-2xl border border-border p-5 shadow-2xs flex flex-col justify-between min-h-[140px]">
            <div class="flex items-center justify-between">
                <div class="text-xs font-bold text-ink">
                    Billing & Rate
                </div>
                <div class="w-7 h-7 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z" />
                    </svg>
                </div>
            </div>
            <div class="mt-2 space-y-1.5">
                <div class="text-sm font-bold text-ink truncate">
                    {{ $client->organization?->name ?? $client->name ?? 'Deltek' }}
                </div>
                <div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200/70">
                        {{ $pricingResolution['formatted'] ?? '1 Credit / Lead' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Credit Requests Compact Status Panel -->
    @if($rechargeRequests->isNotEmpty())
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-2xs space-y-2.5 text-xs">
            <div class="flex items-center justify-between border-b border-border pb-2">
                <span class="font-bold text-ink text-xs uppercase tracking-wider">Recent Credit Requests</span>
                <span class="text-muted text-[11px] font-medium">{{ $rechargeRequests->count() }} request(s)</span>
            </div>
            <div class="space-y-1.5">
                @foreach($rechargeRequests as $req)
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between py-2 px-3 bg-canvas/60 rounded-xl border border-border/50 gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold font-mono text-ink">{{ number_format($req->amount ?? 0) }} Credits</span>
                            @if($req->package_name && $req->package_name !== 'Custom Request')
                                <span class="text-muted text-[11px]">({{ $req->package_name }})</span>
                            @endif
                            <span class="text-muted text-[10px] font-mono">{{ $req->created_at?->format('M d, Y H:i') }}</span>
                        </div>
                        <div>
                            @if($req->status === 'pending')
                                <span class="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-amber-50 text-amber-700 border border-amber-200">Pending Approval</span>
                            @elseif($req->status === 'approved')
                                <span class="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">Approved on {{ \Carbon\Carbon::parse($req->approved_at)->format('M d, Y') }}</span>
                            @else
                                <span class="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-full bg-rose-50 text-rose-700 border border-rose-200">Rejected: {{ $req->rejection_reason }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Credit Ledger Advanced Table (Clean, compact, and responsive) -->
    <div>
        <x-ui.advanced-table 
            tableTitle="Credit Ledger"
            :columns="$this->tableColumns()"
            :rows="$transactions"
            :visibleColumns="$visibleColumns"
            :sortField="$sortField"
            :sortDirection="$sortDirection"
            searchPlaceholder="Search by lead code or type..."
            emptyTitle="No Credit Transactions Found"
            emptyMessage="No credit transaction ledger records match your current filters."
        >
            <!-- Custom Filters Popover Slot -->
            <x-slot:filters>
                <div class="space-y-3">
                    <div>
                        <label class="text-[11px] font-bold text-ink block mb-1">Transaction Type</label>
                        <x-ui.themed-select 
                            wire:model.live="filterType"
                            :options="['' => 'All Transaction Types', 'deduct' => 'Lead Consumption (Deduct)', 'reserve' => 'Reserve', 'refund' => 'Refund', 'recharge' => 'Recharge']"
                            placeholder="All Transaction Types"
                            searchable="true"
                        />
                    </div>

                    <div>
                        <label class="text-[11px] font-bold text-ink block mb-1">Date Range</label>
                        <x-ui.date-range-picker 
                            wire:model.live="filterDateRange"
                            wire:custom-from="customFrom"
                            wire:custom-to="customTo"
                            placeholder="All Time"
                        />
                    </div>
                </div>
            </x-slot:filters>

            <!-- Action Slot: Export to Excel -->
            <x-slot:action>
                <x-ui.export-button target="exportExcel" label="Export" class="text-xs" />
            </x-slot:action>
        </x-ui.advanced-table>
    </div>

    <!-- Request Lead Credits Modal -->
    @if($showRechargeModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 overflow-y-auto overscroll-contain animate-fade-in"
             wire:keydown.escape="closeRechargeModal">
            <div class="bg-surface rounded-2xl border border-border p-6 max-w-lg w-full shadow-2xl space-y-5 max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain"
                 @click.outside="$wire.closeRechargeModal()">
                
                <div class="flex items-center justify-between border-b border-border pb-3.5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-ink">Request Lead Credits</h3>
                            <p class="text-xs text-muted">Submit a lead credit request to Super Admin for approval.</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeRechargeModal" class="text-muted hover:text-ink p-1 rounded-lg hover:bg-canvas transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-4 text-xs">
                    <!-- Current Balances Glance -->
                    <div class="grid grid-cols-2 gap-3 p-3 bg-canvas rounded-xl border border-border">
                        <div>
                            <span class="text-[10px] font-bold text-muted uppercase">Available Balance</span>
                            <div class="text-base font-extrabold font-mono text-ink">{{ number_format($wallet->balance ?? 0) }} Credits</div>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-muted uppercase">Total Credits</span>
                            <div class="text-base font-extrabold font-mono text-indigo-600">{{ number_format($wallet->total_credits ?? 0) }} Credits</div>
                        </div>
                    </div>

                    <!-- Custom Credit Quantity Input -->
                    <div class="space-y-1.5">
                        <label class="block font-semibold text-ink">Credit Quantity *</label>
                        <input 
                            type="number" 
                            min="1" 
                            wire:model.live="customCredits" 
                            placeholder="e.g. 500 or 1000" 
                            class="w-full text-sm font-mono rounded-xl border border-border p-2.5 bg-canvas text-ink focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                        />
                        <p class="text-[11px] text-muted">Specify the exact number of leads / delivery credits you wish to add.</p>
                    </div>

                    <!-- Packages (if configured) -->
                    @if($packages->isNotEmpty())
                        <div class="space-y-2">
                            <label class="block font-semibold text-ink">Or Select a Standard Package</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-48 overflow-y-auto pr-1">
                                @foreach($packages as $pkg)
                                    <div 
                                        wire:click="$set('selectedPackageId', {{ $pkg->id }}); $set('customCredits', {{ $pkg->credit_count }})"
                                        class="p-3 rounded-xl border transition cursor-pointer flex flex-col justify-between space-y-1.5 {{ (int)$customCredits === (int)$pkg->credit_count ? 'border-indigo-600 bg-indigo-50/40 ring-1 ring-indigo-600' : 'border-border bg-surface hover:border-gray-400' }}"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-ink">{{ $pkg->name }}</span>
                                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-800">{{ $pkg->credit_count }}</span>
                                        </div>
                                        <div class="text-xs font-mono font-bold text-ink">{{ number_format($pkg->credit_count) }} Credits</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Reference Notes -->
                    <div class="space-y-1.5">
                        <label class="block font-semibold text-ink">Request Note (Optional)</label>
                        <textarea 
                            wire:model="rechargeNotes"
                            rows="2"
                            placeholder="e.g. Campaign scaling top-up, Bank transfer ref..."
                            class="w-full text-xs rounded-xl border border-border p-2.5 bg-canvas text-ink focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600"
                        ></textarea>
                    </div>

                    <!-- Past Requests Status List inside modal for history -->
                    @if($rechargeRequests->isNotEmpty())
                        <details class="rounded-xl border border-border bg-canvas/60 p-2.5 text-xs">
                            <summary class="font-semibold text-ink cursor-pointer select-none">
                                Request History ({{ $rechargeRequests->count() }} past requests)
                            </summary>
                            <div class="mt-2 space-y-1.5 pt-2 border-t border-border">
                                @foreach($rechargeRequests as $req)
                                    <div class="flex items-center justify-between py-1 border-b border-border/50 last:border-0 text-[11px]">
                                        <div>
                                            <strong class="font-mono">{{ number_format($req->amount ?? 0) }} Credits</strong>
                                            <span class="text-muted ml-1">{{ $req->created_at?->format('M d, Y') }}</span>
                                        </div>
                                        <div>
                                            @if($req->status === 'pending')
                                                <span class="px-2 py-0.5 text-[9px] font-bold uppercase rounded-full bg-amber-50 text-amber-700 border border-amber-200">Pending Approval</span>
                                            @elseif($req->status === 'approved')
                                                <span class="px-2 py-0.5 text-[9px] font-bold uppercase rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">Approved on {{ \Carbon\Carbon::parse($req->approved_at)->format('M d, Y') }}</span>
                                            @else
                                                <span class="px-2 py-0.5 text-[9px] font-bold uppercase rounded-full bg-rose-50 text-rose-700 border border-rose-200">Rejected: {{ $req->rejection_reason }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>

                <div class="flex items-center justify-end space-x-2.5 pt-3.5 border-t border-border">
                    <button type="button" wire:click="closeRechargeModal" class="px-4 py-2 rounded-xl border border-border hover:bg-canvas text-ink text-xs font-semibold transition cursor-pointer">Cancel</button>
                    <button type="button" wire:click="submitRechargeRequest" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition shadow-xs cursor-pointer">Submit Credit Request</button>
                </div>
            </div>
        </div>
    @endif
</div>
