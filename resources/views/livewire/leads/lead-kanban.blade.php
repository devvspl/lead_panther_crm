<div class="space-y-6">
    <!-- 1. COMPACT PAGE HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-surface rounded-card border border-border p-4 shadow-2xs">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-lg font-bold tracking-tight text-ink">{{ ($isBuilder || $isSalesExecutive) ? 'My Leads' : 'Leads' }}</h1>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                    {{ $isSalesExecutive ? 'My Assigned Pipeline' : ($isBuilder ? 'Organization Pipeline' : 'Live Pipeline') }}
                </span>
            </div>
            <p class="text-xs text-muted mt-0.5">
                {{ $isSalesExecutive ? 'Track customer interactions, schedule follow-ups and complete deal lifecycle.' : ($isBuilder ? 'Manage organization leads, team assignments, follow-ups and replacement claims.' : 'Manage leads, assignments, follow-ups and conversions.') }}
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <x-ui.date-range-picker 
                wire:model.live="analyticsRange" 
                wire:custom-from="analyticsCustomFrom"
                wire:custom-to="analyticsCustomTo" 
                placeholder="This Month" 
            />

            <x-ui.export-button target="exportExcel" label="Export Excel" />

            @if(!$isBuilder && !$isSalesExecutive)
                <button type="button" wire:click="openPullLeadsModal"
                    class="inline-flex items-center gap-1.5 px-3.5 h-8 bg-ink hover:bg-neutral-800 text-white rounded-lg text-xs font-semibold transition shadow-2xs cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Add / Ingest Lead</span>
                </button>
            @endif
        </div>
    </div>

    <!-- 2. COMPACT GLOBAL FILTERS -->
    <div class="bg-surface rounded-card border border-border p-4 shadow-2xs space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <span class="text-xs font-bold text-ink uppercase tracking-wider">Filters</span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="$refresh"
                    class="inline-flex items-center gap-1 px-3 py-1 bg-ink text-white rounded-lg text-xs font-semibold hover:bg-neutral-800 transition cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Apply</span>
                </button>
                <button type="button" wire:click="resetFilters"
                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-surface hover:bg-canvas border border-border text-muted hover:text-ink rounded-lg text-xs font-semibold transition cursor-pointer">
                    <span>Reset</span>
                </button>
            </div>
        </div>

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

            $stageOptions = array_merge(['' => 'All Stages'], $mainStages);
        @endphp

        @if($isBuilder)
            <!-- 6 Compact Filters for Builder / Developer -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2.5 items-start">
                <!-- 1. Date Range -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Date Range</label>
                    <x-ui.date-range-picker 
                        wire:model.live="analyticsRange" 
                        wire:custom-from="analyticsCustomFrom"
                        wire:custom-to="analyticsCustomTo" 
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
                        :key="'b-leads-proj-'.($project ?? 'none')" 
                    />
                </div>

                <!-- 3. Lead Source -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Lead Source</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="source" 
                        model="App\Models\LeadSource" 
                        placeholder="All Sources" 
                        class="w-full"
                        :key="'b-leads-src-'.($source ?? 'none')" 
                    />
                </div>

                <!-- 4. Team Member / Assigned -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Team Member</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="executive" 
                        :options="$eligibleAssignees->pluck('name', 'id')->toArray()" 
                        placeholder="All Members" 
                        class="w-full"
                        :key="'b-leads-exec-'.($executive ?? 'none')" 
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
                        :key="'b-leads-status-'.($statusFilter ?? 'none')" 
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
                        :key="'b-leads-stage-'.($stageFilter ?? 'none')" 
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
                    :key="'leads-filter-org-'.($filterOrganizationId ?? 'none')" 
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
                    :key="'leads-filter-proj-'.($project ?? 'none')" 
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
                    :key="'leads-filter-camp-'.($campaign ?? 'none')" 
                />
            </div>

            <!-- 4. Lead Source -->
            <div class="flex flex-col space-y-1 w-full">
                <label class="block text-[11px] font-semibold text-muted truncate">Lead Source</label>
                <livewire:shared.searchable-select 
                    wire:model.live="source" 
                    model="App\Models\LeadSource" 
                    placeholder="All Sources" 
                    class="w-full"
                    :key="'leads-filter-src-'.($source ?? 'none')" 
                />
            </div>

            <!-- 5. Sales Team -->
            <div class="flex flex-col space-y-1 w-full">
                <label class="block text-[11px] font-semibold text-muted truncate">Team</label>
                <livewire:shared.searchable-select 
                    wire:model.live="team" 
                    model="App\Models\SalesTeam" 
                    placeholder="All Teams" 
                    class="w-full"
                    :key="'leads-filter-team-'.($team ?? 'none')" 
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
                    :key="'leads-filter-user-'.($executive ?? 'none')" 
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
                    :key="'leads-filter-status-'.($statusFilter ?? 'none')" 
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
                    :key="'leads-filter-stage-'.($stageFilter ?? 'none')" 
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
                    :key="'leads-filter-type-'.($leadType ?? 'none')" 
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

        <!-- Active Filters Bar -->
        @php
            $hasActiveFilters = $filterOrganizationId || $project || $campaign || $team || $source || $executive || $leadType || ($statusFilter !== 'all') || $stageFilter || $sla_breached || ($analyticsRange !== 'month');
        @endphp
        @if($hasActiveFilters)
            <div class="flex items-center gap-1.5 flex-wrap pt-2 border-t border-border/70 text-xs">
                <span class="font-semibold text-muted text-[11px]">Active:</span>
                @if($analyticsRange !== 'month')
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                        Range: {{ ucfirst(str_replace('_', ' ', $analyticsRange)) }}
                        <button type="button" wire:click="$set('analyticsRange', 'month')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
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
                        Project: #{{ $project }}
                        <button type="button" wire:click="$set('project', '')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                    </span>
                @endif
                @if($source)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                        Source: #{{ $source }}
                        <button type="button" wire:click="$set('source', '')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                    </span>
                @endif
                @if($statusFilter !== 'all')
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                        Status: {{ ucfirst(str_replace('_', ' ', $statusFilter)) }}
                        <button type="button" wire:click="$set('statusFilter', 'all')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                    </span>
                @endif
                @if($stageFilter)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                        Stage: {{ $mainStages[$stageFilter] ?? ucfirst(str_replace('_', ' ', $stageFilter)) }}
                        <button type="button" wire:click="$set('stageFilter', '')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
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
    </div>

    @if($isSalesExecutive)<div class="flex gap-2 text-xs items-center"><label for="lead-followup-filter">Follow-ups</label><select id="lead-followup-filter" wire:model.live="followupFilter" class="rounded-lg border-border"><option value="">All</option><option value="today">Today</option><option value="overdue">Overdue</option><option value="upcoming">Upcoming</option></select></div>@endif
    @if($isBuilder)
        <!-- 3. BUILDER COMPACT 8 OPERATIONAL KPI CARDS -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold text-muted uppercase tracking-wider">Overview Metrics</h2>
                <span class="text-[11px] text-muted">Click any metric card to filter leads</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2.5">
                @foreach($builderKpis as $kpiKey => $kpi)
                    <button type="button" wire:click="$set('statusFilter', '{{ $kpi['filter'] }}')"
                        class="bg-surface hover:bg-canvas border {{ $statusFilter === $kpi['filter'] ? 'border-primary ring-1 ring-primary' : 'border-border' }} rounded-xl p-3 text-left transition shadow-2xs cursor-pointer flex flex-col justify-between">
                        <div class="flex items-center justify-between gap-1 text-[10px] font-bold text-muted uppercase tracking-wider">
                            <span class="truncate">{{ $kpi['label'] }}</span>
                            <span class="w-2 h-2 rounded-full {{ $statusFilter === $kpi['filter'] ? 'bg-primary' : 'bg-neutral-300' }}"></span>
                        </div>
                        <div class="text-xl font-bold font-mono text-ink mt-1.5">
                            {{ number_format($kpi['value']) }}
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @elseif(!$isSalesExecutive)
    <!-- 3. LEAD OVERVIEW KPI CARDS -->
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-xs font-bold text-muted uppercase tracking-wider">Lead Overview</h2>
                <p class="text-[11px] text-muted">Click any metric card to filter leads directory below.</p>
            </div>

            <!-- Group Filter Tabs -->
            <div class="inline-flex p-1 bg-canvas rounded-lg border border-border flex-wrap gap-1">
                @foreach([
                    'all' => 'All Metrics',
                    'intake' => 'Intake',
                    'assignment' => 'Assignment',
                    'progress' => 'Progress',
                    'conversion' => 'Conversion',
                    'quality' => 'Quality'
                ] as $grpKey => $grpLabel)
                    <button type="button" wire:click="$set('activeKpiGroup', '{{ $grpKey }}')"
                        class="px-2.5 py-1 text-[11px] font-bold rounded-md transition cursor-pointer {{ $activeKpiGroup === $grpKey ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                        {{ $grpLabel }}
                    </button>
                @endforeach
            </div>
        </div>

        @php
            $displayCards = [];
            if ($activeKpiGroup === 'all') {
                // Show selected highlights from each group
                $displayCards = array_merge(
                    $kpis['intake'],
                    $kpis['assignment'],
                    $kpis['progress'],
                    $kpis['conversion'],
                    $kpis['quality']
                );
            } else {
                $displayCards = $kpis[$activeKpiGroup] ?? [];
            }
        @endphp

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2.5">
            @foreach($displayCards as $kpi)
                <div wire:click="applyKpiFilter('{{ $kpi['status'] }}', '{{ $kpi['stage'] ?? '' }}')"
                    class="bg-surface hover:bg-canvas border border-border hover:border-ink/30 rounded-xl p-3 transition flex flex-col justify-between cursor-pointer shadow-2xs group">
                    <div class="flex items-center justify-between gap-1.5">
                        <span class="text-[11px] font-semibold text-muted truncate" title="{{ $kpi['title'] }}">{{ $kpi['title'] }}</span>
                        <div class="p-1 rounded-md bg-canvas border border-border text-muted group-hover:text-ink transition shrink-0">
                            @if($kpi['icon'] === 'users')
                                <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            @elseif($kpi['icon'] === 'sparkles')
                                <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                            @elseif($kpi['icon'] === 'user-check')
                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            @elseif($kpi['icon'] === 'user-x')
                                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM18 10l5 5m0-5l-5 5"/></svg>
                            @elseif($kpi['icon'] === 'trophy')
                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                            @elseif($kpi['icon'] === 'alert-triangle')
                                <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            @else
                                <svg class="w-3.5 h-3.5 text-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            @endif
                        </div>
                    </div>

                    <div class="mt-2">
                        <div class="text-xl font-extrabold text-ink tracking-tight">
                            {{ $kpi['value'] }}
                        </div>
                        <div class="flex items-center gap-1 mt-1 text-[10px]">
                            @if($kpi['isPositive'] === true)
                                <span class="font-bold text-emerald-600 inline-flex items-center">
                                    ↑ {{ $kpi['delta'] }}
                                </span>
                            @elseif($kpi['isPositive'] === false)
                                <span class="font-bold text-rose-600 inline-flex items-center">
                                    ↓ {{ $kpi['delta'] }}
                                </span>
                            @else
                                <span class="text-muted font-mono">0%</span>
                            @endif
                            <span class="text-muted truncate font-normal">{{ $kpi['period'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @endif

    @if(!$isBuilder && !$isSalesExecutive)
    <!-- 4. COMPLETE 13-STAGE LEAD LIFECYCLE FUNNEL -->
    <div class="bg-surface rounded-card border border-border p-4 shadow-2xs space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border pb-3">
            <div>
                <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Complete Lead Lifecycle Funnel</h2>
                <p class="text-[11px] text-muted">13-stage journey: Intake → Duplicate → Credit → Assignment → Engagement → Conversion. Click any stage to filter records.</p>
            </div>
            <div class="text-[11px] font-semibold text-muted">
                Horizontal pipeline scroll ⇄
            </div>
        </div>

        <!-- Horizontally Scrollable Stage Cards -->
        <div class="overflow-x-auto pb-2 -mx-1 px-1">
            <div class="flex items-center gap-2 min-w-max">
                @foreach($funnel as $stg)
                    <div wire:click="filterByLifecycleStage('{{ $stg['stage_key'] }}')"
                        class="w-48 bg-canvas hover:bg-surface border {{ $stg['is_alert'] ? 'border-amber-300 ring-1 ring-amber-300' : 'border-border' }} hover:border-ink/40 rounded-xl p-3 transition cursor-pointer flex flex-col justify-between shadow-2xs group relative">
                        
                        <!-- Top Row: Step & SLA Time -->
                        <div class="flex items-center justify-between text-[10px] text-muted font-mono">
                            <span class="font-bold text-ink bg-surface border border-border px-1.5 py-0.2 rounded">
                                {{ sprintf('%02d', $stg['step']) }}
                            </span>
                            <span class="text-[10px] text-muted font-medium">⏱ {{ $stg['avg_time'] }}</span>
                        </div>

                        <!-- Stage Title -->
                        <div class="mt-2">
                            <h4 class="text-xs font-bold text-ink group-hover:text-primary transition truncate">
                                {{ $stg['name'] }}
                            </h4>
                            <div class="text-lg font-black text-ink mt-0.5">
                                {{ number_format($stg['count']) }}
                            </div>
                        </div>

                        <!-- Bottom Stats: Conv % & Drop-off -->
                        <div class="mt-2.5 pt-2 border-t border-border flex items-center justify-between text-[10px]">
                            <span class="font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200">
                                {{ $stg['conversion_rate'] }}%
                            </span>

                            @if($stg['drop_off'] > 0)
                                <span class="font-bold text-rose-600" title="{{ $stg['drop_off'] }} drop-offs from previous step">
                                    -{{ number_format($stg['drop_off']) }} drop
                                </span>
                            @else
                                <span class="text-muted font-mono">0 drop</span>
                            @endif
                        </div>

                        @if($stg['is_alert'])
                            <div class="absolute -top-1.5 -right-1.5 w-3.5 h-3.5 rounded-full bg-amber-500 border-2 border-white flex items-center justify-center text-[8px] text-white font-bold" title="High drop-off rate alert">
                                !
                            </div>
                        @endif
                    </div>

                    @if($stg['step'] < 13)
                        <div class="text-muted/40 font-bold select-none shrink-0">
                            →
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    <!-- 5. LEAD ANALYTICS SECTION -->
    <div class="bg-surface rounded-card border border-border p-4 shadow-2xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border pb-3">
            <div>
                <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Lead Analytics &amp; Breakdown</h2>
                <p class="text-[11px] text-muted">Lead volume generation trends and dimension breakdowns.</p>
            </div>

            <!-- Breakdown Dimension Tabs -->
            <div class="inline-flex p-1 bg-canvas rounded-lg border border-border flex-wrap gap-1">
                @foreach([
                    'project' => 'By Project',
                    'source' => 'By Source',
                    'executive' => 'By Agent',
                    'team' => 'By Team',
                    'organization' => 'By Org',
                    'overview' => 'By Type',
                ] as $tabKey => $tabLabel)
                    <button type="button" wire:click="$set('breakdownTab', '{{ $tabKey }}')"
                        class="px-2.5 py-1 text-[11px] font-bold rounded-md transition cursor-pointer {{ $breakdownTab === $tabKey ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                        {{ $tabLabel }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- 2-Column Analytics Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Left Chart: Lead Generation Trend -->
            <div class="p-3 bg-canvas/60 rounded-xl border border-border space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-ink">Lead Intake vs Conversion Trend</span>
                    <span class="text-[10px] text-muted font-medium">Daily Timeline</span>
                </div>
                <div x-data="{
                        chart: null,
                        initChart(labels, received, converted) {
                            this.chart = window.initSafeChart('leadsTrendChart', {
                                type: 'line',
                                data: {
                                    labels: labels,
                                    datasets: [
                                        {
                                            label: 'Received',
                                            data: received,
                                            borderColor: '#2563EB',
                                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                                            fill: true,
                                            tension: 0.3,
                                            borderWidth: 2,
                                            pointRadius: 2,
                                        },
                                        {
                                            label: 'Converted',
                                            data: converted,
                                            borderColor: '#10B981',
                                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                            fill: true,
                                            tension: 0.3,
                                            borderWidth: 2,
                                            pointRadius: 2,
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { position: 'top', labels: { boxWidth: 10, font: { size: 10 } } } },
                                    scales: {
                                        x: { grid: { display: false }, ticks: { font: { size: 9 } } },
                                        y: { grid: { color: '#E2E8F0' }, ticks: { font: { size: 9 } } }
                                    }
                                }
                            });
                        }
                    }"
                    x-init="$nextTick(function() { initChart(@js($analytics['trendLabels']), @js($analytics['trendReceived']), @js($analytics['trendConverted'])); })"
                    class="h-56 relative">
                    <canvas id="leadsTrendChart"></canvas>
                </div>
            </div>

            <!-- Right Chart: Active Breakdown Ranking -->
            <div class="p-3 bg-canvas/60 rounded-xl border border-border space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-ink">Volume Breakdown ({{ ucfirst($breakdownTab) }})</span>
                    <span class="text-[10px] text-muted font-medium">Top Entities</span>
                </div>
                <div x-data="{
                        chart: null,
                        initBarChart(labels, counts) {
                            this.chart = window.initSafeChart('leadsBreakdownRankingChart', {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: 'Total Leads',
                                        data: counts,
                                        backgroundColor: '#0F172A',
                                        borderRadius: 4,
                                        barThickness: 14
                                    }]
                                },
                                options: {
                                    indexAxis: 'y',
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { display: false } },
                                    scales: {
                                        x: { grid: { color: '#E2E8F0' }, ticks: { font: { size: 9 } } },
                                        y: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' } } }
                                    }
                                }
                            });
                        }
                    }"
                    x-init="$nextTick(function() { initBarChart(@js($analytics['chartLabels']), @js($analytics['chartCounts'])); })"
                    class="h-56 relative">
                    <canvas id="leadsBreakdownRankingChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Breakdown Performance Table Component -->
        <div class="border-t border-border pt-3">
            @php
                $breakdownColumns = [
                    ['key' => 'name', 'label' => 'Entity / Group Name', 'class' => 'font-bold text-ink', 'sortable' => true, 'priority' => 1],
                    ['key' => 'total_leads', 'label' => 'Total Leads', 'class' => 'font-mono font-bold', 'formatter' => fn($v) => number_format((float) $v), 'sortable' => true, 'priority' => 1],
                    ['key' => 'conversion_rate', 'label' => 'Conversion Rate', 'suffix' => '%', 'class' => 'font-mono font-bold text-emerald-600', 'sortable' => true, 'priority' => 1],
                    ['key' => 'avg_sla_time', 'label' => 'Avg Response Time', 'class' => 'font-mono text-muted', 'sortable' => true, 'priority' => 2],
                ];
            @endphp

            <x-ui.advanced-table 
                :columns="$breakdownColumns" 
                :rows="$analytics['paginatedBreakdown']"
                :showSearch="true" 
                searchModel="breakdownSearch"
                searchPlaceholder="Search {{ strtolower($breakdownTab) }} entities..."
                :sortField="$breakdownSortField"
                :sortDirection="$breakdownSortDirection"
                sortAction="sortBreakdownBy"
                :showFilterDropdown="false" 
                :showConfigurations="false"
                tableName="breakdown"
                emptyTitle="No Breakdown Data" 
                emptyMessage="No entities match your search or filter criteria.">
                <x-slot:action>
                    <span class="text-xs text-muted font-semibold bg-canvas px-2.5 py-1 rounded-lg border border-border">
                        {{ $analytics['totalBreakdownCount'] }} Total {{ ucfirst($breakdownTab) }}s
                    </span>
                </x-slot:action>
            </x-ui.advanced-table>
        </div>
    </div>

    <!-- 6. ASSIGNMENT, SLA, FOLLOW-UP & CREDIT MONITORING WIDGETS -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- 1. Assignment & Distribution Monitoring -->
        <div class="bg-surface rounded-card border border-border p-3.5 shadow-2xs space-y-2.5">
            <div class="flex items-center justify-between pb-2 border-b border-border">
                <span class="text-xs font-bold text-ink uppercase tracking-wider">Assignment Status</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    {{ $monitoring['assignment']['success_rate'] }}% Success
                </span>
            </div>
            <div class="space-y-1.5 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-muted">Round Robin Routed</span>
                    <span class="font-bold text-ink">{{ $monitoring['assignment']['round_robin'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted">Manual Assignments</span>
                    <span class="font-bold text-ink">{{ $monitoring['assignment']['manual'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted">Priority Rule Routed</span>
                    <span class="font-bold text-ink">{{ $monitoring['assignment']['priority'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted">Unassigned Pool</span>
                    <span class="font-bold text-amber-600">{{ $monitoring['assignment']['unassigned'] }}</span>
                </div>
            </div>
        </div>

        <!-- 2. SLA Monitoring -->
        <div class="bg-surface rounded-card border border-border p-3.5 shadow-2xs space-y-2.5">
            <div class="flex items-center justify-between pb-2 border-b border-border">
                <span class="text-xs font-bold text-ink uppercase tracking-wider">SLA Response Times</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                    15m Threshold
                </span>
            </div>
            <div class="space-y-1.5 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-muted">Within SLA Met</span>
                    <span class="font-bold text-emerald-600">{{ $monitoring['sla']['within'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted">SLA Breached (&gt;15m)</span>
                    <span class="font-bold text-rose-600">{{ $monitoring['sla']['breached'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted">Pending First Response</span>
                    <span class="font-bold text-amber-600">{{ $monitoring['sla']['pending'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted">Avg Response Speed</span>
                    <span class="font-mono font-bold text-ink">{{ $monitoring['sla']['avg_response'] }}</span>
                </div>
            </div>
        </div>

        <!-- 3. Upcoming Activities (Follow-ups & Site Visits) -->
        <div class="bg-surface rounded-card border border-border p-3.5 shadow-2xs space-y-2.5">
            <div class="flex items-center justify-between pb-2 border-b border-border">
                <span class="text-xs font-bold text-ink uppercase tracking-wider">Upcoming Tasks</span>
                <span class="text-[10px] text-muted">Next Due</span>
            </div>
            <div class="space-y-2 text-xs">
                @forelse($monitoring['upcomingFollowups'] as $fu)
                    <div class="flex items-center justify-between border-b border-border/50 pb-1 cursor-pointer hover:bg-canvas p-1 rounded"
                         @click="$dispatch('open-lead-detail', { id: {{ $fu->lead_id }} })">
                        <div class="truncate pr-1">
                            <span class="font-bold text-ink block truncate">{{ $fu->lead?->name ?: 'Lead #' . $fu->lead_id }}</span>
                            <span class="text-[10px] text-muted truncate">{{ $fu->note ?: 'Follow-up' }}</span>
                        </div>
                        <span class="font-mono text-[10px] text-blue-600 shrink-0 font-semibold">
                            {{ $fu->due_at ? \Carbon\Carbon::parse($fu->due_at)->format('d M H:i') : 'Scheduled' }}
                        </span>
                    </div>
                @empty
                    <div class="text-[11px] text-muted text-center py-2">
                        No pending follow-ups due soon.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- 4. Credits & Replacement Position -->
        <div class="bg-surface rounded-card border border-border p-3.5 shadow-2xs space-y-2.5">
            <div class="flex items-center justify-between pb-2 border-b border-border">
                <span class="text-xs font-bold text-ink uppercase tracking-wider">Credit Protection</span>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-canvas border border-border text-ink">
                    Wallet Engine
                </span>
            </div>
            <div class="space-y-1.5 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-muted">Credits Reserved</span>
                    <span class="font-mono font-bold text-ink">{{ number_format($monitoring['credit']['reserved'], 0) }} Cr</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted">Credits Consumed</span>
                    <span class="font-mono font-bold text-ink">{{ number_format($monitoring['credit']['used'], 0) }} Cr</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted">Credits Refunded</span>
                    <span class="font-mono font-bold text-emerald-600">{{ number_format($monitoring['credit']['refunded'], 0) }} Cr</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted">Approved Replacements</span>
                    <span class="font-bold text-indigo-600">{{ $monitoring['credit']['approved'] }}</span>
                </div>
            </div>
        </div>
    </div>

    @endif

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
                    <select wire:model.live="sort_by" class="h-8 text-xs font-semibold rounded-lg border border-border bg-canvas text-ink px-2.5 py-0 focus:outline-none">
                        <option value="created_desc">Latest Created</option>
                        <option value="score_desc">Priority Score: High to Low</option>
                        <option value="score_asc">Priority Score: Low to High</option>
                    </select>
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
                :selectedRows="$selectedRows"
                :selectAll="$selectAll"
                searchPlaceholder="Search leads by code, name, phone, city..."
                emptyTitle="No Leads Found" 
                emptyMessage="No leads matched your search and filter criteria.">
                <x-slot:action>
                    <div class="flex items-center gap-2">
                        @if(!$isSalesExecutive)
                            @if(!empty($selectedRows))
                                <button type="button" wire:click="openBulkAssignModal"
                                    class="px-3 h-8 bg-primary hover:bg-primary-dark text-white text-xs font-semibold rounded-lg transition shadow-2xs flex items-center gap-1.5 cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                                    <span>Assign Selected ({{ count($selectedRows) }})</span>
                                </button>
                            @endif
                            @if(!$isBuilder)
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
                                <span>Add / Ingest Lead</span>
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
                                                @if($lead->first_response_at && (!\App\Models\Lead::usesSalesPanel() || $lead->created_at->diffInMinutes($lead->first_response_at)<=15))
                                                    <span class="px-1.5 py-0.2 rounded-full bg-emerald-50 text-emerald-700 font-semibold text-[9px]">SLA Met</span>
                                                @elseif(\Carbon\Carbon::parse($lead->created_at)->diffInMinutes(now()) > 15)
                                                    <span class="px-1.5 py-0.2 rounded-full bg-rose-50 text-rose-700 font-bold text-[9px]">SLA Breached</span>
                                                @else
                                                    <span class="px-1.5 py-0.2 rounded-full bg-amber-50 text-amber-700 font-semibold text-[9px]">SLA Pending</span>
                                                @endif

                                                <button type="button"
                                                    @click.stop="$dispatch('open-lead-journey', { id: {{ $lead->id }} })"
                                                    class="p-0.5 rounded hover:bg-canvas text-muted hover:text-ink transition cursor-pointer"
                                                    title="Show Complete Lead Journey Timeline">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Lead Name & City -->
                                        <div>
                                            <h4 class="text-xs font-bold text-ink group-hover:text-blue-600 transition truncate">
                                                {{ $lead->name }}
                                            </h4>
                                            <p class="text-[11px] text-muted truncate">
                                                {{ $lead->city ?: 'Location N/A' }} • {{ $lead->property_type ?: 'Property' }}
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
                                                <span class="px-1.5 py-0.2 text-[9px] font-bold uppercase rounded bg-canvas text-muted border border-border">
                                                    {{ $lead->leadSource?->name ?: 'Direct' }}
                                                </span>
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

    <!-- 8. MODALS & SLIDE-OVERS -->
    <!-- Slide-over Detail Component -->
    <livewire:leads.lead-detail />

    <!-- Full Lead Journey Timeline Drawer Component -->
    <livewire:leads.lead-timeline />

    <!-- Confirmation Modal for Terminal / Exception Stage Transition -->
    @if($showConfirmModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-surface rounded-card border border-border p-6 max-w-md w-full shadow-2xl space-y-4">
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
        <div class="inline-flex p-1 bg-canvas rounded-lg border border-border w-full mb-4">
            <button type="button" wire:click="$set('pullMode', 'meta')"
                class="flex-1 py-1 text-xs font-bold rounded-md transition cursor-pointer {{ $pullMode === 'meta' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                Meta Ads Pull
            </button>
            <button type="button" wire:click="$set('pullMode', 'manual')"
                class="flex-1 py-1 text-xs font-bold rounded-md transition cursor-pointer {{ $pullMode === 'manual' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                Single Manual Entry
            </button>
        </div>

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
                        <select wire:model.live="selectedFormOption"
                            class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1 font-medium">
                            @foreach($formOptions as $val => $lbl)
                                <option value="{{ $val }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="font-bold text-ink">Max Leads to Pull</label>
                        <select wire:model="pullLimit"
                            class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1 font-medium">
                            <option value="10">10 Most Recent</option>
                            <option value="25">25 Leads</option>
                            <option value="50">50 Leads</option>
                            <option value="100">100 Leads</option>
                            <option value="250">250 Leads</option>
                        </select>
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
                        <select wire:model="manualProjectId" class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1">
                            <option value="">Select Project</option>
                            @foreach($allProjects as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        @error('manualProjectId') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="font-bold text-ink">Lead Source</label>
                        <select wire:model="manualLeadSourceId" class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1">
                            <option value="">Direct Entry</option>
                            @foreach($allLeadSources as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="font-bold text-ink">City</label>
                        <input type="text" wire:model="manualCity" placeholder="Mumbai"
                            class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1">
                    </div>
                    <div>
                        <label class="font-bold text-ink">Budget</label>
                        <input type="text" wire:model="manualBudget" placeholder="₹75.0L"
                            class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1">
                    </div>
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
                    class="px-3.5 py-1.5 border border-border bg-white text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer">
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
    <div class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-surface rounded-2xl border border-border shadow-2xl max-w-md w-full p-6 space-y-4">
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
                        :options="$eligibleAssignees->mapWithKeys(fn ($member) => [$member->id => $member->name.' ('.$member->email.')'])->all()"
                        placeholder="Choose Eligible Team Member"
                        :key="'assignment-member-select-'.($assignTeamId ?? 'all')"
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
                                        <span class="font-medium text-ink">{{ $ah->assignee?->name ?? 'User #'.$ah->assigned_to }}</span>
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

    <!-- Return Lead to Super Admin (Replacement Request) Modal -->
    @if($showReturnLeadModal && $returnLeadModel)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-surface rounded-2xl border border-border shadow-2xl max-w-lg w-full p-6 space-y-4">
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
                    <select wire:model="returnReasonId"
                        class="w-full h-9 px-3 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-1 focus:ring-primary focus:border-primary transition cursor-pointer">
                        <option value="">-- Select Reason --</option>
                        @foreach($replacementReasons as $reason)
                            <option value="{{ $reason->id }}">{{ $reason->label }}</option>
                        @endforeach
                    </select>
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
</div>
