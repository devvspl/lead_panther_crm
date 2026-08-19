<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1">
                <span>System Administration</span>
                <span>/</span>
                <span class="text-ink font-semibold">Audit Logs</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                <svg class="w-6 h-6 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>System Audit Logs Browser</span>
            </h1>
            <p class="text-xs text-muted">Immutable system audit trail tracking mutations, security events, role updates, and deployments.</p>
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
        searchPlaceholder="Search action, changes, IP address, user..."
        emptyTitle="No Audit Logs Found"
        emptyMessage="No system audit log records match your current search and filters."
    >
        <!-- Filter Dropdown Slot -->
        <x-slot:filters>
            <div class="space-y-3">
                <div>
                    <label class="text-[11px] font-bold text-ink block mb-1">Performing User</label>
                    <select wire:model.live="selectedUserId" class="w-full h-8 px-2.5 rounded-lg border border-border bg-canvas text-ink text-xs">
                        <option value="">All Performing Users</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-[11px] font-bold text-ink block mb-1">Subject Model</label>
                    <select wire:model.live="subjectType" class="w-full h-8 px-2.5 rounded-lg border border-border bg-canvas text-ink text-xs">
                        <option value="">All Subject Models</option>
                        <option value="Lead">Lead</option>
                        <option value="CreditTransaction">CreditTransaction</option>
                        <option value="LeadReplacement">LeadReplacement</option>
                        <option value="User">User</option>
                        <option value="git_sync">Git Deployments</option>
                    </select>
                </div>

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
