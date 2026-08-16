<div class="space-y-4">

    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Record Payment Received for {{ $lead->name }}
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <div>
                <label class="font-bold text-ink">Associated Booking</label>
                <livewire:shared.searchable-select :model="\App\Models\Booking::class" :searchable="true"
                    placeholder="Select Booking" wire:model="bookingId" key="pay-booking" class="w-full mt-1" />
            </div>

            <div>
                <label class="font-bold text-ink">Payment Amount (₹)</label>
                <input type="number" wire:model="amount" step="5000"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink transition mt-1">
            </div>

            <div>
                <label class="font-bold text-ink">Payment Method</label>
                <x-ui.themed-select wire:model="paymentMethod" :options="['bank_transfer' => 'NEFT / RTGS Bank Transfer', 'cheque' => 'Cheque', 'upi' => 'UPI / Online Gateway', 'cash' => 'Cash Token']"
                    placeholder="Payment Method" searchable="true" class="w-full mt-1" />
            </div>

            <div>
                <label class="font-bold text-ink">Transaction Reference / UTR Number</label>
                <input type="text" wire:model="transactionReference"
                    class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
            </div>

            <div class="md:col-span-2">
                <label class="font-bold text-ink">Notes / Bank Name</label>
                <textarea wire:model="notes" rows="2"
                    class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1"></textarea>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <x-ui.button wire:click="recordPayment" variant="primary" class="text-xs">
                Record Payment & Update Lead Stage
            </x-ui.button>
        </div>
    </div>
</div>