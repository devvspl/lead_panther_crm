<details class="rounded-card border border-border bg-surface p-4 space-y-4" wire:ignore.self open>
    <summary class="cursor-pointer text-sm font-bold">Reconcile Lead Credits &amp; Manage Disputes</summary>
    <p class="text-xs text-muted">Confirm usable credits, correct dummy entries and resolve disputes without deleting history.</p>
    <div class="max-w-md"><label class="text-xs font-semibold">Client</label><x-ui.themed-select wire:model.live="clientId" :options="$clientOptions" /></div>
    @if($message)<p class="text-xs text-success" role="status">{{ $message }}</p>@endif
    @if($clientId)
        <p class="text-xs text-muted">Client default: {{ $pricing }} per delivered lead. Project and team overrides still apply.</p>
        @if($wallet?->getRawOriginal('legacy_balance') !== null)
            <p class="text-xs text-muted">Preserved legacy reference: {{ $wallet->getRawOriginal('legacy_balance') }}. This is not spendable lead credit.</p>
        @endif
        <div class="flex flex-wrap gap-3 text-xs"><span>Remaining: <strong>{{ number_format($wallet?->balance ?? 0) }} Credits</strong></span><span class="{{ $wallet && !$wallet->reconciled_at ? 'text-warning' : 'text-success' }}">{{ $wallet && !$wallet->reconciled_at ? 'Needs reconciliation · delivery is held' : 'Verified credits' }}</span></div>
        <form wire:submit="reconcile" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div><label class="block text-xs font-semibold">Verified remaining lead credits</label><input type="number" min="0" step="1" wire:model="remaining" class="w-full rounded-lg border-border bg-canvas text-ink text-sm" />@error('remaining')<p class="text-xs text-danger">{{ $message }}</p>@enderror</div>
            <div><label class="block text-xs font-semibold">Reconciliation reason</label><input wire:model="reason" class="w-full rounded-lg border-border bg-canvas text-ink text-sm" placeholder="Explain the correction" />@error('reason')<p class="text-xs text-danger">{{ $message }}</p>@enderror</div>
            <label class="flex items-center gap-2 text-xs"><input type="checkbox" wire:model="confirmed" class="rounded border-border text-primary" /> I confirm this is the verified usable quantity.</label>
            <div class="flex justify-end"><x-ui.button type="submit">Confirm Reconciliation</x-ui.button></div>
            @error('confirmed')<p class="text-xs text-danger">{{ $message }}</p>@enderror
        </form>
        <form wire:submit="markLegacyConsumption" class="grid grid-cols-1 sm:grid-cols-2 gap-3 border-t border-border pt-4">
            <h4 class="sm:col-span-2 text-sm font-bold">Mark Legacy Delivery as Consumption</h4>
            <p class="sm:col-span-2 text-xs text-muted">Reconcile the available allocation first, then record credits used by an old delivered lead. This deducts the entered quantity once; it does not resend the lead or convert legacy money.</p>
            <div><label class="text-xs font-semibold">Legacy ledger entry</label><x-ui.themed-select wire:model="transactionId" :options="$transactionOptions" /></div>
            <div><label class="text-xs font-semibold">Credits consumed</label><input type="number" min="1" step="1" wire:model="legacyQuantity" class="w-full rounded-lg border-border bg-canvas text-ink text-sm" /></div>
            <div class="sm:col-span-2"><label class="text-xs font-semibold">Reason</label><input wire:model="legacyReason" class="w-full rounded-lg border-border bg-canvas text-ink text-sm" />@error('legacyReason')<p class="text-xs text-danger">{{ $message }}</p>@enderror</div>
            <label class="text-xs flex items-center gap-2"><input type="checkbox" wire:model="legacyConfirmed" /> I confirm this quantity should be deducted.</label>
            <div class="flex justify-end"><x-ui.button type="submit">Mark as Consumption</x-ui.button></div>
            @foreach(['legacyConfirmed', 'quantity'] as $field) @error($field)<p class="text-xs text-danger">{{ $message }}</p>@enderror @endforeach
        </form>
        <form wire:submit="openDispute" class="grid grid-cols-1 sm:grid-cols-2 gap-3 border-t border-border pt-4">
            <div><label class="text-xs font-semibold">Ledger entry to dispute</label><x-ui.themed-select wire:model="transactionId" :options="$transactionOptions" /></div>
            <div><label class="text-xs font-semibold">Dispute reason</label><input wire:model="disputeReason" class="w-full rounded-lg border-border bg-canvas text-ink text-sm" />@error('reason')<p class="text-xs text-danger">{{ $message }}</p>@enderror</div>
            <div class="sm:col-span-2 flex justify-end"><x-ui.button type="submit" variant="secondary">Record Dispute</x-ui.button></div>
        </form>
        <div class="overflow-x-auto"><table class="w-full text-xs text-left"><thead class="text-muted"><tr><th class="p-2">Entry</th><th class="p-2">Reason / Resolution</th><th class="p-2">Status</th><th class="p-2">Action</th></tr></thead><tbody>@foreach($disputes as $dispute)<tr wire:key="credit-dispute-{{ $dispute->id }}" class="border-t border-border"><td class="p-2">#{{ $dispute->credit_transaction_id }}</td><td class="p-2">{{ $dispute->reason }}<span class="block text-muted">{{ $dispute->resolution }}</span></td><td class="p-2">{{ ucfirst($dispute->status) }}</td><td class="p-2">@if($dispute->status === 'open')<button type="button" wire:click="reviewDispute({{ $dispute->id }})" class="text-primary font-semibold">Resolve</button>@endif</td></tr>@endforeach</tbody></table></div>
    @endif
    <x-ui.modal name="resolve-credit-dispute" centered>
        <form wire:submit="resolve" class="p-5 space-y-4">
            <h3 class="font-bold">Resolve Credit Dispute</h3>
            <div><label class="text-xs font-semibold">Resolution</label><textarea wire:model="resolution" class="w-full rounded-lg border-border bg-canvas text-ink"></textarea>@error('resolution')<p class="text-xs text-danger">{{ $message }}</p>@enderror</div>
            <p class="text-xs text-muted">Optional correction: use 0 to resolve without changing credits. The original entry remains unchanged.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3"><x-ui.themed-select wire:model="correctionAction" :options="['recharge' => 'Add credits', 'deduct' => 'Deduct credits']" /><input aria-label="Credit correction quantity" type="number" step="1" min="0" wire:model="correction" class="w-full rounded-lg border-border bg-canvas text-ink" /></div>
            @error('quantity')<p class="text-xs text-danger">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-2"><x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal','resolve-credit-dispute')">Cancel</x-ui.button><x-ui.button type="submit">Resolve</x-ui.button></div>
        </form>
    </x-ui.modal>
</details>
