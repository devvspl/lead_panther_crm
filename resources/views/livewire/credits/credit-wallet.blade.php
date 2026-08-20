<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Credits & Wallet</h1>
            <p class="text-xs text-muted">Manage your lead ingestion credit balance and view transaction history.</p>
        </div>
    </div>



    <!-- Big Balance Card -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 bg-accent text-white rounded-card p-6 shadow-md flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Current Credit Balance</span>
                <span class="px-2.5 py-1 text-[10px] font-bold rounded-pill bg-white/10 text-white">Live Wallet</span>
            </div>
            <div>
                <div class="text-4xl font-black tracking-tight">
                    {{ number_format($wallet->balance ?? 0) }} <span class="text-lg font-normal text-gray-300">Credits</span>
                </div>
                <p class="text-xs text-gray-400 mt-1">
                    Last recharged: {{ $lastRecharge ? \Carbon\Carbon::parse($lastRecharge->created_at)->format('M d, Y') : 'No recent recharge' }}
                </p>
            </div>
            <div class="pt-2">
                <x-ui.button wire:click="openRechargeModal" variant="secondary" class="bg-white text-ink hover:bg-gray-100 text-xs font-bold">
                    + Recharge Credits
                </x-ui.button>
            </div>
        </div>

        <!-- Wallet Info Card -->
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm flex flex-col justify-between space-y-3">
            <h3 class="text-xs font-bold uppercase text-muted tracking-wider">Client Billing Details</h3>
            <div class="space-y-2">
                <div class="text-sm font-bold text-ink">{{ $client->name ?? 'Organization Client' }}</div>
                <div class="text-xs text-muted">{{ $client->billing_email ?? 'billing@client.com' }}</div>
                <div class="text-xs text-muted">Cost per lead: <span class="font-bold text-ink">10 Credits</span></div>
            </div>
            <div class="text-[11px] text-muted border-t border-border pt-3">
                Need custom enterprise invoicing? Contact your Account Manager.
            </div>
        </div>
    </div>

    <!-- Recent Recharge Requests Status Panel -->
    @if($rechargeRequests->isNotEmpty())
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-3">
            <h3 class="text-xs font-bold uppercase text-muted tracking-wider">Recent Recharge Requests</h3>
            <div class="space-y-2">
                @foreach($rechargeRequests as $req)
                    <div class="flex items-center justify-between p-3 bg-canvas rounded border border-border text-xs">
                        <div>
                            <span class="font-bold text-ink">{{ $req->package->name ?? 'Package' }}</span>
                            <span class="text-muted text-[11px] ml-2">₹{{ number_format($req->amount, 2) }}</span>
                        </div>
                        <div>
                            @if($req->status === 'pending')
                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-pill bg-amber-50 text-amber-700 border border-amber-200">Pending Approval</span>
                            @elseif($req->status === 'approved')
                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-pill bg-green-50 text-green-700 border border-green-200">Approved on {{ \Carbon\Carbon::parse($req->approved_at)->format('M d, Y') }}</span>
                            @else
                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-pill bg-red-50 text-red-700 border border-red-200">Rejected: {{ $req->rejection_reason }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Recharge Packages Modal -->
    @if($showRechargeModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-surface rounded-card border border-border p-6 max-w-xl w-full shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-bold text-ink">Select Credit Recharge Package</h3>
                    <button wire:click="closeRechargeModal" class="text-muted hover:text-ink">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-96 overflow-y-auto p-1">
                    @foreach($packages as $pkg)
                        <div 
                            wire:click="$set('selectedPackageId', {{ $pkg->id }})"
                            class="p-4 rounded-xl border transition cursor-pointer flex flex-col justify-between space-y-2 {{ $selectedPackageId === $pkg->id ? 'border-ink bg-canvas ring-2 ring-ink' : 'border-border bg-surface hover:border-gray-400' }}"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-ink">{{ $pkg->name }}</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-pill bg-ink text-white">{{ $pkg->credit_count }} Credits</span>
                            </div>
                            <div class="text-lg font-black text-ink">₹{{ number_format($pkg->price, 2) }}</div>
                            <div class="text-[10px] text-muted">Valid for {{ $pkg->validity_days }} days</div>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-border">
                    <x-ui.button wire:click="closeRechargeModal" variant="secondary" class="text-xs">Cancel</x-ui.button>
                    <x-ui.button wire:click="submitRechargeRequest" variant="primary" class="text-xs">Submit Recharge Request</x-ui.button>
                </div>
            </div>
        </div>
    @endif

    <!-- Transactions Table Component -->
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Transaction Ledger</h2>
        </div>

        <x-ui.advanced-table 
            :columns="$this->tableColumns()"
            :rows="$transactions"
            :visibleColumns="$visibleColumns"
            :sortField="$sortField"
            :sortDirection="$sortDirection"
            searchPlaceholder="Search lead code, transaction type..."
            emptyTitle="No Credit Transactions Found"
            emptyMessage="No credit transaction ledger records match your current filters."
        >
            <!-- Custom Filters Slot -->
            <x-slot:filters>
                <div class="space-y-3">
                    <div>
                        <label class="text-[11px] font-bold text-ink block mb-1">Transaction Type</label>
                        <x-ui.themed-select 
                            wire:model.live="filterType"
                            :options="['' => 'All Transaction Types', 'reserve' => 'Reserve', 'deduct' => 'Deduct', 'refund' => 'Refund', 'recharge' => 'Recharge']"
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

            <!-- Custom Action Slot -->
            <x-slot:action>
                <x-ui.export-button target="exportExcel" class="text-xs" />
            </x-slot:action>
        </x-ui.advanced-table>
    </div>
</div>
