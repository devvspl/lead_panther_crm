<div class="space-y-6">
    @if($queue === 'internal' && $canInternalQueue)
        <livewire:leads.internal-processing-queue />
    @else
    <!-- 0. MAIN TABS HEADER: LEADS & ANALYTICS -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border/80 pb-3">
        <div class="flex items-center gap-1.5 sm:gap-2">
            <button type="button" wire:click="setMainTab('leads')"
                class="inline-flex items-center gap-2 px-4 py-2 text-xs sm:text-sm font-bold rounded-lg transition-colors cursor-pointer {{ $activeMainTab === 'leads' ? 'bg-ink text-white shadow-2xs' : 'bg-surface hover:bg-canvas text-muted hover:text-ink border border-border' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
                <span>Leads</span>
                <span class="sr-only">Total Leads</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono font-bold {{ $activeMainTab === 'leads' ? 'bg-white/20 text-white' : 'bg-canvas border border-border text-muted' }}">
                    {{ number_format($totalLeadCount ?? 0) }}
                </span>
            </button>

            <button type="button" wire:click="setMainTab('analytics')"
                class="inline-flex items-center gap-2 px-4 py-2 text-xs sm:text-sm font-bold rounded-lg transition-colors cursor-pointer {{ $activeMainTab === 'analytics' ? 'bg-ink text-white shadow-2xs' : 'bg-surface hover:bg-canvas text-muted hover:text-ink border border-border' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span>Analytics</span>
            </button>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                {{ \App\Support\TeamAccess::isLeader(auth()->user()) ? 'My Team Pipeline' : ($isSalesExecutive ? 'My Assigned Pipeline' : ($isBuilder ? 'Organization Pipeline' : 'Live Pipeline')) }}
            </span>
            @if($canInternalQueue)
                <span class="sr-only">Internal Processing Queue</span>
            @endif
            <span class="sr-only">Conversion Rate</span>
        </div>
    </div>
    @if($activeMainTab === 'leads')
    <!-- 1. COMPACT PAGE HEADER -->
    <div
        class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-surface rounded-card border border-border p-4 shadow-2xs">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-bold tracking-tight text-ink">
                    {{ ($isBuilder || $isSalesExecutive) ? 'My Leads' : 'Leads' }}</h1>
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                    {{ \App\Support\TeamAccess::isLeader(auth()->user()) ? 'My Team Pipeline' : ($isSalesExecutive ? 'My Assigned Pipeline' : ($isBuilder ? 'Organization Pipeline' : 'Live Pipeline')) }}
                </span>
            </div>
            <p class="text-xs text-muted mt-0.5">
                {{ $isSalesExecutive ? 'Track customer interactions, schedule follow-ups and complete deal lifecycle.' : ($isBuilder ? 'Manage organization leads, team assignments, follow-ups and replacement claims.' : 'Manage leads, assignments, follow-ups and conversions.') }}
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @if(!$isBuilder)
                <x-ui.date-range-picker wire:model.live="analyticsRange" wire:custom-from="analyticsCustomFrom"
                    wire:custom-to="analyticsCustomTo" placeholder="This Month" />
            @endif

            <x-ui.export-button target="exportExcel" label="Export Excel" />

            @if(!$isBuilder && !$isSalesExecutive)
                <button type="button" wire:click="openPullLeadsModal"
                    class="inline-flex items-center gap-1.5 px-3.5 h-8 bg-ink hover:bg-neutral-800 text-white rounded-lg text-xs font-semibold transition shadow-2xs cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Add Lead</span>
                    </button>
            @endif
        </div>
    </div>

    @if($this->isClientContext())
        @include('livewire.leads.client-lead-controls')
    @else
    <!-- 2. COLLAPSIBLE FILTERS PANEL -->
    <div x-data="{ open: @entangle('filtersOpen') }" class="bg-surface rounded-card border border-border shadow-2xs overflow-hidden transition-all">
        <!-- Filter Header Toggle Bar -->
        <div class="p-3.5 flex flex-wrap items-center justify-between gap-3 bg-surface hover:bg-canvas/50 transition">
            <button type="button" @click="open = !open" wire:click="$toggle('filtersOpen')"
                class="flex items-center gap-2.5 text-xs font-bold text-ink uppercase tracking-wider cursor-pointer select-none">
                <div class="p-1 rounded-md bg-canvas border border-border text-muted">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                </div>
                <span>Filters</span>
                @if($activeFilterCount > 0)
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                        {{ $activeFilterCount }} Active
                    </span>
                @endif
                <svg class="w-3.5 h-3.5 text-muted transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div class="flex items-center gap-2">
                <button type="button" x-show="open" wire:click="$refresh"
                    class="inline-flex items-center gap-1 px-3 py-1 bg-ink text-white rounded-lg text-xs font-semibold hover:bg-neutral-800 transition cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Apply</span>
                </button>
                @if($activeFilterCount > 0)
                    <button type="button" wire:click="resetFilters"
                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-surface hover:bg-canvas border border-border text-muted hover:text-ink rounded-lg text-xs font-semibold transition cursor-pointer">
                        <span>Clear All</span>
                    </button>
                @endif
            </div>
        </div>

        <div x-show="open" x-cloak class="p-4 pt-2 border-t border-border/70 space-y-3 bg-canvas/30">

        @php
            $statusOptions = [
                'all' => 'All Statuses',
                'new' => 'New',
                'assigned' => 'Assigned',
                'unassigned' => 'Unassigned',
                'contacted' => 'Contacted',
                'interested' => 'Interested',
                'qualified' => 'Qualified',
                'site_visit' => 'Site Visit',
                'negotiation' => 'Negotiation',
                'booking' => 'Booking',
                'closed_won' => 'Closed Won',
                'closed_lost' => 'Closed Lost',
                'duplicate' => 'Duplicate',
                'pending_credit' => 'Credit Check',
                'sla_breached' => 'SLA Breached',
            ];

            $typeOptions = [
                '' => 'All Types',
                '1 BHK' => '1 BHK',
                '2 BHK' => '2 BHK',
                '3 BHK' => '3 BHK',
                '4 BHK+' => '4 BHK+',
                'Penthouse' => 'Penthouse',
                'Commercial' => 'Commercial',
                'Villa' => 'Villa',
                'Plot' => 'Plot',
            ];

            $stageOptions = array_merge(['' => 'All Stages'], $mainStages, $otherStages ?? []);
        @endphp

        @if($isBuilder)
            <!-- 5 Compact Filters for Builder / Developer -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2.5 items-start">
                <!-- 1. Date Range -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Date Range</label>
                    <x-ui.date-range-picker 
                        wire:model.live="analyticsRange" 
                        wire:custom-from="analyticsCustomFrom"
                        wire:custom-to="analyticsCustomTo" 
                        placeholder="This Month"
                        class="w-full"
                    />
                </div>

                <!-- 2. Project -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Project</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="project" 
                        :options="$allProjects->pluck('name', 'id')->toArray()" 
                        placeholder="All Projects" 
                        class="w-full"
                        :key="'b-leads-proj-' . ($project ?? 'none')" 
                    />
                </div>

                <!-- 3. Lead Source (only if permitted) -->
                @canViewLeadSource
                    <div class="flex flex-col space-y-1 w-full">
                        <label class="block text-[11px] font-semibold text-muted truncate">Lead Source</label>
                        <livewire:shared.searchable-select 
                            wire:model.live="source" 
                            model="App\Models\LeadSource" 
                            placeholder="All Sources" 
                            class="w-full"
                            :key="'b-leads-src-' . ($source ?? 'none')" 
                        />
                    </div>
                @endcanViewLeadSource

                <!-- 4. Team Member / Assigned -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Team Member</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="executive" 
                        :options="($builderTeamMembers ?? $eligibleAssignees)->pluck('name', 'id')->toArray()" 
                        placeholder="All Members" 
                        class="w-full"
                        :key="'b-leads-exec-' . ($executive ?? 'none') . '-' . md5(($builderTeamMembers ?? $eligibleAssignees)->pluck('name', 'id')->toJson())"
                    />
                </div>

                <!-- 5. Lead Status -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Lead Status</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="statusFilter"
                        :show-clear-option="false"
                        clear-value="all"
                        :options="$statusOptions" 
                        placeholder="All Statuses" 
                        class="w-full"
                        :key="'b-leads-status-' . ($statusFilter ?? 'none')" 
                    />
                </div>

                <!-- 6. Lifecycle Stage -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Lead Stage</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="stageFilter" 
                        :options="$stageOptions" 
                        placeholder="All Stages" 
                        class="w-full"
                        :key="'b-leads-stage-' . ($stageFilter ?? 'none')" 
                    />
                </div>
            </div>
                @else
        <!-- 10 Filters Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2.5 items-start">
            <!-- 1. Organization -->
            <div class="flex flex-col space-y-1 w-full">
                <label class="block text-[11px] font-semibold text-muted truncate">Organization</label>
                <livewire:shared.searchable-select 
                    wire:model.live="filterOrganizationId" 
                    model="App\Models\Organization" 
                    placeholder="All Organizations" 
                    class="w-full"
                    :key="'leads-filter-org-' . ($filterOrganizationId ?? 'none')" 
                />
            </div>

            <!-- 2. Project -->
            <div class="flex flex-col space-y-1 w-full">
                <label class="block text-[11px] font-semibold text-muted truncate">Project</label>
                <livewire:shared.searchable-select 
                    wire:model.live="project" 
                    model="App\Models\Project" 
                    placeholder="All Projects" 
                    class="w-full"
                    :key="'leads-filter-proj-' . ($project ?? 'none')" 
                />
            </div>

            <!-- 3. Campaign -->
            <div class="flex flex-col space-y-1 w-full">
                <label class="block text-[11px] font-semibold text-muted truncate">Campaign</label>
                <livewire:shared.searchable-select 
                    wire:model.live="campaign" 
                    model="App\Models\Campaign" 
                    placeholder="All Campaigns" 
                    class="w-full"
                    :key="'leads-filter-camp-' . ($campaign ?? 'none')" 
                />
            </div>

            <!-- 4. Lead Source -->
            @canViewLeadSource
