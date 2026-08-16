<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Super Admin Credit Oversight</h1>
            <p class="text-xs text-muted">Global client credit audit log and manual balance overrides.</p>
        </div>
        <x-ui.button wire:click="openAdjustModal" variant="primary" class="text-xs">
            + Manual Balance Adjustment
        </x-ui.button>
    </div>



    <!-- Manual Adjustment Modal -->
    @if($showAdjustModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-surface rounded-card border border-border p-6 max-w-lg w-full shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-bold text-ink">Manual Credit Adjustment</h3>
                    <button wire:click="closeAdjustModal" class="text-muted hover:text-ink">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-bold text-ink">Select Client</label>
                        <livewire:shared.searchable-select 
                            :model="\App\Models\Client::class"
                            placeholder="-- Select Client --"
                            wire:model="selectedClientId"
                            key="admin-credit-client-sel"
                        />
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
                            <input type="number" wire:model="adjustmentAmount" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-1">
                            @error('adjustmentAmount') <span class="text-[10px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-ink">Reason for Adjustment <span class="text-danger">*</span></label>
                        <textarea wire:model="reason" placeholder="State reason (required for audit logs)..." class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-1 h-20"></textarea>
                        @error('reason') <span class="text-[10px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-border">
                    <x-ui.button wire:click="closeAdjustModal" variant="secondary" class="text-xs">Cancel</x-ui.button>
                    <x-ui.button wire:click="executeAdjustment" variant="primary" class="text-xs">Execute & Log Audit</x-ui.button>
                </div>
            </div>
        </div>
    @endif

    <!-- Global Transaction Log Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <h2 class="text-base font-bold text-ink">All Client Transactions</h2>

            <div class="flex items-center space-x-3">
                <livewire:shared.searchable-select 
                    :model="\App\Models\Client::class"
                    placeholder="All Clients"
                    wire:model.live="filterClient"
                    key="admin-credit-client-filter"
                />

                <x-ui.themed-select 
                    wire:model.live="filterType"
                    :options="['' => 'All Types', 'reserve' => 'Reserve', 'deduct' => 'Deduct', 'refund' => 'Refund', 'recharge' => 'Recharge']"
                    placeholder="All Types"
                    searchable="true"
                />

                <x-ui.export-button target="exportExcel" />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Date & Time</th>
                        <th class="py-3 px-4">Client</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Lead Ref</th>
                        <th class="py-3 px-4">Before</th>
                        <th class="py-3 px-4">Used / Added</th>
                        <th class="py-3 px-4">After</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($transactions as $tx)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 text-muted">{{ \Carbon\Carbon::parse($tx->created_at)->format('M d, Y H:i') }}</td>
                            <td class="py-3 px-4 font-bold text-ink">{{ $tx->client?->name ?: 'System Client' }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill {{ $tx->transaction_type === 'recharge' ? 'bg-green-50 text-green-700' : ($tx->transaction_type === 'reserve' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700') }}">
                                    {{ ucfirst($tx->transaction_type) }}
                                </span>
                            </td>
                            <td class="py-3 px-4">{{ $tx->lead?->lead_code ?: 'N/A' }}</td>
                            <td class="py-3 px-4 font-mono">{{ number_format($tx->credit_before) }}</td>
                            <td class="py-3 px-4 font-mono font-bold {{ $tx->transaction_type === 'recharge' || $tx->transaction_type === 'refund' ? 'text-success' : 'text-danger' }}">
                                {{ $tx->transaction_type === 'recharge' || $tx->transaction_type === 'refund' ? '+' : '-' }}{{ number_format($tx->credit_used) }}
                            </td>
                            <td class="py-3 px-4 font-mono font-bold">{{ number_format($tx->credit_after) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-muted">No transactions recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $transactions->links('vendor.pagination.tailwind') }}
        </div>
    </div>
</div>
