<div class="space-y-4">

    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Convert {{ $lead->name }} to Confirmed Booking</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <div class="md:col-span-2">
                <label class="font-bold text-ink">Project Unit to Lock</label>
                <livewire:shared.searchable-select 
                    :model="\App\Models\ProjectUnit::class"
                    placeholder="Select Unit to Lock"
                    wire:model="projectUnitId"
                    key="book-unit"
                    class="w-full mt-1"
                />
            </div>

            <div class="md:col-span-2">
                <label class="font-bold text-ink">Booking Token Amount (₹)</label>
                <input type="number" wire:model="bookingAmount" step="10000" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <x-ui.button wire:click="convertToBooking" variant="primary" class="text-xs">
                Confirm Booking & Lock Unit
            </x-ui.button>
        </div>
    </div>
</div>
