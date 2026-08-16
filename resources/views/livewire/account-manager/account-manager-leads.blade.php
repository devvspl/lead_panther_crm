<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Account Manager Overview</h1>
            <p class="text-xs text-muted">Audited operational leads view with server-enforced PII masking (Mobile & Email).</p>
        </div>

        <div class="flex items-center space-x-2">
            <span class="px-2.5 py-1 text-xs font-bold rounded-pill bg-purple-50 text-purple-700 border border-purple-200">
                PII Masking Enforced
            </span>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-surface rounded-card border border-border p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center space-x-3 w-full md:w-auto">
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search lead code or name..." 
                class="text-xs p-2.5 rounded-lg border border-border bg-canvas text-ink w-64"
            >

            <x-ui.themed-select 
                wire:model.live="stageFilter"
                :options="['' => 'All Stages', 'new' => 'New', 'assigned' => 'Assigned', 'connected' => 'Connected', 'qualified' => 'Qualified', 'meeting' => 'Meeting', 'site_visit' => 'Site Visit', 'booking' => 'Booking', 'closed_won' => 'Closed Won', 'closed_lost' => 'Closed Lost']"
                placeholder="All Stages"
                searchable="true"
            />

            <x-ui.export-button target="exportExcel" />
        </div>
    </div>

    <!-- Leads Masked Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Lead ID</th>
                        <th class="py-3 px-4">Lead Name</th>
                        <th class="py-3 px-4">Masked Mobile</th>
                        <th class="py-3 px-4">Masked Email</th>
                        <th class="py-3 px-4">Project</th>
                        <th class="py-3 px-4">Campaign / Source</th>
                        <th class="py-3 px-4">Assigned Executive</th>
                        <th class="py-3 px-4">Stage</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($leads as $lead)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-ink">{{ $lead['lead_code'] }}</td>
                            <td class="py-3 px-4 font-bold text-ink">{{ $lead['name'] }}</td>
                            <td class="py-3 px-4 font-mono text-muted">
                                <span class="bg-canvas px-2 py-0.5 rounded border border-border text-[11px] font-semibold text-ink">
                                    {{ $lead['mobile'] }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono text-muted">
                                <span class="bg-canvas px-2 py-0.5 rounded border border-border text-[11px] font-semibold text-ink">
                                    {{ $lead['email'] }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-muted">{{ $lead['project_name'] }}</td>
                            <td class="py-3 px-4 text-muted">
                                <div>{{ $lead['campaign_name'] }}</div>
                                <div class="text-[10px] text-muted">{{ strtoupper($lead['source_name']) }}</div>
                            </td>
                            <td class="py-3 px-4 font-semibold text-ink">{{ $lead['assigned_executive'] }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill uppercase bg-canvas text-ink border border-border">
                                    {{ $lead['current_stage'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-muted">No leads found in account manager view.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $paginator->links('vendor.pagination.tailwind') }}
        </div>
    </div>
</div>
