<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1">
                <span>Billing &amp; Credits</span>
                <span>/</span>
                <span class="text-ink font-semibold">Credit Ledger</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                <svg class="w-6 h-6 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Super Admin Credit Oversight</span>
            </h1>
            <p class="text-xs text-muted">Global client credit audit log, reservations, refunds, and manual balance overrides.</p>
        </div>
    </div>

    <!-- Global Per-Lead Pricing & System Credit Balance Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Card 1: Global Default Lead Cost Setting Control -->
        <div class="lg:col-span-2 p-5 rounded-2xl bg-surface border border-purple-200 dark:border-purple-900/40 shadow-2xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-border">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-purple-100 text-purple-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-ink">Global Default Per-Lead Cost Setting</h2>
                        <p class="text-[11px] text-muted">Baseline cost deducted per ingested lead when no project, team, or organization override exists.</p>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-950 border border-purple-300 w-fit">
                    Active: {{ number_format($globalDefaultLeadCost, 2) }} Credits
                </span>
            </div>

            <!-- Hierarchy Cascade Visual -->
            <div class="p-3 bg-canvas/60 rounded-xl border border-border text-xs space-y-1.5">
                <div class="text-[10px] font-bold uppercase tracking-wider text-muted">4-Tier Pricing Resolution Priority:</div>
                <div class="flex flex-wrap items-center gap-2 text-[11px]">
                    <span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-950 font-bold border border-amber-300">1. Project Override</span>
                    <span class="text-muted">→</span>
                    <span class="px-2 py-0.5 rounded-md bg-blue-100 text-blue-950 font-bold border border-blue-300">2. Team Override</span>
                    <span class="text-muted">→</span>
                    <span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-950 font-bold border border-emerald-300">3. Organization Override</span>
                    <span class="text-muted">→</span>
                    <span class="px-2 py-0.5 rounded-md bg-purple-100 text-purple-950 font-bold border border-purple-300">4. Global Default Setting</span>
                </div>
            </div>

            <form wire:submit.prevent="updateGlobalDefaultCost" class="flex flex-col sm:flex-row items-end gap-3 text-xs pt-1">
                <div class="flex-1 w-full">
                    <label class="font-bold text-ink block mb-1">Global Default Per-Lead Cost (Credits) <span class="text-red-500">*</span></label>
                    <input 
                        type="number" 
                        step="0.01" 
                        min="0.01" 
                        wire:model="globalDefaultLeadCost" 
                        class="w-full h-9 px-3 rounded-lg border border-border bg-canvas text-ink font-mono text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition"
                        placeholder="10.00"
                    >
                    @error('globalDefaultLeadCost') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>
                <button 
                    type="submit" 
                    class="h-9 px-4 bg-ink hover:bg-neutral-800 text-white font-bold rounded-lg transition shadow-xs cursor-pointer shrink-0"
                >
                    Update Global Default
                </button>
            </form>
        </div>

        <!-- Card 2: System-wide Balances Strip -->
        <div class="p-5 rounded-2xl bg-surface border border-border shadow-2xs space-y-3.5 flex flex-col justify-between">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-muted">Platform Credit Summary</h3>
                <div class="mt-3 space-y-3">
                    <div class="p-3 rounded-xl bg-canvas/50 border border-border flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold uppercase text-muted">System Opening Balance</span>
                            <div class="text-lg font-bold font-mono text-ink">{{ number_format($totalSystemOpeningBalance, 2) }} Cr</div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-sky-100 text-sky-950 border border-sky-300">Opening</span>
                    </div>

                    <div class="p-3 rounded-xl bg-canvas/50 border border-border flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold uppercase text-muted">System Available Balance</span>
                            <div class="text-lg font-bold font-mono text-emerald-600">{{ number_format($totalSystemAvailableBalance, 2) }} Cr</div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-100 text-emerald-950 border border-emerald-300">Available</span>
                    </div>
                </div>
            </div>

            <div class="text-[10px] text-muted flex items-center justify-between border-t border-border pt-2.5">
                <span>Total Active Wallets: <strong class="text-ink font-mono">{{ $clients->count() }}</strong></span>
                <span class="text-primary font-semibold">Real-Time Ledger</span>
            </div>
        </div>
    </div>

    <!-- Advanced Table Component -->
    <x-ui.advanced-table 
        :columns="$this->tableColumns()"
        :rows="$transactions"
        :quickFilters="$this->quickFilters()"
        :activeStatus="$statusFilter"
        :visibleColumns="$visibleColumns"
        :sortField="$sortField"
        :sortDirection="$sortDirection"
        :filterCount="$this->activeFilterCount"
        searchPlaceholder="Search client name, transaction type, or lead code..."
        emptyTitle="No Credit Transactions Found"
        emptyMessage="No transaction logs match your current search and filters."
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
            </div>
        </x-slot:filters>

        <!-- Primary Action Slot -->
        <x-slot:action>
            <div class="flex items-center gap-2">
                <x-ui.export-button target="exportExcel" class="text-xs" />
                <button 
                    type="button" 
                    wire:click="openAdjustModal"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition shadow-xs cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    <span>Manual Adjustment</span>
                </button>
            </div>
        </x-slot:action>
    </x-ui.advanced-table>

    <!-- Manual Adjustment Modal -->
    @if($showAdjustModal)
        <div class="fixed inset-0 bg-black/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-surface rounded-card border border-border p-6 max-w-lg w-full shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-bold text-ink">Manual Credit Adjustment</h3>
                    <button type="button" wire:click="closeAdjustModal" class="text-muted hover:text-ink cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-bold text-ink">Select Client</label>
                        <select wire:model="selectedClientId" class="w-full h-9 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1">
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-ink">Adjustment Type</label>
                            <x-ui.themed-select 
                                wire:model="adjustmentType"
                                :options="['credit' => 'Add Credits (Credit)', 'debit' => 'Deduct Credits (Debit)']"
                                placeholder="Adjustment Type"
                            />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-ink">Credits Amount</label>
                            <input type="number" wire:model="adjustmentAmount" class="w-full text-xs h-9 px-3 rounded-lg border border-border bg-canvas text-ink mt-1">
                            @error('adjustmentAmount') <span class="text-[10px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-ink">Reason for Adjustment <span class="text-danger">*</span></label>
                        <textarea wire:model="reason" placeholder="State reason (required for audit logs)..." class="w-full text-xs p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1 h-20"></textarea>
                        @error('reason') <span class="text-[10px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-border">
                    <x-ui.button wire:click="closeAdjustModal" variant="secondary" class="text-xs">Cancel</x-ui.button>
                    <x-ui.button wire:click="executeAdjustment" variant="primary" class="text-xs">Execute &amp; Log Audit</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
