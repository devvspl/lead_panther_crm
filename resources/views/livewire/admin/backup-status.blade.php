<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">System Backups & Health Status</h1>
            <p class="text-xs text-muted">Monitor automated database and document disk backups and health metrics.</p>
        </div>

        <div class="flex space-x-2">
            <button wire:click="runBackup" wire:loading.attr="disabled" wire:target="runBackup" class="px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-black transition flex items-center gap-2 disabled:opacity-50">
                <span wire:loading.remove wire:target="runBackup">Run Backup Now</span>
                <span wire:loading wire:target="runBackup" class="flex items-center gap-2">
                    <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Executing Backup...
                </span>
            </button>
            <button wire:click="cleanBackups" wire:loading.attr="disabled" wire:target="cleanBackups" class="px-4 py-2 border border-border bg-white text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition flex items-center gap-2 disabled:opacity-50">
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
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-2">
            <span class="text-xs font-bold uppercase text-muted tracking-wider">Health Status</span>
            <div class="flex items-center space-x-2">
                <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                <span class="text-lg font-bold text-ink">System Healthy</span>
            </div>
            <p class="text-[11px] text-muted">Daily database & document backups active.</p>
        </div>

        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-2">
            <span class="text-xs font-bold uppercase text-muted tracking-wider">Last Backup Timestamp</span>
            <div class="text-lg font-mono font-bold text-ink">{{ $lastBackupTime }}</div>
            <p class="text-[11px] text-muted">Destination: <span class="font-mono text-ink">backup_storage</span> disk.
            </p>
        </div>

        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-2">
            <span class="text-xs font-bold uppercase text-muted tracking-wider">Total Storage Used</span>
            <div class="text-lg font-mono font-bold text-primary">{{ $totalSizeFormatted }}</div>
            <p class="text-[11px] text-muted">Retention policy: 7 daily, 8 weekly, 4 monthly.</p>
        </div>
    </div>

    <!-- Backups List Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h3 class="text-xs font-bold uppercase text-muted tracking-wider">Available Backup Archives</h3>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Archive Filename</th>
                        <th class="py-3 px-4">Storage Path</th>
                        <th class="py-3 px-4">Archive Size</th>
                        <th class="py-3 px-4">Backup Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($archives as $file)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-ink">{{ $file['name'] }}</td>
                            <td class="py-3 px-4 font-mono text-muted text-[11px]">{{ $file['path'] }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-primary">{{ $file['size'] }}</td>
                            <td class="py-3 px-4 font-mono text-muted whitespace-nowrap">{{ $file['modified_at'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-muted">No backup files present on destination disk.
                                Click "Run Backup Now" to create an initial snapshot.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $archives->links('vendor.pagination.tailwind') }}
        </div>
    </div>
</div>