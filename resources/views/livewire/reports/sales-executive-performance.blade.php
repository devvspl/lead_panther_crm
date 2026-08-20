<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-ink">Sales Executive Performance Leaderboard</h2>
            <p class="text-xs text-muted">Comparative breakdown: Assigned volume, SLA response rate, site visits, and closed bookings.</p>
        </div>
        <x-ui.export-button target="exportExcel" />
    </div>

    <!-- Leaderboard Table Component -->
    <div class="space-y-2">
        <x-ui.advanced-table 
            :columns="$this->tableColumns()"
            :rows="$executives"
            :sortField="$sortField"
            :sortDirection="$sortDirection"
            :showSearch="false"
            :showFilterDropdown="false"
            :showConfigurations="false"
            emptyTitle="No Executive Data"
            emptyMessage="No sales executive performance metrics recorded yet."
        />
    </div>
</div>
