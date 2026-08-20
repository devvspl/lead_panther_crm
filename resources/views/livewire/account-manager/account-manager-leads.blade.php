<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Account Manager Overview</h1>
            <p class="text-xs text-muted">Audited operational leads view with server-enforced PII masking (Mobile &
                Email).</p>
        </div>

        <div class="flex items-center space-x-2">
            <span
                class="px-2.5 py-1 text-xs font-bold rounded-pill bg-purple-50 text-purple-700 border border-purple-200">
                PII Masking Enforced
            </span>
        </div>
    </div>

    <!-- Masked Leads Table Component -->
    <div class="space-y-2">
        <x-ui.advanced-table 
            :columns="$this->tableColumns()" 
            :rows="$leads" 
            :visibleColumns="$visibleColumns"
            :sortField="$sortField" 
            :sortDirection="$sortDirection" 
            searchPlaceholder="Search lead code, name..."
            emptyTitle="No Leads Found" 
            emptyMessage="No leads found matching your account manager criteria."
        >
            <!-- Custom Filters Slot -->
            <x-slot:filters>
                <div>
                    <label class="text-[11px] font-bold text-ink block mb-1">Stage Filter</label>
                    <x-ui.themed-select wire:model.live="stageFilter" :options="['' => 'All Stages', 'new' => 'New', 'assigned' => 'Assigned', 'connected' => 'Connected', 'qualified' => 'Qualified', 'meeting' => 'Meeting', 'site_visit' => 'Site Visit', 'booking' => 'Booking', 'closed_won' => 'Closed Won', 'closed_lost' => 'Closed Lost']" placeholder="All Stages" searchable="true" />
                </div>
            </x-slot:filters>

            <!-- Custom Action Slot -->
            <x-slot:action>
                <x-ui.export-button target="exportExcel" class="text-xs" />
            </x-slot:action>
        </x-ui.advanced-table>
    </div>
</div>