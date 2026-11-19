<div>
    <x-modal name="delivered-batch-{{ $organizationId }}" wire:model.live="show" :show="$show" maxWidth="7xl">
        <div class="p-4 sm:p-6 space-y-4" @if($show) wire:poll.5s="$refresh" @endif>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
                <div><h2 class="font-bold text-lg text-ink">Delivered Leads @if($batch) · Batch #{{ $batch->id }}@endif</h2><p class="text-xs text-muted">Successful deliveries in the selected batch.</p></div>
                <button type="button" wire:click="close" aria-label="Close delivered leads" title="Close" class="p-2 rounded-lg text-muted hover:bg-canvas hover:text-ink"><x-ui.team-icon name="close" /></button>
            </div>
            @if($batch)
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach($summary as $label=>$value)<div class="rounded-lg border border-border bg-canvas p-3"><div class="text-xs text-muted">{{ $label }}</div><div class="mt-1 font-mono font-bold text-ink">{{ number_format($value) }}</div></div>@endforeach
                </div>
                <x-ui.advanced-table :columns="$this->columns()" :rows="$leads" searchModel="deliveredSearch" searchPlaceholder="Search lead ID, customer or mobile..." :show-configurations="false" :show-skeleton="false" table-name="delivered-batch-leads" empty-title="No delivered leads found" empty-message="No active leads in this batch match the selected filters.">
                    <x-slot:filters>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="text-xs text-ink">Delivered From<x-text-input variant="compact" type="date" wire:model.live="deliveredFrom" class="mt-1" /></label>
                            <label class="text-xs text-ink">Delivered To<x-text-input variant="compact" type="date" wire:model.live="deliveredTo" class="mt-1" /></label>
                        </div>
                        <label class="block text-xs text-ink">Project<x-ui.themed-select wire:model.live="deliveredProject" :options="[''=>'All projects'] + $projects" :searchable="true" class="mt-1 w-full" /></label>
                        <label class="block text-xs text-ink">Campaign<x-ui.themed-select wire:model.live="deliveredCampaign" :options="[''=>'All campaigns'] + $campaigns" :searchable="true" class="mt-1 w-full" /></label>
                    </x-slot:filters>
                    <x-slot:action>
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="text-xs text-muted">Batch Number<x-ui.themed-select wire:model.live="batchNumber" :options="$batchOptions" :searchable="true" class="w-full sm:w-52" /></label>
                            <x-ui.export-button target="exportDelivered" label="Export Delivered Leads" />
                        </div>
                    </x-slot:action>
                </x-ui.advanced-table>
                <p class="text-[11px] text-muted">Batch totals retain processing history. Archived or deleted leads are excluded from this table.</p>
                @error('deliveredFrom')<p class="text-xs text-danger">{{ $message }}</p>@enderror
                @error('deliveredTo')<p class="text-xs text-danger">{{ $message }}</p>@enderror
            @endif
        </div>
    </x-modal>
</div>
