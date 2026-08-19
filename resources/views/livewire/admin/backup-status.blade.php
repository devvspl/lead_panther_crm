<div class="space-y-6">
    @if($backupRunning)
        <div wire:poll.3s="checkBackupStatus"></div>
    @endif

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1">
                <span>System Administration</span>
                <span>/</span>
                <span class="text-ink font-semibold">Backups &amp; Snapshots</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                <svg class="w-6 h-6 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
                <span>System Backups &amp; Health Status</span>
            </h1>
            <p class="text-xs text-muted">Monitor automated database and document disk backups and health metrics.</p>
        </div>

        <div class="flex items-center space-x-2">
            <button 
                type="button"
                wire:click="runBackup" 
                wire:loading.attr="disabled" 
                wire:target="runBackup" 
                @if($backupRunning) disabled @endif
                class="px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed shadow-xs cursor-pointer"
            >
                @if(!$backupRunning)
                    <span wire:loading.remove wire:target="runBackup">+ Run Backup Now</span>
                @endif

                <span 
                    @if(!$backupRunning) wire:loading wire:target="runBackup" @else class="flex items-center gap-2" @endif
                >
                    <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Executing Backup
                </span>
            </button>

            <button 
                type="button"
                wire:click="cleanBackups" 
                wire:loading.attr="disabled" 
                wire:target="cleanBackups" 
                class="px-4 py-2 border border-border bg-white text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed shadow-xs cursor-pointer"
            >
                <span wire:loading.remove wire:target="cleanBackups">Clean Old Backups</span>
                <span wire:loading wire:target="cleanBackups" class="flex items-center gap-2">
                    <svg class="animate-spin h-3.5 w-3.5 text-ink" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Cleaning...
                </span>
            </button>
        </div>
    </div>

    <!-- Backup Status Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="bg-surface rounded-card border border-border p-5 shadow-2xs space-y-2">
            <span class="text-[11px] font-bold uppercase text-muted tracking-wider">Health Status</span>
            <div class="flex items-center space-x-2">
                <span class="h-3 w-3 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-base font-bold text-ink">System Healthy</span>
            </div>
            <p class="text-[11px] text-muted">Daily database &amp; document backups active.</p>
        </div>

        <div class="bg-surface rounded-card border border-border p-5 shadow-2xs space-y-2">
            <span class="text-[11px] font-bold uppercase text-muted tracking-wider">Last Backup Timestamp</span>
            <div class="text-base font-mono font-bold text-ink">{{ $lastBackupTime }}</div>
            <p class="text-[11px] text-muted">Destination: <span class="font-mono text-ink">backup_storage</span> disk.</p>
        </div>

        <div class="bg-surface rounded-card border border-border p-5 shadow-2xs space-y-2">
            <span class="text-[11px] font-bold uppercase text-muted tracking-wider">Total Storage Used</span>
            <div class="text-base font-mono font-bold text-primary">{{ $totalSizeFormatted }}</div>
            <p class="text-[11px] text-muted">Retention policy: 7 daily, 8 weekly, 4 monthly.</p>
        </div>
    </div>

    <!-- Advanced Table Component -->
    <div class="space-y-3">
        <h2 class="text-sm font-bold text-ink">Available Backup Archives</h2>
        <x-ui.advanced-table 
            :columns="$this->tableColumns()"
        :rows="$archives"
        :quickFilters="$this->quickFilters()"
        :activeStatus="$statusFilter"
        :visibleColumns="$visibleColumns"
        :sortField="$sortField"
        :sortDirection="$sortDirection"
        :filterCount="$this->activeFilterCount"
        searchPlaceholder="Search backup zip archives..."
        emptyTitle="No Backup Archives Found"
        emptyMessage="No backup archives present on destination disk. Click 'Run Backup Now' to create a snapshot."
    />
    </div>
</div>