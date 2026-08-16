<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-surface rounded-card border border-border p-6 shadow-sm">
        <div>
            <div class="flex items-center space-x-2">
                <h1 class="text-xl font-bold tracking-tight text-ink">Developer Tools & Data Reset</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-amber-50 text-amber-700 border border-amber-200">
                    {{ app()->environment() }}
                </span>
            </div>
            <p class="text-xs text-muted mt-1">Manage database seeding and test data reset tools for local & staging environments.</p>
        </div>
        <button wire:click="refreshStats" class="inline-flex items-center space-x-1.5 px-3 py-1.5 text-xs font-bold rounded-lg border border-border bg-canvas text-ink hover:bg-surface transition">
            <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            <span>Refresh Stats</span>
        </button>
    </div>

    <!-- Current Database Statistics Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
        <div class="bg-surface p-3.5 rounded-card border border-border shadow-2xs">
            <div class="text-[10px] font-bold uppercase text-muted truncate">Organizations</div>
            <div class="text-lg font-extrabold text-ink mt-0.5">{{ number_format($stats['organizations'] ?? 0) }}</div>
        </div>
        <div class="bg-surface p-3.5 rounded-card border border-border shadow-2xs">
            <div class="text-[10px] font-bold uppercase text-muted truncate">Users</div>
            <div class="text-lg font-extrabold text-ink mt-0.5">{{ number_format($stats['users'] ?? 0) }}</div>
        </div>
        <div class="bg-surface p-3.5 rounded-card border border-border shadow-2xs">
            <div class="text-[10px] font-bold uppercase text-muted truncate">Clients</div>
            <div class="text-lg font-extrabold text-ink mt-0.5">{{ number_format($stats['clients'] ?? 0) }}</div>
        </div>
        <div class="bg-surface p-3.5 rounded-card border border-border shadow-2xs">
            <div class="text-[10px] font-bold uppercase text-muted truncate">Projects</div>
            <div class="text-lg font-extrabold text-ink mt-0.5">{{ number_format($stats['projects'] ?? 0) }}</div>
        </div>
        <div class="bg-surface p-3.5 rounded-card border border-border shadow-2xs">
            <div class="text-[10px] font-bold uppercase text-muted truncate">Leads</div>
            <div class="text-lg font-extrabold text-ink mt-0.5">{{ number_format($stats['leads'] ?? 0) }}</div>
        </div>
        <div class="bg-surface p-3.5 rounded-card border border-border shadow-2xs">
            <div class="text-[10px] font-bold uppercase text-muted truncate">Transactions</div>
            <div class="text-lg font-extrabold text-ink mt-0.5">{{ number_format($stats['credit_transactions'] ?? 0) }}</div>
        </div>
        <div class="bg-surface p-3.5 rounded-card border border-border shadow-2xs">
            <div class="text-[10px] font-bold uppercase text-muted truncate">Replacements</div>
            <div class="text-lg font-extrabold text-ink mt-0.5">{{ number_format($stats['replacements'] ?? 0) }}</div>
        </div>
        <div class="bg-surface p-3.5 rounded-card border border-border shadow-2xs">
            <div class="text-[10px] font-bold uppercase text-muted truncate">Audit Logs</div>
            <div class="text-lg font-extrabold text-ink mt-0.5">{{ number_format($stats['audit_logs'] ?? 0) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Seed Dummy Data Section -->
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm flex flex-col justify-between space-y-4">
            <div class="space-y-3">
                <div class="flex items-center space-x-2 text-ink font-bold">
                    <svg class="w-5 h-5 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <span>Seed Dummy Data</span>
                </div>
                <p class="text-xs text-muted leading-relaxed">
                    Populate the database with a complete demo dataset across all models and relationships (Organizations, Users, Projects, Campaigns, Leads, Credit Transactions, Activities, and Audit Logs).
                </p>

                @if(!empty($lastSeedSummary))
                    <div class="p-3.5 rounded-lg bg-green-50 border border-green-200 text-xs text-green-800 space-y-1">
                        <div class="font-bold">Last Seeding Summary:</div>
                        <ul class="list-disc list-inside text-[11px] space-y-0.5">
                            <li>{{ number_format($lastSeedSummary['organizations'] ?? 0) }} Organizations</li>
                            <li>{{ number_format($lastSeedSummary['users'] ?? 0) }} Users</li>
                            <li>{{ number_format($lastSeedSummary['projects'] ?? 0) }} Projects</li>
                            <li>{{ number_format($lastSeedSummary['leads'] ?? 0) }} Leads</li>
                            <li>{{ number_format($lastSeedSummary['transactions'] ?? 0) }} Credit Transactions</li>
                        </ul>
                    </div>
                @endif
            </div>

            <div class="pt-4 border-t border-border">
                <button 
                    wire:click="reseedDatabase"
                    wire:loading.attr="disabled"
                    class="w-full inline-flex items-center justify-center space-x-2 px-4 py-2.5 bg-ink text-white font-bold text-xs rounded-lg hover:bg-neutral-800 transition disabled:opacity-50 cursor-pointer shadow-xs"
                >
                    <svg wire:loading wire:target="reseedDatabase" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span wire:loading.remove wire:target="reseedDatabase">Reseed Full Demo Dataset</span>
                    <span wire:loading wire:target="reseedDatabase">Seeding... this may take a minute</span>
                </button>
            </div>
        </div>

        <!-- Clear All Data Section (Danger Guarded) -->
        <div class="bg-surface rounded-card border-2 border-red-500/40 p-6 shadow-sm flex flex-col justify-between space-y-4">
            <div class="space-y-3">
                <div class="flex items-center space-x-2 text-danger font-bold">
                    <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span>Clear All Data (Destructive)</span>
                </div>
                <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-xs text-red-800 leading-relaxed font-medium">
                    This will permanently delete ALL data in every table except the Super Admin user account (<code class="font-bold text-red-900">admin@leadpanther.com</code>) and core role/permission definitions. This action cannot be undone.
                </div>

                <div class="space-y-1.5 pt-2">
                    <label class="block text-xs font-bold text-ink">Type <span class="font-mono text-danger">DELETE ALL DATA</span> to confirm:</label>
                    <input 
                        type="text" 
                        wire:model.live="confirmPhrase"
                        placeholder="DELETE ALL DATA"
                        class="w-full px-3 py-2 text-xs rounded-lg border border-border bg-canvas text-ink focus:ring-2 focus:ring-danger focus:border-danger transition font-mono uppercase tracking-wider"
                    />
                </div>
            </div>

            <div class="pt-4 border-t border-border">
                <button 
                    wire:click="clearAllData"
                    wire:loading.attr="disabled"
                    @disabled(trim($confirmPhrase) !== 'DELETE ALL DATA')
                    class="w-full inline-flex items-center justify-center space-x-2 px-4 py-2.5 bg-red-600 text-white font-bold text-xs rounded-lg hover:bg-red-700 transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer shadow-xs"
                >
                    <svg wire:loading wire:target="clearAllData" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span wire:loading.remove wire:target="clearAllData">Truncate & Reset Database</span>
                    <span wire:loading wire:target="clearAllData">Clearing All Data...</span>
                </button>
            </div>
        </div>
    </div>
</div>