<div class="flex flex-col space-y-1 w-full">
                <label class="block text-[11px] font-semibold text-muted truncate">Lead Source</label>
                <livewire:shared.searchable-select 
                    wire:model.live="source" 
                    model="App\Models\LeadSource" 
                    placeholder="All Sources" 
                    class="w-full"
                    :key="'leads-filter-src-' . ($source ?? 'none')" 
                />
            </div>
@endcanViewLeadSource

            <!-- 5. Sales Team -->
            <div class="flex flex-col space-y-1 w-full">
                <label class="block text-[11px] font-semibold text-muted truncate">Team</label>
                <livewire:shared.searchable-select 
                    wire:model.live="team" 
                    model="App\Models\SalesTeam" 
                    placeholder="All Teams" 
                    class="w-full"
                    :key="'leads-filter-team-' . ($team ?? 'none')" 
                />
            </div>

            <!-- 6. Assigned Agent -->
            <div class="flex flex-col space-y-1 w-full">
                <label class="block text-[11px] font-semibold text-muted truncate">Assigned Agent</label>
                <livewire:shared.searchable-select 
                    wire:model.live="executive" 
                    model="App\Models\User" 
                    placeholder="All Agents" 
                    class="w-full"
                    :key="'leads-filter-user-' . ($executive ?? 'none')" 
                />
            </div>

            <!-- 7. Lead Status -->
            <div class="flex flex-col space-y-1 w-full">
                <label class="block text-[11px] font-semibold text-muted truncate">Lead Status</label>
                <livewire:shared.searchable-select 
                    wire:model.live="statusFilter"
                    :show-clear-option="false"
                    clear-value="all"
                    :options="$statusOptions" 
                    placeholder="All Statuses" 
                    class="w-full"
                    :key="'leads-filter-status-' . ($statusFilter ?? 'none')" 
                />
            </div>

            <!-- 8. Lifecycle Stage -->
            <div class="flex flex-col space-y-1 w-full">
                <label class="block text-[11px] font-semibold text-muted truncate">Lead Stage</label>
                <livewire:shared.searchable-select 
                    wire:model.live="stageFilter" 
                    :options="$stageOptions" 
                    placeholder="All Stages" 
                    class="w-full"
                    :key="'leads-filter-stage-' . ($stageFilter ?? 'none')" 
                />
            </div>

            <!-- 9. Lead Type -->
            <div class="flex flex-col space-y-1 w-full">
                <label class="block text-[11px] font-semibold text-muted truncate">Lead Type</label>
                <livewire:shared.searchable-select 
                    wire:model.live="leadType" 
                    :options="$typeOptions" 
                    placeholder="All Types" 
                    class="w-full"
                    :key="'leads-filter-type-' . ($leadType ?? 'none')" 
                />
            </div>

            <!-- 10. Date Range & SLA Quick Toggle -->
            <div class="flex flex-col space-y-1 w-full">
                <label class="block text-[11px] font-semibold text-muted truncate">SLA Flag</label>
                <label class="flex items-center space-x-1.5 h-8 text-xs text-ink cursor-pointer bg-canvas px-3 rounded-lg border border-border select-none">
                    <input type="checkbox" wire:model.live="sla_breached" class="rounded text-ink focus:ring-ink">
                    <span class="font-medium text-danger text-[11px]">SLA Breached Only</span>
                </label>
            </div>
        </div>
        @endif
        </div>
    </div>

    <!-- Active Filters Bar -->
    @php
        $defaultRange = $this->isClientContext() ? 'all' : 'month';
        $hasActiveFilters = $filterOrganizationId || $project || $campaign || $team || $source || $executive || $leadType || ($statusFilter !== 'all') || $stageFilter || $sla_breached || ($analyticsRange !== $defaultRange) || ($this->isClientContext() && !empty($deliveryFilter));
    @endphp
    @if($hasActiveFilters)
        <div class="flex items-center gap-1.5 flex-wrap px-1 text-xs">
            <span class="font-semibold text-muted text-[11px]">Active:</span>
            @if($analyticsRange !== $defaultRange)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                    Range: {{ ucfirst(str_replace('_', ' ', $analyticsRange)) }}
                    <button type="button" wire:click="$set('analyticsRange', '{{ $defaultRange }}')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                </span>
            @endif
            @if($filterOrganizationId)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                    Org: #{{ $filterOrganizationId }}
                    <button type="button" wire:click="$set('filterOrganizationId', '')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                </span>
            @endif
            @if($project)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                    Project: {{ $allProjects->firstWhere('id', $project)?->name ?? '#'.$project }}
                    <button type="button" wire:click="$set('project', '')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                </span>
            @endif
            @if($campaign)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                    Campaign: {{ $allCampaigns->firstWhere('id', $campaign)?->name ?? '#'.$campaign }}
                    <button type="button" wire:click="$set('campaign', '')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                </span>
            @endif
            @if(\App\Support\LeadSourceVisibility::allowed() && $source)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                    Source: #{{ $source }}
                    <button type="button" wire:click="$set('source', '')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                </span>
            @endif
            @if($executive)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                    Team Member: {{ ($builderTeamMembers ?? $eligibleAssignees)->firstWhere('id', $executive)?->name ?? '#'.$executive }}
                    <button type="button" wire:click="$set('executive', '')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                </span>
            @endif
            @if($statusFilter !== 'all')
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                    Status: {{ $statusOptions[$statusFilter] ?? ucfirst(str_replace('_', ' ', $statusFilter)) }}
                    <button type="button" wire:click="$set('statusFilter', 'all')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                </span>
            @endif
            @if($stageFilter)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                    Stage: {{ $stageOptions[$stageFilter] ?? ($mainStages[$stageFilter] ?? ucfirst(str_replace('_', ' ', $stageFilter))) }}
                    <button type="button" wire:click="$set('stageFilter', '')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                </span>
            @endif
            @if($this->isClientContext() && !empty($deliveryFilter))
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                    Delivery: {{ ucfirst($deliveryFilter) }}
                    <button type="button" wire:click="$set('deliveryFilter', '')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                </span>
            @endif
            @if($sla_breached)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-red-50 border border-red-200 text-[11px] font-medium text-red-700">
                    SLA Breached
                    <button type="button" wire:click="$set('sla_breached', false)" class="text-red-500 hover:text-red-800 cursor-pointer">&times;</button>
                </span>
            @endif
            <button type="button" wire:click="resetFilters" class="text-[11px] text-blue-600 hover:underline font-semibold ml-1 cursor-pointer">
                Clear All
            </button>
        </div>
    @endif
    @endif
    @if($isSalesExecutive)<div class="flex gap-2 text-xs items-center"><label for="lead-followup-filter">Follow-ups</label><x-ui.themed-select class="max-w-full" searchable  id="lead-followup-filter" wire:model.live="followupFilter" trigger-class="rounded-lg border-border"><option value="">All</option><option value="today">Today</option><option value="overdue">Overdue</option><option value="upcoming">Upcoming</option></x-ui.themed-select></div>@endif

    <!-- 7. LEAD DIRECTORY (TOOLBAR + KANBAN / ADVANCED TABLE) -->
    <div class="space-y-3">
        <!-- Toolbar with Search, Quick Filters & View Toggle -->
        <div class="bg-surface rounded-card border border-border p-3.5 shadow-2xs flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2 flex-1">
                <!-- Search Input -->
                <div class="relative w-48 sm:w-64">
                    <input type="search" name="leads_search_query" id="leads_search_query" autocomplete="off"
                        autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true"
                        wire:model.live.debounce.300ms="search" placeholder="Search code, name, phone, city..."
                        class="w-full h-8 pl-8 pr-3 bg-canvas text-ink text-xs rounded-lg border border-border focus:ring-1 focus:ring-ink focus:bg-surface">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-muted">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </div>

                <!-- Sort Dropdown -->
                <div class="flex items-center gap-1.5 shrink-0">
                    <span class="text-xs text-muted font-medium">Sort By:</span>
                    <x-ui.themed-select class="max-w-full" searchable  wire:model.live="sort_by" trigger-class="h-8 text-xs font-semibold rounded-lg border border-border bg-canvas text-ink px-2.5 py-0 focus:outline-none">
                        <option value="created_desc">Latest Created</option>
                        <option value="created_asc">Oldest Created</option>
                        <option value="score_desc">Priority Score: High to Low</option>
                        <option value="score_asc">Priority Score: Low to High</option>
                    </x-ui.themed-select>
                </div>

                <!-- SLA Quick Toggle -->
                <label class="flex items-center space-x-1.5 h-8 text-xs text-ink cursor-pointer bg-canvas px-3 rounded-lg border border-border select-none">
                    <input type="checkbox" wire:model.live="sla_breached" class="rounded text-ink focus:ring-ink">
                    <span class="font-medium text-danger text-[11px]">SLA Breached</span>
                </label>
            </div>

            <!-- View Toggle Segmented Control -->
            <div class="inline-flex p-1 bg-canvas rounded-lg border border-border shrink-0 self-end md:self-auto">
                <button type="button" wire:click="switchViewMode('kanban')"
                    class="flex items-center space-x-1.5 px-3 py-1 text-xs font-bold rounded-md transition cursor-pointer {{ $viewMode === 'kanban' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                    </svg>
                    <span>Kanban</span>
                </button>
                <button type="button" wire:click="switchViewMode('table')"
                    class="flex items-center space-x-1.5 px-3 py-1 text-xs font-bold rounded-md transition cursor-pointer {{ $viewMode === 'table' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                    <span>Table</span>
                </button>
            </div>
        </div>

        <!-- Directory View Content: Table or Kanban -->
        @if($viewMode === 'table')
            <!-- Advanced Server-Side Table -->
            <x-ui.advanced-table 
                :columns="$this->tableColumns()" 
                :rows="$tableLeads" 
                :quickFilters="$this->quickFilters()"
                :activeStatus="$statusFilter" 
                :visibleColumns="$visibleColumns" 
                :sortField="$sortField"
                :sortDirection="$sortDirection" 
                :showCheckboxes="true"
                :stickySelection="$this->isClientContext()"
                :selectedRows="$selectedRows"
                :selectAll="$selectAll"
                searchPlaceholder="Search leads by code, name, phone, city..."
                emptyTitle="No Leads Found" 
                emptyMessage="No leads matched your search and filter criteria.">
                <x-slot:action>
                    <div class="flex items-center gap-2">
                        @if($this->isClientContext())
                            <button type="button" wire:click="pushSelectedToClient" @disabled(empty($selectedRows)) wire:loading.attr="disabled"
                                class="px-3 h-8 bg-ink text-white text-xs font-semibold rounded-lg whitespace-nowrap disabled:opacity-50">
                                Push Selected to Client ({{ count($selectedRows) }})
                            </button>
                        @endif
                        @if(!$isSalesExecutive)
                            @if(!empty($selectedRows))
                                <button type="button" wire:click="openBulkAssignModal"
                                    class="px-3 h-8 bg-primary hover:bg-primary-dark text-white text-xs font-semibold rounded-lg transition shadow-2xs flex items-center gap-1.5 cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                                    <span>Assign Selected ({{ count($selectedRows) }})</span>
                                </button>
                                <button type="button" wire:click="unassignSelectedLeads"
                                    wire:confirm="Remove executive assignments from all selected leads? They will be returned to the unassigned pool."
                                    class="px-3 h-8 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 text-xs font-semibold rounded-lg transition shadow-2xs flex items-center gap-1.5 cursor-pointer"
                                    title="Unassign executive from selected leads (especially when no action was taken)">
                                    <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6" /></svg>
                                    <span>Unassign Selected ({{ count($selectedRows) }})</span>
                                </button>
                            @endif
                            @if(!$isBuilder && !$this->isClientContext())
                                <button type="button" wire:click="autoDistributeUnassignedLeads"
                                    class="px-3 h-8 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-semibold rounded-lg transition shadow-2xs flex items-center gap-1.5 cursor-pointer"
                                    title="Distribute unassigned leads using the Super Admin configured distribution rule">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    <span>Auto-Assign</span>
                                </button>
                            @endif
                        @endif
                        <x-ui.export-button target="exportExcel" class="text-xs" />
                        @if(!$isBuilder && !$isSalesExecutive)
                            <button type="button" wire:click="openPullLeadsModal"
                                class="px-3 h-8 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition shadow-2xs flex items-center gap-1.5 cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Add Lead</span>
                            </button>
                        @endif
                    </div>
                </x-slot:action>
            </x-ui.advanced-table>
        @else
            <!-- Contained Horizontal Kanban Board -->
            <div x-data="{
                    isMouseDown: false,
                    startX: 0,
                    scrollLeft: 0,
                    onMouseDown(e) {
                        if (e.button !== 0) return;
                        if (e.target.closest('button, input, select, a, [data-lead-id]')) return;
                        this.isMouseDown = true;
                        this.startX = e.pageX - $el.offsetLeft;
                        this.scrollLeft = $el.scrollLeft;
                    },
                    onMouseMove(e) {
                        if (!this.isMouseDown) return;
                        e.preventDefault();
                        const x = e.pageX - $el.offsetLeft;
                        const walk = (x - this.startX) * 1.5;
                        $el.scrollLeft = this.scrollLeft - walk;
                    },
                    onMouseUp() { this.isMouseDown = false; },
                    onMouseLeave() { this.isMouseDown = false; }
                }" 
                @mousedown="onMouseDown($event)" 
                @mousemove="onMouseMove($event)" 
                @mouseup="onMouseUp()"
                @mouseleave="onMouseLeave()" 
                :class="isMouseDown ? 'cursor-grabbing select-none' : 'cursor-grab'"
                class="w-full overflow-x-auto pb-4">
                
                <div class="flex items-start space-x-3 min-w-max">
                    <!-- Main Lifecycle Stages Columns -->
                    @foreach($mainStages as $stageKey => $stageLabel)
                        <div class="w-72 bg-canvas/60 rounded-card border border-border p-3 flex flex-col max-h-[calc(100vh-220px)] shrink-0">
                            <!-- Column Header -->
                            <div class="flex items-center justify-between pb-2.5 mb-2.5 border-b border-border">
                                <div class="flex items-center space-x-2">
                                    <span class="w-2.5 h-2.5 rounded-full {{ in_array($stageKey, ['new', 'duplicate', 'pending_credit', 'assigned']) ? 'bg-blue-500' : (in_array($stageKey, ['contacted', 'connected', 'interested', 'qualified']) ? 'bg-amber-500' : (in_array($stageKey, ['closed_won', 'booking', 'payment']) ? 'bg-emerald-500' : 'bg-purple-500')) }}"></span>
                                    <h3 class="text-xs font-bold text-ink tracking-tight">{{ $stageLabel }}</h3>
                                </div>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-surface text-ink border border-border shadow-2xs">
                                    {{ $stageCounts[$stageKey] ?? 0 }}
                                </span>
                            </div>

                            <!-- Cards Droppable Container -->
                            <div data-stage-key="{{ $stageKey }}" 
                                 x-data="{ 
                                    initSortable() { 
                                        new Sortable($el, { 
                                            disabled: @js($isBuilder), group: 'kanban',
                                            animation: 150, 
                                            ghostClass: 'opacity-50',
                                            onEnd: function(evt) { 
                                                const leadId = evt.item.getAttribute('data-lead-id'); 
                                                const toStage = evt.to.getAttribute('data-stage-key'); 
                                                $wire.updateLeadStage(leadId, toStage); 
                                            } 
                                        }); 
                                    } 
                                 }" 
                                 x-init="initSortable()"
                                 class="flex-1 overflow-y-auto space-y-2.5 pr-1 min-h-[140px]">
                                @forelse($stageLeads[$stageKey] ?? [] as $lead)
                                    @php
                                        $score = $lead->lead_score ?? 0;
                                        $scoreBadgeClass = $score >= 70
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                            : ($score >= 40 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-50 text-slate-600 border-slate-200');
                                    @endphp

                                    <div data-lead-id="{{ $lead->id }}" 
                                         @click="$dispatch('open-lead-detail', { id: {{ $lead->id }} })"
                                         class="bg-surface rounded-xl border border-border p-3 shadow-2xs hover:shadow-md transition cursor-pointer space-y-2 group relative border-l-4 {{ $lead->first_response_at ? 'border-l-emerald-500' : (\Carbon\Carbon::parse($lead->created_at)->diffInMinutes(now()) > 15 ? 'border-l-rose-500' : 'border-l-amber-500') }}">
                                        
                                        <!-- Top Row: Lead Code, Score & SLA -->
                                        <div class="flex items-center justify-between text-[10px]">
                                            <div class="flex items-center space-x-1.5">
                                                <span class="font-bold text-muted font-mono tracking-tight">{{ $lead->lead_code }}</span>
                                                <span class="px-1.5 py-0.2 rounded-full text-[9px] font-extrabold border {{ $scoreBadgeClass }}" title="Lead Priority Score: {{ $score }}/100">
                                                    ⚡ {{ $score }}
                                                </span>
                                            </div>

                                            <div class="flex items-center gap-1">
                                                @if($lead->first_response_at && (!\App\Models\Lead::usesSalesPanel() || $lead->created_at->diffInMinutes($lead->first_response_at) <= 15))
                                                    <span class="px-1.5 py-0.2 rounded-full bg-emerald-50 text-emerald-700 font-semibold text-[9px]">SLA Met</span>
                                                @elseif(\Carbon\Carbon::parse($lead->created_at)->diffInMinutes(now()) > 15)
                                                    <span class="px-1.5 py-0.2 rounded-full bg-rose-50 text-rose-700 font-bold text-[9px]">SLA Breached</span>
                                                @else
                                                    <span class="px-1.5 py-0.2 rounded-full bg-amber-50 text-amber-700 font-semibold text-[9px]">SLA Pending</span>
                                                @endif

                                                @if(auth()->user()?->status === 'active' && auth()->user()->hasAnyRole(['Super Admin', 'super-admin']))
                                                    <button type="button"
                                                        @click.stop="$dispatch('open-lead-journey', { id: {{ $lead->id }} })"
                                                        class="p-0.5 rounded hover:bg-canvas text-muted hover:text-ink transition cursor-pointer"
                                                        title="Show Complete Lead Journey Timeline">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Lead Name & City -->
                                        <div>
                                            <h4 class="text-xs font-bold text-ink group-hover:text-blue-600 transition truncate">
                                                {{ $lead->name }}
                                            </h4>
                                            <p class="text-[11px] text-muted truncate">
                                                {{ $lead->city ?: 'Location N/A' }} • {{ $lead->actual_property_type ?: ($lead->property_type ?: '—') }}
                                            </p>
                                        </div>

                                        <!-- Budget & Source -->
                                        <div class="flex items-center justify-between pt-0.5">
                                            <span class="text-xs font-extrabold text-ink font-mono">
                                                {{ $lead->budget_formatted }}
                                            </span>
                                            <div class="flex items-center gap-1">
                                                @if($lead->campaign?->name)
                                                    <span class="px-1.5 py-0.2 text-[9px] font-semibold rounded bg-indigo-50 text-indigo-700 border border-indigo-200 truncate max-w-[80px]" title="Campaign: {{ $lead->campaign->name }}">
                                                        {{ $lead->campaign->name }}
                                                    </span>
                                                @endif
                                                @canViewLeadSource
<span class="px-1.5 py-0.2 text-[9px] font-bold uppercase rounded bg-canvas text-muted border border-border">
                                                    {{ $lead->leadSource?->name ?: 'Direct' }}
                                                </span>
@endcanViewLeadSource
                                            </div>
                                        </div>

                                        <!-- Footer: Assigned Agent & Age -->
                                        <div class="flex items-center justify-between border-t border-border pt-1.5 text-[10px] text-muted">
                                            <div class="flex items-center space-x-1.5 min-w-0">
                                                <div class="h-4.5 w-4.5 rounded-full bg-ink text-white flex items-center justify-center font-bold text-[9px] shrink-0">
                                                    {{ strtoupper(substr($lead->assignedTo?->name ?? 'U', 0, 1)) }}
                                                </div>
                                                <span class="truncate font-medium text-ink">{{ $lead->assignedTo?->name ?: 'Unassigned' }}</span>
                                            </div>
                                            <span class="font-semibold text-muted shrink-0 font-mono">
                                                {{ (int) \Carbon\Carbon::parse($lead->created_at)->diffInDays(now()) }}d
                                            </span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center py-6 text-xs text-muted border border-dashed border-border rounded-xl">
                                        No leads in this stage
                                    </div>
                                @endforelse
                            </div>

                            <!-- Pagination Load More -->
                            @if(($stageCounts[$stageKey] ?? 0) > count($stageLeads[$stageKey] ?? []))
                                <div class="pt-2 text-center">
                                    <button type="button" wire:click="loadMore('{{ $stageKey }}')"
                                        class="text-[11px] font-bold text-muted hover:text-ink transition cursor-pointer">
                                        Load More (+20)
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <!-- Other / Closed Column -->
                    <div class="w-72 bg-canvas/60 rounded-card border border-border p-3 flex flex-col max-h-[calc(100vh-220px)] shrink-0">
                        <div class="flex items-center justify-between pb-2.5 mb-2.5 border-b border-border">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                                <h3 class="text-xs font-bold text-ink tracking-tight">Other / Terminal</h3>
                            </div>
                            <button type="button" wire:click="$toggle('otherColumnExpanded')"
                                class="text-xs text-muted hover:text-ink font-semibold cursor-pointer">
                                {{ $otherColumnExpanded ? 'Collapse' : 'Expand' }}
                            </button>
                        </div>

                        @if($otherColumnExpanded)
                            <div class="space-y-3 overflow-y-auto flex-1">
                                @foreach($otherStages as $oStageKey => $oStageLabel)
                                    <div class="border-t border-border pt-2">
                                        <div class="text-[10px] font-bold text-muted uppercase mb-1.5 flex items-center justify-between">
                                            <span>{{ $oStageLabel }}</span>
                                            <span class="px-1.5 py-0.2 rounded bg-surface border border-border font-mono">{{ $stageCounts[$oStageKey] ?? 0 }}</span>
                                        </div>
                                        <div data-stage-key="{{ $oStageKey }}"
                                            x-data="{ initSortable() { new Sortable($el, { disabled: @js($isBuilder), group: 'kanban',animation: 150, onEnd: function(evt) { const leadId = evt.item.getAttribute('data-lead-id'); const toStage = evt.to.getAttribute('data-stage-key'); $wire.updateLeadStage(leadId, toStage); } }); } }"
                                            x-init="initSortable()" class="space-y-2 min-h-[50px]">
                                            @foreach($stageLeads[$oStageKey] ?? [] as $oLead)
                                                <div data-lead-id="{{ $oLead->id }}"
                                                    @click="$dispatch('open-lead-detail', { id: {{ $oLead->id }} })"
                                                    class="p-2 bg-surface rounded-lg border border-border text-xs cursor-pointer hover:bg-canvas">
                                                    <div class="font-bold text-ink flex items-center justify-between">
                                                        <span>{{ $oLead->name }}</span>
                                                        <span class="text-[9px] px-1 rounded bg-canvas font-mono font-bold">⚡ {{ $oLead->lead_score ?? 0 }}</span>
                                                    </div>
                                                    <div class="text-[10px] text-muted">{{ $oLead->lead_code }} • {{ $oLead->budget_formatted }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="flex-1 flex flex-col items-center justify-center text-center p-6 text-muted space-y-2">
                                <svg class="w-8 h-8 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                <p class="text-xs">Closed Lost, Wrong Number, Rental, Replaced</p>
                                <button type="button" wire:click="$set('otherColumnExpanded', true)" class="text-xs font-bold text-ink underline cursor-pointer">
                                    View {{ array_sum(array_intersect_key($stageCounts, $otherStages)) }} Leads
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

        @elseif($activeMainTab === 'analytics')
    <!-- ANALYTICS DASHBOARD -->
    <div class="space-y-6">
        <!-- 1. ANALYTICS HEADER & ACTIONS -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-surface rounded-card border border-border p-4 shadow-2xs">
            <div>
                <h1 class="text-base sm:text-lg font-bold text-ink">Lead Analytics & Performance</h1>
                <p class="text-xs text-muted mt-0.5">Real-time reporting, stage progression, conversion insights, and team productivity.</p>
            </div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <!-- Date Range Selector -->
                <div class="flex items-center gap-1.5">
                    <span class="text-xs text-muted font-medium">Period:</span>
                    <x-ui.themed-select class="w-36 text-xs" searchable wire:model.live="analyticsRange" trigger-class="h-8 rounded-lg border-border">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="this_week">This Week</option>
                        <option value="last_week">Last Week</option>
                        <option value="month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="quarter">This Quarter</option>
                        <option value="year">This Year</option>
                        <option value="all">All Time</option>
                    </x-ui.themed-select>
                </div>

                <!-- Export Report Button -->
                <button type="button" wire:click="exportAnalyticsReport"
                    class="inline-flex items-center gap-1.5 px-3.5 h-8 text-xs font-semibold rounded-lg bg-surface hover:bg-canvas border border-border text-ink transition shadow-2xs cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Export Report</span>
                </button>
            </div>
        </div>

        <!-- 2. SIX KPI CARDS -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <!-- Total Leads -->
            <button type="button" wire:click="filterFromAnalytics('status', 'all')"
                class="bg-surface hover:bg-canvas border border-border hover:border-ink/30 rounded-xl p-3.5 text-left transition shadow-2xs cursor-pointer flex flex-col justify-between group">
                <div class="flex items-center justify-between gap-1 text-[11px] font-bold text-muted uppercase tracking-wider">
                    <span>Total Leads</span>
                    <span class="p-1 rounded bg-blue-50 text-blue-600 group-hover:bg-blue-100 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    </span>
                </div>
                <div class="mt-2">
                    <div class="text-2xl font-bold font-mono text-ink tracking-tight">
                        {{ number_format($analyticsData['kpis']['total']) }}
                    </div>
                    <div class="text-[10px] text-muted mt-0.5 flex items-center justify-between">
                        <span>All registered</span>
                        <span class="text-blue-600 group-hover:underline font-medium">View &rarr;</span>
                    </div>
                </div>
            </button>

            <!-- New Leads -->
            <button type="button" wire:click="filterFromAnalytics('status', 'new')"
                class="bg-surface hover:bg-canvas border border-border hover:border-blue-400 rounded-xl p-3.5 text-left transition shadow-2xs cursor-pointer flex flex-col justify-between group">
                <div class="flex items-center justify-between gap-1 text-[11px] font-bold text-muted uppercase tracking-wider">
                    <span>New Leads</span>
                    <span class="p-1 rounded bg-blue-50 text-blue-600 group-hover:bg-blue-100 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    </span>
                </div>
                <div class="mt-2">
                    <div class="text-2xl font-bold font-mono text-blue-600 tracking-tight">
                        {{ number_format($analyticsData['kpis']['new']) }}
                    </div>
                    <div class="text-[10px] text-muted mt-0.5 flex items-center justify-between">
                        <span>Awaiting action</span>
                        <span class="text-blue-600 group-hover:underline font-medium">View &rarr;</span>
                    </div>
                </div>
            </button>

            <!-- In Progress -->
            <button type="button" wire:click="filterFromAnalytics('status', 'in_progress')"
                class="bg-surface hover:bg-canvas border border-border hover:border-amber-400 rounded-xl p-3.5 text-left transition shadow-2xs cursor-pointer flex flex-col justify-between group">
                <div class="flex items-center justify-between gap-1 text-[11px] font-bold text-muted uppercase tracking-wider">
                    <span>In Progress</span>
                    <span class="p-1 rounded bg-amber-50 text-amber-600 group-hover:bg-amber-100 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </span>
                </div>
                <div class="mt-2">
                    <div class="text-2xl font-bold font-mono text-amber-600 tracking-tight">
                        {{ number_format($analyticsData['kpis']['in_progress']) }}
                    </div>
                    <div class="text-[10px] text-muted mt-0.5 flex items-center justify-between">
                        <span>Active followups</span>
                        <span class="text-amber-600 group-hover:underline font-medium">View &rarr;</span>
                    </div>
                </div>
            </button>

            <!-- Follow-ups Due -->
            <button type="button" wire:click="filterFromAnalytics('followup', 'overdue')"
                class="bg-surface hover:bg-canvas border border-border hover:border-purple-400 rounded-xl p-3.5 text-left transition shadow-2xs cursor-pointer flex flex-col justify-between group">
                <div class="flex items-center justify-between gap-1 text-[11px] font-bold text-muted uppercase tracking-wider">
                    <span>Follow-ups Due</span>
                    <span class="p-1 rounded bg-purple-50 text-purple-600 group-hover:bg-purple-100 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                </div>
                <div class="mt-2">
                    <div class="text-2xl font-bold font-mono text-purple-600 tracking-tight">
                        {{ number_format($analyticsData['kpis']['followups_due']) }}
                    </div>
                    <div class="text-[10px] text-muted mt-0.5 flex items-center justify-between">
                        <span>Pending / Overdue</span>
                        <span class="text-purple-600 group-hover:underline font-medium">View &rarr;</span>
                    </div>
                </div>
            </button>

            <!-- Closed Won -->
            <button type="button" wire:click="filterFromAnalytics('status', 'closed_won')"
                class="bg-surface hover:bg-canvas border border-border hover:border-emerald-400 rounded-xl p-3.5 text-left transition shadow-2xs cursor-pointer flex flex-col justify-between group">
                <div class="flex items-center justify-between gap-1 text-[11px] font-bold text-muted uppercase tracking-wider">
                    <span>Closed Won</span>
                    <span class="p-1 rounded bg-emerald-50 text-emerald-600 group-hover:bg-emerald-100 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                </div>
                <div class="mt-2">
                    <div class="text-2xl font-bold font-mono text-emerald-600 tracking-tight">
                        {{ number_format($analyticsData['kpis']['closed_won']) }}
                    </div>
                    <div class="text-[10px] text-muted mt-0.5 flex items-center justify-between">
                        <span>Won conversions</span>
                        <span class="text-emerald-600 group-hover:underline font-medium">View &rarr;</span>
                    </div>
                </div>
            </button>

            <!-- Lost Leads -->
            <button type="button" wire:click="filterFromAnalytics('status', 'lost')"
                class="bg-surface hover:bg-canvas border border-border hover:border-rose-400 rounded-xl p-3.5 text-left transition shadow-2xs cursor-pointer flex flex-col justify-between group">
                <div class="flex items-center justify-between gap-1 text-[11px] font-bold text-muted uppercase tracking-wider">
                    <span>Lost Leads</span>
                    <span class="p-1 rounded bg-rose-50 text-rose-600 group-hover:bg-rose-100 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </span>
                </div>
                <div class="mt-2">
                    <div class="text-2xl font-bold font-mono text-rose-600 tracking-tight">
                        {{ number_format($analyticsData['kpis']['lost']) }}
                    </div>
                    <div class="text-[10px] text-muted mt-0.5 flex items-center justify-between">
                        <span>Lost / Disqualified</span>
                        <span class="text-rose-600 group-hover:underline font-medium">View &rarr;</span>
                    </div>
                </div>
            </button>
        </div>

        <!-- 3. CHARTS ROW: LEAD TREND (LINE) & LEADS BY STAGE (DONUT) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Lead Trend Line Chart (2 cols on lg) -->
            <div class="lg:col-span-2 bg-surface rounded-card border border-border p-4 shadow-2xs flex flex-col justify-between">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-xs sm:text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                            <span>Lead Trend</span>
                            <span class="text-[11px] font-normal text-muted lowercase">({{ $trendGranularity }})</span>
                        </h2>
                        <p class="text-[11px] text-muted">Inflow volume across selected timeframe</p>
                    </div>

                    <!-- Granularity switchers: Daily / Weekly / Monthly -->
                    <div class="inline-flex items-center p-0.5 rounded-lg bg-canvas border border-border text-xs">
                        <button type="button" wire:click="$set('trendGranularity', 'daily')"
                            class="px-2.5 py-1 rounded-md font-medium transition cursor-pointer {{ $trendGranularity === 'daily' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                            Daily
                        </button>
                        <button type="button" wire:click="$set('trendGranularity', 'weekly')"
                            class="px-2.5 py-1 rounded-md font-medium transition cursor-pointer {{ $trendGranularity === 'weekly' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                            Weekly
                        </button>
                        <button type="button" wire:click="$set('trendGranularity', 'monthly')"
                            class="px-2.5 py-1 rounded-md font-medium transition cursor-pointer {{ $trendGranularity === 'monthly' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                            Monthly
                        </button>
                    </div>
                </div>

                <div x-data="{
                    initTrend() {
                        const canvas = this.$refs.trendCanvas;
                        if (!canvas) return;
                        window.initSafeChart(canvas, {
                            type: 'line',
                            data: {
                                labels: @js($analyticsData['trend']['labels']),
                                datasets: [{
                                    label: 'Leads Inflow',
                                    data: @js($analyticsData['trend']['counts']),
                                    borderColor: '#2563eb',
                                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                                    fill: true,
                                    tension: 0.35,
                                    borderWidth: 2.5,
                                    pointRadius: 3.5,
                                    pointHoverRadius: 6,
                                    pointBackgroundColor: '#2563eb',
                                    pointBorderColor: '#ffffff',
                                    pointBorderWidth: 1.5,
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        backgroundColor: '#111827',
                                        titleFont: { size: 12, weight: '600' },
                                        bodyFont: { size: 12 },
                                        padding: 8,
                                        cornerRadius: 6,
                                    }
                                },
                                scales: {
                                    x: {
                                        grid: { display: false },
                                        ticks: { font: { size: 11 }, maxRotation: 45 }
                                    },
                                    y: {
                                        beginAtZero: true,
                                        grid: { color: 'rgba(229, 231, 235, 0.5)' },
                                        ticks: { precision: 0, font: { size: 11 } }
                                    }
                                }
                            }
                        });
                    }
                }" x-init="initTrend()" wire:key="trend-chart-{{ $trendGranularity }}-{{ md5(json_encode($analyticsData['trend'])) }}" class="h-64 sm:h-72 w-full relative">
                    <canvas x-ref="trendCanvas"></canvas>
                </div>
            </div>

            <!-- Leads by Stage Donut (1 col on lg) -->
            <div class="bg-surface rounded-card border border-border p-4 shadow-2xs flex flex-col justify-between">
                <div class="mb-2">
                    <h2 class="text-xs sm:text-sm font-bold text-ink uppercase tracking-wider">Leads by Stage</h2>
                    <p class="text-[11px] text-muted">Current stage distribution</p>
                </div>

                <div x-data="{
                    initDonut() {
                        const canvas = this.$refs.stageCanvas;
                        if (!canvas) return;
                        window.initSafeChart(canvas, {
                            type: 'doughnut',
                            data: {
                                labels: @js($analyticsData['stages']['labels']),
                                datasets: [{
                                    data: @js($analyticsData['stages']['counts']),
                                    backgroundColor: @js($analyticsData['stages']['colors']),
                                    borderWidth: 2,
                                    borderColor: '#ffffff',
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '65%',
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        callbacks: {
                                            label: function(ctx) {
                                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                                const val = ctx.parsed;
                                                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                                return ` ${ctx.label}: ${val} (${pct}%)`;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }
                }" x-init="initDonut()" wire:key="donut-stage-{{ md5(json_encode($analyticsData['stages'])) }}" class="h-44 w-full relative my-auto">
                    <canvas x-ref="stageCanvas"></canvas>
                </div>

                <!-- Stage Breakdown List -->
                <div class="mt-3 max-h-40 overflow-y-auto divide-y divide-border/60 text-xs pr-1">
                    @foreach($analyticsData['stages']['breakdown'] as $stg)
                        <button type="button" wire:click="filterFromAnalytics('stage', '{{ $stg['key'] }}')"
                            class="w-full flex items-center justify-between py-1.5 px-1 hover:bg-canvas rounded transition cursor-pointer group text-left">
                            <div class="flex items-center gap-2 truncate">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $stg['color'] }};"></span>
                                <span class="font-medium text-ink group-hover:text-blue-600 truncate">{{ $stg['label'] }}</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0 font-mono text-[11px]">
                                <span class="font-bold text-ink">{{ number_format($stg['count']) }}</span>
                                <span class="text-muted w-10 text-right">{{ $stg['percentage'] }}%</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- 4. MIDDLE ROW: LEAD STATUS DISTRIBUTION & LEAD SOURCE -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Lead Status Distribution -->
            <div class="bg-surface rounded-card border border-border p-4 shadow-2xs space-y-4">
                <div>
                    <h2 class="text-xs sm:text-sm font-bold text-ink uppercase tracking-wider">Lead Status Distribution</h2>
                    <p class="text-[11px] text-muted">Click any status to filter leads</p>
                </div>

                <div class="space-y-2.5">
                    @foreach($analyticsData['status_distribution'] as $st)
                        <button type="button" wire:click="filterFromAnalytics('status', '{{ $st['key'] }}')"
                            class="w-full text-left group p-1.5 hover:bg-canvas rounded-lg transition cursor-pointer">
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-semibold text-ink group-hover:text-blue-600 flex items-center gap-1.5">
                                    {{ $st['label'] }}
                                </span>
                                <span class="font-mono text-[11px] text-muted">
                                    <strong class="text-ink font-bold">{{ number_format($st['count']) }}</strong> ({{ $st['percentage'] }}%)
                                </span>
                            </div>
                            <div class="w-full bg-neutral-100 rounded-full h-2 overflow-hidden">
                                <div class="h-2 rounded-full transition-all duration-300 {{ $st['key'] === 'closed_won' ? 'bg-emerald-500' : ($st['key'] === 'lost' ? 'bg-rose-500' : ($st['key'] === 'in_progress' ? 'bg-amber-500' : ($st['key'] === 'new' ? 'bg-blue-500' : 'bg-neutral-400'))) }}"
                                    style="width: {{ max($st['percentage'], $st['count'] > 0 ? 3 : 0) }}%"></div>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Lead Source Breakdown -->
            <div class="bg-surface rounded-card border border-border p-4 shadow-2xs space-y-4">
                <div>
                    <h2 class="text-xs sm:text-sm font-bold text-ink uppercase tracking-wider">Lead Source Breakdown</h2>
                    <p class="text-[11px] text-muted">Acquisition channels performance</p>
                </div>

                <div class="space-y-3">
                    @foreach($analyticsData['sources'] as $src)
                        <button type="button" wire:click="filterFromAnalytics('source', '{{ $src['key'] }}')"
                            class="w-full text-left group p-2 hover:bg-canvas rounded-lg border border-border/60 transition cursor-pointer">
                            <div class="flex items-center justify-between text-xs mb-1.5">
                                <div class="flex items-center gap-2">
                                    @if(str_contains(strtolower($src['label']), 'meta') || str_contains(strtolower($src['label']), 'facebook') || str_contains(strtolower($src['label']), 'instagram'))
                                        <span class="w-6 h-6 rounded-md bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-[10px]">M</span>
                                    @elseif(str_contains(strtolower($src['label']), 'google'))
                                        <span class="w-6 h-6 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-[10px]">G</span>
                                    @elseif(str_contains(strtolower($src['label']), 'website'))
                                        <span class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-[10px]">W</span>
                                    @elseif(str_contains(strtolower($src['label']), 'manual') || str_contains(strtolower($src['label']), 'upload'))
                                        <span class="w-6 h-6 rounded-md bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-[10px]">U</span>
                                    @else
                                        <span class="w-6 h-6 rounded-md bg-neutral-100 text-neutral-600 flex items-center justify-center font-bold text-[10px]">O</span>
                                    @endif
                                    <span class="font-semibold text-ink group-hover:text-blue-600">{{ $src['label'] }}</span>
                                </div>
                                <div class="font-mono text-xs">
                                    <strong class="text-ink font-bold">{{ number_format($src['count']) }}</strong>
                                    <span class="text-muted text-[11px]">({{ $src['percentage'] }}%)</span>
                                </div>
                            </div>
                            <div class="w-full bg-neutral-100 rounded-full h-1.5 overflow-hidden">
                                <div class="h-1.5 rounded-full bg-ink transition-all duration-300"
                                    style="width: {{ max($src['percentage'], $src['count'] > 0 ? 3 : 0) }}%"></div>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- 5. TEAM PERFORMANCE LEADERBOARD TABLE -->
        <div class="bg-surface rounded-card border border-border p-4 shadow-2xs space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-xs sm:text-sm font-bold text-ink uppercase tracking-wider">Team Performance & Conversion</h2>
                    <p class="text-[11px] text-muted">Assigned volume, responsiveness, and conversion rate by team member</p>
                </div>
                <span class="text-[11px] text-muted">{{ count($analyticsData['team_performance']) }} Members Tracked</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-border text-[11px] font-bold text-muted uppercase tracking-wider bg-canvas">
                            <th class="py-2.5 px-3">Team Member</th>
                            <th class="py-2.5 px-3 text-right">Assigned Leads</th>
                            <th class="py-2.5 px-3 text-right">Contacted Leads</th>
                            <th class="py-2.5 px-3 text-right">Converted Won</th>
                            <th class="py-2.5 px-3 text-right">Conversion Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @forelse($analyticsData['team_performance'] as $tp)
                            <tr class="hover:bg-canvas transition cursor-pointer group" wire:click="filterFromAnalytics('executive', '{{ $tp['id'] }}')">
                                <td class="py-2.5 px-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-neutral-200 text-ink font-bold flex items-center justify-center text-xs shrink-0">
                                            {{ strtoupper(substr($tp['name'], 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-ink group-hover:text-blue-600">{{ $tp['name'] }}</div>
                                            <div class="text-[10px] text-muted">{{ $tp['email'] }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-ink">
                                    {{ number_format($tp['assigned']) }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono text-amber-600 font-semibold">
                                    {{ number_format($tp['contacted']) }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono text-emerald-600 font-bold">
                                    {{ number_format($tp['converted']) }}
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <div class="inline-flex items-center gap-2 justify-end">
                                        <div class="w-16 bg-neutral-100 rounded-full h-1.5 overflow-hidden hidden sm:block">
                                            <div class="h-1.5 rounded-full {{ $tp['conversion_rate'] >= 20 ? 'bg-emerald-500' : ($tp['conversion_rate'] >= 10 ? 'bg-blue-500' : 'bg-neutral-400') }}"
                                                style="width: {{ min($tp['conversion_rate'], 100) }}%"></div>
                                        </div>
                                        <span class="font-mono font-bold {{ $tp['conversion_rate'] >= 20 ? 'text-emerald-600' : ($tp['conversion_rate'] >= 10 ? 'text-blue-600' : 'text-ink') }}">
                                            {{ $tp['conversion_rate'] }}%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-muted">
                                    No active sales executives or assigned leads found for this scope.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
<!-- 8. MODALS & SLIDE-OVERS -->
    <!-- Slide-over Detail Component -->
    <livewire:leads.lead-detail />

    <!-- Full Lead Journey Timeline Drawer Component -->
    <livewire:leads.lead-timeline />

    <!-- Lead Details Display Configuration Modal -->
    <livewire:leads.lead-display-config-modal />

    <!-- Super Admin Lead Master Editor & Inspector Modal -->
    <livewire:leads.super-admin-lead-modal />

    <!-- Confirmation Modal for Terminal / Exception Stage Transition -->
    @if($showConfirmModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 overflow-y-auto overscroll-contain">
            <div class="bg-surface rounded-card border border-border p-6 max-w-md w-full shadow-2xl space-y-4 max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain">
                <div class="flex items-center space-x-3 text-amber-500">
                    <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <h3 class="text-base font-bold text-ink">Confirm Stage Change</h3>
                </div>
                <p class="text-xs text-muted">
                    Moving a lead to <span class="font-bold text-ink">{{ ucfirst(str_replace('_', ' ', $pendingToStage)) }}</span> is an exception/terminal transition. Are you sure you want to proceed?
                </p>
                <div class="flex items-center justify-end space-x-3 pt-2">
                    <x-ui.button wire:click="cancelTerminalTransition" variant="secondary" class="text-xs">Cancel</x-ui.button>
                    <x-ui.button wire:click="confirmTerminalTransition" variant="danger" class="text-xs">Confirm Move</x-ui.button>
                </div>
            </div>
        </div>
    @endif

    <!-- Meta Lead Ads Sync & Manual Add Drawer -->
    @if(!$isBuilder && !$isSalesExecutive)
    <x-ui.offcanvas wire:model="showPullLeadsModal" name="pull-leads-drawer" title="Add / Ingest Leads"
        subtitle="Pull leads from Meta Ads (Facebook & Instagram) or create a single manual lead entry." maxWidth="lg">
        <x-slot:headerIcon>
            <div class="p-1.5 rounded-lg bg-blue-50 text-blue-600 border border-blue-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </div>
        </x-slot:headerIcon>

        <!-- Segmented Mode Control -->
        @if(!$this->isClientContext())
            <div class="inline-flex p-1 bg-canvas rounded-lg border border-border w-full mb-4">
                <button type="button" wire:click="$set('pullMode', 'meta')"
                    class="flex-1 py-1 text-xs font-bold rounded-md transition cursor-pointer {{ $pullMode === 'meta' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                    Meta Ads Pull
                </button>
                <button type="button" wire:click="$set('pullMode', 'manual')"
                    class="flex-1 py-1 text-xs font-bold rounded-md transition cursor-pointer {{ $pullMode === 'manual' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                    {{ \App\Support\ClientFacingText::label('Single Manual Entry') }}
                </button>
            </div>
        @endif

        @if($pullMode === 'meta')
            <div class="space-y-4 text-xs">
                <div>
                    <label class="font-bold text-ink">Select Connected Meta Account</label>
                    @php
                        $accOptions = [];
                        foreach ($portalAccounts as $acc) {
                            $accOptions[(string) $acc->id] = $acc->name . ' (' . ucfirst($acc->type) . ')';
                        }
                    @endphp
                    @if(empty($accOptions))
                        <div class="p-3 bg-canvas rounded-lg border border-border text-muted text-[11px] mt-1 space-y-1">
                            <p>No Meta Portal Accounts configured yet.</p>
                            <a href="{{ route('settings.integrations') }}" class="font-bold text-blue-600 hover:underline block">
                                &rarr; Configure Meta Page Access Token in Integrations
                            </a>
                        </div>
                    @else
                        <x-ui.themed-select wire:model.live="selectedPortalAccountId" :options="$accOptions"
                            placeholder="Choose Account" class="w-full mt-1" />
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-ink">Select Lead Form</label>
                        @php
                            $formOptions = ['' => 'All Active Forms (Recommended)'];
                            if (isset($activeFormMappings) && $activeFormMappings->isNotEmpty()) {
                                foreach ($activeFormMappings as $m) {
                                    $formOptions[$m->form_id] = $m->form_name;
                                }
                            }
                            $formOptions['custom'] = 'Custom Form ID...';
                        @endphp
                        <x-ui.themed-select class="w-full" searchable  wire:model.live="selectedFormOption"
                            trigger-class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1 font-medium">
                            @foreach($formOptions as $val => $lbl)
                                <option value="{{ $val }}">{{ $lbl }}</option>
                            @endforeach
                        </x-ui.themed-select>
                    </div>
                    <div>
                        <label class="font-bold text-ink">Max Leads to Pull</label>
                        <x-ui.themed-select class="w-full" searchable  wire:model="pullLimit"
                            trigger-class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1 font-medium">
                            <option value="10">10 Most Recent</option>
                            <option value="25">25 Leads</option>
                            <option value="50">50 Leads</option>
                            <option value="100">100 Leads</option>
                            <option value="250">250 Leads</option>
                        </x-ui.themed-select>
                    </div>
                </div>

                @if($selectedFormOption === 'custom')
                    <div>
                        <label class="font-bold text-ink">Custom Form ID</label>
                        <input type="text" wire:model="customFormId" placeholder="e.g. 1144292554640400"
                            class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs font-mono mt-1">
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-ink">Target Project</label>
                        @php
                            $projOptions = ['' => 'Default / Auto from Mapping'];
                            foreach ($allProjects as $p) {
                                $projOptions[(string) $p->id] = $p->name;
                            }
                        @endphp
                        <x-ui.themed-select wire:model="syncProjectId" :options="$projOptions" searchable="true"
                            placeholder="Auto from Mapping" class="w-full mt-1" />
                    </div>
                    <div>
                        <label class="font-bold text-ink">Target Campaign</label>
                        @php
                            $campOptions = ['' => 'Auto from Meta Campaign'];
                            foreach ($allCampaigns as $c) {
                                $campOptions[(string) $c->id] = $c->name;
                            }
                        @endphp
                        <x-ui.themed-select wire:model="syncCampaignId" :options="$campOptions" searchable="true"
                            placeholder="Auto from Meta Campaign" class="w-full mt-1" />
                    </div>
                </div>

                @if($pullSummary)
                    <div class="p-3 bg-canvas rounded-xl border border-border space-y-2 mt-3">
                        <div class="font-bold text-ink flex items-center justify-between text-xs">
                            <span>Sync Results</span>
                            <span class="text-emerald-600 font-bold">✓ Complete</span>
                        </div>
                        <div class="grid grid-cols-4 gap-2 text-center pt-1">
                            <div class="bg-surface p-2 rounded-lg border border-border">
                                <div class="text-base font-bold text-ink">{{ $pullSummary['fetched'] }}</div>
                                <div class="text-[10px] text-muted">Fetched</div>
                            </div>
                            <div class="bg-surface p-2 rounded-lg border border-emerald-200">
                                <div class="text-base font-bold text-emerald-600">{{ $pullSummary['created'] }}</div>
                                <div class="text-[10px] text-muted">Created</div>
                            </div>
                            <div class="bg-surface p-2 rounded-lg border border-amber-200">
                                <div class="text-base font-bold text-amber-600">{{ $pullSummary['duplicates'] }}</div>
                                <div class="text-[10px] text-muted">Duplicates</div>
                            </div>
                            <div class="bg-surface p-2 rounded-lg border border-border">
                                <div class="text-base font-bold text-red-500">{{ $pullSummary['errors'] }}</div>
                                <div class="text-[10px] text-muted">Errors</div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <!-- Single Manual Lead Creation Form -->
            <form wire:submit.prevent="createManualLead" class="space-y-3 text-xs">
                <div>
                    <label class="font-bold text-ink">Contact Name <span class="text-rose-500">*</span></label>
                    <input type="text" wire:model="manualName" placeholder="Full Name"
                        class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1">
                    @error('manualName') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-ink">Mobile Number <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="manualMobile" placeholder="e.g. 9876543210"
                            class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs font-mono mt-1">
                        @error('manualMobile') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="font-bold text-ink">Email Address</label>
                        <input type="email" wire:model="manualEmail" placeholder="contact@example.com"
                            class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-ink">Associated Project <span class="text-rose-500">*</span></label>
                        <x-ui.themed-select class="w-full" searchable  wire:model.live="manualProjectId" trigger-class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1">
                            <option value="">Select Project</option>
                            @foreach($allProjects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </x-ui.themed-select>
                        @error('manualProjectId') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    @canViewLeadSource
<div>
                        <label class="font-bold text-ink">Lead Source</label>
                        <x-ui.themed-select class="w-full" searchable  wire:model="manualLeadSourceId" trigger-class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1">
                            <option value="">Direct Entry</option>
                            @foreach($allLeadSources as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </x-ui.themed-select>
                    </div>
@endcanViewLeadSource
                </div>

                @if($this->isClientContext())
                    <div><label class="font-bold text-ink">Campaign</label><x-ui.themed-select wire:model="manualCampaignId" :options="$allCampaigns->where('project_id', $manualProjectId)->pluck('name', 'id')->all()" placeholder="Use project campaign" class="w-full mt-1" />@error('manualCampaignId')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</div>
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="font-bold text-ink">City</label>
                        <input type="text" wire:model="manualCity" placeholder="Mumbai"
                            class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1">
                    </div>
                    @if(\App\Support\FinancialVisibility::allowed())
                        <div>
                            <label class="font-bold text-ink">Budget</label>
                            <input type="text" wire:model="manualBudget" placeholder="₹75.0L"
                                class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1">
                            @error('manualBudget') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                            <p class="text-[10px] text-muted mt-1">Rupees, lakh or crore (e.g. 5000000 or 50 Lakh).</p>
                        </div>
                    @endif
                    <div>
                        <label class="font-bold text-ink">Property Type</label>
                        <input type="text" wire:model="manualPropertyType" placeholder="2 BHK"
                            class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1">
                    </div>
                </div>

                <div>
                    <label class="font-bold text-ink">Client Requirement / Inquiry Notes</label>
                    <textarea wire:model="manualRequirement" rows="2" placeholder="Specific requirements, configuration, or preferences..."
                        class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink text-xs mt-1"></textarea>
                </div>

                <div class="pt-2 flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition shadow-2xs cursor-pointer">
                        Save &amp; Add Lead
                    </button>
                </div>
            </form>
        @endif

        <x-slot:footer>
            <div class="flex items-center justify-end gap-2 w-full">
                <button type="button" wire:click="closePullLeadsModal"
                    class="px-3.5 py-1.5 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer">
                    Close
                </button>

                @if($pullMode === 'meta')
                    <button type="button" wire:click="pullMetaLeads" wire:loading.attr="disabled"
                        class="px-3.5 py-1.5 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition shadow-2xs flex items-center gap-1.5 disabled:opacity-60 cursor-pointer">
                        <svg wire:loading wire:target="pullMetaLeads" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="pullMetaLeads">Pull &amp; Ingest Meta Leads</span>
                        <span wire:loading wire:target="pullMetaLeads">Fetching Graph API...</span>
                    </button>
                @endif
            </div>
        </x-slot:footer>
    </x-ui.offcanvas>
    @endif

    <!-- Lead Manual & Bulk Assignment Modal -->
    @if($showAssignModal)
        <div class="fixed inset-0 z-[80] overflow-y-auto bg-ink/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-surface rounded-2xl border border-border shadow-2xl max-w-md w-full p-6 space-y-4 max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-ink">Assign Leads to Team Member</h3>
                            <p class="text-[11px] text-muted">
                                Assigning <span class="font-bold text-primary">{{ count($selectedLeadIds) }}</span> lead(s) internally.
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeAssignModal" class="text-muted hover:text-ink cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-3">
                    @if(isset($assignTeams) && count($assignTeams) > 0)
                        <div>
                            <label class="block text-xs font-semibold text-ink mb-1">Filter by Team (Optional)</label>
                            <livewire:shared.searchable-select
                                wire:model.live="assignTeamId"
                                class="block w-full"
                                :options="$assignTeams->pluck('name', 'id')->all()"
                                placeholder="All Teams / Members"
                                :key="'assignment-team-select'"
                            />
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">Select Sales Agent / Member <span class="text-red-500">*</span></label>
                        <livewire:shared.searchable-select
                            wire:model.live="assignUserId"
                            class="block w-full"
                            :options="$eligibleAssignees->mapWithKeys(fn($member) => [$member->id => $member->name . ' (' . $member->email . ')'])->all()"
                            placeholder="Choose Eligible Team Member"
                            :key="'assignment-member-select-' . ($assignTeamId ?? 'all')"
                        />
                        @error('assignUserId') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                    </div>

                    @if(count($selectedLeadIds) === 1)
                        @php
                            $singleLead = \App\Models\Lead::find($selectedLeadIds[0]);
                            $assignHistory = $singleLead ? \App\Models\LeadAssignment::where('lead_id', $singleLead->id)->with(['assignee', 'assigner'])->latest('assigned_at')->take(5)->get() : collect();
                        @endphp
                        @if($assignHistory->isNotEmpty())
                            <div class="p-3 rounded-lg bg-canvas border border-border space-y-1.5 text-xs">
                                <span class="text-[10px] font-bold text-muted uppercase tracking-wider block">Lead Assignment History</span>
                                <div class="space-y-1 max-h-28 overflow-y-auto">
                                    @foreach($assignHistory as $ah)
                                        <div class="flex items-center justify-between text-[11px] py-0.5 border-b border-border/50 last:border-0">
                                            <span class="font-medium text-ink">{{ $ah->assignee?->name ?? 'User #' . $ah->assigned_to }}</span>
                                            <span class="text-muted text-[10px] font-mono">{{ \Carbon\Carbon::parse($ah->assigned_at)->format('M d, H:i') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif

                    <div class="p-3 rounded-lg bg-canvas border border-border text-[11px] text-muted space-y-1">
                        <div class="flex items-center gap-1.5 font-semibold text-ink">
                            <svg class="w-3.5 h-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Credit &amp; Assignment Policy:</span>
                        </div>
                        <p>Internal team assignments do not consume extra client credits. The assignment will be recorded in the audit history and the agent will be notified.</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                    <button type="button" wire:click="closeAssignModal"
                        class="px-3.5 py-1.5 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:bg-surface transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" wire:click="executeAssignment"
                        class="px-4 py-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-xs font-semibold transition shadow-2xs cursor-pointer inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Confirm Assignment</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Unassign Sales Executive Modal -->
    @if($showUnassignModal && $unassignLeadModel)
        <div class="fixed inset-0 z-[80] overflow-y-auto bg-ink/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-surface rounded-2xl border border-border shadow-2xl max-w-md w-full p-6 space-y-4 max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-ink">Remove Executive Assignment</h3>
                            <p class="text-[11px] text-muted">Unassign sales executive and return lead to unassigned pool</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeUnassignModal" class="text-muted hover:text-ink cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Lead & Assignment Details Summary -->
                <div class="p-3 rounded-xl bg-canvas border border-border space-y-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-mono font-bold text-ink">{{ $unassignLeadModel->lead_code }}</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                            {{ ucfirst(str_replace('_', ' ', $unassignLeadModel->current_stage)) }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-[10px] text-muted block">Contact Name</span>
                            <span class="font-semibold text-ink">{{ $unassignLeadModel->name }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-muted block">Mobile Number</span>
                            <span class="font-mono font-semibold text-ink">{{ $unassignLeadModel->mobile }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-muted block">Assigned Executive</span>
                            <span class="font-semibold text-ink">{{ $unassignLeadModel->assignedTo?->name ?? 'User #' . $unassignLeadModel->assigned_to }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-muted block">Executive Action Status</span>
                            @if($unassignLeadModel->executive_first_response_at || $unassignLeadModel->first_response_at)
                                <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">Action Taken</span>
                            @else
                                <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">No Action Taken</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if(!$unassignLeadModel->executive_first_response_at && !$unassignLeadModel->first_response_at)
                    <div class="p-3 rounded-xl bg-amber-50/80 border border-amber-200 text-amber-900 text-[11px] space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>Executive Inactive / No Action</span>
                        </div>
                        <p class="text-amber-800 leading-relaxed">
                            The assigned sales executive has not responded or taken any action on this lead. Removing this assignment will reset the lead back to unassigned status so it can be manually reassigned or auto-distributed.
                        </p>
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-semibold text-ink mb-1">
                        Reason / Remarks
                    </label>
                    <input type="text" wire:model="unassignReason"
                        placeholder="e.g. Sales executive inactive / no action taken, reassignment requested"
                        class="w-full px-3 py-2 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-1 focus:ring-primary focus:border-primary transition" />
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                    <button type="button" wire:click="closeUnassignModal"
                        class="px-3.5 py-1.5 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:bg-surface transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" wire:click="executeUnassignment"
                        class="px-4 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold transition shadow-2xs cursor-pointer inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Confirm Unassign</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Return Lead to Super Admin (Replacement Request) Modal -->
    @if($showReturnLeadModal && $returnLeadModel)
        <div class="fixed inset-0 z-[80] overflow-y-auto bg-ink/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-surface rounded-2xl border border-border shadow-2xl max-w-lg w-full p-6 space-y-4 max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H4m0 0l4-4m-4 4l4 4m6 4v1a3 3 0 003 3h3a3 3 0 003-3V7a3 3 0 00-3-3h-3a3 3 0 00-3 3v1" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-ink">Return Lead to Super Admin</h3>
                            <p class="text-[11px] text-muted">Submit lead replacement request for review &amp; credit refund</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeReturnLeadModal" class="text-muted hover:text-ink cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                @foreach($errors->all() as $error)<p class="text-xs text-red-700" role="alert">{{ $error }}</p>@endforeach
                @if($isBuilder)<textarea aria-label="Supporting information" wire:model="returnSupportingInfo" class="w-full text-xs rounded-lg border-border" placeholder="Supporting information (optional)"></textarea>@endif
                <!-- Lead Details Summary -->
                <div class="p-3 rounded-xl bg-canvas border border-border space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-mono font-bold text-ink">{{ $returnLeadModel->lead_code }}</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                            {{ ucfirst(str_replace('_', ' ', $returnLeadModel->current_stage)) }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-[10px] text-muted block">Contact Name</span>
                            <span class="font-semibold text-ink">{{ $returnLeadModel->name }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-muted block">Mobile Number</span>
                            <span class="font-mono font-semibold text-ink">{{ $returnLeadModel->mobile }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-muted block">Project</span>
                            <span class="text-ink">{{ $returnLeadModel->project_name }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-muted block">Delivered At</span>
                            <span class="font-mono text-muted text-[11px]">{{ $returnLeadModel->created_at?->format('d M Y, H:i') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Replacement Form -->
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">
                            Replacement Reason <span class="text-rose-500">*</span>
                        </label>
                        <x-ui.themed-select
                            wire:model="returnReasonId"
                            :options="$replacementReasons->pluck('label', 'id')->all()"
                            :searchable="true"
                            placeholder="-- Select Reason --"
                            class="w-full"
                        />
                        @error('returnReasonId') <span class="text-rose-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">
                            Remarks / Unsuitability Details
                        </label>
                        <textarea wire:model="returnRemarks" rows="3"
                            placeholder="Provide details for Super Admin review (e.g. customer unreachable after 3 attempts, invalid number, duplicate lead)..."
                            class="w-full px-3 py-2 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-1 focus:ring-primary focus:border-primary transition"></textarea>
                        @error('returnRemarks') <span class="text-rose-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="p-3 rounded-xl bg-amber-50/70 border border-amber-200 text-amber-800 text-[11px] space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Super Admin Review Policy</span>
                        </div>
                        <p class="text-amber-700 leading-relaxed">
                            Submitting this return request places the claim into the Super Admin Replacement Review Queue. Once reviewed and approved by Super Admin, the lead credit will be refunded to your client wallet and a replacement lead will be generated per policy.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                    <button type="button" wire:click="closeReturnLeadModal"
                        class="px-3.5 py-1.5 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:bg-surface transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitReturnLead"
                        class="px-4 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold transition shadow-2xs cursor-pointer inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Return Lead to Super Admin</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- CDN Script for SortableJS (Kanban drag-and-drop) -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    @endif
</div>
