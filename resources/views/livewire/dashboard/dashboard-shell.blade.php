<div wire:poll.30s class="space-y-6">
    @if($role === 'admin')
        <!-- ================================================================= -->
        <!-- SUPER ADMIN COMMAND CENTRE                                       -->
        <!-- ================================================================= -->

        <!-- 1. Header & Master Telemetry Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b border-border">
            <div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h1 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight">Super Admin Command Centre</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border bg-rose-50 text-rose-700 border-rose-200">
                        Lead Panther Master Telemetry
                    </span>
                    <span class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 bg-surface border border-border rounded-full text-[11px] font-semibold text-muted">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Live 30s</span>
                    </span>
                </div>
                <p class="text-xs text-muted mt-1">Complete Lead Management Lifecycle, Real-time Distribution Telemetry & Credit Protection Engine</p>
            </div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <button type="button" wire:click="resetFilters"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-surface hover:bg-canvas border border-border rounded-lg text-xs font-semibold text-ink transition shadow-2xs cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Reset Filters</span>
                </button>
                <button type="button" wire:click="exportDashboardReport"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-ink hover:bg-neutral-800 text-white rounded-lg text-xs font-semibold transition shadow-2xs cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span>Export to Excel</span>
                </button>
            </div>
        </div>

        <!-- 2. Global Dashboard Filters Bar -->
        <div class="bg-surface border border-border rounded-xl p-4 shadow-2xs space-y-3">
            <div class="flex items-center justify-between gap-2 border-b border-border pb-2.5">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span class="text-xs font-bold text-ink uppercase tracking-wider">Global Command Filters</span>
                </div>
                <div class="text-[11px] text-muted">
                    Dynamic updates applied instantly across all cards, funnel, charts & tables.
                </div>
            </div>

            <!-- Filters Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-5 gap-3">
                <!-- Date Range Filter -->
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-muted uppercase tracking-wider">Date Period</label>
                    <x-ui.date-range-picker 
                        wire:model.live="dateRange" 
                        wire:custom-from.live="customFrom" 
                        wire:custom-to.live="customTo" 
                        class="w-full"
                    />
                </div>

                <!-- Organization Filter -->
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-muted uppercase tracking-wider">Organization</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterOrganizationId" 
                        model="App\Models\Organization" 
                        placeholder="All Organizations" 
                        :key="'dash-filter-org-'.($filterOrganizationId ?? 'none')" 
                    />
                </div>

                <!-- Project Filter -->
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-muted uppercase tracking-wider">Project</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterProjectId" 
                        model="App\Models\Project" 
                        placeholder="All Projects" 
                        :key="'dash-filter-project-'.($filterProjectId ?? 'none')" 
                    />
                </div>

                <!-- Sales Team Filter -->
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-muted uppercase tracking-wider">Team</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterTeamId" 
                        model="App\Models\SalesTeam" 
                        placeholder="All Teams" 
                        :key="'dash-filter-team-'.($filterTeamId ?? 'none')" 
                    />
                </div>

                <!-- Lead Source Filter -->
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-muted uppercase tracking-wider">Lead Source</label>
                    <select wire:model.live="filterLeadSourceId"
                            class="w-full text-xs rounded-lg border border-border bg-surface text-ink px-2.5 py-1.5 focus:border-ink focus:ring-0">
                        <option value="">All Sources (Meta, Google, Portals...)</option>
                        @foreach($sourceOptions as $srcId => $srcName)
                            <option value="{{ $srcId }}">{{ $srcName }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Assigned User Filter -->
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-muted uppercase tracking-wider">Assigned Agent</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterAssignedUserId" 
                        model="App\Models\User" 
                        placeholder="All Agents" 
                        :key="'dash-filter-user-'.($filterAssignedUserId ?? 'none')" 
                    />
                </div>

                <!-- Lead Status Filter -->
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-muted uppercase tracking-wider">Lead Status</label>
                    <select wire:model.live="filterStatus"
                            class="w-full text-xs rounded-lg border border-border bg-surface text-ink px-2.5 py-1.5 focus:border-ink focus:ring-0">
                        <option value="">All Lead Statuses</option>
                        <option value="new">New (Uncontacted)</option>
                        <option value="assigned">Assigned</option>
                        <option value="contacted">Contacted</option>
                        <option value="in_progress">In Progress</option>
                        <option value="qualified">Qualified</option>
                        <option value="site_visit">Site Visit</option>
                        <option value="negotiation">Negotiation</option>
                        <option value="booking">Booking</option>
                        <option value="closed_won">Closed Won</option>
                        <option value="lost">Lost / Disqualified</option>
                        <option value="duplicate">Duplicate</option>
                        <option value="unassigned">Unassigned</option>
                        <option value="sla_breached">SLA Breached (>30m)</option>
                    </select>
                </div>

                <!-- Campaign Filter -->
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-muted uppercase tracking-wider">Campaign</label>
                    <livewire:shared.searchable-select 
                        wire:model.live="filterCampaignId" 
                        model="App\Models\Campaign" 
                        placeholder="All Campaigns" 
                        :key="'dash-filter-campaign-'.($filterCampaignId ?? 'none')" 
                    />
                </div>

                <!-- Credit Transaction Type -->
                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-muted uppercase tracking-wider">Credit Tx Type</label>
                    <select wire:model.live="filterTransactionType"
                            class="w-full text-xs rounded-lg border border-border bg-surface text-ink px-2.5 py-1.5 focus:border-ink focus:ring-0">
                        <option value="">All Transaction Types</option>
                        <option value="reserve">Reserve (Inbound Lead)</option>
                        <option value="deduct">Deduct (Consumed)</option>
                        <option value="refund">Refund (Replacement Credit)</option>
                        <option value="recharge">Recharge (Wallet Topup)</option>
                    </select>
                </div>

                <!-- Quick Reset Action in Grid -->
                <div class="space-y-1 flex flex-col justify-end">
                    <label class="text-[10px] font-bold text-muted uppercase tracking-wider">&nbsp;</label>
                    <button type="button" wire:click="resetFilters"
                            class="w-full text-xs font-semibold py-1.5 px-3 rounded-lg border border-dashed border-border hover:bg-canvas text-muted hover:text-ink transition flex items-center justify-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        <span>Clear All Filters</span>
                    </button>
                </div>
            </div>

            <!-- Active Filter Badges Bar -->
            @php
                $hasActiveFilters = $filterOrganizationId || $filterProjectId || $filterTeamId || $filterLeadSourceId || $filterAssignedUserId || $filterStatus || $filterCampaignId || $filterTransactionType || $dateRange !== '30_days';
            @endphp
            @if($hasActiveFilters)
                <div class="flex items-center gap-2 flex-wrap pt-2 border-t border-border text-xs">
                    <span class="text-[11px] font-semibold text-muted">Active Filters:</span>
                    @if($dateRange !== '30_days')
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                            Period: {{ ucfirst(str_replace('_', ' ', $dateRange)) }}
                            <button type="button" wire:click="$set('dateRange', '30_days')" class="text-muted hover:text-ink">&times;</button>
                        </span>
                    @endif
                    @if($filterOrganizationId)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                            Org ID: {{ $filterOrganizationId }}
                            <button type="button" wire:click="$set('filterOrganizationId', null)" class="text-muted hover:text-ink">&times;</button>
                        </span>
                    @endif
                    @if($filterProjectId)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                            Project ID: {{ $filterProjectId }}
                            <button type="button" wire:click="$set('filterProjectId', null)" class="text-muted hover:text-ink">&times;</button>
                        </span>
                    @endif
                    @if($filterTeamId)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                            Team ID: {{ $filterTeamId }}
                            <button type="button" wire:click="$set('filterTeamId', null)" class="text-muted hover:text-ink">&times;</button>
                        </span>
                    @endif
                    @if($filterLeadSourceId)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                            Source: {{ $sourceOptions[$filterLeadSourceId] ?? $filterLeadSourceId }}
                            <button type="button" wire:click="$set('filterLeadSourceId', null)" class="text-muted hover:text-ink">&times;</button>
                        </span>
                    @endif
                    @if($filterStatus)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                            Status: {{ ucfirst(str_replace('_', ' ', $filterStatus)) }}
                            <button type="button" wire:click="$set('filterStatus', null)" class="text-muted hover:text-ink">&times;</button>
                        </span>
                    @endif
                    @if($filterTransactionType)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-canvas border border-border text-[11px] font-medium text-ink">
                            Tx: {{ ucfirst($filterTransactionType) }}
                            <button type="button" wire:click="$set('filterTransactionType', null)" class="text-muted hover:text-ink">&times;</button>
                        </span>
                    @endif
                </div>
            @endif
        </div>

        <!-- 3. Main KPI Cards (17 Metrics with Previous Period Deltas) -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold text-muted uppercase tracking-wider">Executive Performance KPIs & Pipeline Telemetry (17 Metrics)</h2>
                <span class="text-[11px] text-muted">Click any KPI card to drill down</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-6 gap-3">
                @foreach($adminKpis as $kpi)
                    <a href="{{ $kpi['route'] }}" wire:navigate
                       class="group bg-surface hover:bg-canvas border border-border hover:border-ink/30 rounded-xl p-3.5 transition shadow-2xs flex flex-col justify-between cursor-pointer relative overflow-hidden">
                        <div class="flex items-start justify-between gap-1">
                            <span class="text-[11px] font-medium text-muted line-clamp-1 group-hover:text-ink transition" title="{{ $kpi['title'] }}">
                                {{ $kpi['title'] }}
                            </span>
                            <div class="p-1 rounded-md bg-canvas border border-border shrink-0 text-muted group-hover:text-ink group-hover:border-ink/20 transition">
                                @if($kpi['icon'] === 'users')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                @elseif($kpi['icon'] === 'calendar')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                @elseif($kpi['icon'] === 'sparkles')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                @elseif($kpi['icon'] === 'copy')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                @elseif($kpi['icon'] === 'badge-check' || $kpi['icon'] === 'shield-check')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @elseif($kpi['icon'] === 'user-check')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                @elseif($kpi['icon'] === 'user-x')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a7 7 0 00-7 7h14a7 7 0 00-7-7zm8 2l4 4m0-4l-4 4"/></svg>
                                @elseif($kpi['icon'] === 'clock')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @elseif($kpi['icon'] === 'trending-up')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                @elseif($kpi['icon'] === 'trophy')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                @elseif($kpi['icon'] === 'x-circle')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @elseif($kpi['icon'] === 'refresh-cw')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                @elseif($kpi['icon'] === 'alert-triangle')
                                    <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                @elseif($kpi['icon'] === 'wallet')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                @elseif($kpi['icon'] === 'arrow-down-circle')
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13l-3 3m0 0l-3-3m3 3V8m0 13a9 9 0 110-18 9 9 0 010 18z"/></svg>
                                @elseif($kpi['icon'] === 'dollar-sign')
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @else
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                @endif
                            </div>
                        </div>

                        <div class="mt-2.5">
                            <div class="text-xl sm:text-2xl font-bold text-ink tracking-tight">
                                {{ $kpi['value'] }}
                            </div>
                            <div class="mt-1 flex items-center justify-between gap-1 text-[10px]">
                                @if($kpi['isPositive'] === true)
                                    <span class="inline-flex items-center text-emerald-600 font-bold">
                                        <svg class="w-2.5 h-2.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                        {{ $kpi['delta'] }}
                                    </span>
                                @elseif($kpi['isPositive'] === false)
                                    <span class="inline-flex items-center {{ $kpi['id'] === 'sla_breached' ? 'text-rose-600' : 'text-neutral-500' }} font-bold">
                                        <svg class="w-2.5 h-2.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                        {{ $kpi['delta'] }}
                                    </span>
                                @else
                                    <span class="text-muted font-medium">0.0%</span>
                                @endif
                                <span class="text-muted truncate" title="{{ $kpi['period'] }}">{{ $kpi['period'] }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- 4. Complete Lead Journey Funnel (13 Stages) -->
        <div class="bg-surface border border-border rounded-xl p-4 shadow-2xs space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border pb-2.5">
                <div>
                    <h2 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        <span>Complete Lead Journey Funnel (13 Lifecycle Stages)</span>
                    </h2>
                    <p class="text-[11px] text-muted mt-0.5">End-to-end conversion efficiency, drop-off analysis and average duration at every lifecycle milestone</p>
                </div>
                <div class="flex items-center gap-3 text-[11px] font-semibold text-muted">
                    <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Ingestion</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Engagement</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Conversion</span>
                </div>
            </div>

            <!-- Horizontal Responsive Funnel Stream -->
            <div class="overflow-x-auto no-scrollbar pb-2 pt-1">
                <div class="flex items-stretch gap-2 min-w-max">
                    @foreach($adminFunnel as $stg)
                        <div class="w-36 bg-canvas border border-border hover:border-ink/30 rounded-lg p-2.5 transition flex flex-col justify-between shrink-0 relative group">
                            <!-- Stage Header -->
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-[10px] font-bold text-muted uppercase tracking-wider">#{{ $stg['index'] }}</span>
                                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-surface border border-border text-muted">
                                    {{ $stg['avg_time'] }}
                                </span>
                            </div>

                            <!-- Stage Title -->
                            <div class="text-xs font-bold text-ink mt-1.5 line-clamp-1" title="{{ $stg['name'] }}">
                                {{ $stg['name'] }}
                            </div>

                            <!-- Lead Count & Conversion -->
                            <div class="mt-2 space-y-1">
                                <div class="text-lg font-extrabold text-ink tracking-tight">
                                    {{ number_format($stg['count']) }}
                                </div>
                                
                                <div class="flex items-center justify-between text-[10px] text-muted">
                                    <span>Conv:</span>
                                    <span class="font-bold text-emerald-600">{{ $stg['conversion_pct'] }}%</span>
                                </div>

                                @if($stg['drop_off'] > 0)
                                    <div class="flex items-center justify-between text-[10px] text-rose-600 font-medium">
                                        <span>Drop:</span>
                                        <span>-{{ number_format($stg['drop_off']) }} ({{ $stg['drop_off_pct'] }}%)</span>
                                    </div>
                                @else
                                    <div class="text-[10px] text-muted text-right">0 drop-off</div>
                                @endif
                            </div>

                            <!-- Visual Step Progress Bar -->
                            <div class="w-full bg-surface rounded-full h-1 mt-2 overflow-hidden border border-border">
                                <div class="h-1 rounded-full {{ $stg['index'] <= 5 ? 'bg-blue-600' : ($stg['index'] <= 9 ? 'bg-amber-500' : 'bg-emerald-600') }}"
                                     style="width: {{ max(5, $stg['conversion_pct']) }}%;"></div>
                            </div>
                        </div>

                        @if(!$loop->last)
                            <div class="flex items-center justify-center text-muted shrink-0 px-0.5">
                                <svg class="w-3.5 h-3.5 text-border" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <!-- 5. Lead Analytics & Interactive Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Lead Generation Trend (2 Cols) -->
            <div class="lg:col-span-2 bg-surface border border-border rounded-xl p-4 shadow-2xs space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border pb-2.5">
                    <div>
                        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Lead Generation Velocity & Conversion Trend</h2>
                        <p class="text-[11px] text-muted mt-0.5">Comparing Ingested Volume vs Closed Deals across time</p>
                    </div>
                    <div class="flex items-center gap-1 bg-canvas p-0.5 rounded-lg border border-border">
                        <button type="button" wire:click="setTrendInterval('daily')"
                                class="px-2.5 py-1 text-[11px] font-bold rounded-md transition cursor-pointer {{ $trendInterval === 'daily' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                            Daily
                        </button>
                        <button type="button" wire:click="setTrendInterval('weekly')"
                                class="px-2.5 py-1 text-[11px] font-bold rounded-md transition cursor-pointer {{ $trendInterval === 'weekly' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                            Weekly
                        </button>
                        <button type="button" wire:click="setTrendInterval('monthly')"
                                class="px-2.5 py-1 text-[11px] font-bold rounded-md transition cursor-pointer {{ $trendInterval === 'monthly' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                            Monthly
                        </button>
                    </div>
                </div>

                <div class="relative h-64 w-full">
                    <canvas id="adminLeadTrendChart"></canvas>
                </div>
            </div>

            <!-- Lead Status Distribution (1 Col) -->
            <div class="bg-surface border border-border rounded-xl p-4 shadow-2xs space-y-3 flex flex-col justify-between">
                <div class="border-b border-border pb-2.5">
                    <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Lead Status & Stage Distribution</h2>
                    <p class="text-[11px] text-muted mt-0.5">Active distribution across pipeline stages</p>
                </div>

                <div class="relative h-52 w-full flex items-center justify-center">
                    <canvas id="adminLeadStatusChart"></canvas>
                </div>

                <div class="grid grid-cols-2 gap-2 pt-2 border-t border-border text-[11px]">
                    <div class="flex items-center justify-between text-muted">
                        <span>New / Uncontacted:</span>
                        <span class="font-bold text-ink">{{ number_format($adminKpis[2]['raw_value'] ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-muted">
                        <span>Qualified:</span>
                        <span class="font-bold text-emerald-600">{{ number_format($adminKpis[4]['raw_value'] ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-muted">
                        <span>Assigned:</span>
                        <span class="font-bold text-ink">{{ number_format($adminKpis[5]['raw_value'] ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-muted">
                        <span>Closed Won:</span>
                        <span class="font-bold text-emerald-600">{{ number_format($adminKpis[9]['raw_value'] ?? 0) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Source Breakdown & Sales Executive Performance -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Lead Source Performance Chart -->
            <div class="bg-surface border border-border rounded-xl p-4 shadow-2xs space-y-3">
                <div class="border-b border-border pb-2.5 flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Lead Source Performance</h2>
                        <p class="text-[11px] text-muted mt-0.5">Meta, Google, Real Estate Portals & Organic Channels</p>
                    </div>
                    <span class="text-xs font-semibold text-muted">Total Sources: {{ count($adminLeadAnalytics['sources']['labels'] ?? []) }}</span>
                </div>
                <div class="relative h-56 w-full">
                    <canvas id="adminLeadSourceChart"></canvas>
                </div>
            </div>

            <!-- Top Sales Executives & Team Performance Table -->
            <div class="bg-surface border border-border rounded-xl p-4 shadow-2xs space-y-3">
                <div class="border-b border-border pb-2.5 flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Top Performing Sales Agents</h2>
                        <p class="text-[11px] text-muted mt-0.5">Conversion rate & response performance</p>
                    </div>
                    <a href="{{ route('admin.users') }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">Manage All &rarr;</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-border text-[10px] font-bold text-muted uppercase tracking-wider">
                                <th class="py-2">Agent Name</th>
                                <th class="py-2 text-right">Assigned</th>
                                <th class="py-2 text-right">Contacted</th>
                                <th class="py-2 text-right">Won</th>
                                <th class="py-2 text-right">Conversion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($adminLeadAnalytics['agents'] as $agent)
                                <tr class="hover:bg-canvas/50 transition">
                                    <td class="py-2.5 font-semibold text-ink flex items-center gap-2">
                                        <div class="w-5 h-5 rounded-full bg-canvas border border-border flex items-center justify-center font-bold text-[9px] text-muted">
                                            {{ strtoupper(substr($agent['name'], 0, 1)) }}
                                        </div>
                                        <span>{{ $agent['name'] }}</span>
                                    </td>
                                    <td class="py-2.5 text-right font-medium text-ink">{{ number_format($agent['assigned']) }}</td>
                                    <td class="py-2.5 text-right font-medium text-muted">{{ number_format($agent['contacted']) }}</td>
                                    <td class="py-2.5 text-right font-bold text-emerald-600">{{ number_format($agent['won']) }}</td>
                                    <td class="py-2.5 text-right font-bold text-ink">
                                        <span class="inline-flex px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[10px] font-bold">
                                            {{ $agent['conversion_rate'] }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-xs text-muted">
                                        No sales agents with active assignments found for selected period.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 7. Organization & Project Performance Data Tables -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Organization Performance Table -->
            <div class="bg-surface border border-border rounded-xl p-4 shadow-2xs space-y-3">
                <div class="flex items-center justify-between border-b border-border pb-2.5">
                    <div>
                        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Organization Performance Breakdown</h2>
                        <p class="text-[11px] text-muted mt-0.5">Leads, credit usage, wallet balance & SLA breaches</p>
                    </div>
                    <a href="{{ route('admin.organizations') }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">All Clients &rarr;</a>
                </div>

                <div class="overflow-x-auto max-h-80 overflow-y-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 bg-surface">
                            <tr class="border-b border-border text-[10px] font-bold text-muted uppercase tracking-wider">
                                <th class="py-2">Organization</th>
                                <th class="py-2 text-right">Leads</th>
                                <th class="py-2 text-right">Credits Consumed</th>
                                <th class="py-2 text-right">Wallet Balance</th>
                                <th class="py-2 text-right">SLA Breach</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($adminOrgPerformance as $org)
                                <tr class="hover:bg-canvas/50 transition">
                                    <td class="py-2.5">
                                        <a href="{{ route('admin.organizations') }}" wire:navigate class="font-semibold text-ink hover:text-blue-600 transition block">
                                            {{ $org['name'] }}
                                        </a>
                                        <span class="text-[10px] text-muted">{{ $org['active_users'] }} active users</span>
                                    </td>
                                    <td class="py-2.5 text-right font-medium text-ink">{{ number_format($org['total_leads']) }}</td>
                                    <td class="py-2.5 text-right font-medium text-muted">{{ number_format($org['credits_consumed']) }} Cr</td>
                                    <td class="py-2.5 text-right font-bold {{ $org['wallet_balance'] < 100 ? 'text-rose-600' : 'text-ink' }}">
                                        {{ number_format($org['wallet_balance']) }} Cr
                                        @if($org['wallet_balance'] < 100)
                                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-rose-500 ml-0.5" title="Low Balance"></span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 text-right">
                                        @if($org['sla_breaches'] > 0)
                                            <span class="inline-flex px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 text-[10px] font-bold">
                                                {{ $org['sla_breaches'] }}
                                            </span>
                                        @else
                                            <span class="text-muted text-[11px]">0</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-xs text-muted">
                                        No organization activity recorded.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Project Performance Table -->
            <div class="bg-surface border border-border rounded-xl p-4 shadow-2xs space-y-3">
                <div class="flex items-center justify-between border-b border-border pb-2.5">
                    <div>
                        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Project Performance Telemetry</h2>
                        <p class="text-[11px] text-muted mt-0.5">Project lead volume, client and credit charge</p>
                    </div>
                    <a href="{{ route('leads.index') }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">View Pipeline &rarr;</a>
                </div>

                <div class="overflow-x-auto max-h-80 overflow-y-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 bg-surface">
                            <tr class="border-b border-border text-[10px] font-bold text-muted uppercase tracking-wider">
                                <th class="py-2">Project</th>
                                <th class="py-2">Client</th>
                                <th class="py-2 text-right">Leads</th>
                                <th class="py-2 text-right">Credits Charged</th>
                                <th class="py-2 text-right">Conv Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($adminProjectPerformance as $proj)
                                <tr class="hover:bg-canvas/50 transition">
                                    <td class="py-2.5">
                                        <a href="{{ route('leads.index') }}" wire:navigate class="font-semibold text-ink hover:text-blue-600 transition block">
                                            {{ $proj['name'] }}
                                        </a>
                                        <span class="text-[10px] text-muted">{{ $proj['location'] }}</span>
                                    </td>
                                    <td class="py-2.5 text-muted">{{ $proj['client_name'] }}</td>
                                    <td class="py-2.5 text-right font-medium text-ink">{{ number_format($proj['total_leads']) }}</td>
                                    <td class="py-2.5 text-right font-medium text-muted">{{ number_format($proj['total_credits_charged']) }} Cr</td>
                                    <td class="py-2.5 text-right font-bold text-emerald-600">{{ $proj['conversion_rate'] }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-xs text-muted">
                                        <div class="flex flex-col items-center justify-center space-y-1">
                                            <svg class="w-8 h-8 text-muted/50 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                            <span class="font-semibold text-ink">No Project Records Found</span>
                                            <span class="text-[11px] text-muted">Campaigns are mapped dynamically for incoming agency leads.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 8. Credit Engine, Distribution Telemetry & Multi-Channel Notifications -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Credit & Wallet Analytics -->
            <div class="bg-surface border border-border rounded-xl p-4 shadow-2xs space-y-3">
                <div class="flex items-center justify-between border-b border-border pb-2.5">
                    <div>
                        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Credit Protection & Billing</h2>
                        <p class="text-[11px] text-muted mt-0.5">Supply, consumption & recharge requests</p>
                    </div>
                    <a href="{{ route('admin.credits') }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">Ledger &rarr;</a>
                </div>

                <div class="space-y-2.5">
                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-canvas border border-border">
                        <span class="text-xs text-muted">Global Platform Supply</span>
                        <span class="text-xs font-extrabold text-ink">{{ number_format($adminCreditAnalytics['global_balance'] ?? 0) }} Credits</span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-canvas border border-border">
                        <span class="text-xs text-muted">Average Cost Per Lead</span>
                        <span class="text-xs font-extrabold text-ink">{{ $adminCreditAnalytics['cost_per_lead'] ?? 10 }} Credits</span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-canvas border border-border">
                        <span class="text-xs text-muted">Credits Consumed</span>
                        <span class="text-xs font-bold text-neutral-700">{{ number_format($adminCreditAnalytics['credits_consumed'] ?? 0) }} Cr</span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-canvas border border-border">
                        <span class="text-xs text-muted">Credits Refunded</span>
                        <span class="text-xs font-bold text-emerald-600">{{ number_format($adminCreditAnalytics['credits_refunded'] ?? 0) }} Cr</span>
                    </div>
                    
                    @if(($adminCreditAnalytics['pending_recharges'] ?? 0) > 0)
                        <div class="p-2.5 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-between">
                            <span class="text-xs font-bold text-amber-800">Pending Recharge Requests</span>
                            <span class="inline-flex px-2 py-0.5 rounded-full bg-amber-200 text-amber-900 text-xs font-extrabold">
                                {{ $adminCreditAnalytics['pending_recharges'] }}
                            </span>
                        </div>
                    @else
                        <div class="p-2 text-center text-[11px] text-muted border border-dashed border-border rounded-lg">
                            All wallet recharge requests cleared.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Distribution Engine Monitoring -->
            <div class="bg-surface border border-border rounded-xl p-4 shadow-2xs space-y-3">
                <div class="border-b border-border pb-2.5 flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Distribution Engine Health</h2>
                        <p class="text-[11px] text-muted mt-0.5">Algorithm efficiency & unassigned hold queue</p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        {{ $adminDistributionMonitoring['success_rate'] ?? 100 }}% Success
                    </span>
                </div>

                <div class="space-y-2 text-xs">
                    <div class="flex items-center justify-between py-1 border-b border-border">
                        <span class="text-muted">Round Robin Rules:</span>
                        <span class="font-bold text-ink">{{ number_format($adminDistributionMonitoring['round_robin'] ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-border">
                        <span class="text-muted">Manual Routing Pool:</span>
                        <span class="font-medium text-ink">{{ number_format($adminDistributionMonitoring['manual'] ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-border">
                        <span class="text-muted">Priority / Capacity Rules:</span>
                        <span class="font-medium text-ink">{{ number_format(($adminDistributionMonitoring['priority'] ?? 0) + ($adminDistributionMonitoring['capacity'] ?? 0)) }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-border">
                        <span class="text-muted">Unassigned Leads:</span>
                        <span class="font-bold {{ ($adminDistributionMonitoring['unassigned'] ?? 0) > 0 ? 'text-amber-600' : 'text-muted' }}">
                            {{ number_format($adminDistributionMonitoring['unassigned'] ?? 0) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span class="text-muted">Failed Distribution:</span>
                        <span class="font-bold text-muted">{{ number_format($adminDistributionMonitoring['failed'] ?? 0) }}</span>
                    </div>

                    <div class="pt-2">
                        <div class="text-[10px] font-semibold text-muted mb-1">Distribution Success Rate</div>
                        <div class="w-full bg-canvas rounded-full h-2 overflow-hidden border border-border">
                            <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ $adminDistributionMonitoring['success_rate'] ?? 100 }}%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multi-Channel Notifications Health -->
            <div class="bg-surface border border-border rounded-xl p-4 shadow-2xs space-y-3">
                <div class="border-b border-border pb-2.5 flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Multi-Channel Delivery</h2>
                        <p class="text-[11px] text-muted mt-0.5">WhatsApp, SMS, Email & CRM Webhooks</p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                        {{ $adminNotificationMonitoring['overall_rate'] ?? 100 }}% Delivery
                    </span>
                </div>

                <div class="space-y-2">
                    @foreach($adminNotificationMonitoring['channels'] as $chanKey => $chan)
                        <div class="p-2 rounded-lg bg-canvas border border-border space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-ink">{{ $chan['name'] }}</span>
                                <span class="font-bold text-emerald-600 text-[11px]">{{ $chan['success_rate'] }}%</span>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-muted">
                                <span>Sent: {{ number_format($chan['sent']) }} &bull; Del: {{ number_format($chan['delivered']) }}</span>
                                <span class="{{ $chan['failed'] > 0 ? 'text-rose-600 font-bold' : '' }}">Failed: {{ $chan['failed'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- 9. Recent Activities & Alerts Stream Container -->
        <div class="bg-surface border border-border rounded-xl p-4 shadow-2xs space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border pb-2.5">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Live System Telemetry & Alert Stream</h2>
                </div>

                <!-- Tabs -->
                <div class="flex items-center gap-1 bg-canvas p-0.5 rounded-lg border border-border overflow-x-auto no-scrollbar">
                    <button type="button" wire:click="setActiveActivityTab('leads')"
                            class="px-2.5 py-1 text-[11px] font-bold rounded-md transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'leads' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                        Recent Leads
                    </button>
                    <button type="button" wire:click="setActiveActivityTab('sla')"
                            class="px-2.5 py-1 text-[11px] font-bold rounded-md transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'sla' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                        SLA Breaches
                    </button>
                    <button type="button" wire:click="setActiveActivityTab('transactions')"
                            class="px-2.5 py-1 text-[11px] font-bold rounded-md transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'transactions' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                        Credit Ledger
                    </button>
                    <button type="button" wire:click="setActiveActivityTab('replacements')"
                            class="px-2.5 py-1 text-[11px] font-bold rounded-md transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'replacements' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                        Replacements
                    </button>
                    <button type="button" wire:click="setActiveActivityTab('audit')"
                            class="px-2.5 py-1 text-[11px] font-bold rounded-md transition cursor-pointer whitespace-nowrap {{ $activeActivityTab === 'audit' ? 'bg-ink text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                        Audit Logs
                    </button>
                </div>
            </div>

            <!-- Tab 1: Recent Leads -->
            @if($activeActivityTab === 'leads')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['recent_leads'] as $lead)
                        <div class="py-2.5 flex items-center justify-between text-xs hover:bg-canvas/40 px-1 rounded transition">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-full bg-blue-50 text-blue-700 border border-blue-200 flex items-center justify-center font-bold text-[10px] shrink-0">
                                    LP
                                </div>
                                <div>
                                    <div class="font-semibold text-ink flex items-center gap-2">
                                        <a href="{{ route('leads.index') }}" wire:navigate class="hover:text-blue-600 transition">
                                            #{{ $lead->lead_code }} &bull; {{ $lead->name }}
                                        </a>
                                        <span class="inline-flex px-1.5 py-0.2 rounded text-[10px] font-medium bg-canvas border border-border text-muted">
                                            {{ ucfirst($lead->leadSource?->name ?: 'Direct') }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-muted">
                                        Org: {{ $lead->client?->organization?->name ?: 'Platform HQ' }} &bull; Phone: {{ substr($lead->mobile, 0, 4) . '***' . substr($lead->mobile, -3) }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-[11px] font-medium text-ink">{{ $lead->created_at ? $lead->created_at->diffForHumans() : 'Recently' }}</div>
                                <span class="inline-flex px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $lead->assigned_to ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                    {{ $lead->assigned_to ? 'Assigned' : 'Unassigned' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-muted">No recent leads found.</div>
                    @endforelse
                </div>

            <!-- Tab 2: SLA Breached Leads -->
            @elseif($activeActivityTab === 'sla')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['sla_breached'] as $lead)
                        <div class="py-2.5 flex items-center justify-between text-xs hover:bg-canvas/40 px-1 rounded transition">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-full bg-rose-50 text-rose-700 border border-rose-200 flex items-center justify-center font-bold text-[10px] shrink-0">
                                    !
                                </div>
                                <div>
                                    <div class="font-semibold text-ink flex items-center gap-2">
                                        <a href="{{ route('leads.index') }}" wire:navigate class="hover:text-rose-600 transition">
                                            #{{ $lead->lead_code }} &bull; {{ $lead->name }}
                                        </a>
                                        <span class="inline-flex px-1.5 py-0.2 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            SLA Breached (>30m)
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-muted">
                                        Assigned To: {{ $lead->assignedTo?->name ?: 'Unassigned' }} &bull; Created: {{ $lead->created_at ? $lead->created_at->diffForHumans() : 'Earlier' }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <a href="{{ route('leads.index') }}" wire:navigate
                                   class="inline-flex items-center gap-1 px-2 py-1 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 text-[11px] font-bold transition">
                                    Respond Now &rarr;
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-muted">
                            <div class="flex flex-col items-center justify-center space-y-1">
                                <span class="font-semibold text-emerald-600">&check; Zero SLA Breaches</span>
                                <span class="text-[11px] text-muted">All leads are contacted within response timer guidelines.</span>
                            </div>
                        </div>
                    @endforelse
                </div>

            <!-- Tab 3: Recent Transactions -->
            @elseif($activeActivityTab === 'transactions')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['transactions'] as $tx)
                        <div class="py-2.5 flex items-center justify-between text-xs hover:bg-canvas/40 px-1 rounded transition">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-full {{ $tx->transaction_type === 'refund' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-canvas border-border text-muted' }} border flex items-center justify-center font-bold text-[10px] shrink-0">
                                    {{ strtoupper(substr($tx->transaction_type, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="font-semibold text-ink flex items-center gap-2">
                                        <span>Tx #{{ $tx->id }} &bull; {{ ucfirst($tx->transaction_type) }}</span>
                                        <span class="inline-flex px-1.5 py-0.2 rounded text-[10px] font-bold {{ $tx->transaction_type === 'refund' ? 'bg-emerald-50 text-emerald-700' : 'bg-canvas text-ink' }}">
                                            {{ $tx->credit_used }} Credits
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-muted">
                                        Client: {{ $tx->client?->name ?: 'Client' }} &bull; Balance After: {{ $tx->credit_after }} Cr
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

            <!-- Tab 4: Replacement Claims -->
            @elseif($activeActivityTab === 'replacements')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['replacements'] as $claim)
                        <div class="py-2.5 flex items-center justify-between text-xs hover:bg-canvas/40 px-1 rounded transition">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-full bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center font-bold text-[10px] shrink-0">
                                    R
                                </div>
                                <div>
                                    <div class="font-semibold text-ink flex items-center gap-2">
                                        <span>Claim #{{ $claim->id }} &bull; Lead #{{ $claim->lead?->lead_code ?: $claim->lead_id }}</span>
                                        <span class="inline-flex px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            {{ ucfirst($claim->status) }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-muted">
                                        Requested by: {{ $claim->requester?->name ?: 'Agent' }} &bull; Reason: {{ $claim->reason?->name ?: 'Invalid Contact' }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <a href="{{ route('replacements.queue') }}" wire:navigate
                                   class="inline-flex items-center gap-1 px-2 py-1 rounded bg-surface hover:bg-canvas border border-border text-ink text-[11px] font-bold transition">
                                    Review &rarr;
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-muted">
                            <div class="flex flex-col items-center justify-center space-y-1">
                                <span class="font-semibold text-ink">No Pending Replacement Claims</span>
                                <span class="text-[11px] text-muted">All lead replacement claims have been reviewed.</span>
                            </div>
                        </div>
                    @endforelse
                </div>

            <!-- Tab 5: Audit Logs -->
            @elseif($activeActivityTab === 'audit')
                <div class="divide-y divide-border">
                    @forelse($adminRecentActivities['audit_logs'] as $log)
                        <div class="py-2.5 flex items-center justify-between text-xs hover:bg-canvas/40 px-1 rounded transition">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-full bg-canvas border border-border flex items-center justify-center font-bold text-[10px] text-muted shrink-0">
                                    LOG
                                </div>
                                <div>
                                    <div class="font-semibold text-ink">
                                        {{ $log->user?->name ?: 'System' }} executed {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                    </div>
                                    <div class="text-[11px] text-muted">
                                        Target: {{ class_basename($log->subject_type ?? 'Entity') }} #{{ $log->subject_id }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right text-[11px] text-muted">
                                {{ $log->created_at ? $log->created_at->diffForHumans() : 'Recent' }}
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-muted">No audit trail records found.</div>
                    @endforelse
                </div>
            @endif
        </div>

        <!-- Safe Chart.js Initialization for Super Admin -->
        <script>
            function renderAdminDashboardCharts() {
                const analytics = @json($adminLeadAnalytics);

                // 1. Lead Velocity & Won Line Chart
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
                                    borderColor: '#0A0A0A',
                                    backgroundColor: 'rgba(10, 10, 10, 0.05)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.3,
                                    pointRadius: 3,
                                    pointBackgroundColor: '#0A0A0A',
                                },
                                {
                                    label: 'Closed Won / Converted',
                                    data: trend.won || [],
                                    borderColor: '#10B981',
                                    borderWidth: 2,
                                    borderDash: [3, 3],
                                    fill: false,
                                    tension: 0.3,
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
                                    labels: { font: { family: 'Inter', size: 11 }, color: '#6B7280', boxWidth: 12 }
                                }
                            },
                            scales: {
                                x: { grid: { color: '#F5F5F5' }, ticks: { font: { family: 'Inter', size: 10 }, color: '#6B7280' } },
                                y: { grid: { color: '#E5E7EB' }, ticks: { font: { family: 'Inter', size: 10 }, color: '#6B7280' }, beginAtZero: true }
                            }
                        }
                    });
                }

                // 2. Status Distribution Doughnut Chart
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
                                    labels: { font: { family: 'Inter', size: 10 }, color: '#6B7280', boxWidth: 10 }
                                }
                            },
                            cutout: '70%'
                        }
                    });
                }

                // 3. Lead Source Performance Bar Chart
                if (document.getElementById('adminLeadSourceChart')) {
                    const sources = analytics.sources || {};
                    window.initSafeChart('adminLeadSourceChart', {
                        type: 'bar',
                        data: {
                            labels: sources.labels || [],
                            datasets: [
                                {
                                    label: 'Leads Received',
                                    data: sources.counts || [],
                                    backgroundColor: '#2563EB',
                                    borderRadius: 4,
                                    barPercentage: 0.6,
                                },
                                {
                                    label: 'Deals Won',
                                    data: sources.won || [],
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
                                    labels: { font: { family: 'Inter', size: 11 }, color: '#6B7280', boxWidth: 12 }
                                }
                            },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 10 }, color: '#6B7280' } },
                                y: { grid: { color: '#E5E7EB' }, ticks: { font: { family: 'Inter', size: 10 }, color: '#6B7280' }, beginAtZero: true }
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
