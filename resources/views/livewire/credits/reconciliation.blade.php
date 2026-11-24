<div class="inline-block">
    <!-- Reconcile / Manage Credits Clean Button -->
    <button type="button" wire:click="openModal"
        class="px-3.5 py-2 border border-border bg-surface hover:bg-canvas text-ink text-xs font-semibold rounded-lg transition shadow-2xs hover:border-primary/40 inline-flex items-center gap-1.5 cursor-pointer">
        <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        <span>Reconcile / Manage Credits</span>
    </button>

    <!-- Centered Reconcile / Manage Credits Modal -->
    <x-ui.modal name="reconcile-manage-credits-modal" :show="$showModal" maxWidth="2xl" :centered="true">
        <div class="p-5 sm:p-6 space-y-5">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3.5 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-primary/10 text-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Reconcile / Manage Credits</h3>
                        <p class="text-xs text-muted">Verify usable balances, record legacy consumption, or raise disputes.</p>
                    </div>
                </div>
                <button type="button" wire:click="closeModal" x-on:click="$dispatch('close-modal', 'reconcile-manage-credits-modal')"
                    class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Client Selection & Status Card -->
            <div class="space-y-3">
                @if(count($clientOptions) > 2)
                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">Select Billing Client</label>
                        <x-ui.themed-select wire:model.live="clientId" :options="$clientOptions" />
                    </div>
                @endif

                @if($clientId)
                    <div class="p-3.5 rounded-xl bg-canvas border border-border space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                            <span class="text-muted">Client Default: <strong class="text-ink">{{ $pricing }}</strong> per delivered lead. Project &amp; team overrides still apply.</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $wallet && !$wallet->reconciled_at ? 'bg-amber-100 text-amber-900 border-amber-300 dark:bg-amber-950/40 dark:text-amber-300' : 'bg-emerald-100 text-emerald-900 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300' }}">
                                {{ $wallet && !$wallet->reconciled_at ? 'Needs reconciliation · delivery is held' : 'Verified credits' }}
                            </span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-xs text-muted">Remaining Balance:</span>
                            <span class="text-lg font-bold font-mono text-ink">{{ number_format($wallet?->balance ?? 0) }} Credits</span>
                        </div>
                        @if($wallet?->getRawOriginal('legacy_balance') !== null)
                            <p class="text-[11px] text-muted border-t border-border/60 pt-1.5">
                                Preserved legacy reference: <strong>{{ $wallet->getRawOriginal('legacy_balance') }}</strong>. This is not spendable lead credit.
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Three Clean Navigation Tabs -->
            <div class="flex items-center gap-1.5 p-1 bg-canvas rounded-xl border border-border">
                <button type="button" wire:click="setSection('reconcile')"
                    class="flex-1 py-2 px-3 rounded-lg text-xs font-semibold transition cursor-pointer text-center {{ $activeSection === 'reconcile' ? 'bg-surface text-ink font-bold shadow-2xs border border-border/80' : 'text-muted hover:text-ink' }}">
                    Reconcile Credits
                </button>
                <button type="button" wire:click="setSection('consumed')"
                    class="flex-1 py-2 px-3 rounded-lg text-xs font-semibold transition cursor-pointer text-center {{ $activeSection === 'consumed' ? 'bg-surface text-ink font-bold shadow-2xs border border-border/80' : 'text-muted hover:text-ink' }}">
                    Mark as Consumed
                </button>
                <button type="button" wire:click="setSection('dispute')"
                    class="flex-1 py-2 px-3 rounded-lg text-xs font-semibold transition cursor-pointer text-center {{ $activeSection === 'dispute' ? 'bg-surface text-ink font-bold shadow-2xs border border-border/80' : 'text-muted hover:text-ink' }}">
                    Raise Dispute
                </button>
            </div>

            <!-- Flash/Status Message -->
            @if($message)
                <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-700 dark:text-emerald-300 font-semibold flex items-center justify-between" role="status">
                    <span>{{ $message }}</span>
                    <button type="button" wire:click="$set('message', '')" class="text-emerald-700 hover:opacity-80">&times;</button>
                </div>
            @endif

            @if($clientId)
                <!-- TAB 1: RECONCILE CREDITS -->
                @if($activeSection === 'reconcile')
                    <form wire:submit="reconcile" class="space-y-4">
                        <div class="text-xs text-muted">
                            Verify and correct usable credit balances. Updates the spendable allocation and marks the wallet verified for lead delivery.
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div>
                                <label class="block text-xs font-semibold text-ink mb-1">Verified remaining lead credits</label>
                                <input type="number" min="0" step="1" wire:model="remaining"
                                    class="w-full rounded-lg border border-border bg-canvas text-ink text-sm p-2 focus:ring-1 focus:ring-primary focus:border-primary" />
                                @error('remaining') <p class="text-xs text-danger text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-ink mb-1">Reconciliation reason</label>
                                <input wire:model="reason"
                                    class="w-full rounded-lg border border-border bg-canvas text-ink text-sm p-2 focus:ring-1 focus:ring-primary focus:border-primary"
                                    placeholder="Explain the correction" />
                                @error('reason') <p class="text-xs text-danger text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <label class="flex items-start gap-2 text-xs text-ink cursor-pointer pt-1">
                            <input type="checkbox" wire:model="confirmed" class="rounded border-border text-primary focus:ring-primary mt-0.5" />
                            <span>I confirm this is the verified usable quantity.</span>
                        </label>
                        @error('confirmed') <p class="text-xs text-danger text-red-600">{{ $message }}</p> @enderror

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                            <button type="button" wire:click="closeModal" x-on:click="$dispatch('close-modal', 'reconcile-manage-credits-modal')"
                                class="px-3.5 py-2 rounded-lg border border-border text-xs font-semibold text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                                Cancel
                            </button>
                            <x-ui.button type="submit">
                                Confirm Reconciliation
                            </x-ui.button>
                        </div>
                    </form>
                @endif

                <!-- TAB 2: MARK AS CONSUMED -->
                @if($activeSection === 'consumed')
                    <form wire:submit="markLegacyConsumption" class="space-y-4">
                        <div class="p-3 rounded-xl bg-canvas border border-border text-xs text-muted space-y-1">
                            <strong class="text-ink">Mark Legacy Delivery as Consumption</strong>
                            <p>Reconcile the available allocation first, then record credits used by an old delivered lead. This deducts the entered quantity once; it does not resend the lead or convert legacy money.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-ink mb-1">Legacy ledger entry</label>
                                <x-ui.themed-select wire:model="transactionId" :options="$transactionOptions" />
                                @error('transactionId') <p class="text-xs text-danger text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-ink mb-1">Credits consumed</label>
                                <input type="number" min="1" step="1" wire:model="legacyQuantity"
                                    class="w-full rounded-lg border border-border bg-canvas text-ink text-sm p-2 focus:ring-1 focus:ring-primary focus:border-primary" />
                                @error('legacyQuantity') <p class="text-xs text-danger text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-ink mb-1">Reason</label>
                                <input wire:model="legacyReason"
                                    class="w-full rounded-lg border border-border bg-canvas text-ink text-sm p-2 focus:ring-1 focus:ring-primary focus:border-primary"
                                    placeholder="Reason for legacy deduction" />
                                @error('legacyReason') <p class="text-xs text-danger text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <label class="flex items-start gap-2 text-xs text-ink cursor-pointer pt-1">
                            <input type="checkbox" wire:model="legacyConfirmed" class="rounded border-border text-primary focus:ring-primary mt-0.5" />
                            <span>I confirm this quantity should be deducted.</span>
                        </label>
                        @error('legacyConfirmed') <p class="text-xs text-danger text-red-600">{{ $message }}</p> @enderror

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                            <button type="button" wire:click="closeModal" x-on:click="$dispatch('close-modal', 'reconcile-manage-credits-modal')"
                                class="px-3.5 py-2 rounded-lg border border-border text-xs font-semibold text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                                Cancel
                            </button>
                            <x-ui.button type="submit">
                                Mark as Consumption
                            </x-ui.button>
                        </div>
                    </form>
                @endif

                <!-- TAB 3: RAISE DISPUTE -->
                @if($activeSection === 'dispute')
                    <div class="space-y-4">
                        <form wire:submit="openDispute" class="space-y-4">
                            <div class="text-xs text-muted">
                                Select a ledger entry and record a dispute reason. Disputes can be reviewed and resolved without altering existing transaction history.
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1">Ledger entry to dispute</label>
                                    <x-ui.themed-select wire:model="transactionId" :options="$transactionOptions" />
                                    @error('transactionId') <p class="text-xs text-danger text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1">Dispute reason</label>
                                    <input wire:model="disputeReason"
                                        class="w-full rounded-lg border border-border bg-canvas text-ink text-sm p-2 focus:ring-1 focus:ring-primary focus:border-primary"
                                        placeholder="Reason for dispute" />
                                    @error('disputeReason') <p class="text-xs text-danger text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                                <button type="button" wire:click="closeModal" x-on:click="$dispatch('close-modal', 'reconcile-manage-credits-modal')"
                                    class="px-3.5 py-2 rounded-lg border border-border text-xs font-semibold text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                                    Cancel
                                </button>
                                <x-ui.button type="submit" variant="secondary">
                                    Record Dispute
                                </x-ui.button>
                            </div>
                        </form>

                        @if($disputes->isNotEmpty())
                            <div class="pt-3 border-t border-border space-y-2">
                                <h4 class="text-xs font-bold text-ink uppercase tracking-wider">Existing Disputes</h4>
                                <div class="overflow-x-auto rounded-lg border border-border">
                                    <table class="w-full text-xs text-left">
                                        <thead class="bg-canvas text-muted text-[11px] font-semibold">
                                            <tr>
                                                <th class="p-2.5">Entry</th>
                                                <th class="p-2.5">Reason / Resolution</th>
                                                <th class="p-2.5">Status</th>
                                                <th class="p-2.5 text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border">
                                            @foreach($disputes as $dispute)
                                                <tr wire:key="credit-dispute-{{ $dispute->id }}">
                                                    <td class="p-2.5 font-mono text-ink">#{{ $dispute->credit_transaction_id }}</td>
                                                    <td class="p-2.5">
                                                        <span class="text-ink">{{ $dispute->reason }}</span>
                                                        @if($dispute->resolution)
                                                            <span class="block text-muted text-[10px]">{{ $dispute->resolution }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="p-2.5">
                                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $dispute->status === 'open' ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-emerald-100 text-emerald-900 border border-emerald-300' }}">
                                                            {{ ucfirst($dispute->status) }}
                                                        </span>
                                                    </td>
                                                    <td class="p-2.5 text-right">
                                                        @if($dispute->status === 'open')
                                                            <button type="button" wire:click="reviewDispute({{ $dispute->id }})"
                                                                class="text-primary hover:underline font-semibold text-xs cursor-pointer">
                                                                Resolve
                                                            </button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            @else
                <div class="p-6 text-center text-muted text-xs">
                    Please select a client to view credit reconciliation options.
                </div>
            @endif
        </div>
    </x-ui.modal>

    <!-- Sub-Modal: Resolve Credit Dispute -->
    <x-ui.modal name="resolve-credit-dispute" centered>
        <form wire:submit="resolve" class="p-5 space-y-4">
            <h3 class="font-bold text-sm text-ink">Resolve Credit Dispute</h3>
            <div>
                <label class="block text-xs font-semibold text-ink mb-1">Resolution</label>
                <textarea wire:model="resolution" class="w-full rounded-lg border-border bg-canvas text-ink text-xs p-2 focus:ring-1 focus:ring-primary focus:border-primary"></textarea>
                @error('resolution') <p class="text-xs text-danger text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <p class="text-xs text-muted">Optional correction: use 0 to resolve without changing credits. The original entry remains unchanged.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-ui.themed-select wire:model="correctionAction" :options="['recharge' => 'Add credits', 'deduct' => 'Deduct credits']" />
                <input aria-label="Credit correction quantity" type="number" step="1" min="0" wire:model="correction" class="w-full rounded-lg border border-border bg-canvas text-ink text-xs p-2 focus:ring-1 focus:ring-primary focus:border-primary" />
            </div>
            @error('quantity') <p class="text-xs text-danger text-red-600">{{ $message }}</p> @enderror
            <div class="flex justify-end gap-2 pt-2 border-t border-border">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal','resolve-credit-dispute')">Cancel</x-ui.button>
                <x-ui.button type="submit">Resolve</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
