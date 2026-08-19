<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1">
                <span>Integrations &amp; Portals</span>
                <span>/</span>
                <span class="text-ink font-semibold">Webhook Inspector</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                <svg class="w-6 h-6 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>Inbound Webhook Logs Inspector</span>
            </h1>
            <p class="text-xs text-muted">Audit raw incoming payloads from Meta, Google, 99acres, MagicBricks, and direct landing portals.</p>
        </div>
    </div>

    <!-- Advanced Table Component -->
    <x-ui.advanced-table 
        :columns="$this->tableColumns()"
        :rows="$logs"
        :quickFilters="$this->quickFilters()"
        :activeStatus="$statusFilter"
        :visibleColumns="$visibleColumns"
        :sortField="$sortField"
        :sortDirection="$sortDirection"
        :filterCount="$this->activeFilterCount"
        searchPlaceholder="Search source, payload, error message..."
        emptyTitle="No Webhook Logs Found"
        emptyMessage="No webhook payloads match your current search and filters."
    >
        <!-- Filter Dropdown Slot -->
        <x-slot:filters>
            <div class="space-y-3">
                <div>
                    <label class="text-[11px] font-bold text-ink block mb-1">Date Scope</label>
                    <x-ui.date-range-picker 
                        wire:model.live="dateRange"
                        wire:custom-from="customFrom"
                        wire:custom-to="customTo"
                        placeholder="All Time"
                    />
                </div>
            </div>
        </x-slot:filters>

        <!-- Primary Action Slot -->
        <x-slot:action>
            <x-ui.export-button target="exportExcel" class="text-xs" />
        </x-slot:action>
    </x-ui.advanced-table>
</div>
