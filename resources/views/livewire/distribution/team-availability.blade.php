<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Team Availability & Duty Roster</h1>
            <p class="text-xs text-muted">Toggle sales executive online/offline availability status for real-time lead routing.</p>
        </div>
    </div>



    <!-- Team Availability Table Component -->
    <div class="space-y-2">
        <x-ui.advanced-table 
            :columns="$this->tableColumns()"
            :rows="$members"
            :visibleColumns="$visibleColumns"
            :sortField="$sortField"
            :sortDirection="$sortDirection"
            :showFilterDropdown="false"
            searchPlaceholder="Search executives by name, email, phone..."
            emptyTitle="No Executives Found"
            emptyMessage="No sales executives registered in duty roster."
        />
    </div>
</div>
