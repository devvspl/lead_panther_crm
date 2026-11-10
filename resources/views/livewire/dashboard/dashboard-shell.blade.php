<div wire:poll.30s class="space-y-8 pb-12">
    @if($role === 'admin')
        <!-- ================================================================= -->
        <!-- SUPER ADMIN MASTER COMMAND CENTRE (PREMIUM SAAS CRM)              -->
        <!-- ================================================================= -->

        <!-- 1. Header & Quick Actions -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-border">
            <div class="space-y-1">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-ink tracking-tight">Super Admin Command Centre</h1>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border bg-rose-50 text-rose-700 border-rose-200">
                        Lead Panther Telemetry
                    </span>
                    <span class="inline-flex items-center space-x-1.5 px-3 py-1 bg-surface border border-border rounded-full text-xs font-semibold text-muted shadow-2xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Live 30s Refresh</span>
                    </span>
                </div>
                <p class="text-sm text-muted">Complete Lead Management Lifecycle, Distribution Telemetry & Credit Protection Engine</p>
            </div>
            
            <div class="flex items-center gap-3 flex-wrap">
                <button type="button" wire:click="resetFilters"
                        class="inline-flex items-center gap-2 px-3.5 py-2 bg-surface hover:bg-canvas border border-border rounded-xl text-xs font-semibold text-ink transition shadow-2xs cursor-pointer">
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Reset Filters</span>
                </button>
                <button type="button" wire:click="exportDashboardReport"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-ink hover:bg-neutral-800 text-white rounded-xl text-xs font-semibold transition shadow-2xs cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export to Excel</span>
                </button>
            </div>
        </div>

        <!-- 2. Global Dashboard Filters Bar (Clean, Spacious Horizontal Container) -->
        <div class="bg-surface border border-border rounded-2xl p-5 shadow-2xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border/80 pb-3">
                <div class="flex items-center gap-2">
                    <div class="p-1.5 rounded-lg bg-canvas border border-border text-ink">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Global Filters</h2>
                        <p class="text-xs text-muted">Filter cards, charts, pipelines, and performance reports</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" wire:click="applyFilters"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold transition shadow-2xs cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Apply Filter</span>
                    </button>
                    <button type="button" wire:click="resetFilters"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-surface hover:bg-canvas border border-border text-muted hover:text-ink rounded-lg text-xs font-semibold transition cursor-pointer">
                        <span>Reset</span>
                    </button>
                </div>
            </div>

            <!-- Filters Grid (7 Filters) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-3.5">
                <!-- 1. Date Range -->
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-muted">Date Range</label>
                    <x-ui.date-range-picker 
                        wire:model.live="dateRange" 
                        wire:custom-from.live="customFrom" 
                        wire:custom-to.live="customTo" 
                        class="w-full"
                    />
                </div>

                <!-- 2. Organization -->
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-muted">Organization</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterOrganizationId" 
                        model="App\Models\Organization" 
                        placeholder="All Organizations" 
                        :key="'dash-filter-org-'.($filterOrganizationId ?? 'none')" 
                    />
                </div>

                <!-- 3. Project -->
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-muted">Project</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterProjectId" 
                        model="App\Models\Project" 
                        placeholder="All Projects" 
                        :key="'dash-filter-project-'.($filterProjectId ?? 'none')" 
                    />
                </div>

                <!-- 4. Team -->
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-muted">Team</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterTeamId" 
                        model="App\Models\SalesTeam" 
                        placeholder="All Teams" 
                        :key="'dash-filter-team-'.($filterTeamId ?? 'none')" 
                    />
                </div>

                <!-- 5. Lead Source -->
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-muted">Lead Source</label>
                    <select wire:model.live="filterLeadSourceId"
                            class="w-full text-xs rounded-lg border border-border bg-surface text-ink px-3 py-2 focus:border-ink focus:ring-0">
                        <option value="">All Sources</option>
                        @foreach($sourceOptions as $srcId => $srcName)
                            <option value="{{ $srcId }}">{{ $srcName }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- 6. Assigned Agent -->
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-muted">Assigned Agent</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterAssignedUserId" 
                        model="App\Models\User" 
                        placeholder="All Agents" 
                        :key="'dash-filter-user-'.($filterAssignedUserId ?? 'none')" 
                    />
                </div>

                <!-- 7. Lead Status -->
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-muted">Lead Status</label>
                    <select wire:model.live="filterStatus"
                            class="w-full text-xs rounded-lg border border-border bg-surface text-ink px-3 py-2 focus:border-ink focus:ring-0">
                        <option value="">All Statuses</option>
                        <option value="new">New (Uncontacted)</option>
                        <option value="assigned">Assigned</option>
                        <option value="contacted">Contacted</option>
                        <option value="in_progress">In Progress</option>
                        <option value="qualified">Qualified</option>
                        <option value="site_visit">Site Visit</option>
                        <option value="negotiation">Negotiation</option>
                        <option value="booking">Booking</option>
                        <option value="closed_won">Closed Won</option>
                        <option value="lost">Lost</option>
                        <option value="duplicate">Duplicate</option>
                        <option value="unassigned">Unassigned</option>
                        <option value="sla_breached">SLA Breached (>30m)</option>
                    </select>
                </div>
            </div>

            <!-- Active Filters Bar -->
            @php
                $hasActiveFilters = $filterOrganizationId || $filterProjectId || $filterTeamId || $filterLeadSourceId || $filterAssignedUserId || $filterStatus || $filterCampaignId || $filterTransactionType || $dateRange !== '30_days';
            @endphp
            @if($hasActiveFilters)
                <div class="flex items-center gap-2 flex-wrap pt-3 border-t border-border/80 text-xs">
                    <span class="font-semibold text-muted">Active Scope:</span>
                    @if($dateRange !== '30_days')
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-canvas border border-border text-xs font-medium text-ink">
                            Period: {{ ucfirst(str_replace('_', ' ', $dateRange)) }}
                            <button type="button" wire:click="$set('dateRange', '30_days')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                        </span>
                    @endif
                    @if($filterOrganizationId)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-canvas border border-border text-xs font-medium text-ink">
                            Org ID: {{ $filterOrganizationId }}
                            <button type="button" wire:click="$set('filterOrganizationId', null)" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                        </span>
                    @endif
                    @if($filterProjectId)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-canvas border border-border text-xs font-medium text-ink">
                            Project ID: {{ $filterProjectId }}
                            <button type="button" wire:click="$set('filterProjectId', null)" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                        </span>
                    @endif
                    @if($filterTeamId)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-canvas border border-border text-xs font-medium text-ink">
                            Team ID: {{ $filterTeamId }}
                            <button type="button" wire:click="$set('filterTeamId', null)" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                        </span>
                    @endif
                    @if($filterLeadSourceId)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-canvas border border-border text-xs font-medium text-ink">
                            Source: {{ $sourceOptions[$filterLeadSourceId] ?? $filterLeadSourceId }}
                            <button type="button" wire:click="$set('filterLeadSourceId', null)" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                        </span>
                    @endif
                    @if($filterStatus)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-canvas border border-border text-xs font-medium text-ink">
                            Status: {{ ucfirst(str_replace('_', ' ', $filterStatus)) }}
                            <button type="button" wire:click="$set('filterStatus', null)" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                        </span>
                    @endif
                    <button type="button" wire:click="resetFilters" class="text-xs text-blue-600 hover:underline font-semibold ml-1 cursor-pointer">
                        Clear All
                    </button>
                </div>
            @endif
        </div>

        <!-- 3. Primary Hero KPI Cards (5 Spacious Cards in Top Row) -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold text-muted uppercase tracking-wider">Primary Performance Indicators</h2>
                <span class="text-xs text-muted">Real-time database aggregation</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
                @foreach($groupedKpis['hero'] as $hero)
                    <a href="{{ $hero['route'] }}" wire:navigate
                       class="group bg-surface hover:bg-canvas border border-border hover:border-ink/40 rounded-2xl p-5 transition shadow-2xs flex flex-col justify-between cursor-pointer">
                        <div class="flex items-center justify-between gap-2">
                            <span class="inline-flex px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-semibold text-muted group-hover:text-ink transition">
                                {{ $hero['badge'] }}
                            </span>
                            <div class="p-2 rounded-xl bg-canvas border border-border text-muted group-hover:text-ink transition">
                                @if($hero['icon'] === 'users')
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                @elseif($hero['icon'] === 'trophy')
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                @elseif($hero['icon'] === 'alert-triangle')
                                    <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                @elseif($hero['icon'] === 'wallet')
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                @elseif($hero['icon'] === 'dollar-sign')
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @else
                                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4">
                            <span class="text-xs font-semibold text-muted block">{{ $hero['title'] }}</span>
                            <div class="text-2xl sm:text-3xl font-extrabold text-ink tracking-tight mt-1">
                                {{ $hero['value'] }}
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-border/80 flex items-center justify-between text-xs">
                            <span class="inline-flex items-center font-bold {{ $hero['isPositive'] ? 'text-emerald-600' : 'text-rose-600' }}">
                                @if($hero['isPositive'])
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                @else
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                @endif
                                {{ $hero['delta'] }}
                            </span>
                            <span class="text-muted truncate ml-1">{{ $hero['subtext'] }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- 4. Reorganized Secondary Metrics (4 Logical Group Summary Cards) -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-bold text-muted uppercase tracking-wider">Metric Breakdown by Logical Category</h2>
                    <p class="text-xs text-muted">Structured overview of all 17 CRM lifecycle metrics without visual clutter</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                <!-- Group 1: Lead Overview -->
                <div class="bg-surface border border-border rounded-2xl p-5 shadow-2xs space-y-3 flex flex-col justify-between">
                    <div class="border-b border-border/80 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-ink flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                <span>{{ $groupedKpis['groups']['overview']['title'] }}</span>
                            </h3>
                            <p class="text-xs text-muted mt-0.5">{{ $groupedKpis['groups']['overview']['subtitle'] }}</p>
                        </div>
                    </div>

                    <div class="space-y-2.5 text-xs">
                        @foreach($groupedKpis['groups']['overview']['metrics'] as $m)
                            <a href="{{ $m['route'] }}" wire:navigate class="flex items-center justify-between py-1 hover:bg-canvas px-1.5 rounded-lg transition group">
                                <span class="text-muted group-hover:text-ink font-medium">{{ $m['label'] }}</span>
                                <span class="font-bold text-ink text-sm">{{ $m['value'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Group 2: Lead Conversion -->
                <div class="bg-surface border border-border rounded-2xl p-5 shadow-2xs space-y-3 flex flex-col justify-between">
                    <div class="border-b border-border/80 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-ink flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span>{{ $groupedKpis['groups']['conversion']['title'] }}</span>
                            </h3>
                            <p class="text-xs text-muted mt-0.5">{{ $groupedKpis['groups']['conversion']['subtitle'] }}</p>
                        </div>
                    </div>

                    <div class="space-y-2.5 text-xs">
                        @foreach($groupedKpis['groups']['conversion']['metrics'] as $m)
                            <a href="{{ $m['route'] }}" wire:navigate class="flex items-center justify-between py-1 hover:bg-canvas px-1.5 rounded-lg transition group">
                                <span class="text-muted group-hover:text-ink font-medium">{{ $m['label'] }}</span>
                                <span class="font-bold text-ink text-sm">{{ $m['value'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Group 3: SLA & Performance -->
                <div class="bg-surface border border-border rounded-2xl p-5 shadow-2xs space-y-3 flex flex-col justify-between">
                    <div class="border-b border-border/80 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-ink flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                <span>{{ $groupedKpis['groups']['sla']['title'] }}</span>
                            </h3>
                            <p class="text-xs text-muted mt-0.5">{{ $groupedKpis['groups']['sla']['subtitle'] }}</p>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs">
                        @foreach($groupedKpis['groups']['sla']['metrics'] as $m)
                            <a href="{{ $m['route'] }}" wire:navigate class="flex items-center justify-between py-1 hover:bg-canvas px-1.5 rounded-lg transition group">
                                <span class="text-muted group-hover:text-ink font-medium">{{ $m['label'] }}</span>
                                <span class="font-bold text-ink text-sm">{{ $m['value'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Group 4: Credit & Revenue -->
                <div class="bg-surface border border-border rounded-2xl p-5 shadow-2xs space-y-3 flex flex-col justify-between">
                    <div class="border-b border-border/80 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-ink flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                <span>{{ $groupedKpis['groups']['credit']['title'] }}</span>
                            </h3>
                            <p class="text-xs text-muted mt-0.5">{{ $groupedKpis['groups']['credit']['subtitle'] }}</p>
                        </div>
                    </div>

                    <div class="space-y-2.5 text-xs">
                        @foreach($groupedKpis['groups']['credit']['metrics'] as $m)
                            <a href="{{ $m['route'] }}" wire:navigate class="flex items-center justify-between py-1 hover:bg-canvas px-1.5 rounded-lg transition group">
                                <span class="text-muted group-hover:text-ink font-medium">{{ $m['label'] }}</span>
                                <span class="font-bold text-ink text-sm">{{ $m['value'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Complete Lead Journey Pipeline (Clickable Interactive Flow) -->
        <div class="bg-surface border border-border rounded-2xl p-6 shadow-2xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border/80 pb-3">
                <div>
                    <h2 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        <span>Lead Journey Lifecycle Pipeline</span>
                    </h2>
                    <p class="text-xs text-muted mt-0.5">Click any stage to filter and inspect its leads in the pipeline</p>
                </div>
                <div class="flex items-center gap-4 text-xs font-semibold text-muted">
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Ingestion</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Engagement</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Conversion</span>
                </div>
            </div>

            <!-- Horizontal Responsive Interactive Pipeline -->
            <div class="overflow-x-auto no-scrollbar pb-2 pt-1">
                <div class="flex items-stretch gap-2.5 min-w-max">
                    @foreach($adminFunnel as $stg)
                        <a href="{{ $stg['drill_route'] }}" wire:navigate
                           class="w-40 bg-canvas hover:bg-surface border border-border hover:border-ink/50 rounded-xl p-3.5 transition flex flex-col justify-between shrink-0 relative group shadow-2xs cursor-pointer">
                            <!-- Stage Header -->
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-xs font-bold text-muted uppercase">#{{ $stg['index'] }}</span>
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-surface border border-border text-muted">
                                    {{ $stg['avg_time'] }}
                                </span>
                            </div>

                            <!-- Stage Name -->
                            <div class="text-xs font-bold text-ink mt-2 line-clamp-1 group-hover:text-blue-600 transition" title="{{ $stg['name'] }}">
                                {{ $stg['name'] }}
                            </div>

                            <!-- Lead Count & Conversion -->
                            <div class="mt-2.5 space-y-1">
                                <div class="text-xl font-extrabold text-ink tracking-tight">
                                    {{ number_format($stg['count']) }}
                                </div>
                                
                                <div class="flex items-center justify-between text-xs text-muted">
                                    <span>Conv:</span>
                                    <span class="font-bold text-emerald-600">{{ $stg['conversion_pct'] }}%</span>
                                </div>

                                @if($stg['drop_off'] > 0)
                                    <div class="flex items-center justify-between text-[11px] text-rose-600 font-medium">
                                        <span>Drop:</span>
                                        <span>-{{ number_format($stg['drop_off']) }}</span>
                                    </div>
                                @else
                                    <div class="text-[11px] text-muted text-right">0 drop</div>
                                @endif
                            </div>

                            <!-- Step Progress Bar -->
                            <div class="w-full bg-surface rounded-full h-1.5 mt-2.5 overflow-hidden border border-border">
                                <div class="h-1.5 rounded-full {{ $stg['index'] <= 5 ? 'bg-blue-600' : ($stg['index'] <= 9 ? 'bg-amber-500' : 'bg-emerald-600') }}"
                                     style="width: {{ max(6, $stg['conversion_pct']) }}%;"></div>
                            </div>
                        </a>

                        @if(!$loop->last)
                            <div class="flex items-center justify-center text-muted shrink-0">
                                <svg class="w-4 h-4 text-border" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <!-- 6. Analytics Section (Balanced 2-Column & 3-Column Responsive Grid) -->
        <div class="space-y-6">
            <!-- Row 1: Lead Trend (2 Cols) + Lead Source Donut (1 Col) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Lead Generation Velocity (2 Cols) -->
                <div class="lg:col-span-2 bg-surface border border-border rounded-2xl p-6 shadow-2xs space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border/80 pb-3">
                        <div>
                            <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Lead Generation Velocity & Conversion Trends</h2>
                            <p class="text-xs text-muted mt-0.5">Ingested Volume vs Converted Won Deals across time</p>
                        </div>
                        
                        <div class="flex items-center gap-1 bg-canvas p-1 rounded-xl border border-border">
                            <button type="button" wire:click="setTrendInterval('daily')"
                                    class="px-3 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer {{ $trendInterval === 'daily' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                                Daily
                            </button>
                            <button type="button" wire:click="setTrendInterval('weekly')"
                                    class="px-3 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer {{ $trendInterval === 'weekly' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                                Weekly
                            </button>
                            <button type="button" wire:click="setTrendInterval('monthly')"
                                    class="px-3 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer {{ $trendInterval === 'monthly' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                                Monthly
                            </button>
                        </div>
                    </div>

                    <div class="relative h-72 w-full">
                        <canvas id="adminLeadTrendChart"></canvas>
                    </div>
                </div>

                <!-- Lead Source Performance Donut (1 Col) -->
                <div class="bg-surface border border-border rounded-2xl p-6 shadow-2xs space-y-4 flex flex-col justify-between">
                    <div class="border-b border-border/80 pb-3">
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Lead Source Performance</h2>
                        <p class="text-xs text-muted mt-0.5">Distribution across acquisition channels</p>
                    </div>

                    <div class="relative h-56 w-full flex items-center justify-center">
                        <canvas id="adminLeadSourceChart"></canvas>
                    </div>

                    <div class="pt-3 border-t border-border/80 space-y-1.5 text-xs">
                        @foreach($adminLeadAnalytics['sources']['labels'] as $idx => $label)
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-2 text-muted">
                                    <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $adminLeadAnalytics['sources']['colors'][$idx] ?? '#2563EB' }};"></span>
                                    <span>{{ $label }}</span>
                                </span>
                                <span class="font-bold text-ink">{{ $adminLeadAnalytics['sources']['counts'][$idx] ?? 0 }} ({{ $adminLeadAnalytics['sources']['percentages'][$idx] ?? 0 }}%)</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Row 2: Status Distribution (1 Col) + Organization Comparison (1 Col) + Top Sales Agents (1 Col) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Status Distribution Chart -->
                <div class="bg-surface border border-border rounded-2xl p-6 shadow-2xs space-y-4 flex flex-col justify-between">
                    <div class="border-b border-border/80 pb-3">
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Lead Status & Stage Distribution</h2>
                        <p class="text-xs text-muted mt-0.5">Proportion across active CRM stages</p>
                    </div>

                    <div class="relative h-52 w-full flex items-center justify-center">
                        <canvas id="adminLeadStatusChart"></canvas>
                    </div>

                    <div class="pt-3 border-t border-border/80 grid grid-cols-2 gap-2 text-xs">
                        <div class="p-2 rounded-xl bg-canvas border border-border">
                            <span class="text-muted block">Uncontacted</span>
                            <span class="font-bold text-ink text-sm">{{ $groupedKpis['groups']['overview']['metrics'][1]['value'] ?? '0' }}</span>
                        </div>
                        <div class="p-2 rounded-xl bg-canvas border border-border">
                            <span class="text-muted block">Qualified</span>
                            <span class="font-bold text-emerald-600 text-sm">{{ $groupedKpis['groups']['overview']['metrics'][3]['value'] ?? '0' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Organization Comparison Chart -->
                <div class="bg-surface border border-border rounded-2xl p-6 shadow-2xs space-y-4 flex flex-col justify-between">
                    <div class="border-b border-border/80 pb-3 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Organization Comparison</h2>
                            <p class="text-xs text-muted mt-0.5">Top organizations by lead volume</p>
                        </div>
                    </div>

                    <div class="relative h-52 w-full">
                        <canvas id="adminOrgComparisonChart"></canvas>
                    </div>

                    <div class="pt-3 border-t border-border/80 text-xs flex items-center justify-between text-muted">
                        <span>Comparing Top 5 Client Portfolios</span>
                        <a href="{{ route('admin.organizations') }}" wire:navigate class="text-blue-600 font-semibold hover:underline">All Clients &rarr;</a>
                    </div>
                </div>

                <!-- Top Performing Sales Executives Leaderboard -->
                <div class="bg-surface border border-border rounded-2xl p-6 shadow-2xs space-y-4 flex flex-col justify-between">
                    <div class="border-b border-border/80 pb-3 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Team Performance Ranking</h2>
                            <p class="text-xs text-muted mt-0.5">Top sales executives by conversion</p>
                        </div>
                        <a href="{{ route('admin.users') }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">All &rarr;</a>
                    </div>

                    <div class="space-y-3">
                        @forelse($adminLeadAnalytics['agents'] as $agent)
                            <div class="p-2.5 rounded-xl bg-canvas border border-border space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-surface border border-border flex items-center justify-center font-bold text-xs text-ink">
                                            {{ strtoupper(substr($agent['name'], 0, 1)) }}
                                        </div>
                                        <span class="font-semibold text-ink">{{ $agent['name'] }}</span>
                                    </div>
                                    <span class="font-extrabold text-emerald-600">{{ $agent['conversion_rate'] }}% Conv</span>
                                </div>
                                <div class="flex items-center justify-between text-xs text-muted">
                                    <span>Assigned: {{ number_format($agent['assigned']) }} &bull; Contacted: {{ number_format($agent['contacted']) }}</span>
                                    <span class="font-semibold text-ink">Won: {{ number_format($agent['won']) }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-xs text-muted">
                                No sales assignments recorded for selected filters.
                            </div>
                        @endforelse
                    </div>

                    <div class="pt-2 text-xs text-center text-muted">
                        Ranked by deal closure velocity & contact rate
                    </div>
                </div>
            </div>
        </div>

        <!-- 7. Organization & Project Analytics (Spacious Separate Sections) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Organization Performance Table -->
            <div class="bg-surface border border-border rounded-2xl p-6 shadow-2xs space-y-4">
                <div class="flex items-center justify-between border-b border-border/80 pb-3">
                    <div>
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Organization Performance Breakdown</h2>
                        <p class="text-xs text-muted mt-0.5">Lead volume, credit usage, wallet balance & SLA compliance</p>
                    </div>
                    <a href="{{ route('admin.organizations') }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">Manage Orgs &rarr;</a>
                </div>

                <div class="overflow-x-auto max-h-88 overflow-y-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 bg-surface">
                            <tr class="border-b border-border text-xs font-bold text-muted uppercase tracking-wider">
                                <th class="py-2.5">Organization</th>
                                <th class="py-2.5 text-right">Leads</th>
                                <th class="py-2.5 text-right">Credits Used</th>
                                <th class="py-2.5 text-right">Wallet Balance</th>
                                <th class="py-2.5 text-right">SLA Breach</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($adminOrgPerformance as $org)
                                <tr class="hover:bg-canvas/50 transition">
                                    <td class="py-3 font-semibold text-ink">
                                        <a href="{{ route('admin.organizations') }}" wire:navigate class="hover:text-blue-600 transition block font-bold text-sm">
                                            {{ $org['name'] }}
                                        </a>
                                        <span class="text-xs text-muted font-normal">{{ $org['active_users'] }} active agents</span>
                                    </td>
                                    <td class="py-3 text-right font-semibold text-ink text-sm">{{ number_format($org['total_leads']) }}</td>
                                    <td class="py-3 text-right font-medium text-muted">{{ number_format($org['credits_consumed']) }} Cr</td>
                                    <td class="py-3 text-right font-bold {{ $org['wallet_balance'] < 100 ? 'text-rose-600' : 'text-ink' }}">
                                        {{ number_format($org['wallet_balance']) }} Cr
                                        @if($org['wallet_balance'] < 100)
                                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-rose-500 ml-0.5" title="Low Balance"></span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-right">
                                        @if($org['sla_breaches'] > 0)
                                            <span class="inline-flex px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 text-xs font-bold border border-rose-200">
                                                {{ $org['sla_breaches'] }}
                                            </span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-xs text-muted">No organization activity found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Project Performance Table -->
            <div class="bg-surface border border-border rounded-2xl p-6 shadow-2xs space-y-4">
                <div class="flex items-center justify-between border-b border-border/80 pb-3">
                    <div>
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Project Performance Telemetry</h2>
                        <p class="text-xs text-muted mt-0.5">Project leads, client mapping, and credit charge</p>
                    </div>
                    <a href="{{ route('leads.index') }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">View Pipeline &rarr;</a>
                </div>

                <div class="overflow-x-auto max-h-88 overflow-y-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 bg-surface">
                            <tr class="border-b border-border text-xs font-bold text-muted uppercase tracking-wider">
                                <th class="py-2.5">Project</th>
                                <th class="py-2.5">Client</th>
                                <th class="py-2.5 text-right">Leads</th>
                                <th class="py-2.5 text-right">Credits Charged</th>
                                <th class="py-2.5 text-right">Conv Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($adminProjectPerformance as $proj)
                                <tr class="hover:bg-canvas/50 transition">
                                    <td class="py-3 font-semibold text-ink">
                                        <a href="{{ route('leads.index') }}" wire:navigate class="hover:text-blue-600 transition block font-bold text-sm">
                                            {{ $proj['name'] }}
                                        </a>
                                        <span class="text-xs text-muted font-normal">{{ $proj['location'] }}</span>
                                    </td>
                                    <td class="py-3 text-muted">{{ $proj['client_name'] }}</td>
                                    <td class="py-3 text-right font-semibold text-ink text-sm">{{ number_format($proj['total_leads']) }}</td>
                                    <td class="py-3 text-right font-medium text-muted">{{ number_format($proj['total_credits_charged']) }} Cr</td>
                                    <td class="py-3 text-right font-bold text-emerald-600">{{ $proj['conversion_rate'] }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-xs text-muted">
                                        <div class="flex flex-col items-center justify-center space-y-1.5">
                                            <div class="w-10 h-10 rounded-full bg-canvas border border-border flex items-center justify-center text-muted">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                            </div>
                                            <span class="font-bold text-ink text-sm">No Projects In Current Scope</span>
                                            <span class="text-xs text-muted max-w-xs">Agency leads map dynamically to client campaigns and assigned inventory.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 8. Credit & Distribution Monitoring (2 Balanced Cards) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Credit Protection & Wallet Engine -->
            <div class="bg-surface border border-border rounded-2xl p-6 shadow-2xs space-y-4">
                <div class="flex items-center justify-between border-b border-border/80 pb-3">
                    <div>
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Credit Protection & Wallet Engine</h2>
                        <p class="text-xs text-muted mt-0.5">Circulating supply, consumption ledger & recharge activity</p>
                    </div>
                    <a href="{{ route('admin.credits') }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">Credits Hub &rarr;</a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="p-3 rounded-xl bg-canvas border border-border">
                        <span class="text-xs text-muted block">Platform Supply</span>
                        <span class="text-base font-extrabold text-ink mt-0.5 block">{{ number_format($adminCreditAnalytics['global_balance'] ?? 0) }} Cr</span>
                    </div>
                    <div class="p-3 rounded-xl bg-canvas border border-border">
                        <span class="text-xs text-muted block">Avg Cost / Lead</span>
                        <span class="text-base font-extrabold text-ink mt-0.5 block">{{ $adminCreditAnalytics['cost_per_lead'] ?? 10 }} Cr</span>
                    </div>
                    <div class="p-3 rounded-xl bg-canvas border border-border">
                        <span class="text-xs text-muted block">Consumed</span>
                        <span class="text-base font-extrabold text-neutral-700 mt-0.5 block">{{ number_format($adminCreditAnalytics['credits_consumed'] ?? 0) }} Cr</span>
                    </div>
                    <div class="p-3 rounded-xl bg-canvas border border-border">
                        <span class="text-xs text-muted block">Refunded</span>
                        <span class="text-base font-extrabold text-emerald-600 mt-0.5 block">{{ number_format($adminCreditAnalytics['credits_refunded'] ?? 0) }} Cr</span>
                    </div>
                </div>

                @if(($adminCreditAnalytics['pending_recharges'] ?? 0) > 0)
                    <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span class="text-xs font-bold text-amber-900">{{ $adminCreditAnalytics['pending_recharges'] }} Wallet Recharge Requests Pending Approval</span>
                        </div>
                        <a href="{{ route('admin.credits') }}" wire:navigate class="px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold transition">
                            Review &rarr;
                        </a>
                    </div>
                @endif
            </div>

            <!-- Distribution Engine Monitoring -->
            <div class="bg-surface border border-border rounded-2xl p-6 shadow-2xs space-y-4">
                <div class="flex items-center justify-between border-b border-border/80 pb-3">
                    <div>
                        <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Distribution Engine Health</h2>
                        <p class="text-xs text-muted mt-0.5">Algorithm efficiency, assignment rules & hold queue</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        {{ $adminDistributionMonitoring['success_rate'] ?? 100 }}% Success
                    </span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div class="p-3 rounded-xl bg-canvas border border-border">
                        <span class="text-muted block">Round Robin</span>
                        <span class="text-base font-extrabold text-ink mt-0.5 block">{{ number_format($adminDistributionMonitoring['round_robin'] ?? 0) }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-canvas border border-border">
                        <span class="text-muted block">Manual Pool</span>
                        <span class="text-base font-extrabold text-ink mt-0.5 block">{{ number_format($adminDistributionMonitoring['manual'] ?? 0) }}</span>
                    </div>
                    <div class="p-3 rounded-xl bg-canvas border border-border">
                        <span class="text-muted block">Unassigned</span>
                        <span class="text-base font-extrabold {{ ($adminDistributionMonitoring['unassigned'] ?? 0) > 0 ? 'text-amber-600' : 'text-ink' }} mt-0.5 block">
                            {{ number_format($adminDistributionMonitoring['unassigned'] ?? 0) }}
                        </span>
                    </div>
                    <div class="p-3 rounded-xl bg-canvas border border-border">
                        <span class="text-muted block">Failed Routings</span>
                        <span class="text-base font-extrabold text-muted mt-0.5 block">{{ number_format($adminDistributionMonitoring['failed'] ?? 0) }}</span>
                    </div>
                </div>

                <div class="space-y-1">
                    <div class="flex items-center justify-between text-xs text-muted">
                        <span>Assignment Distribution Success</span>
                        <span class="font-bold text-ink">{{ $adminDistributionMonitoring['success_rate'] ?? 100 }}%</span>
                    </div>
                    <div class="w-full bg-canvas rounded-full h-2 overflow-hidden border border-border">
                        <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ $adminDistributionMonitoring['success_rate'] ?? 100 }}%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 9. Recent Activity & Operations Hub (6 Clear Sections in a Modern Tabbed Container) -->
        <div class="bg-surface border border-border rounded-2xl p-6 shadow-2xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border/80 pb-3">
                <div>
                    <h2 class="text-sm font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span>Live Activity & Operations Stream</span>
                    </h2>
                    <p class="text-xs text-muted mt-0.5">Real-time telemetry across leads, SLA alerts, transactions, and multi-channel delivery</p>
                </div>

                <!-- 6 Category Tabs -->
                <div class="flex items-center gap-1.5 bg-canvas p-1 rounded-xl border border-border overflow-x-auto no-scrollbar">
                    <button type="button" wire:click="setActiveActivityTab('leads')"
                            class="px-3 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'leads' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                        Recent Leads
                    </button>
                    <button type="button" wire:click="setActiveActivityTab('followups')"
                            class="px-3 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'followups' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                        Upcoming Follow-ups
                    </button>
                    <button type="button" wire:click="setActiveActivityTab('sla')"
                            class="px-3 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'sla' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                        SLA Alerts
                    </button>
                    <button type="button" wire:click="setActiveActivityTab('transactions')"
                            class="px-3 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'transactions' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                        Credit Ledger
                    </button>
                    <button type="button" wire:click="setActiveActivityTab('replacements')"
                            class="px-3 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'replacements' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                        Replacements
                    </button>
                    <button type="button" wire:click="setActiveActivityTab('notifications')"
                            class="px-3 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'notifications' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                        Delivery Status
                    </button>
                </div>
            </div>

            <!-- Tab 1: Recent Leads -->
            @if($activeActivityTab === 'leads')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['recent_leads'] as $lead)
                        <div class="py-3 flex items-center justify-between text-xs hover:bg-canvas/50 px-2 rounded-xl transition">
                            <div class="flex items-center gap-3.5">
                                <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-700 border border-blue-200 flex items-center justify-center font-bold text-xs shrink-0">
                                    LP
                                </div>
                                <div>
                                    <div class="font-bold text-ink text-sm flex items-center gap-2">
                                        <a href="{{ route('leads.index') }}" wire:navigate class="hover:text-blue-600 transition">
                                            #{{ $lead->lead_code }} &bull; {{ $lead->name }}
                                        </a>
                                        <span class="inline-flex px-2 py-0.5 rounded-md text-xs font-medium bg-canvas border border-border text-muted">
                                            {{ ucfirst($lead->leadSource?->name ?: 'Direct') }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-muted mt-0.5">
                                        Org: {{ $lead->client?->organization?->name ?: 'Platform HQ' }} &bull; Phone: {{ substr($lead->mobile, 0, 4) . '***' . substr($lead->mobile, -3) }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs font-semibold text-ink">{{ $lead->created_at ? $lead->created_at->diffForHumans() : 'Recently' }}</div>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold mt-1 {{ $lead->assigned_to ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                    {{ $lead->assigned_to ? 'Assigned' : 'Unassigned Pool' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-muted">No recent leads found.</div>
                    @endforelse
                </div>

            <!-- Tab 2: Upcoming Follow-ups -->
            @elseif($activeActivityTab === 'followups')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['upcoming_followups'] as $event)
                        <div class="py-3 flex items-center justify-between text-xs hover:bg-canvas/50 px-2 rounded-xl transition">
                            <div class="flex items-center gap-3.5">
                                <div class="w-8 h-8 rounded-full bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center font-bold text-xs shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <div class="font-bold text-ink text-sm">
                                        Follow-up: {{ $event->lead?->name ?? 'Lead Client' }}
                                    </div>
                                    <div class="text-xs text-muted mt-0.5">
                                        {{ $event->note ?? 'Scheduled follow-up & discovery call' }} &bull; Assigned: {{ $event->lead?->assignedTo?->name ?? 'Sales Team' }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs font-bold text-ink">{{ $event->due_at ? \Carbon\Carbon::parse($event->due_at)->diffForHumans() : 'Upcoming' }}</div>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold bg-canvas border border-border text-muted mt-1">
                                    {{ ucfirst(str_replace('_', ' ', $event->status ?? 'Active')) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-muted">No upcoming follow-ups scheduled.</div>
                    @endforelse
                </div>

            <!-- Tab 3: SLA Alerts -->
            @elseif($activeActivityTab === 'sla')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['sla_breached'] as $lead)
                        <div class="py-3 flex items-center justify-between text-xs hover:bg-canvas/50 px-2 rounded-xl transition">
                            <div class="flex items-center gap-3.5">
                                <div class="w-8 h-8 rounded-full bg-rose-50 text-rose-700 border border-rose-200 flex items-center justify-center font-bold text-xs shrink-0">
                                    !
                                </div>
                                <div>
                                    <div class="font-bold text-ink text-sm flex items-center gap-2">
                                        <a href="{{ route('leads.index') }}" wire:navigate class="hover:text-rose-600 transition">
                                            #{{ $lead->lead_code }} &bull; {{ $lead->name }}
                                        </a>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            SLA Breached (>30m)
                                        </span>
                                    </div>
                                    <div class="text-xs text-muted mt-0.5">
                                        Assigned To: {{ $lead->assignedTo?->name ?: 'Unassigned' }} &bull; Ingested: {{ $lead->created_at ? $lead->created_at->diffForHumans() : 'Earlier' }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <a href="{{ route('leads.index') }}" wire:navigate
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition">
                                    Respond &rarr;
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="py-10 text-center text-xs text-muted">
                            <div class="flex flex-col items-center justify-center space-y-1">
                                <span class="font-bold text-emerald-600 text-sm">&check; Zero Active SLA Breaches</span>
                                <span class="text-xs text-muted">All leads have been responded to within the target response speed.</span>
                            </div>
                        </div>
                    @endforelse
                </div>

            <!-- Tab 4: Credit Transactions -->
            @elseif($activeActivityTab === 'transactions')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['transactions'] as $tx)
                        <div class="py-3 flex items-center justify-between text-xs hover:bg-canvas/50 px-2 rounded-xl transition">
                            <div class="flex items-center gap-3.5">
                                <div class="w-8 h-8 rounded-full {{ $tx->transaction_type === 'refund' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-canvas border-border text-muted' }} border flex items-center justify-center font-bold text-xs shrink-0">
                                    {{ strtoupper(substr($tx->transaction_type, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="font-bold text-ink text-sm flex items-center gap-2">
                                        <span>Tx #{{ $tx->id }} &bull; {{ ucfirst($tx->transaction_type) }}</span>
                                        <span class="inline-flex px-2 py-0.5 rounded-md text-xs font-bold {{ $tx->transaction_type === 'refund' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-canvas text-ink border border-border' }}">
                                            {{ $tx->credit_used }} Credits
                                        </span>
                                    </div>
                                    <div class="text-xs text-muted mt-0.5">
                                        Client: {{ $tx->client?->name ?: 'Client' }} &bull; Balance After: {{ $tx->credit_after }} Cr
                                    </div>
                                </div>
                            </div>
                            <div class="text-right text-xs text-muted">
                                {{ $tx->created_at ? $tx->created_at->diffForHumans() : 'Recent' }}
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-muted">No recent credit transactions found.</div>
                    @endforelse
                </div>

            <!-- Tab 5: Replacement Claims -->
            @elseif($activeActivityTab === 'replacements')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['replacements'] as $claim)
                        <div class="py-3 flex items-center justify-between text-xs hover:bg-canvas/50 px-2 rounded-xl transition">
                            <div class="flex items-center gap-3.5">
                                <div class="w-8 h-8 rounded-full bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center font-bold text-xs shrink-0">
                                    R
                                </div>
                                <div>
                                    <div class="font-bold text-ink text-sm flex items-center gap-2">
                                        <span>Claim #{{ $claim->id }} &bull; Lead #{{ $claim->lead?->lead_code ?: $claim->lead_id }}</span>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            {{ ucfirst($claim->status) }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-muted mt-0.5">
                                        Requested by: {{ $claim->requester?->name ?: 'Agent' }} &bull; Reason: {{ $claim->reason?->name ?: 'Invalid Contact' }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <a href="{{ route('replacements.queue') }}" wire:navigate
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface hover:bg-canvas border border-border text-ink text-xs font-bold transition">
                                    Review &rarr;
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="py-10 text-center text-xs text-muted">
                            <div class="flex flex-col items-center justify-center space-y-1">
                                <span class="font-bold text-ink text-sm">No Pending Replacement Claims</span>
                                <span class="text-xs text-muted">All client lead replacement claims have been reviewed.</span>
                            </div>
                        </div>
                    @endforelse
                </div>

            <!-- Tab 6: Multi-Channel Delivery Status -->
            @elseif($activeActivityTab === 'notifications')
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-1">
                    @foreach($adminNotificationMonitoring['channels'] as $chan)
                        <div class="p-4 rounded-xl bg-canvas border border-border space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-ink text-sm">{{ $chan['name'] }}</span>
                                <span class="font-extrabold text-emerald-600 text-xs px-2 py-0.5 rounded bg-emerald-50 border border-emerald-200">{{ $chan['success_rate'] }}%</span>
                            </div>
                            <div class="flex items-center justify-between text-xs text-muted pt-1 border-t border-border/80">
                                <span>Sent: {{ number_format($chan['sent']) }}</span>
                                <span>Delivered: {{ number_format($chan['delivered']) }}</span>
                                <span class="{{ $chan['failed'] > 0 ? 'text-rose-600 font-bold' : '' }}">Failed: {{ $chan['failed'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Safe Chart.js Initialization for Super Admin (Clean, High-Res, Balanced) -->
        <script>
            function renderAdminDashboardCharts() {
                const analytics = @json($adminLeadAnalytics);

                // 1. Lead Generation Trend Line Chart
                if (document.getElementById('adminLeadTrendChart')) {
                    const trend = analytics.trend || {};
                    window.initSafeChart('adminLeadTrendChart', {
                        type: 'line',
                        data: {
                            labels: trend.labels || [],
                            datasets: [
                                {
                                    label: 'Leads Ingested',
                                    data: trend.leads || [],
                                    borderColor: '#0F172A',
                                    backgroundColor: 'rgba(15, 23, 42, 0.04)',
                                    borderWidth: 2.5,
                                    fill: true,
                                    tension: 0.35,
                                    pointRadius: 3,
                                    pointBackgroundColor: '#0F172A',
                                },
                                {
                                    label: 'Deals Won / Converted',
                                    data: trend.won || [],
                                    borderColor: '#10B981',
                                    borderWidth: 2,
                                    borderDash: [4, 4],
                                    fill: false,
                                    tension: 0.35,
                                    pointRadius: 3,
                                    pointBackgroundColor: '#10B981',
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: { font: { family: 'Inter', size: 12, weight: 'bold' }, color: '#475569', boxWidth: 12 }
                                }
                            },
                            scales: {
                                x: { grid: { color: '#F1F5F9' }, ticks: { font: { family: 'Inter', size: 11 }, color: '#64748B' } },
                                y: { grid: { color: '#E2E8F0' }, ticks: { font: { family: 'Inter', size: 11 }, color: '#64748B' }, beginAtZero: true }
                            }
                        }
                    });
                }

                // 2. Lead Source Performance Donut Chart
                if (document.getElementById('adminLeadSourceChart')) {
                    const sources = analytics.sources || {};
                    window.initSafeChart('adminLeadSourceChart', {
                        type: 'doughnut',
                        data: {
                            labels: sources.labels || ['Meta', 'Google', 'Portals'],
                            datasets: [{
                                data: sources.counts && sources.counts.length ? sources.counts : [1],
                                backgroundColor: sources.colors || ['#2563EB', '#10B981', '#F59E0B'],
                                borderWidth: 3,
                                borderColor: '#FFFFFF',
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            cutout: '68%'
                        }
                    });
                }

                // 3. Status Distribution Doughnut Chart
                if (document.getElementById('adminLeadStatusChart')) {
                    const statuses = analytics.statuses || {};
                    window.initSafeChart('adminLeadStatusChart', {
                        type: 'doughnut',
                        data: {
                            labels: statuses.labels || ['New', 'Assigned', 'Contacted'],
                            datasets: [{
                                data: statuses.counts && statuses.counts.length ? statuses.counts : [1, 0, 0],
                                backgroundColor: statuses.colors || ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'],
                                borderWidth: 2.5,
                                borderColor: '#FFFFFF',
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'right',
                                    labels: { font: { family: 'Inter', size: 11 }, color: '#475569', boxWidth: 10 }
                                }
                            },
                            cutout: '65%'
                        }
                    });
                }

                // 4. Organization Comparison Horizontal Bar Chart
                if (document.getElementById('adminOrgComparisonChart')) {
                    const orgComp = analytics.org_comparison || {};
                    window.initSafeChart('adminOrgComparisonChart', {
                        type: 'bar',
                        data: {
                            labels: orgComp.labels || [],
                            datasets: [
                                {
                                    label: 'Leads Received',
                                    data: orgComp.leads || [],
                                    backgroundColor: '#2563EB',
                                    borderRadius: 4,
                                    barPercentage: 0.6,
                                },
                                {
                                    label: 'Credits Used',
                                    data: orgComp.credits || [],
                                    backgroundColor: '#64748B',
                                    borderRadius: 4,
                                    barPercentage: 0.6,
                                }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: { font: { family: 'Inter', size: 11 }, color: '#475569', boxWidth: 10 }
                                }
                            },
                            scales: {
                                x: { grid: { color: '#F1F5F9' }, ticks: { font: { family: 'Inter', size: 10 }, color: '#64748B' }, beginAtZero: true },
                                y: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 11 }, color: '#334155' } }
                            }
                        }
                    });
                }
            }

            document.addEventListener('livewire:navigated', renderAdminDashboardCharts);
            document.addEventListener('DOMContentLoaded', renderAdminDashboardCharts);
            document.addEventListener('livewire:initialized', renderAdminDashboardCharts);
            document.addEventListener('livewire:updated', renderAdminDashboardCharts);
        </script>

    @else
        <!-- ================================================================= -->
        <!-- TAILORED DASHBOARD VIEW FOR NON-ADMIN ROLES                       -->
        <!-- (Builder, Broker, Channel Partner, Sales Executive, Client)       -->
        <!-- ================================================================= -->
        <!-- Role-Tailored Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b border-border">
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight">{{ $headerInfo['title'] }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $headerInfo['badgeClass'] }}">
                        {{ $headerInfo['roleBadge'] }}
                    </span>
                </div>
                <p class="text-xs text-muted mt-1">{{ $headerInfo['subtitle'] }}</p>
            </div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="inline-flex items-center space-x-1.5 px-3 py-1.5 bg-surface border border-border rounded-full text-xs font-semibold text-ink shadow-2xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Live 30s Polling</span>
                </span>
            </div>
        </div>

        <!-- Quick Action Shortcuts Bar -->
        @if(!empty($quickActions))
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
                <span class="text-xs font-bold text-muted uppercase tracking-wider shrink-0 mr-1">Quick Actions:</span>
                @foreach($quickActions as $act)
                    <a href="{{ $act['url'] }}" wire:navigate
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition shrink-0 cursor-pointer {{ ($act['primary'] ?? false) ? 'bg-ink text-white hover:bg-neutral-800 shadow-2xs' : 'bg-surface border border-border text-ink hover:bg-canvas' }}">
                        @if(($act['icon'] ?? '') === 'upload')
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        @elseif(($act['icon'] ?? '') === 'leads')
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        @elseif(($act['icon'] ?? '') === 'wallet')
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        @elseif(($act['icon'] ?? '') === 'team' || ($act['icon'] ?? '') === 'users')
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        @elseif(($act['icon'] ?? '') === 'building')
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        @elseif(($act['icon'] ?? '') === 'reports')
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        @else
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        @endif
                        <span>{{ $act['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <!-- Stat Cards Grid (Row of 4) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach ($stats as $stat)
                <livewire:dashboard.stat-card
                    :title="$stat['title']"
                    :label="$stat['label']"
                    :value="$stat['value']"
                    :delta="$stat['delta']"
                    :isPositive="$stat['isPositive']"
                    :icon="$stat['icon']"
                    :key="$stat['title'] . '-' . $stat['value']"
                />
            @endforeach
        </div>

        <!-- Main Section: Chart + Upcoming Panel -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Revenue & Lead Volume Line Chart (2 Cols) -->
            <div class="lg:col-span-2">
                <x-ui.card title="Lead Volume & Velocity Trends (6 Months)">
                    <div class="relative h-72 w-full">
                        <canvas id="dashboardRevenueChart"></canvas>
                    </div>
                </x-ui.card>
            </div>

            <!-- Upcoming Panel -->
            <div>
                <x-ui.card title="Upcoming Schedule & Follow-ups">
                    <div class="divide-y divide-border">
                        @forelse ($upcomingEvents as $event)
                            <div class="py-3 first:pt-0 last:pb-0 flex items-start justify-between">
                                <div class="space-y-1">
                                    <div class="text-xs font-semibold text-ink">{{ $event['title'] }}</div>
                                    <div class="text-[11px] text-muted">{{ $event['project'] }} &bull; <span class="font-medium text-ink">{{ $event['due_time'] }}</span></div>
                                </div>
                                <x-ui.badge :status="$event['status']" />
                            </div>
                        @empty
                            <div class="py-6 text-center text-xs text-muted">
                                No immediate follow-ups scheduled.
                            </div>
                        @endforelse
                    </div>  
                </x-ui.card>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <x-ui.card title="Recent Activity Stream">
            <div class="space-y-3">
                @forelse ($auditLogs as $index => $log)
                    <div class="flex items-center space-x-3 text-xs py-1 border-b border-border last:border-0">
                        <div class="h-6 w-6 rounded-full bg-canvas border border-border flex items-center justify-center font-bold text-muted flex-shrink-0 text-[10px]">
                            {{ $index + 1 }}
                        </div>
                        <div class="text-ink flex-1 leading-relaxed">
                            {!! $log !!}
                        </div>
                        <span class="text-[10px] text-muted flex-shrink-0">Recent</span>
                    </div>
                @empty
                    <div class="py-4 text-center text-xs text-muted">
                        No recent activity records found.
                    </div>
                @endforelse
            </div>
        </x-ui.card>

        <!-- Chart.js Initialization for Non-Admin -->
        <script>
            function renderDashboardRevenueChart() {
                const chartData = @json($chartData);
                window.initSafeChart('dashboardRevenueChart', {
                    type: 'line',
                    data: {
                        labels: chartData.labels || [],
                        datasets: [
                            {
                                label: 'Lead Ingestion',
                                data: chartData.leads || [],
                                borderColor: '#0A0A0A',
                                backgroundColor: 'rgba(10, 10, 10, 0.05)',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3,
                                pointRadius: 3,
                                pointBackgroundColor: '#0A0A0A',
                            },
                            {
                                label: 'Closed Revenue (₹ Lacs)',
                                data: chartData.revenue || [],
                                borderColor: '#6B7280',
                                borderWidth: 2,
                                borderDash: [4, 4],
                                fill: false,
                                tension: 0.3,
                                pointRadius: 3,
                                pointBackgroundColor: '#6B7280',
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: { font: { family: 'Inter', size: 11 }, color: '#6B7280', boxWidth: 12 }
                            }
                        },
                        scales: {
                            x: { grid: { color: '#F5F5F5' }, ticks: { font: { family: 'Inter', size: 11 }, color: '#6B7280' } },
                            y: { grid: { color: '#E5E7EB' }, ticks: { font: { family: 'Inter', size: 11 }, color: '#6B7280' } }
                        }
                    }
                });
            }

            document.addEventListener('livewire:navigated', renderDashboardRevenueChart);
            document.addEventListener('DOMContentLoaded', renderDashboardRevenueChart);
            document.addEventListener('livewire:initialized', renderDashboardRevenueChart);
        </script>
    @endif
</div>
