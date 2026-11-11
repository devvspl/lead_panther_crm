<div wire:poll.30s class="space-y-4 pb-8">
    @if($role === 'admin')
        <!-- ================================================================= -->
        <!-- SUPER ADMIN DASHBOARD                                             -->
        <!-- ================================================================= -->

        <!-- 1. Header & Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-border">
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold text-ink tracking-tight">Super Admin Dashboard</h1>
                <span class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 bg-surface border border-border rounded-full text-[11px] font-medium text-muted">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Live</span>
                </span>
            </div>
            
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" wire:click="resetFilters"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-surface hover:bg-canvas border border-border rounded-lg text-xs font-semibold text-ink transition cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Reset</span>
                </button>
                <x-ui.export-button target="exportExcel" label="Export to Excel" />
            </div>
        </div>

        <!-- 2. Global Dashboard Filters Bar -->
        <div class="bg-surface border border-border rounded-xl p-3 sm:p-3.5 space-y-2.5">
            <div class="flex items-center justify-between gap-2 border-b border-border/70 pb-2">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-ink uppercase tracking-wider">Filters</span>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" wire:click="applyFilters"
                            class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-semibold transition cursor-pointer">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Apply</span>
                    </button>
                    <button type="button" wire:click="resetFilters"
                            class="inline-flex items-center gap-1 px-2.5 py-1 bg-surface hover:bg-canvas border border-border text-muted hover:text-ink rounded-lg text-xs font-semibold transition cursor-pointer">
                        <span>Reset</span>
                    </button>
                </div>
            </div>

            @php
                $leadStatusOptions = [
                    'new' => 'New (Uncontacted)',
                    'assigned' => 'Assigned',
                    'contacted' => 'Contacted',
                    'in_progress' => 'In Progress',
                    'qualified' => 'Qualified',
                    'site_visit' => 'Site Visit',
                    'negotiation' => 'Negotiation',
                    'booking' => 'Booking',
                    'closed_won' => 'Closed Won',
                    'lost' => 'Lost',
                    'duplicate' => 'Duplicate',
                    'unassigned' => 'Unassigned',
                    'sla_breached' => 'SLA Breached (>30m)',
                ];
            @endphp

            <!-- Filters Grid (7 Filters) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2.5 items-start">
                <!-- 1. Date Range -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Date Range</label>
                    <x-ui.date-range-picker 
                        wire:model.live="dateRange" 
                        wire:custom-from.live="customFrom" 
                        wire:custom-to.live="customTo" 
                        class="w-full"
                    />
                </div>

                <!-- 2. Organization -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Organization</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterOrganizationId" 
                        model="App\Models\Organization" 
                        placeholder="All Organizations" 
                        class="w-full"
                        :key="'dash-filter-org-'.($filterOrganizationId ?? 'none')" 
                    />
                </div>

                <!-- 3. Project -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Project</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterProjectId" 
                        model="App\Models\Project" 
                        placeholder="All Projects" 
                        class="w-full"
                        :key="'dash-filter-project-'.($filterProjectId ?? 'none')" 
                    />
                </div>

                <!-- 4. Team -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Team</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterTeamId" 
                        model="App\Models\SalesTeam" 
                        placeholder="All Teams" 
                        class="w-full"
                        :key="'dash-filter-team-'.($filterTeamId ?? 'none')" 
                    />
                </div>

                <!-- 5. Lead Source -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Lead Source</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterLeadSourceId" 
                        model="App\Models\LeadSource" 
                        placeholder="All Sources" 
                        class="w-full"
                        :key="'dash-filter-src-'.($filterLeadSourceId ?? 'none')" 
                    />
                </div>

                <!-- 6. Assigned Agent -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Assigned Agent</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterAssignedUserId" 
                        model="App\Models\User" 
                        placeholder="All Agents" 
                        class="w-full"
                        :key="'dash-filter-user-'.($filterAssignedUserId ?? 'none')" 
                    />
                </div>

                <!-- 7. Lead Status -->
                <div class="flex flex-col space-y-1 w-full">
                    <label class="block text-[11px] font-semibold text-muted truncate">Lead Status</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterStatus" 
                        :options="$leadStatusOptions" 
                        placeholder="All Statuses" 
                        class="w-full"
                        :key="'dash-filter-status-'.($filterStatus ?? 'none')" 
                    />
                </div>
            </div>

            <!-- Active Filters Bar -->
            @php
                $hasActiveFilters = $filterOrganizationId || $filterProjectId || $filterTeamId || $filterLeadSourceId || $filterAssignedUserId || $filterStatus || $filterCampaignId || $filterTransactionType || $dateRange !== '30_days';
            @endphp
            @if($hasActiveFilters)
                <div class="flex items-center gap-2 flex-wrap pt-2 border-t border-border/70 text-xs">
                    <span class="font-semibold text-muted text-[11px]">Active:</span>
                    @if($dateRange !== '30_days')
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                            {{ ucfirst(str_replace('_', ' ', $dateRange)) }}
                            <button type="button" wire:click="$set('dateRange', '30_days')" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                        </span>
                    @endif
                    @if($filterOrganizationId)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                            Org #{{ $filterOrganizationId }}
                            <button type="button" wire:click="$set('filterOrganizationId', null)" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                        </span>
                    @endif
                    @if($filterProjectId)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                            Project #{{ $filterProjectId }}
                            <button type="button" wire:click="$set('filterProjectId', null)" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                        </span>
                    @endif
                    @if($filterTeamId)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                            Team #{{ $filterTeamId }}
                            <button type="button" wire:click="$set('filterTeamId', null)" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                        </span>
                    @endif
                    @if($filterLeadSourceId)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                            {{ $sourceOptions[$filterLeadSourceId] ?? $filterLeadSourceId }}
                            <button type="button" wire:click="$set('filterLeadSourceId', null)" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                        </span>
                    @endif
                    @if($filterStatus)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                            {{ ucfirst(str_replace('_', ' ', $filterStatus)) }}
                            <button type="button" wire:click="$set('filterStatus', null)" class="text-muted hover:text-ink cursor-pointer">&times;</button>
                        </span>
                    @endif
                    <button type="button" wire:click="resetFilters" class="text-[11px] text-blue-600 hover:underline font-semibold ml-1 cursor-pointer">
                        Clear All
                    </button>
                </div>
            @endif
        </div>

        <!-- 3. Primary KPI Cards -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold text-muted uppercase tracking-wider">Key Metrics</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                @foreach($groupedKpis['hero'] as $hero)
                    <a href="{{ $hero['route'] }}" wire:navigate
                       class="group bg-surface hover:bg-canvas border border-border hover:border-ink/30 rounded-xl p-3.5 transition flex flex-col justify-between cursor-pointer">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-muted">{{ $hero['title'] }}</span>
                            <div class="p-1.5 rounded-lg bg-canvas border border-border text-muted group-hover:text-ink transition">
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

                        <div class="mt-2">
                            <div class="text-xl sm:text-2xl font-bold text-ink tracking-tight">
                                {{ $hero['value'] }}
                            </div>
                        </div>

                        <div class="mt-2.5 pt-2 border-t border-border/70 flex items-center justify-between text-[11px]">
                            <span class="inline-flex items-center font-bold {{ $hero['isPositive'] ? 'text-emerald-600' : 'text-rose-600' }}">
                                @if($hero['isPositive'])
                                    <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                @else
                                    <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                @endif
                                {{ $hero['delta'] }}
                            </span>
                            <span class="text-muted truncate ml-1">{{ $hero['subtext'] }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- 4. Secondary Metric Groups (4 Summary Cards) -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold text-muted uppercase tracking-wider">Summary by Category</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- Group 1: Lead Overview -->
                <div class="bg-surface border border-border rounded-xl p-3.5 space-y-2 flex flex-col justify-between">
                    <div class="border-b border-border/70 pb-2">
                        <h3 class="text-xs font-bold text-ink flex items-center gap-1.5 uppercase tracking-wider">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            <span>{{ $groupedKpis['groups']['overview']['title'] }}</span>
                        </h3>
                    </div>

                    <div class="space-y-1.5 text-xs">
                        @foreach($groupedKpis['groups']['overview']['metrics'] as $m)
                            <a href="{{ $m['route'] }}" wire:navigate class="flex items-center justify-between py-0.5 hover:bg-canvas px-1 rounded transition group">
                                <span class="text-muted group-hover:text-ink font-medium">{{ $m['label'] }}</span>
                                <span class="font-bold text-ink">{{ $m['value'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Group 2: Lead Conversion -->
                <div class="bg-surface border border-border rounded-xl p-3.5 space-y-2 flex flex-col justify-between">
                    <div class="border-b border-border/70 pb-2">
                        <h3 class="text-xs font-bold text-ink flex items-center gap-1.5 uppercase tracking-wider">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>{{ $groupedKpis['groups']['conversion']['title'] }}</span>
                        </h3>
                    </div>

                    <div class="space-y-1.5 text-xs">
                        @foreach($groupedKpis['groups']['conversion']['metrics'] as $m)
                            <a href="{{ $m['route'] }}" wire:navigate class="flex items-center justify-between py-0.5 hover:bg-canvas px-1 rounded transition group">
                                <span class="text-muted group-hover:text-ink font-medium">{{ $m['label'] }}</span>
                                <span class="font-bold text-ink">{{ $m['value'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Group 3: SLA & Speed -->
                <div class="bg-surface border border-border rounded-xl p-3.5 space-y-2 flex flex-col justify-between">
                    <div class="border-b border-border/70 pb-2">
                        <h3 class="text-xs font-bold text-ink flex items-center gap-1.5 uppercase tracking-wider">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            <span>{{ $groupedKpis['groups']['sla']['title'] }}</span>
                        </h3>
                    </div>

                    <div class="space-y-2 text-xs">
                        @foreach($groupedKpis['groups']['sla']['metrics'] as $m)
                            <a href="{{ $m['route'] }}" wire:navigate class="flex items-center justify-between py-0.5 hover:bg-canvas px-1 rounded transition group">
                                <span class="text-muted group-hover:text-ink font-medium">{{ $m['label'] }}</span>
                                <span class="font-bold text-ink">{{ $m['value'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Group 4: Credit & Revenue -->
                <div class="bg-surface border border-border rounded-xl p-3.5 space-y-2 flex flex-col justify-between">
                    <div class="border-b border-border/70 pb-2">
                        <h3 class="text-xs font-bold text-ink flex items-center gap-1.5 uppercase tracking-wider">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            <span>{{ $groupedKpis['groups']['credit']['title'] }}</span>
                        </h3>
                    </div>

                    <div class="space-y-1.5 text-xs">
                        @foreach($groupedKpis['groups']['credit']['metrics'] as $m)
                            <a href="{{ $m['route'] }}" wire:navigate class="flex items-center justify-between py-0.5 hover:bg-canvas px-1 rounded transition group">
                                <span class="text-muted group-hover:text-ink font-medium">{{ $m['label'] }}</span>
                                <span class="font-bold text-ink">{{ $m['value'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Complete Lead Journey Pipeline -->
        <div class="bg-surface border border-border rounded-xl p-3.5 sm:p-4 space-y-2.5">
            <div class="flex items-center justify-between gap-2 border-b border-border/70 pb-2">
                <h2 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>Lead Pipeline</span>
                </h2>
                <div class="flex items-center gap-3 text-[11px] font-medium text-muted">
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Ingestion</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Follow-up</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Closure</span>
                </div>
            </div>

            <!-- Horizontal Responsive Interactive Pipeline -->
            <div class="overflow-x-auto no-scrollbar pb-1">
                <div class="flex items-stretch gap-2 min-w-max">
                    @foreach($adminFunnel as $stg)
                        <a href="{{ $stg['drill_route'] }}" wire:navigate
                           class="w-36 bg-canvas hover:bg-surface border border-border hover:border-ink/40 rounded-lg p-2.5 transition flex flex-col justify-between shrink-0 relative group cursor-pointer">
                            <!-- Stage Header -->
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-[10px] font-bold text-muted uppercase">#{{ $stg['index'] }}</span>
                                <span class="text-[10px] font-medium px-1.5 py-0.2 rounded bg-surface border border-border text-muted">
                                    {{ $stg['avg_time'] }}
                                </span>
                            </div>

                            <!-- Stage Name -->
                            <div class="text-xs font-bold text-ink mt-1.5 truncate group-hover:text-blue-600 transition" title="{{ $stg['name'] }}">
                                {{ $stg['name'] }}
                            </div>

                            <!-- Lead Count & Conversion -->
                            <div class="mt-1.5 space-y-0.5">
                                <div class="text-base font-bold text-ink tracking-tight">
                                    {{ number_format($stg['count']) }}
                                </div>
                                
                                <div class="flex items-center justify-between text-[11px] text-muted">
                                    <span>Conv:</span>
                                    <span class="font-bold text-emerald-600">{{ $stg['conversion_pct'] }}%</span>
                                </div>

                                @if($stg['drop_off'] > 0)
                                    <div class="flex items-center justify-between text-[10px] text-rose-600 font-medium">
                                        <span>Drop:</span>
                                        <span>-{{ number_format($stg['drop_off']) }}</span>
                                    </div>
                                @else
                                    <div class="text-[10px] text-muted text-right">0 drop</div>
                                @endif
                            </div>

                            <!-- Step Progress Bar -->
                            <div class="w-full bg-surface rounded-full h-1 mt-1.5 overflow-hidden border border-border">
                                <div class="h-1 rounded-full {{ $stg['index'] <= 5 ? 'bg-blue-600' : ($stg['index'] <= 9 ? 'bg-amber-500' : 'bg-emerald-600') }}"
                                     style="width: {{ max(6, $stg['conversion_pct']) }}%;"></div>
                            </div>
                        </a>

                        @if(!$loop->last)
                            <div class="flex items-center justify-center text-muted shrink-0">
                                <svg class="w-3.5 h-3.5 text-border" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <!-- 6. Analytics Section (Balanced Grid) -->
        <div class="space-y-3">
            <!-- Row 1: Lead Trend (2 Cols) + Lead Source Donut (1 Col) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                <!-- Lead Generation Velocity (2 Cols) -->
                <div class="lg:col-span-2 bg-surface border border-border rounded-xl p-3.5 sm:p-4 space-y-3">
                    <div class="flex items-center justify-between gap-2 border-b border-border/70 pb-2">
                        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Lead Trends</h2>
                        
                        <div class="flex items-center gap-1 bg-canvas p-0.5 rounded-lg border border-border">
                            <button type="button" wire:click="setTrendInterval('daily')"
                                    class="px-2.5 py-1 text-xs font-semibold rounded transition cursor-pointer {{ $trendInterval === 'daily' ? 'bg-ink text-white' : 'text-muted hover:text-ink' }}">
                                Daily
                            </button>
                            <button type="button" wire:click="setTrendInterval('weekly')"
                                    class="px-2.5 py-1 text-xs font-semibold rounded transition cursor-pointer {{ $trendInterval === 'weekly' ? 'bg-ink text-white' : 'text-muted hover:text-ink' }}">
                                Weekly
                            </button>
                            <button type="button" wire:click="setTrendInterval('monthly')"
                                    class="px-2.5 py-1 text-xs font-semibold rounded transition cursor-pointer {{ $trendInterval === 'monthly' ? 'bg-ink text-white' : 'text-muted hover:text-ink' }}">
                                Monthly
                            </button>
                        </div>
                    </div>

                    <div class="relative h-52 w-full">
                        <canvas id="adminLeadTrendChart"></canvas>
                    </div>
                </div>

                <!-- Lead Source Performance Donut (1 Col) -->
                <div class="bg-surface border border-border rounded-xl p-3.5 sm:p-4 space-y-2.5 flex flex-col justify-between">
                    <div class="border-b border-border/70 pb-2">
                        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Lead Sources</h2>
                    </div>

                    <div class="relative h-40 w-full flex items-center justify-center">
                        <canvas id="adminLeadSourceChart"></canvas>
                    </div>

                    <div class="pt-2 border-t border-border/70 space-y-1 text-xs">
                        @foreach($adminLeadAnalytics['sources']['labels'] as $idx => $label)
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 text-muted">
                                    <span class="w-2 h-2 rounded-full" style="background-color: {{ $adminLeadAnalytics['sources']['colors'][$idx] ?? '#2563EB' }};"></span>
                                    <span>{{ $label }}</span>
                                </span>
                                <span class="font-bold text-ink">{{ $adminLeadAnalytics['sources']['counts'][$idx] ?? 0 }} ({{ $adminLeadAnalytics['sources']['percentages'][$idx] ?? 0 }}%)</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Row 2: Status Distribution (1 Col) + Organization Comparison (1 Col) + Top Sales Agents (1 Col) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                <!-- Status Distribution Chart -->
                <div class="bg-surface border border-border rounded-xl p-3.5 sm:p-4 space-y-2.5 flex flex-col justify-between">
                    <div class="border-b border-border/70 pb-2">
                        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Lead Status</h2>
                    </div>

                    <div class="relative h-40 w-full flex items-center justify-center">
                        <canvas id="adminLeadStatusChart"></canvas>
                    </div>

                    <div class="pt-2 border-t border-border/70 grid grid-cols-2 gap-2 text-xs">
                        <div class="p-1.5 rounded-lg bg-canvas border border-border">
                            <span class="text-muted block text-[11px]">Uncontacted</span>
                            <span class="font-bold text-ink text-sm">{{ $groupedKpis['groups']['overview']['metrics'][1]['value'] ?? '0' }}</span>
                        </div>
                        <div class="p-1.5 rounded-lg bg-canvas border border-border">
                            <span class="text-muted block text-[11px]">Qualified</span>
                            <span class="font-bold text-emerald-600 text-sm">{{ $groupedKpis['groups']['overview']['metrics'][3]['value'] ?? '0' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Organization Comparison Chart -->
                <div class="bg-surface border border-border rounded-xl p-3.5 sm:p-4 space-y-2.5 flex flex-col justify-between">
                    <div class="border-b border-border/70 pb-2 flex items-center justify-between">
                        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Organizations</h2>
                        <a href="{{ route('admin.organizations') }}" wire:navigate class="text-xs text-blue-600 font-semibold hover:underline">View All &rarr;</a>
                    </div>

                    <div class="relative h-40 w-full">
                        <canvas id="adminOrgComparisonChart"></canvas>
                    </div>

                    <div class="pt-2 border-t border-border/70 text-[11px] text-muted text-center">
                        Top 5 clients by lead volume & credits
                    </div>
                </div>

                <!-- Top Sales Executives Leaderboard -->
                <div class="bg-surface border border-border rounded-xl p-3.5 sm:p-4 space-y-2.5 flex flex-col justify-between">
                    <div class="border-b border-border/70 pb-2 flex items-center justify-between">
                        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Top Agents</h2>
                        <a href="{{ route('admin.users') }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">All &rarr;</a>
                    </div>

                    <div class="space-y-2">
                        @forelse($adminLeadAnalytics['agents'] as $agent)
                            <div class="p-2 rounded-lg bg-canvas border border-border space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-5 h-5 rounded-full bg-surface border border-border flex items-center justify-center font-bold text-[10px] text-ink">
                                            {{ strtoupper(substr($agent['name'], 0, 1)) }}
                                        </div>
                                        <span class="font-semibold text-ink">{{ $agent['name'] }}</span>
                                    </div>
                                    <span class="font-bold text-emerald-600">{{ $agent['conversion_rate'] }}%</span>
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-muted">
                                    <span>Assigned: {{ number_format($agent['assigned']) }}</span>
                                    <span class="font-semibold text-ink">Won: {{ number_format($agent['won']) }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="py-6 text-center text-xs text-muted">
                                No sales assignments recorded.
                            </div>
                        @endforelse
                    </div>

                    <div class="pt-1 text-[11px] text-center text-muted">
                        Ranked by deal conversions
                    </div>
                </div>
            </div>
        </div>

        <!-- 7. Organization & Project Analytics -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <!-- Organization Performance Table -->
            <div class="bg-surface border border-border rounded-xl p-3.5 sm:p-4 space-y-2.5">
                <div class="flex items-center justify-between border-b border-border/70 pb-2">
                    <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Organizations</h2>
                    <a href="{{ route('admin.organizations') }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">Manage &rarr;</a>
                </div>

                <div class="overflow-x-auto max-h-72 overflow-y-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 bg-surface">
                            <tr class="border-b border-border text-[11px] font-bold text-muted uppercase tracking-wider">
                                <th class="py-2">Organization</th>
                                <th class="py-2 text-right">Leads</th>
                                <th class="py-2 text-right">Credits</th>
                                <th class="py-2 text-right">Wallet</th>
                                <th class="py-2 text-right">SLA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($adminOrgPerformance as $org)
                                <tr class="hover:bg-canvas/50 transition">
                                    <td class="py-2 font-semibold text-ink">
                                        <a href="{{ route('admin.organizations') }}" wire:navigate class="hover:text-blue-600 transition block font-bold text-xs">
                                            {{ $org['name'] }}
                                        </a>
                                        <span class="text-[11px] text-muted font-normal">{{ $org['active_users'] }} agents</span>
                                    </td>
                                    <td class="py-2 text-right font-semibold text-ink">{{ number_format($org['total_leads']) }}</td>
                                    <td class="py-2 text-right text-muted">{{ number_format($org['credits_consumed']) }} Cr</td>
                                    <td class="py-2 text-right font-bold {{ $org['wallet_balance'] < 100 ? 'text-rose-600' : 'text-ink' }}">
                                        {{ number_format($org['wallet_balance']) }} Cr
                                    </td>
                                    <td class="py-2 text-right">
                                        @if($org['sla_breaches'] > 0)
                                            <span class="inline-flex px-1.5 py-0.2 rounded-full bg-rose-50 text-rose-700 text-[10px] font-bold border border-rose-200">
                                                {{ $org['sla_breaches'] }}
                                            </span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-xs text-muted">No organization activity found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Project Performance Table -->
            <div class="bg-surface border border-border rounded-xl p-3.5 sm:p-4 space-y-2.5">
                <div class="flex items-center justify-between border-b border-border/70 pb-2">
                    <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Projects</h2>
                    <a href="{{ route('leads.index') }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">View &rarr;</a>
                </div>

                <div class="overflow-x-auto max-h-72 overflow-y-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 bg-surface">
                            <tr class="border-b border-border text-[11px] font-bold text-muted uppercase tracking-wider">
                                <th class="py-2">Project</th>
                                <th class="py-2">Client</th>
                                <th class="py-2 text-right">Leads</th>
                                <th class="py-2 text-right">Credits</th>
                                <th class="py-2 text-right">Conv %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($adminProjectPerformance as $proj)
                                <tr class="hover:bg-canvas/50 transition">
                                    <td class="py-2 font-semibold text-ink">
                                        <a href="{{ route('leads.index') }}" wire:navigate class="hover:text-blue-600 transition block font-bold text-xs">
                                            {{ $proj['name'] }}
                                        </a>
                                        <span class="text-[11px] text-muted font-normal">{{ $proj['location'] }}</span>
                                    </td>
                                    <td class="py-2 text-muted truncate max-w-xs">{{ $proj['client_name'] }}</td>
                                    <td class="py-2 text-right font-semibold text-ink">{{ number_format($proj['total_leads']) }}</td>
                                    <td class="py-2 text-right text-muted">{{ number_format($proj['total_credits_charged']) }} Cr</td>
                                    <td class="py-2 text-right font-bold text-emerald-600">{{ $proj['conversion_rate'] }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-xs text-muted">
                                        No projects in current scope.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 8. Credit & Distribution Monitoring -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <!-- Credit Protection & Wallet Engine -->
            <div class="bg-surface border border-border rounded-xl p-3.5 sm:p-4 space-y-2.5">
                <div class="flex items-center justify-between border-b border-border/70 pb-2">
                    <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Credits & Wallet</h2>
                    <a href="{{ route('admin.credits') }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">Manage &rarr;</a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                    <div class="p-2.5 rounded-lg bg-canvas border border-border">
                        <span class="text-[11px] text-muted block">Supply</span>
                        <span class="text-sm font-bold text-ink mt-0.5 block">{{ number_format($adminCreditAnalytics['global_balance'] ?? 0) }} Cr</span>
                    </div>
                    <div class="p-2.5 rounded-lg bg-canvas border border-border">
                        <span class="text-[11px] text-muted block">Avg Cost</span>
                        <span class="text-sm font-bold text-ink mt-0.5 block">{{ $adminCreditAnalytics['cost_per_lead'] ?? 10 }} Cr</span>
                    </div>
                    <div class="p-2.5 rounded-lg bg-canvas border border-border">
                        <span class="text-[11px] text-muted block">Consumed</span>
                        <span class="text-sm font-bold text-neutral-700 mt-0.5 block">{{ number_format($adminCreditAnalytics['credits_consumed'] ?? 0) }} Cr</span>
                    </div>
                    <div class="p-2.5 rounded-lg bg-canvas border border-border">
                        <span class="text-[11px] text-muted block">Refunded</span>
                        <span class="text-sm font-bold text-emerald-600 mt-0.5 block">{{ number_format($adminCreditAnalytics['credits_refunded'] ?? 0) }} Cr</span>
                    </div>
                </div>

                @if(($adminCreditAnalytics['pending_recharges'] ?? 0) > 0)
                    <div class="p-2.5 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-between">
                        <span class="text-xs font-bold text-amber-900">{{ $adminCreditAnalytics['pending_recharges'] }} recharge requests pending</span>
                        <a href="{{ route('admin.credits') }}" wire:navigate class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded text-xs font-bold transition">
                            Review &rarr;
                        </a>
                    </div>
                @endif
            </div>

            <!-- Distribution Engine Monitoring -->
            <div class="bg-surface border border-border rounded-xl p-3.5 sm:p-4 space-y-2.5">
                <div class="flex items-center justify-between border-b border-border/70 pb-2">
                    <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Distribution</h2>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        {{ $adminDistributionMonitoring['success_rate'] ?? 100 }}% Success
                    </span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                    <div class="p-2.5 rounded-lg bg-canvas border border-border">
                        <span class="text-[11px] text-muted block">Round Robin</span>
                        <span class="text-sm font-bold text-ink mt-0.5 block">{{ number_format($adminDistributionMonitoring['round_robin'] ?? 0) }}</span>
                    </div>
                    <div class="p-2.5 rounded-lg bg-canvas border border-border">
                        <span class="text-[11px] text-muted block">Manual</span>
                        <span class="text-sm font-bold text-ink mt-0.5 block">{{ number_format($adminDistributionMonitoring['manual'] ?? 0) }}</span>
                    </div>
                    <div class="p-2.5 rounded-lg bg-canvas border border-border">
                        <span class="text-[11px] text-muted block">Unassigned</span>
                        <span class="text-sm font-bold {{ ($adminDistributionMonitoring['unassigned'] ?? 0) > 0 ? 'text-amber-600' : 'text-ink' }} mt-0.5 block">
                            {{ number_format($adminDistributionMonitoring['unassigned'] ?? 0) }}
                        </span>
                    </div>
                    <div class="p-2.5 rounded-lg bg-canvas border border-border">
                        <span class="text-[11px] text-muted block">Failed</span>
                        <span class="text-sm font-bold text-muted mt-0.5 block">{{ number_format($adminDistributionMonitoring['failed'] ?? 0) }}</span>
                    </div>
                </div>

                <div class="space-y-1">
                    <div class="flex items-center justify-between text-[11px] text-muted">
                        <span>Success Rate</span>
                        <span class="font-bold text-ink">{{ $adminDistributionMonitoring['success_rate'] ?? 100 }}%</span>
                    </div>
                    <div class="w-full bg-canvas rounded-full h-1.5 overflow-hidden border border-border">
                        <div class="bg-emerald-500 h-1.5 rounded-full" style="width: {{ $adminDistributionMonitoring['success_rate'] ?? 100 }}%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 9. Recent Activity -->
        <div class="bg-surface border border-border rounded-xl p-3.5 sm:p-4 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border/70 pb-2">
                <h2 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Recent Activity</span>
                </h2>

                <!-- 6 Category Tabs -->
                <div class="flex items-center gap-1 bg-canvas p-0.5 rounded-lg border border-border overflow-x-auto no-scrollbar">
                    <button type="button" wire:click="setActiveActivityTab('leads')"
                            class="px-2.5 py-1 text-xs font-semibold rounded transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'leads' ? 'bg-ink text-white' : 'text-muted hover:text-ink' }}">
                        Leads
                    </button>
                    <button type="button" wire:click="setActiveActivityTab('followups')"
                            class="px-2.5 py-1 text-xs font-semibold rounded transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'followups' ? 'bg-ink text-white' : 'text-muted hover:text-ink' }}">
                        Follow-ups
                    </button>
                    <button type="button" wire:click="setActiveActivityTab('sla')"
                            class="px-2.5 py-1 text-xs font-semibold rounded transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'sla' ? 'bg-ink text-white' : 'text-muted hover:text-ink' }}">
                        SLA Alerts
                    </button>
                    <button type="button" wire:click="setActiveActivityTab('transactions')"
                            class="px-2.5 py-1 text-xs font-semibold rounded transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'transactions' ? 'bg-ink text-white' : 'text-muted hover:text-ink' }}">
                        Credits
                    </button>
                    <button type="button" wire:click="setActiveActivityTab('replacements')"
                            class="px-2.5 py-1 text-xs font-semibold rounded transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'replacements' ? 'bg-ink text-white' : 'text-muted hover:text-ink' }}">
                        Replacements
                    </button>
                    <button type="button" wire:click="setActiveActivityTab('notifications')"
                            class="px-2.5 py-1 text-xs font-semibold rounded transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'notifications' ? 'bg-ink text-white' : 'text-muted hover:text-ink' }}">
                        Delivery
                    </button>
                </div>
            </div>

            <!-- Tab 1: Recent Leads -->
            @if($activeActivityTab === 'leads')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['recent_leads'] as $lead)
                        <div class="py-2 flex items-center justify-between text-xs hover:bg-canvas/50 px-1 rounded transition">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-full bg-blue-50 text-blue-700 border border-blue-200 flex items-center justify-center font-bold text-[11px] shrink-0">
                                    LP
                                </div>
                                <div>
                                    <div class="font-bold text-ink text-xs flex items-center gap-1.5">
                                        <a href="{{ route('leads.index') }}" wire:navigate class="hover:text-blue-600 transition">
                                            #{{ $lead->lead_code }} &bull; {{ $lead->name }}
                                        </a>
                                        <span class="inline-flex px-1.5 py-0.2 rounded text-[10px] font-medium bg-canvas border border-border text-muted">
                                            {{ ucfirst($lead->leadSource?->name ?: 'Direct') }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-muted">
                                        {{ $lead->client?->organization?->name ?: 'HQ' }} &bull; {{ substr($lead->mobile, 0, 4) . '***' . substr($lead->mobile, -3) }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-[11px] font-medium text-ink">{{ $lead->created_at ? $lead->created_at->diffForHumans() : 'Recently' }}</div>
                                <span class="inline-flex px-1.5 py-0.2 rounded-full text-[10px] font-bold mt-0.5 {{ $lead->assigned_to ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                    {{ $lead->assigned_to ? 'Assigned' : 'Unassigned' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-muted">No recent leads found.</div>
                    @endforelse
                </div>

            <!-- Tab 2: Upcoming Follow-ups -->
            @elseif($activeActivityTab === 'followups')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['upcoming_followups'] as $event)
                        <div class="py-2 flex items-center justify-between text-xs hover:bg-canvas/50 px-1 rounded transition">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-full bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center font-bold text-[11px] shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <div class="font-bold text-ink text-xs">
                                        {{ $event->lead?->name ?? 'Lead' }}
                                    </div>
                                    <div class="text-[11px] text-muted">
                                        {{ $event->note ?? 'Follow-up' }} &bull; {{ $event->lead?->assignedTo?->name ?? 'Agent' }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-[11px] font-medium text-ink">{{ $event->due_at ? \Carbon\Carbon::parse($event->due_at)->diffForHumans() : 'Upcoming' }}</div>
                                <span class="inline-flex px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-canvas border border-border text-muted mt-0.5">
                                    {{ ucfirst(str_replace('_', ' ', $event->status ?? 'Active')) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-muted">No upcoming follow-ups scheduled.</div>
                    @endforelse
                </div>

            <!-- Tab 3: SLA Alerts -->
            @elseif($activeActivityTab === 'sla')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['sla_breached'] as $lead)
                        <div class="py-2 flex items-center justify-between text-xs hover:bg-canvas/50 px-1 rounded transition">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-full bg-rose-50 text-rose-700 border border-rose-200 flex items-center justify-center font-bold text-xs shrink-0">
                                    !
                                </div>
                                <div>
                                    <div class="font-bold text-ink text-xs flex items-center gap-1.5">
                                        <a href="{{ route('leads.index') }}" wire:navigate class="hover:text-rose-600 transition">
                                            #{{ $lead->lead_code }} &bull; {{ $lead->name }}
                                        </a>
                                        <span class="inline-flex px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            >30m
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-muted">
                                        {{ $lead->assignedTo?->name ?: 'Unassigned' }} &bull; {{ $lead->created_at ? $lead->created_at->diffForHumans() : 'Earlier' }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <a href="{{ route('leads.index') }}" wire:navigate
                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition">
                                    Respond &rarr;
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-muted">
                            <span class="font-bold text-emerald-600 text-xs">&check; Zero Active SLA Breaches</span>
                        </div>
                    @endforelse
                </div>

            <!-- Tab 4: Credit Transactions -->
            @elseif($activeActivityTab === 'transactions')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['transactions'] as $tx)
                        <div class="py-2 flex items-center justify-between text-xs hover:bg-canvas/50 px-1 rounded transition">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-full {{ $tx->transaction_type === 'refund' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-canvas border-border text-muted' }} border flex items-center justify-center font-bold text-xs shrink-0">
                                    {{ strtoupper(substr($tx->transaction_type, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="font-bold text-ink text-xs flex items-center gap-1.5">
                                        <span>#{{ $tx->id }} &bull; {{ ucfirst($tx->transaction_type) }}</span>
                                        <span class="inline-flex px-1.5 py-0.2 rounded text-[10px] font-bold {{ $tx->transaction_type === 'refund' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-canvas text-ink border border-border' }}">
                                            {{ $tx->credit_used }} Cr
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-muted">
                                        {{ $tx->client?->name ?: 'Client' }} &bull; Bal: {{ $tx->credit_after }} Cr
                                    </div>
                                </div>
                            </div>
                            <div class="text-right text-[11px] text-muted">
                                {{ $tx->created_at ? $tx->created_at->diffForHumans() : 'Recent' }}
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-muted">No recent credit transactions found.</div>
                    @endforelse
                </div>

            <!-- Tab 5: Replacement Claims -->
            @elseif($activeActivityTab === 'replacements')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['replacements'] as $claim)
                        <div class="py-2 flex items-center justify-between text-xs hover:bg-canvas/50 px-1 rounded transition">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-full bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center font-bold text-xs shrink-0">
                                    R
                                </div>
                                <div>
                                    <div class="font-bold text-ink text-xs flex items-center gap-1.5">
                                        <span>#{{ $claim->id }} &bull; Lead #{{ $claim->lead?->lead_code ?: $claim->lead_id }}</span>
                                        <span class="inline-flex px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            {{ ucfirst($claim->status) }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-muted">
                                        {{ ($claim->requestedBy?->name ?? $claim->requester?->name) ?: 'Agent' }} &bull; {{ ($claim->reason?->label ?? $claim->reason?->reason_name) ?: 'Invalid Contact' }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <a href="{{ route('replacements.queue') }}" wire:navigate
                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-surface hover:bg-canvas border border-border text-ink text-xs font-bold transition">
                                    Review &rarr;
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-muted">
                            <span class="font-bold text-ink text-xs">No Pending Replacement Claims</span>
                        </div>
                    @endforelse
                </div>

            <!-- Tab 6: Multi-Channel Delivery Status -->
            @elseif($activeActivityTab === 'notifications')
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 pt-1">
                    @foreach($adminNotificationMonitoring['channels'] as $chan)
                        <div class="p-3 rounded-lg bg-canvas border border-border space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-ink text-xs">{{ $chan['name'] }}</span>
                                <span class="font-bold text-emerald-600 text-[11px] px-1.5 py-0.2 rounded bg-emerald-50 border border-emerald-200">{{ $chan['success_rate'] }}%</span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-muted pt-1 border-t border-border/70">
                                <span>Sent: {{ number_format($chan['sent']) }}</span>
                                <span>Del: {{ number_format($chan['delivered']) }}</span>
                                <span class="{{ $chan['failed'] > 0 ? 'text-rose-600 font-bold' : '' }}">Fail: {{ $chan['failed'] }}</span>
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
                                    label: 'Leads',
                                    data: trend.leads || [],
                                    borderColor: '#0F172A',
                                    backgroundColor: 'rgba(15, 23, 42, 0.04)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.35,
                                    pointRadius: 2.5,
                                    pointBackgroundColor: '#0F172A',
                                },
                                {
                                    label: 'Won',
                                    data: trend.won || [],
                                    borderColor: '#10B981',
                                    borderWidth: 2,
                                    borderDash: [3, 3],
                                    fill: false,
                                    tension: 0.35,
                                    pointRadius: 2.5,
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
                                    labels: { font: { family: 'Inter', size: 11, weight: 'bold' }, color: '#475569', boxWidth: 10 }
                                }
                            },
                            scales: {
                                x: { grid: { color: '#F1F5F9' }, ticks: { font: { family: 'Inter', size: 10 }, color: '#64748B' } },
                                y: { grid: { color: '#E2E8F0' }, ticks: { font: { family: 'Inter', size: 10 }, color: '#64748B' }, beginAtZero: true }
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
                                borderWidth: 2,
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
                            cutout: '70%'
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
                                borderWidth: 2,
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
                                    labels: { font: { family: 'Inter', size: 10 }, color: '#475569', boxWidth: 8 }
                                }
                            },
                            cutout: '68%'
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
                                    label: 'Leads',
                                    data: orgComp.leads || [],
                                    backgroundColor: '#2563EB',
                                    borderRadius: 3,
                                    barPercentage: 0.6,
                                },
                                {
                                    label: 'Credits',
                                    data: orgComp.credits || [],
                                    backgroundColor: '#64748B',
                                    borderRadius: 3,
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
                                    labels: { font: { family: 'Inter', size: 10 }, color: '#475569', boxWidth: 8 }
                                }
                            },
                            scales: {
                                x: { grid: { color: '#F1F5F9' }, ticks: { font: { family: 'Inter', size: 10 }, color: '#64748B' }, beginAtZero: true },
                                y: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 10 }, color: '#334155' } }
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

    @elseif($role === 'builder')
        <!-- ================================================================= -->
        <!-- BUILDER / DEVELOPER COMMAND CENTRE DASHBOARD                     -->
        <!-- 12 KPIs, Wallet Summary Card + Modal, Lead Distribution Card,     -->
        <!-- 6 Interactive Charts, Compact Filters Bar, Recent Inbound Leads  -->
        <!-- ================================================================= -->

        <!-- 1. Role-Tailored Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b border-border">
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight">Builder / Developer Dashboard</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border bg-emerald-50 text-emerald-800 border-emerald-200">
                        Builder / Developer
                    </span>
                </div>
                <p class="text-xs text-muted mt-1">
                    Portfolio performance, organization leads &amp; credits for <span class="font-bold text-ink">{{ $builderData['organization']?->name ?? 'Organization' }}</span>
                </p>
            </div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="inline-flex items-center space-x-1.5 px-3 py-1.5 bg-surface border border-border rounded-full text-xs font-semibold text-ink shadow-2xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Live 30s Polling</span>
                </span>
                <button type="button" wire:click="resetFilters"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border bg-surface hover:bg-canvas text-muted hover:text-ink text-xs font-semibold transition cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Reset</span>
                </button>
            </div>
        </div>

        <!-- 2. Quick Action Shortcuts Bar -->
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
                        @else
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        @endif
                        <span>{{ $act['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <!-- 3. Global Filters Bar (Compact Horizontal Section) -->
        <div class="bg-surface rounded-2xl border border-border p-3 shadow-2xs space-y-2">
            <div class="flex items-center justify-between pb-1.5 border-b border-border/60">
                <div class="flex items-center gap-1.5 text-xs font-bold text-ink uppercase tracking-wider">
                    <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span>Dashboard Filters</span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="$refresh"
                        class="px-2.5 py-1 bg-primary hover:bg-primary-dark text-white rounded-lg text-xs font-semibold transition cursor-pointer flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Apply</span>
                    </button>
                    <button type="button" wire:click="resetFilters"
                        class="px-2 py-1 text-muted hover:text-ink text-xs font-medium cursor-pointer">
                        Reset
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 text-xs">
                <!-- Date Range -->
                <div>
                    <label class="text-[10px] uppercase font-bold text-muted block mb-0.5">Date Range</label>
                    <select wire:model.live="dateRange"
                        class="w-full h-8 px-2 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-1 focus:ring-primary focus:border-primary transition cursor-pointer">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="7_days">Last 7 Days</option>
                        <option value="30_days">This Month (30d)</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>

                <!-- Project -->
                <div>
                    <label class="text-[10px] uppercase font-bold text-muted block mb-0.5">Project</label>
                    <select wire:model.live="filterProjectId"
                        class="w-full h-8 px-2 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-1 focus:ring-primary focus:border-primary transition cursor-pointer">
                        <option value="">All Projects</option>
                        @foreach($builderProjects as $pId => $pName)
                            <option value="{{ $pId }}">{{ $pName }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Team -->
                <div>
                    <label class="text-[10px] uppercase font-bold text-muted block mb-0.5">Team</label>
                    <select wire:model.live="filterTeamId"
                        class="w-full h-8 px-2 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-1 focus:ring-primary focus:border-primary transition cursor-pointer">
                        <option value="">All Teams</option>
                        @foreach($builderTeams as $tId => $tName)
                            <option value="{{ $tId }}">{{ $tName }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Team Member / Agent -->
                <div>
                    <label class="text-[10px] uppercase font-bold text-muted block mb-0.5">Team Member</label>
                    <select wire:model.live="filterAssignedUserId"
                        class="w-full h-8 px-2 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-1 focus:ring-primary focus:border-primary transition cursor-pointer">
                        <option value="">All Members</option>
                        @foreach($builderTeamMembers as $mId => $mName)
                            <option value="{{ $mId }}">{{ $mName }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Lead Status -->
                <div>
                    <label class="text-[10px] uppercase font-bold text-muted block mb-0.5">Status / Stage</label>
                    <select wire:model.live="filterStatus"
                        class="w-full h-8 px-2 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-1 focus:ring-primary focus:border-primary transition cursor-pointer">
                        <option value="">All Statuses</option>
                        <option value="new">New / Received</option>
                        <option value="assigned">Assigned</option>
                        <option value="unassigned">Unassigned Queue</option>
                        <option value="in_progress">In Progress</option>
                        <option value="contacted">Contacted</option>
                        <option value="interested">Interested</option>
                        <option value="site_visit">Site Visit</option>
                        <option value="negotiation">Negotiation</option>
                        <option value="booking">Booked / Won</option>
                        <option value="lost">Lost / Dead</option>
                    </select>
                </div>

                <!-- Lead Source -->
                <div>
                    <label class="text-[10px] uppercase font-bold text-muted block mb-0.5">Lead Source</label>
                    <select wire:model.live="filterLeadSourceId"
                        class="w-full h-8 px-2 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-1 focus:ring-primary focus:border-primary transition cursor-pointer">
                        <option value="">All Sources</option>
                        @foreach($sourceOptions as $sId => $sName)
                            <option value="{{ $sId }}">{{ $sName }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if($dateRange === 'custom')
                <div class="flex items-center gap-2 pt-1 border-t border-border/40">
                    <span class="text-[11px] text-muted">From:</span>
                    <input type="date" wire:model.live="customFrom" class="h-7 px-2 text-xs border border-border rounded bg-canvas">
                    <span class="text-[11px] text-muted">To:</span>
                    <input type="date" wire:model.live="customTo" class="h-7 px-2 text-xs border border-border rounded bg-canvas">
                </div>
            @endif
        </div>

        <!-- 4. Main Operational KPI Cards (11 Real Metrics) -->
        <!-- Primary Headline Row (4 Cards) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Total Leads Received -->
            <div class="bg-surface rounded-2xl border border-border p-4 shadow-2xs space-y-1 hover:border-primary/40 transition">
                <div class="flex items-center justify-between text-muted text-[11px] font-semibold uppercase tracking-wider">
                    <span>Total Leads Received</span>
                    <div class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-bold font-mono text-ink">
                    {{ number_format($builderData['kpis']['total_leads']['value'] ?? 0) }}
                </div>
                <div class="text-[10px] text-muted flex items-center justify-between">
                    <span class="inline-flex items-center gap-1 {{ ($builderData['kpis']['total_leads']['delta']['isPositive'] ?? true) ? 'text-emerald-600 font-bold' : 'text-neutral-500' }}">
                        {{ $builderData['kpis']['total_leads']['delta']['delta'] ?? '0%' }} vs prior
                    </span>
                    <span class="text-muted font-mono font-medium">Inbound portfolio</span>
                </div>
            </div>

            <!-- 2. New Leads -->
            <div class="bg-surface rounded-2xl border border-border p-4 shadow-2xs space-y-1 hover:border-primary/40 transition">
                <div class="flex items-center justify-between text-muted text-[11px] font-semibold uppercase tracking-wider">
                    <span>New Leads</span>
                    <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-bold font-mono text-emerald-600">
                    {{ number_format($builderData['kpis']['new_leads']['value'] ?? 0) }}
                </div>
                <div class="text-[10px] text-muted flex items-center justify-between">
                    <span class="text-emerald-700 font-bold">
                        {{ $builderData['kpis']['new_leads']['delta']['delta'] ?? '0%' }} vs prior
                    </span>
                    <span class="text-muted font-mono font-medium">Fresh inbound</span>
                </div>
            </div>

            <!-- 3. Unassigned Leads -->
            <div class="bg-surface rounded-2xl border border-border p-4 shadow-2xs space-y-1 hover:border-primary/40 transition">
                <div class="flex items-center justify-between text-muted text-[11px] font-semibold uppercase tracking-wider">
                    <span>Unassigned Leads</span>
                    <div class="w-7 h-7 rounded-lg {{ ($builderData['kpis']['unassigned_leads']['value'] ?? 0) > 0 ? 'bg-rose-50 text-rose-600' : 'bg-slate-50 text-slate-600' }} flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-bold font-mono {{ ($builderData['kpis']['unassigned_leads']['value'] ?? 0) > 0 ? 'text-rose-600' : 'text-ink' }}">
                    {{ number_format($builderData['kpis']['unassigned_leads']['value'] ?? 0) }}
                </div>
                <div class="text-[10px] text-muted flex items-center justify-between">
                    <span class="{{ ($builderData['kpis']['unassigned_leads']['value'] ?? 0) > 0 ? 'text-rose-600 font-bold' : 'text-muted' }}">
                        {{ ($builderData['kpis']['unassigned_leads']['value'] ?? 0) > 0 ? 'Needs assignment' : 'All assigned' }}
                    </span>
                    <button type="button" wire:click="autoDistributeBuilderLeads" class="text-primary hover:underline font-semibold cursor-pointer">
                        Distribute &rarr;
                    </button>
                </div>
            </div>

            <!-- 4. Assigned Leads -->
            <div class="bg-surface rounded-2xl border border-border p-4 shadow-2xs space-y-1 hover:border-primary/40 transition">
                <div class="flex items-center justify-between text-muted text-[11px] font-semibold uppercase tracking-wider">
                    <span>Assigned Leads</span>
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-bold font-mono text-ink">
                    {{ number_format($builderData['kpis']['assigned_leads']['value'] ?? 0) }}
                </div>
                <div class="text-[10px] text-muted flex items-center justify-between">
                    <span class="text-emerald-600 font-bold">
                        {{ $builderData['kpis']['assigned_leads']['delta']['delta'] ?? '0%' }} allocated
                    </span>
                    <span class="text-muted font-mono font-medium">Distributed to reps</span>
                </div>
            </div>
        </div>

        <!-- Secondary Compact Metric Grid (7 Operational Metrics) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
            <!-- 5. Leads in Progress -->
            <div class="bg-surface rounded-xl border border-border p-3 shadow-2xs space-y-0.5">
                <span class="text-[10px] font-semibold text-muted uppercase tracking-wider block">In Progress</span>
                <div class="text-lg font-bold font-mono text-amber-600">
                    {{ number_format($builderData['kpis']['in_progress_leads']['value'] ?? 0) }}
                </div>
                <div class="text-[9px] text-muted">Active conversations</div>
            </div>

            <!-- 6. Follow-ups Due -->
            <div class="bg-surface rounded-xl border border-border p-3 shadow-2xs space-y-0.5">
                <span class="text-[10px] font-semibold text-muted uppercase tracking-wider block">Follow-ups Due</span>
                <div class="text-lg font-bold font-mono text-blue-600">
                    {{ number_format($builderData['kpis']['followups_due']['value'] ?? 0) }}
                </div>
                <div class="text-[9px] text-muted">Scheduled client calls</div>
            </div>

            <!-- 7. Site Visits -->
            <div class="bg-surface rounded-xl border border-border p-3 shadow-2xs space-y-0.5">
                <span class="text-[10px] font-semibold text-muted uppercase tracking-wider block">Site Visits</span>
                <div class="text-lg font-bold font-mono text-purple-600">
                    {{ number_format($builderData['kpis']['site_visits']['value'] ?? 0) }}
                </div>
                <div class="text-[9px] text-muted">Property inspections</div>
            </div>

            <!-- 8. Bookings -->
            <div class="bg-surface rounded-xl border border-border p-3 shadow-2xs space-y-0.5">
                <span class="text-[10px] font-semibold text-muted uppercase tracking-wider block">Bookings</span>
                <div class="text-lg font-bold font-mono text-emerald-600">
                    {{ number_format($builderData['kpis']['bookings']['value'] ?? 0) }}
                </div>
                <div class="text-[9px] text-muted">Tokens &amp; units booked</div>
            </div>

            <!-- 9. Closed Won -->
            <div class="bg-surface rounded-xl border border-border p-3 shadow-2xs space-y-0.5">
                <span class="text-[10px] font-semibold text-muted uppercase tracking-wider block">Closed Won</span>
                <div class="text-lg font-bold font-mono text-green-700">
                    {{ number_format($builderData['kpis']['closed_won']['value'] ?? 0) }}
                </div>
                <div class="text-[9px] text-muted">Deals successfully closed</div>
            </div>

            <!-- 10. Replacement Requests -->
            <div class="bg-surface rounded-xl border border-border p-3 shadow-2xs space-y-0.5">
                <span class="text-[10px] font-semibold text-muted uppercase tracking-wider block">Replacements</span>
                <div class="text-lg font-bold font-mono text-rose-600">
                    {{ number_format($builderData['kpis']['replacement_requests']['value'] ?? 0) }}
                </div>
                <div class="text-[9px] text-muted">Claims submitted</div>
            </div>

            <!-- 11. Approved Replacements -->
            <div class="bg-surface rounded-xl border border-border p-3 shadow-2xs space-y-0.5">
                <span class="text-[10px] font-semibold text-muted uppercase tracking-wider block">Approved Repl.</span>
                <div class="text-lg font-bold font-mono text-emerald-600">
                    {{ number_format($builderData['kpis']['approved_replacements']['value'] ?? 0) }}
                </div>
                <div class="text-[9px] text-muted">Credits refunded</div>
            </div>
        </div>

        <!-- 5. Two Hero Operational Panels (Team Assignment & Lead Replacements) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Panel A: Team Assignment & Lead Distribution -->
            <div class="bg-surface rounded-2xl border border-border p-4 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-ink uppercase tracking-wider">Team Assignment Engine</h3>
                            <p class="text-[11px] text-muted">Internal sales distribution &amp; representative capacity</p>
                        </div>
                    </div>
                    <a href="{{ route('builder.team') }}" wire:navigate
                        class="px-3 py-1.5 rounded-lg border border-border bg-canvas hover:bg-surface text-ink text-xs font-semibold transition cursor-pointer flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <span>Team Management</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Super Admin Configured Distribution Rule (Read-Only) -->
                    <div class="p-3 rounded-xl bg-canvas border border-border space-y-1.5">
                        <span class="text-[10px] text-muted font-bold uppercase tracking-wider block">Active Distribution Rule</span>
                        <div class="flex items-center gap-1.5">
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                {{ ucwords(str_replace('_', ' ', $builderData['distribution_summary']['distribution_method'] ?? 'round_robin')) }}
                            </span>
                            <span class="text-[10px] text-muted font-medium">(Super Admin Configured)</span>
                        </div>
                        <p class="text-[10px] text-muted">
                            Leads are automatically routed to your sales executives using this policy.
                        </p>
                    </div>

                    <!-- Auto-Assignment Status -->
                    <div class="p-3 rounded-xl bg-canvas border border-border space-y-1.5">
                        <span class="text-[10px] text-muted font-bold uppercase tracking-wider block">Auto-Assignment Status</span>
                        <div>
                            @if($builderData['distribution_summary']['auto_assign_enabled'] ?? true)
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 inline-flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>Active Engine</span>
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                    Paused by Super Admin
                                </span>
                            @endif
                        </div>
                        <p class="text-[10px] text-muted">
                            New inbound leads are automatically dispatched to active sales reps upon delivery.
                        </p>
                    </div>
                </div>

                <!-- Unassigned Queue Alert & Action -->
                @if(($builderData['kpis']['unassigned_leads']['value'] ?? 0) > 0)
                    <div class="p-3 rounded-xl bg-blue-50 border border-blue-200 flex items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-2 text-blue-900">
                            <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
                            <span><strong>{{ $builderData['kpis']['unassigned_leads']['value'] }}</strong> leads waiting in unassigned queue.</span>
                        </div>
                        <button type="button" wire:click="autoDistributeBuilderLeads"
                            class="px-3 py-1 bg-primary hover:bg-primary-dark text-white text-xs font-semibold rounded-lg transition shadow-2xs cursor-pointer shrink-0">
                            Distribute Unassigned Leads
                        </button>
                    </div>
                @endif

                <!-- Team Workload Preview -->
                @if(!empty($builderData['distribution_summary']['team_distribution']) && count($builderData['distribution_summary']['team_distribution']) > 0)
                    <div class="space-y-1.5 pt-1">
                        <span class="text-[10px] font-bold text-muted uppercase tracking-wider block">Team Workload Distribution</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($builderData['distribution_summary']['team_distribution']->take(4) as $member)
                                <div class="p-2 rounded-lg bg-canvas border border-border/80 flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-primary/10 text-primary font-bold text-[10px] flex items-center justify-center">
                                            {{ strtoupper(substr($member->name, 0, 1)) }}
                                        </div>
                                        <span class="font-medium text-ink truncate max-w-[120px]">{{ $member->name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 font-mono text-[11px]">
                                        <span class="text-primary font-bold" title="Active Leads">{{ $member->active_leads_count }} act</span>
                                        <span class="text-emerald-600 font-bold" title="Won Leads">{{ $member->converted_leads_count }} won</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Panel B: Lead Replacements & Quality Operations -->
            <div class="bg-surface rounded-2xl border border-border p-4 shadow-2xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H4m0 0l4-4m-4 4l4 4m6 4v1a3 3 0 003 3h3a3 3 0 003-3V7a3 3 0 00-3-3h-3a3 3 0 00-3 3v1"/></svg>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-ink uppercase tracking-wider">Lead Replacement Operations</h3>
                            <p class="text-[11px] text-muted">Super Admin review queue &amp; credit refund tracking</p>
                        </div>
                    </div>
                    <a href="{{ route('replacements.client') }}" wire:navigate
                        class="px-3 py-1.5 rounded-lg border border-border bg-canvas hover:bg-surface text-ink text-xs font-semibold transition cursor-pointer flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span>Replacement Requests</span>
                    </a>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3 rounded-xl bg-canvas border border-border space-y-0.5">
                        <span class="text-[10px] text-muted font-semibold uppercase">Pending Claims</span>
                        <div class="text-xl font-bold font-mono text-amber-600">
                            {{ max(0, ($builderData['kpis']['replacement_requests']['value'] ?? 0) - ($builderData['kpis']['approved_replacements']['value'] ?? 0)) }}
                        </div>
                        <span class="text-[9px] text-muted">Awaiting Super Admin review</span>
                    </div>

                    <div class="p-3 rounded-xl bg-canvas border border-border space-y-0.5">
                        <span class="text-[10px] text-muted font-semibold uppercase">Approved Replacements</span>
                        <div class="text-xl font-bold font-mono text-emerald-600">
                            {{ number_format($builderData['kpis']['approved_replacements']['value'] ?? 0) }}
                        </div>
                        <span class="text-[9px] text-muted">Refunded &amp; replaced</span>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-canvas border border-border space-y-2 text-xs">
                    <div class="flex items-center gap-2 font-bold text-ink">
                        <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>How Lead Returns Work</span>
                    </div>
                    <p class="text-muted text-[11px] leading-relaxed">
                        If a lead has an invalid mobile number, duplicate contact details, or is unreachable within policy limits, click <strong>Return Lead</strong> on your leads table to submit a claim. Super Admin exclusively evaluates and approves claims to process credit refunds.
                    </p>
                </div>

                <div class="flex items-center justify-between pt-1 text-xs">
                    <a href="{{ route('leads.index') }}" wire:navigate class="text-primary font-bold hover:underline flex items-center gap-1 text-[11px]">
                        <span>Open My Leads to Return Unsuitable Leads</span>
                        <span>&rarr;</span>
                    </a>
                    <a href="{{ route('replacements.client') }}" wire:navigate class="text-muted hover:text-ink text-[11px] font-medium">
                        View Claims Log &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- 6. Six Interactive Analytics Charts Grid -->
        <div class="space-y-4">
            <!-- Charts Row 1: Generation Trend & Received vs Assigned -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Chart 1: Inbound Lead Generation Trend -->
                <div class="bg-surface rounded-2xl border border-border p-4 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-border">
                        <div>
                            <h3 class="text-xs font-bold text-ink uppercase tracking-wider">Inbound Lead Generation Trend</h3>
                            <p class="text-[11px] text-muted">Daily, weekly, or monthly lead inflow</p>
                        </div>
                        <div class="flex items-center gap-1 bg-canvas p-0.5 rounded-lg border border-border">
                            <button type="button" wire:click="$set('trendInterval', 'daily')"
                                class="px-2 py-0.5 text-[10px] font-bold rounded cursor-pointer {{ $trendInterval === 'daily' ? 'bg-surface text-ink shadow-2xs' : 'text-muted hover:text-ink' }}">
                                Daily
                            </button>
                            <button type="button" wire:click="$set('trendInterval', 'weekly')"
                                class="px-2 py-0.5 text-[10px] font-bold rounded cursor-pointer {{ $trendInterval === 'weekly' ? 'bg-surface text-ink shadow-2xs' : 'text-muted hover:text-ink' }}">
                                Weekly
                            </button>
                            <button type="button" wire:click="$set('trendInterval', 'monthly')"
                                class="px-2 py-0.5 text-[10px] font-bold rounded cursor-pointer {{ $trendInterval === 'monthly' ? 'bg-surface text-ink shadow-2xs' : 'text-muted hover:text-ink' }}">
                                Monthly
                            </button>
                        </div>
                    </div>
                    <div class="relative h-64 w-full">
                        <canvas id="builderTrendChart"></canvas>
                    </div>
                </div>

                <!-- Chart 2: Leads Received vs Assigned -->
                <div class="bg-surface rounded-2xl border border-border p-4 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-border">
                        <div>
                            <h3 class="text-xs font-bold text-ink uppercase tracking-wider">Leads Received vs Assigned</h3>
                            <p class="text-[11px] text-muted">Comparing total delivered vs assigned over time</p>
                        </div>
                        <span class="text-[10px] font-bold text-muted uppercase">Distribution velocity</span>
                    </div>
                    <div class="relative h-64 w-full">
                        <canvas id="builderReceivedVsAssignedChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2: Status Distribution & Credits Consumed -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Chart 3: Lead Status Distribution (Doughnut) -->
                <div class="bg-surface rounded-2xl border border-border p-4 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-border">
                        <div>
                            <h3 class="text-xs font-bold text-ink uppercase tracking-wider">Lead Status Distribution</h3>
                            <p class="text-[11px] text-muted">Breakdown of portfolio lifecycle stages</p>
                        </div>
                        <span class="text-[10px] font-bold text-muted uppercase">Current stages</span>
                    </div>
                    <div class="relative h-64 w-full">
                        <canvas id="builderStatusChart"></canvas>
                    </div>
                </div>

                <!-- Chart 4: Credits Consumed Trend -->
                <div class="bg-surface rounded-2xl border border-border p-4 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-border">
                        <div>
                            <h3 class="text-xs font-bold text-ink uppercase tracking-wider">Credits Consumed Trend</h3>
                            <p class="text-[11px] text-muted">Credit spend deducted per delivery period</p>
                        </div>
                        <span class="text-[10px] font-bold text-muted uppercase">Deduction history</span>
                    </div>
                    <div class="relative h-64 w-full">
                        <canvas id="builderCreditsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 3: Team Performance & Lead Conversion Funnel -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Chart 5: Team Member Performance Ranking -->
                <div class="bg-surface rounded-2xl border border-border p-4 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-border">
                        <div>
                            <h3 class="text-xs font-bold text-ink uppercase tracking-wider">Team Member Performance</h3>
                            <p class="text-[11px] text-muted">Active lead volume vs closed won deals per rep</p>
                        </div>
                        <span class="text-[10px] font-bold text-muted uppercase">Agent rankings</span>
                    </div>
                    <div class="relative h-64 w-full">
                        <canvas id="builderTeamChart"></canvas>
                    </div>
                </div>

                <!-- Chart 6: Lead Conversion Funnel Progression -->
                <div class="bg-surface rounded-2xl border border-border p-4 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-border">
                        <div>
                            <h3 class="text-xs font-bold text-ink uppercase tracking-wider">Lead Conversion Funnel</h3>
                            <p class="text-[11px] text-muted">Pipeline progression from intake to closed deal</p>
                        </div>
                        <span class="text-[10px] font-bold text-emerald-600 font-mono">
                            {{ $builderData['kpis']['conversion_rate']['value'] ?? 0 }}% Conversion
                        </span>
                    </div>
                    
                    <div class="space-y-2 py-2">
                        @if(!empty($builderData['charts']['funnel']))
                            @foreach($builderData['charts']['funnel'] as $stg)
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-medium text-ink">{{ $stg['stage'] }}</span>
                                        <span class="font-mono text-muted text-[11px]">
                                            <strong class="text-ink">{{ number_format($stg['count']) }}</strong> leads ({{ $stg['pct'] }}%)
                                        </span>
                                    </div>
                                    <div class="h-2 w-full bg-canvas rounded-full overflow-hidden border border-border/60">
                                        <div class="h-full bg-primary rounded-full transition-all duration-500" style="width: {{ max(4, min(100, $stg['pct'])) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="py-12 text-center text-xs text-muted">No funnel records for this period.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- 7. Recent Inbound Leads Table -->
        <div class="bg-surface rounded-2xl border border-border p-4 shadow-2xs space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-border">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-ink uppercase tracking-wider">Recent Inbound Leads</h3>
                        <p class="text-[11px] text-muted">Latest inquiries delivered to organization portfolio</p>
                    </div>
                </div>
                <a href="{{ route('leads.index') }}" wire:navigate class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                    <span>View All Leads</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas/40">
                            <th class="py-2.5 px-3">Lead Code</th>
                            <th class="py-2.5 px-3">Contact</th>
                            <th class="py-2.5 px-3">Project</th>
                            <th class="py-2.5 px-3">Source</th>
                            <th class="py-2.5 px-3">Stage</th>
                            <th class="py-2.5 px-3">Assigned Agent</th>
                            <th class="py-2.5 px-3 text-right">Received</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($builderData['recent_leads'] ?? [] as $lead)
                            <tr class="hover:bg-canvas/50 transition">
                                <td class="py-2.5 px-3 font-mono font-bold text-ink">
                                    {{ $lead->lead_code ?: ('#LEAD-' . $lead->id) }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-ink">{{ $lead->name }}</div>
                                    <div class="text-[11px] text-muted font-mono">{{ $lead->mobile }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-ink font-medium">
                                    {{ $lead->campaign?->project?->name ?: 'Direct' }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-canvas border border-border text-ink">
                                        {{ $lead->leadSource?->name ?: 'Manual' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border 
                                        {{ in_array($lead->current_stage, ['booking', 'closed_won']) ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : (in_array($lead->current_stage, ['lost', 'dead']) ? 'bg-rose-50 text-rose-800 border-rose-200' : 'bg-blue-50 text-blue-800 border-blue-200') }}">
                                        {{ ucfirst(str_replace('_', ' ', $lead->current_stage ?: 'new')) }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3">
                                    @if($lead->assignedTo)
                                        <div class="flex items-center gap-1.5 font-medium text-ink">
                                            <div class="w-5 h-5 rounded-full bg-primary/10 text-primary font-bold text-[9px] flex items-center justify-center">
                                                {{ strtoupper(substr($lead->assignedTo->name, 0, 1)) }}
                                            </div>
                                            <span>{{ $lead->assignedTo->name }}</span>
                                        </div>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            Unassigned
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 font-mono text-[11px] text-muted text-right">
                                    {{ $lead->created_at ? $lead->created_at->format('M d, H:i') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-xs text-muted">
                                    No leads received yet for this organization.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 8. Builder Recharge Request Modal -->
        @if($showBuilderRechargeModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-surface rounded-2xl border border-border shadow-2xl max-w-md w-full p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-ink">Request Credit Recharge</h3>
                            <p class="text-[11px] text-muted">Submit a recharge request to Super Admin for approval.</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeBuilderRechargeModal" class="text-muted hover:text-ink cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">Recharge Amount (Credits) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="number" wire:model="builderRechargeAmount" min="10" step="10" placeholder="e.g. 500"
                                class="w-full px-3 py-2 rounded-lg border border-border bg-canvas text-ink text-xs font-mono font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition">
                            <span class="absolute right-3 inset-y-0 flex items-center text-xs font-bold text-muted pointer-events-none">Credits</span>
                        </div>
                        @error('builderRechargeAmount') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Quick Amount Pills -->
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] text-muted font-semibold">Quick select:</span>
                        @foreach([100, 250, 500, 1000] as $preset)
                            <button type="button" wire:click="$set('builderRechargeAmount', {{ $preset }})"
                                class="px-2 py-0.5 rounded text-[10px] font-bold border border-border bg-canvas hover:bg-surface text-ink transition cursor-pointer">
                                {{ $preset }} Cr
                            </button>
                        @endforeach
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">Reference Note (Optional)</label>
                        <textarea wire:model="builderRechargeNote" rows="3" placeholder="e.g. Monthly campaign recharge for Q4 launch..."
                            class="w-full px-3 py-2 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition"></textarea>
                        @error('builderRechargeNote') <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                    <button type="button" wire:click="closeBuilderRechargeModal"
                        class="px-3.5 py-1.5 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:bg-surface transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitBuilderRechargeRequest"
                        class="px-4 py-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-xs font-semibold transition shadow-2xs cursor-pointer inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Submit Request</span>
                    </button>
                </div>
            </div>
        </div>
        @endif

        <!-- 9. Chart.js Initialization for Builder Dashboard -->
        <script>
            function renderBuilderCharts() {
                const bData = @json($builderData['charts'] ?? []);
                if (!bData || Object.keys(bData).length === 0) return;

                // 1. Inbound Trend Chart
                if (document.getElementById('builderTrendChart') && bData.trend) {
                    window.initSafeChart('builderTrendChart', {
                        type: 'line',
                        data: {
                            labels: bData.trend.labels || [],
                            datasets: [{
                                label: 'Inbound Leads',
                                data: bData.trend.received || [],
                                borderColor: '#2563EB',
                                backgroundColor: 'rgba(37, 99, 235, 0.08)',
                                borderWidth: 2.5,
                                fill: true,
                                tension: 0.3,
                                pointRadius: 3,
                                pointBackgroundColor: '#2563EB',
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                x: { grid: { color: '#F1F5F9' }, ticks: { font: { family: 'Inter', size: 10 }, color: '#64748B' } },
                                y: { grid: { color: '#F1F5F9' }, ticks: { font: { family: 'Inter', size: 10 }, color: '#64748B', precision: 0 }, beginAtZero: true }
                            }
                        }
                    });
                }

                // 2. Received vs Assigned Chart
                if (document.getElementById('builderReceivedVsAssignedChart') && bData.trend) {
                    window.initSafeChart('builderReceivedVsAssignedChart', {
                        type: 'bar',
                        data: {
                            labels: bData.trend.labels || [],
                            datasets: [
                                {
                                    label: 'Received',
                                    data: bData.trend.received || [],
                                    backgroundColor: '#2563EB',
                                    borderRadius: 4,
                                    barPercentage: 0.6,
                                },
                                {
                                    label: 'Assigned',
                                    data: bData.trend.assigned || [],
                                    backgroundColor: '#10B981',
                                    borderRadius: 4,
                                    barPercentage: 0.6,
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
                                    labels: { font: { family: 'Inter', size: 10 }, color: '#475569', boxWidth: 10 }
                                }
                            },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 10 }, color: '#64748B' } },
                                y: { grid: { color: '#F1F5F9' }, ticks: { font: { family: 'Inter', size: 10 }, color: '#64748B', precision: 0 }, beginAtZero: true }
                            }
                        }
                    });
                }

                // 3. Status Distribution Doughnut Chart
                if (document.getElementById('builderStatusChart') && bData.status_distribution) {
                    const keys = Object.keys(bData.status_distribution);
                    const values = Object.values(bData.status_distribution);
                    window.initSafeChart('builderStatusChart', {
                        type: 'doughnut',
                        data: {
                            labels: keys,
                            datasets: [{
                                data: values,
                                backgroundColor: ['#3B82F6', '#6366F1', '#F59E0B', '#10B981', '#94A3B8'],
                                borderWidth: 2,
                                borderColor: '#FFFFFF',
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '68%',
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'right',
                                    labels: { font: { family: 'Inter', size: 10 }, color: '#334155', boxWidth: 10, padding: 8 }
                                }
                            }
                        }
                    });
                }

                // 4. Credits Consumed Trend Chart
                if (document.getElementById('builderCreditsChart') && bData.trend) {
                    window.initSafeChart('builderCreditsChart', {
                        type: 'bar',
                        data: {
                            labels: bData.trend.labels || [],
                            datasets: [{
                                label: 'Credits Consumed (Cr)',
                                data: bData.trend.credits || [],
                                backgroundColor: '#F59E0B',
                                borderRadius: 4,
                                barPercentage: 0.5,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 10 }, color: '#64748B' } },
                                y: { grid: { color: '#F1F5F9' }, ticks: { font: { family: 'Inter', size: 10 }, color: '#64748B', precision: 0 }, beginAtZero: true }
                            }
                        }
                    });
                }

                // 5. Team Member Performance Ranking
                if (document.getElementById('builderTeamChart') && bData.team_performance) {
                    const names = bData.team_performance.map(t => t.name);
                    const activeCounts = bData.team_performance.map(t => t.active);
                    const convCounts = bData.team_performance.map(t => t.converted);
                    window.initSafeChart('builderTeamChart', {
                        type: 'bar',
                        data: {
                            labels: names.length > 0 ? names : ['No active members'],
                            datasets: [
                                {
                                    label: 'Active Leads',
                                    data: activeCounts.length > 0 ? activeCounts : [0],
                                    backgroundColor: '#2563EB',
                                    borderRadius: 3,
                                    barPercentage: 0.6,
                                },
                                {
                                    label: 'Won Deals',
                                    data: convCounts.length > 0 ? convCounts : [0],
                                    backgroundColor: '#10B981',
                                    borderRadius: 3,
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
                                    labels: { font: { family: 'Inter', size: 10 }, color: '#475569', boxWidth: 10 }
                                }
                            },
                            scales: {
                                x: { grid: { color: '#F1F5F9' }, ticks: { font: { family: 'Inter', size: 10 }, color: '#64748B', precision: 0 }, beginAtZero: true },
                                y: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 10 }, color: '#334155' } }
                            }
                        }
                    });
                }
            }

            document.addEventListener('livewire:navigated', renderBuilderCharts);
            document.addEventListener('DOMContentLoaded', renderBuilderCharts);
            document.addEventListener('livewire:initialized', renderBuilderCharts);
            document.addEventListener('livewire:updated', renderBuilderCharts);
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
