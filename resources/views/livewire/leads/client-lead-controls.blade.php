<section class="bg-surface rounded-card border border-border p-4 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-bold text-ink">Client Leads Management</h2>
            <p class="text-xs text-muted mt-1">Includes delivery queues, internal processing and client handovers for
                this organization.</p>
        </div>
        <div class="flex items-center gap-2"><button type="button" wire:click="resetFilters"
                class="px-3 h-8 rounded-lg border border-border text-xs font-semibold text-ink">Reset
                Filters</button><button type="button" wire:click="openClientImport"
                class="px-3 h-8 rounded-lg border border-border text-xs font-semibold text-ink hover:bg-canvas transition cursor-pointer">Import Excel /
                CSV</button></div>
    </div>
    @error('selectedRows')
    <p role="alert" class="text-xs text-red-600">{{ $message }}</p>@enderror
    <livewire:leads.client-delivery-center :organization-id="$this->clientOrganizationId"
        :key="'delivery-center-' . $this->clientOrganizationId" />
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
        <div><label class="block text-xs text-muted mb-1">Project</label><x-ui.themed-select wire:model.live="project"
                :options="$allProjects->pluck('name', 'id')->all()" placeholder="All Projects" class="w-full" /></div>
        <div><label class="block text-xs text-muted mb-1">Campaign</label><x-ui.themed-select wire:model.live="campaign"
                :options="$allCampaigns->when($project, fn($rows) => $rows->where('project_id', $project))->pluck('name', 'id')->all()" placeholder="All Campaigns" class="w-full" /></div>
        @canViewLeadSource
        <div><label class="block text-xs text-muted mb-1">Lead Source</label><x-ui.themed-select
                wire:model.live="source" :options="$allLeadSources->pluck('name', 'id')->all()"
                placeholder="All Sources" class="w-full" /></div>
        @endcanViewLeadSource
        <div><label class="block text-xs text-muted mb-1">Assigned Executive</label><x-ui.themed-select
                wire:model.live="executive" :options="$eligibleAssignees->pluck('name', 'id')->all()"
                placeholder="All Executives" class="w-full" /></div>
        <div><label class="block text-xs text-muted mb-1">Lead Stage</label><x-ui.themed-select
                wire:model.live="stageFilter" :options="array_merge($mainStages, $otherStages)" placeholder="All Stages"
                class="w-full" /></div>
        <div><label class="block text-xs text-muted mb-1">Lead Status</label><x-ui.themed-select
                wire:model.live="statusFilter" :options="array_merge(['all' => 'All Statuses', 'new' => 'New', 'assigned' => 'Assigned', 'unassigned' => 'Unassigned', 'in_progress' => 'In Progress', 'contacted' => 'Contacted', 'followups' => 'Follow-ups Due', 'closed_won' => 'Closed Won', 'lost' => 'Lost', 'pending_credit' => 'Pending Credit', 'duplicate' => 'Duplicate'], $clientStatusOptions)" class="w-full" />
        </div>
        <div><label class="block text-xs text-muted mb-1">Delivery / Handover</label><x-ui.themed-select
                wire:model.live="deliveryFilter" :options="['' => 'All Deliveries', 'delivered' => 'Delivered', 'pending' => 'Pending', 'failed' => 'Failed', 'internal' => 'Internal Processing', 'handed_over' => 'Handed Over']" class="w-full" /></div>
        <div><label class="block text-xs text-muted mb-1">Search</label><x-text-input
                wire:model.live.debounce.300ms="search" placeholder="Lead ID, name, mobile, email or city"
                class="w-full h-8 !bg-canvas !text-xs !shadow-none" /></div>
    </div>
    <x-modal name="client-lead-import-modal" wire:model.live="showClientImport" :show="$showClientImport" maxWidth="5xl">
        <div class="p-5 sm:p-6 space-y-4 max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3.5 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-primary/10 text-primary border border-primary/20">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 01-2 2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Import Excel / CSV Leads</h3>
                        <p class="text-xs text-muted">Upload multi-lead spreadsheets, map column headers, and import directly into this organization.</p>
                    </div>
                </div>
                <button type="button" wire:click="closeClientImport"
                    class="text-muted hover:text-ink cursor-pointer p-1.5 rounded-lg hover:bg-canvas transition"
                    aria-label="Close modal">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            @if($showClientImport)
                <livewire:leads.bulk-lead-upload :organization-id="$this->clientOrganizationId"
                    :key="'client-lead-import-' . $this->clientOrganizationId" />
            @endif
        </div>
    </x-modal>
    @if(count($selectedRows))
        <div class="flex flex-wrap items-end gap-2 border-t border-border pt-3">
            <div class="min-w-48"><label class="block text-xs text-muted mb-1">Selected leads:
                    {{ count($selectedRows) }}</label><x-ui.themed-select wire:model="bulkStage"
                    :options="array_merge($mainStages, $otherStages)" placeholder="Choose status" class="w-full" /></div>
            <button type="button" wire:click="changeSelectedStatus" wire:confirm="Change the status of the selected leads?"
                class="px-3 h-8 bg-ink text-white rounded-lg text-xs font-semibold">Change Status</button>
            <button type="button" wire:click="openBulkAssignModal"
                class="px-3 h-8 border border-border rounded-lg text-xs font-semibold">Assign / Reassign</button>
            <x-ui.export-button target="exportSelectedLeads" label="Export Selected" />
            @can('delete-leads')<button type="button" wire:click="deleteSelectedLeads"
                wire:confirm="Archive the selected leads? Their details, assignments and history will be preserved."
                class="px-3 h-8 border border-red-200 text-red-700 rounded-lg text-xs font-semibold">Archive
            Selected</button>@endcan
            @error('bulkStage')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
            @error('selectedRows')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        </div>
    @endif
</section>