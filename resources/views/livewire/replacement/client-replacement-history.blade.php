<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">My Lead Replacements</h1>
            <p class="text-xs text-muted">View replacement claims, SLA status, and resolution outcomes for your leads.
            </p>
        </div>
        <x-ui.export-button target="exportExcel" />
    </div>

    <!-- Replacements Table Component -->
    <div class="space-y-2">
        <x-ui.advanced-table 
            :columns="$this->tableColumns()" 
            :rows="$replacements" 
            :visibleColumns="$visibleColumns"
            :sortField="$sortField" 
            :sortDirection="$sortDirection" 
            :quickFilters="[
                ['key' => 'all', 'label' => 'All Claims'],
                ['key' => 'pending', 'label' => 'Pending'],
                ['key' => 'approved', 'label' => 'Approved'],
                ['key' => 'rejected', 'label' => 'Rejected'],
            ]" 
            :activeStatus="$statusFilter"
            searchPlaceholder="Search lead, code, or notes..." 
            emptyTitle="No Replacement Claims Found"
            emptyMessage="No replacement requests match your filters."
        >
            <x-slot:action>
                <x-ui.export-button target="exportExcel" class="text-xs" />
            </x-slot:action>
        </x-ui.advanced-table>
    </div>
</div>