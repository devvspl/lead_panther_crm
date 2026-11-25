<div class="space-y-6">

    @if(!$lead || !$lead->exists)
        <!-- Standalone Page Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b border-border">
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-2xl font-bold text-ink tracking-tight">Lead Follow-Up &amp; Reminders</h1>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        {{ $todayCount + $overdueCount }} Due Action
                    </span>
                </div>
                <p class="text-xs text-muted mt-0.5">Track, complete, reschedule, and manage multi-tier escalations for prospect follow-ups</p>
            </div>
            <div class="flex items-center gap-2">
                <!-- Excel Export Dropdown -->
                <x-ui.dropdown align="right" width="64">
                    <x-slot:trigger>
                        <button type="button" class="h-8 shrink-0 whitespace-nowrap border border-border bg-surface rounded-lg px-3 text-xs font-semibold text-ink hover:bg-canvas transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <span>Export to Excel</span>
                            <svg class="w-3 h-3 text-muted ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </x-slot:trigger>
                    <x-slot:content>
                        <div class="p-1 text-xs">
                            <button type="button" wire:click="exportFiltered" wire:loading.attr="disabled" class="w-full text-left px-3 py-2 rounded-md hover:bg-canvas text-ink flex items-center gap-2.5 transition cursor-pointer">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                </svg>
                                <div class="min-w-0">
                                    <span class="font-semibold block text-ink">Export Filtered Follow-Ups</span>
                                    <span class="text-[10px] text-muted block truncate">All records matching active filters</span>
                                </div>
                            </button>
                            <button type="button" wire:click="exportAll" wire:loading.attr="disabled" class="w-full text-left px-3 py-2 rounded-md hover:bg-canvas text-ink flex items-center gap-2.5 transition cursor-pointer">
                                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                </svg>
                                <div class="min-w-0">
                                    <span class="font-semibold block text-ink">Export All Follow-Ups</span>
                                    <span class="text-[10px] text-muted block truncate">All authorized records in system</span>
                                </div>
                            </button>
                            <button type="button" wire:click="exportCurrentView" wire:loading.attr="disabled" class="w-full text-left px-3 py-2 rounded-md hover:bg-canvas text-ink flex items-center gap-2.5 transition cursor-pointer">
                                <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <div class="min-w-0">
                                    <span class="font-semibold block text-ink">Export Current View</span>
                                    <span class="text-[10px] text-muted block truncate">Current page records only ({{ $followups->count() }})</span>
                                </div>
                            </button>
                        </div>
                    </x-slot:content>
                </x-ui.dropdown>

                <a href="{{ route('leads.index') }}" wire:navigate class="px-3 py-1.5 rounded-lg border border-border bg-surface text-xs font-semibold text-ink hover:bg-canvas transition flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
                    <span>Leads Pipeline</span>
                </a>
            </div>
        </div>

        <!-- Collapsible Filter & Search Container -->
        <div class="bg-surface rounded-xl border border-border shadow-xs overflow-hidden">
            <!-- Filter Bar Toolbar -->
            <div class="p-3.5 flex flex-col md:flex-row md:items-center justify-between gap-3 bg-canvas/40 border-b border-border/60">
                <!-- Quick Search Input -->
                <div class="relative flex-1 max-w-md">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search customer, mobile, code or note..."
                        class="w-full text-xs pl-8 pr-3 py-2 rounded-lg border border-border bg-surface text-ink placeholder:text-muted focus:ring-1 focus:ring-primary focus:border-primary">
                    <svg class="w-3.5 h-3.5 text-muted absolute left-2.5 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    @if($search)
                        <button type="button" wire:click="clearFilter('search')" class="absolute right-2.5 top-2.5 text-muted hover:text-ink text-xs cursor-pointer">&times;</button>
                    @endif
                </div>

                <!-- Right Filter Controls: Toggle, Active count, Reset, Export -->
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" wire:click="toggleFilters"
                        class="h-8 px-3 rounded-lg border {{ $filtersOpen || $this->activeFilterCount > 0 ? 'border-primary/50 bg-primary/5 text-primary' : 'border-border bg-surface text-ink hover:bg-canvas' }} text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        <span>Filters</span>
                        @if($this->activeFilterCount > 0)
                            <span class="inline-flex items-center justify-center px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-primary text-white">
                                {{ $this->activeFilterCount }}
                            </span>
                        @endif
                        <svg class="w-3 h-3 transition-transform duration-200 {{ $filtersOpen ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    @if($this->activeFilterCount > 0)
                        <button type="button" wire:click="resetFilters"
                            class="h-8 px-2.5 rounded-lg border border-border bg-surface text-xs font-semibold text-rose-600 hover:bg-rose-50 transition flex items-center gap-1 cursor-pointer"
                            title="Reset all filters">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>Reset</span>
                        </button>
                    @endif
                </div>
            </div>

            <!-- Active Filter Badges Strip (Shown when any filter is active) -->
            @if($this->activeFilterCount > 0)
                <div class="px-3.5 py-2 bg-canvas/70 border-b border-border/50 flex items-center gap-1.5 flex-wrap text-[11px]">
                    <span class="text-muted font-bold uppercase tracking-wider text-[10px] mr-1">Active:</span>

                    @if($search)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface border border-border text-ink">
                            <span class="text-muted">Search:</span> <strong class="font-medium truncate max-w-[120px]">{{ $search }}</strong>
                            <button type="button" wire:click="clearFilter('search')" class="text-muted hover:text-rose-600 cursor-pointer">&times;</button>
                        </span>
                    @endif

                    @if($organizationId && $isSuperAdmin)
                        @php $activeOrg = $organizations->firstWhere('id', $organizationId); @endphp
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface border border-border text-ink">
                            <span class="text-muted">Org:</span> <strong class="font-medium truncate max-w-[120px]">{{ $activeOrg?->name ?? $organizationId }}</strong>
                            <button type="button" wire:click="clearFilter('organizationId')" class="text-muted hover:text-rose-600 cursor-pointer">&times;</button>
                        </span>
                    @endif

                    @if($projectId)
                        @php $activeProj = $projects->firstWhere('id', $projectId); @endphp
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface border border-border text-ink">
                            <span class="text-muted">Project:</span> <strong class="font-medium truncate max-w-[120px]">{{ $activeProj?->name ?? $projectId }}</strong>
                            <button type="button" wire:click="clearFilter('projectId')" class="text-muted hover:text-rose-600 cursor-pointer">&times;</button>
                        </span>
                    @endif

                    @if($teamId)
                        @php $activeTeam = $teams->firstWhere('id', $teamId); @endphp
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface border border-border text-ink">
                            <span class="text-muted">Team:</span> <strong class="font-medium truncate max-w-[120px]">{{ $activeTeam?->name ?? $teamId }}</strong>
                            <button type="button" wire:click="clearFilter('teamId')" class="text-muted hover:text-rose-600 cursor-pointer">&times;</button>
                        </span>
                    @endif

                    @if($executiveId)
                        @php $activeExec = $executives->firstWhere('id', $executiveId); @endphp
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface border border-border text-ink">
                            <span class="text-muted">Executive:</span> <strong class="font-medium truncate max-w-[120px]">{{ $activeExec?->name ?? $executiveId }}</strong>
                            <button type="button" wire:click="clearFilter('executiveId')" class="text-muted hover:text-rose-600 cursor-pointer">&times;</button>
                        </span>
                    @endif

                    @if($typeFilter)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface border border-border text-ink">
                            <span class="text-muted">Action:</span> <strong class="font-medium capitalize">{{ $typeOptions[$typeFilter] ?? $typeFilter }}</strong>
                            <button type="button" wire:click="clearFilter('typeFilter')" class="text-muted hover:text-rose-600 cursor-pointer">&times;</button>
                        </span>
                    @endif

                    @if($statusFilter)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface border border-border text-ink">
                            <span class="text-muted">Status:</span> <strong class="font-medium capitalize">{{ $statusOptions[$statusFilter] ?? $statusFilter }}</strong>
                            <button type="button" wire:click="clearFilter('statusFilter')" class="text-muted hover:text-rose-600 cursor-pointer">&times;</button>
                        </span>
                    @endif

                    @if($stageFilter)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface border border-border text-ink">
                            <span class="text-muted">Stage:</span> <strong class="font-medium capitalize">{{ $stageOptions[$stageFilter] ?? $stageFilter }}</strong>
                            <button type="button" wire:click="clearFilter('stageFilter')" class="text-muted hover:text-rose-600 cursor-pointer">&times;</button>
                        </span>
                    @endif

                    @if($outcomeFilter)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface border border-border text-ink">
                            <span class="text-muted">Outcome:</span> <strong class="font-medium">{{ \App\Models\Followup::outcomeOptions()[$outcomeFilter] ?? $outcomeFilter }}</strong>
                            <button type="button" wire:click="clearFilter('outcomeFilter')" class="text-muted hover:text-rose-600 cursor-pointer">&times;</button>
                        </span>
                    @endif

                    @if($dateRange !== 'all' || $dateFrom || $dateTo)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-surface border border-border text-ink">
                            <span class="text-muted">Date:</span>
                            <strong class="font-medium">
                                @if($dateFrom && $dateTo)
                                    {{ \Carbon\Carbon::parse($dateFrom)->format('d M') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
                                @else
                                    {{ ucfirst($dateRange) }}
                                @endif
                            </strong>
                            <button type="button" wire:click="clearFilter('date')" class="text-muted hover:text-rose-600 cursor-pointer">&times;</button>
                        </span>
                    @endif

                    <button type="button" wire:click="resetFilters" class="text-primary hover:underline font-semibold ml-auto text-[11px] cursor-pointer">
                        Clear all
                    </button>
                </div>
            @endif

            <!-- Collapsible Filters Form Panel -->
            <div x-show="$wire.filtersOpen" x-cloak class="p-4 bg-surface border-t border-border/40 space-y-4" style="{{ $filtersOpen ? '' : 'display: none;' }}">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 text-xs">
                        <!-- Organization (Super Admin) -->
                        @if($isSuperAdmin && $organizations->count() > 0)
                            <div>
                                <label class="text-[11px] font-bold text-ink block mb-1">Organization / Client</label>
                                <x-ui.themed-select wire:model.live="organizationId" placeholder="All Organizations" :searchable="true" class="w-full">
                                    <option value="">All Organizations</option>
                                    @foreach($organizations as $org)
                                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                                    @endforeach
                                </x-ui.themed-select>
                            </div>
                        @endif

                        <!-- Project -->
                        <div>
                            <label class="text-[11px] font-bold text-ink block mb-1">Project</label>
                            <x-ui.themed-select wire:model.live="projectId" placeholder="All Projects" :searchable="true" class="w-full">
                                <option value="">All Projects</option>
                                @foreach($projects as $proj)
                                    <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                                @endforeach
                            </x-ui.themed-select>
                        </div>

                        <!-- Team / Team Leader -->
                        @if($teams->count() > 0)
                            <div>
                                <label class="text-[11px] font-bold text-ink block mb-1">Team / Team Leader</label>
                                <x-ui.themed-select wire:model.live="teamId" placeholder="All Teams" :searchable="true" class="w-full">
                                    <option value="">All Teams</option>
                                    @foreach($teams as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }}{{ $t->leader ? ' (' . $t->leader->name . ')' : '' }}</option>
                                    @endforeach
                                </x-ui.themed-select>
                            </div>
                        @endif

                        <!-- Assigned Executive -->
                        @if($executives->count() > 0)
                            <div>
                                <label class="text-[11px] font-bold text-ink block mb-1">Assigned Executive</label>
                                <x-ui.themed-select wire:model.live="executiveId" placeholder="All Executives" :searchable="true" class="w-full">
                                    <option value="">All Executives</option>
                                    @foreach($executives as $exec)
                                        <option value="{{ $exec->id }}">{{ $exec->name }}</option>
                                    @endforeach
                                </x-ui.themed-select>
                            </div>
                        @endif

                        <!-- Follow-up Action Type -->
                        <div>
                            <label class="text-[11px] font-bold text-ink block mb-1">Action Type</label>
                            <x-ui.themed-select wire:model.live="typeFilter" placeholder="All Action Types" class="w-full">
                                <option value="">All Action Types</option>
                                @foreach($typeOptions as $tVal => $tLbl)
                                    <option value="{{ $tVal }}">{{ $tLbl }}</option>
                                @endforeach
                            </x-ui.themed-select>
                        </div>

                        <!-- Follow-up Status -->
                        <div>
                            <label class="text-[11px] font-bold text-ink block mb-1">Follow-Up Status</label>
                            <x-ui.themed-select wire:model.live="statusFilter" placeholder="All Statuses" class="w-full">
                                <option value="">All Statuses</option>
                                @foreach($statusOptions as $sVal => $sLbl)
                                    <option value="{{ $sVal }}">{{ $sLbl }}</option>
                                @endforeach
                            </x-ui.themed-select>
                        </div>

                        <!-- Lead Stage -->
                        <div>
                            <label class="text-[11px] font-bold text-ink block mb-1">Lead Stage</label>
                            <x-ui.themed-select wire:model.live="stageFilter" placeholder="All Lead Stages" class="w-full">
                                <option value="">All Lead Stages</option>
                                @foreach($stageOptions as $stVal => $stLbl)
                                    <option value="{{ $stVal }}">{{ $stLbl }}</option>
                                @endforeach
                            </x-ui.themed-select>
                        </div>

                        <!-- Follow-up Date Range -->
                        <div>
                            <label class="text-[11px] font-bold text-ink block mb-1">Date Range</label>
                            <x-ui.date-range-picker
                                wire:model.live="dateRange"
                                wire:custom-from="dateFrom"
                                wire:custom-to="dateTo"
                                placeholder="All Time"
                                class="w-full"
                            />
                        </div>

                        <!-- Outcome Filter -->
                        <div>
                            <label class="text-[11px] font-bold text-ink block mb-1">Outcome</label>
                            <x-ui.themed-select wire:model.live="outcomeFilter" placeholder="All Outcomes" class="w-full">
                                <option value="">All Outcomes</option>
                                @foreach(\App\Models\Followup::outcomeOptions() as $oVal => $oLbl)
                                    <option value="{{ $oVal }}">{{ $oLbl }}</option>
                                @endforeach
                            </x-ui.themed-select>
                        </div>
                    </div>

                    <!-- Panel Footer Actions -->
                    <div class="pt-3 border-t border-border/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <span class="text-[11px] text-muted">Showing follow-up records matching combined criteria</span>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="resetFilters" class="px-3 py-1.5 rounded-lg border border-border bg-surface text-xs font-semibold text-ink hover:bg-canvas transition cursor-pointer">
                                Reset Filters
                            </button>
                            <button type="button" wire:click="applyFilters" class="px-4 py-1.5 rounded-lg bg-primary text-white text-xs font-semibold hover:bg-primary-dark transition cursor-pointer flex items-center gap-1.5 shadow-xs">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Apply Filters</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Tabs Navigation -->
        <div class="flex items-center gap-1 border-b border-border overflow-x-auto text-xs font-semibold">
            @foreach([
                'all' => 'All (' . $totalCount . ')',
                'today' => 'Due Today (' . $todayCount . ')',
                'upcoming' => 'Upcoming (' . $upcomingCount . ')',
                'overdue' => 'Overdue (' . $overdueCount . ')',
                'requests' => 'Requests (' . $requestsCount . ')',
                'escalated' => 'Escalated (' . $escalatedCount . ')',
                'completed' => 'Completed (' . $completedCount . ')',
            ] as $tabKey => $tabLabel)
                <button type="button" wire:click="setTab('{{ $tabKey }}')"
                    class="px-4 py-2 border-b-2 transition whitespace-nowrap cursor-pointer {{ $activeTab === $tabKey ? 'border-primary text-primary font-bold bg-surface' : 'border-transparent text-muted hover:text-ink' }}">
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>

        <!-- Follow-ups Table / Roster -->
        <div class="bg-surface rounded-xl border border-border shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-border bg-canvas/60 text-muted font-bold text-[11px] uppercase tracking-wider">
                            <th class="p-3">Customer &amp; Project</th>
                            <th class="p-3">Follow-up Action</th>
                            <th class="p-3">Due Date / Time</th>
                            <th class="p-3">Assigned Executive</th>
                            <th class="p-3">Status &amp; Escalation</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($followups as $fu)
                            @php
                                $leadRecord = $fu->lead;
                            @endphp
                            <tr class="hover:bg-canvas/40 transition" wire:key="followup-row-{{ $fu->id }}">
                                <!-- Customer & Project -->
                                <td class="p-3">
                                    <div class="font-bold text-ink">
                                        <a href="{{ route('leads.index') }}?search={{ $leadRecord?->lead_code ?: $fu->lead_id }}&notification_lead={{ $leadRecord?->id }}" wire:navigate class="hover:text-primary hover:underline">
                                            {{ $leadRecord?->name ?? 'Lead #' . $fu->lead_id }}
                                        </a>
                                    </div>
                                    <div class="text-[11px] text-muted flex items-center gap-1.5 mt-0.5">
                                        <span class="font-mono text-[10px]">{{ $leadRecord?->lead_code ?: '—' }}</span>
                                        <span>&bull;</span>
                                        <span>{{ $leadRecord?->project?->name ?: 'General' }}</span>
                                    </div>
                                    @if($leadRecord?->phone || $leadRecord?->mobile)
                                        @php $leadPhone = $leadRecord->phone ?: $leadRecord->mobile; @endphp
                                        <div class="flex items-center gap-2 mt-1">
                                            <a href="tel:{{ $leadPhone }}" class="text-[10px] text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200 font-mono hover:bg-emerald-100 flex items-center gap-1">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                                <span>{{ $leadPhone }}</span>
                                            </a>
                                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $leadPhone) }}" target="_blank" class="text-[10px] text-emerald-700 hover:text-emerald-800 font-semibold flex items-center gap-0.5">
                                                <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                                <span>WhatsApp</span>
                                            </a>
                                        </div>
                                    @endif
                                </td>

                                <!-- Follow-up Action -->
                                <td class="p-3">
                                    <div class="flex items-center gap-1.5">
                                        <span class="capitalize font-bold text-ink px-1.5 py-0.5 rounded bg-primary/10 text-primary text-[10px]">
                                            {{ $fu->type_label }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-ink font-medium mt-1">{{ $fu->note ?: 'Follow-up discussion' }}</p>
                                    @if($fu->status === 'completed' && $fu->outcome)
                                        <div class="mt-1 flex items-center gap-1">
                                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.2 rounded border border-emerald-200">
                                                Outcome: {{ $fu->outcome_label }}
                                            </span>
                                            @if($fu->outcome_notes)
                                                <span class="text-[10px] text-muted truncate max-w-xs" title="{{ $fu->outcome_notes }}">({{ $fu->outcome_notes }})</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if($fu->status === 'cancelled' && $fu->cancellation_reason)
                                        <p class="text-[10px] text-rose-600 mt-0.5 italic">Cancelled: {{ $fu->cancellation_reason }}</p>
                                    @endif
                                </td>

                                <!-- Due Date / Time -->
                                <td class="p-3">
                                    <span class="font-bold text-ink block font-mono">
                                        {{ \Carbon\Carbon::parse($fu->due_at)->format('d M Y, h:i A') }}
                                    </span>
                                    <span class="text-[10px] text-muted block mt-0.5">
                                        {{ \Carbon\Carbon::parse($fu->due_at)->diffForHumans() }}
                                    </span>
                                </td>

                                <!-- Assigned Executive -->
                                <td class="p-3">
                                    <div class="font-semibold text-ink">
                                        {{ $fu->assignedTo?->name ?: ($leadRecord?->assignedTo?->name ?: 'Unassigned') }}
                                    </div>
                                    @if($fu->assignedBy)
                                        <div class="text-[9px] text-muted">Assigned by {{ $fu->assignedBy->name }}</div>
                                    @endif
                                    @if($fu->isRequested())
                                        <div class="mt-0.5">
                                            <span class="inline-block px-1.5 py-0.2 rounded text-[9px] font-bold border {{ $fu->request_status === 'completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($fu->request_status === 'in_progress' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : ($fu->request_status === 'accepted' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-amber-50 text-amber-700 border-amber-200')) }}">
                                                Req: {{ $fu->request_status_label }}
                                                @if($fu->requestedBy)
                                                    <span class="font-normal opacity-75">({{ $fu->requestedBy->name }})</span>
                                                @endif
                                            </span>
                                        </div>
                                    @endif
                                    <div class="text-[10px] text-muted mt-0.5">
                                        {{ $leadRecord?->client?->organization?->name ?: '—' }}
                                    </div>
                                </td>

                                <!-- Status & Escalation -->
                                <td class="p-3">
                                    <div class="space-y-1">
                                        @if($fu->status === 'completed')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                Completed
                                            </span>
                                        @elseif($fu->status === 'cancelled')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-neutral-100 text-neutral-600 border border-neutral-200">
                                                Cancelled
                                            </span>
                                        @elseif($fu->urgency === 'overdue')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                                Overdue
                                            </span>
                                        @elseif($fu->urgency === 'due_today')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                Due Today
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                Scheduled
                                            </span>
                                        @endif

                                        @if($fu->escalation_level > 0)
                                            <span class="inline-block px-1.5 py-0.2 rounded text-[9px] font-bold bg-purple-100 text-purple-800 border border-purple-200" title="{{ $fu->escalation_notes }}">
                                                ⚠️ Esc: Level {{ $fu->escalation_level }}
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="p-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end">
                                        <x-ui.followup-actions-menu :followup="$fu" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-12 text-center text-muted text-xs">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <svg class="w-8 h-8 text-muted/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                        </svg>
                                        <span class="font-medium text-ink">No follow-ups match your current filter and tab criteria.</span>
                                        @if($this->activeFilterCount > 0)
                                            <button type="button" wire:click="resetFilters" class="text-xs text-primary hover:underline font-semibold cursor-pointer">
                                                Reset Filters
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($followups->hasPages())
                <div class="p-4 border-t border-border">
                    {{ $followups->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- Complete Next-Step Modal -->
    @if($completingFollowupId)
        <div class="fixed inset-0 bg-ink/40 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto overscroll-contain">
            <div class="bg-surface rounded-card border border-border p-6 shadow-xl max-w-md w-full space-y-4 max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-ink">Complete Follow-up: Record Outcome</h3>
                    <button type="button" wire:click="$set('completingFollowupId', null)" class="text-muted hover:text-ink text-sm cursor-pointer">&times;</button>
                </div>
                <p class="text-xs text-muted">Document customer response, discussion points, and optionally schedule the next milestone.</p>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-bold text-ink">Outcome Status <span class="text-rose-500">*</span></label>
                        <x-ui.themed-select wire:model="followupOutcome" placeholder="Select Outcome" class="w-full mt-1">
                            @foreach(\App\Models\Followup::outcomeOptions() as $val => $lbl)
                                <option value="{{ $val }}">{{ $lbl }}</option>
                            @endforeach
                        </x-ui.themed-select>
                    </div>

                    <div>
                        <label class="font-bold text-ink">Discussion Notes / Remarks</label>
                        <textarea wire:model="followupOutcomeNotes" rows="2" placeholder="Details of conversation, customer budget, interest level..."
                            class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1"></textarea>
                    </div>

                    <!-- Next Follow-up Step -->
                    <div class="pt-3 border-t border-border space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer font-bold text-ink">
                            <input type="checkbox" wire:model.live="scheduleNextFollowup" class="rounded border-border text-primary focus:ring-primary">
                            <span>Schedule Next Follow-Up Action</span>
                        </label>

                        @if($scheduleNextFollowup)
                            <div class="space-y-2.5 pt-1">
                                <div>
                                    <label class="font-semibold text-muted">Next Action Type</label>
                                    <x-ui.themed-select wire:model="nextFollowupType" placeholder="Select Action Type" class="w-full mt-0.5">
                                        <option value="call">Phone Call</option>
                                        <option value="whatsapp">WhatsApp Message</option>
                                        <option value="meeting">Face-to-Face Meeting</option>
                                        <option value="site_visit">Site Visit</option>
                                        <option value="other">Other Action</option>
                                    </x-ui.themed-select>
                                </div>
                                <div>
                                    <label class="font-semibold text-muted">Next Date &amp; Time <span class="text-rose-500">*</span></label>
                                    <input type="datetime-local" wire:model="nextDueAt" class="w-full p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                </div>
                                <div>
                                    <label class="font-semibold text-muted">Objective / Instructions</label>
                                    <input type="text" wire:model="nextNote" placeholder="Confirm unit preferences or pricing" class="w-full p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <button type="button" wire:click="$set('completingFollowupId', null)" class="px-3 py-1.5 rounded-lg border border-border text-xs font-semibold text-ink hover:bg-canvas cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" wire:click="completeWithNextStep" class="px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs cursor-pointer shadow-xs">
                        Save &amp; Complete
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Reschedule Modal -->
    @if($reschedulingFollowupId)
        <div class="fixed inset-0 bg-ink/40 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto overscroll-contain">
            <div class="bg-surface rounded-card border border-border p-6 shadow-xl max-w-md w-full space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-ink">Reschedule Follow-up</h3>
                    <button type="button" wire:click="$set('reschedulingFollowupId', null)" class="text-muted hover:text-ink text-sm cursor-pointer">&times;</button>
                </div>
                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-bold text-ink">Action Type</label>
                        <x-ui.themed-select wire:model="rescheduleType" placeholder="Select Action Type" class="w-full mt-1">
                            <option value="call">Phone Call</option>
                            <option value="whatsapp">WhatsApp Message</option>
                            <option value="meeting">Face-to-Face Meeting</option>
                            <option value="site_visit">Site Visit</option>
                            <option value="other">Other Action</option>
                        </x-ui.themed-select>
                    </div>
                    <div>
                        <label class="font-bold text-ink">New Date &amp; Time <span class="text-rose-500">*</span></label>
                        <input type="datetime-local" wire:model="rescheduleDueAt" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
                    </div>
                    <div>
                        <label class="font-bold text-ink">Reschedule Agenda / Reason</label>
                        <textarea wire:model="rescheduleNote" rows="2" placeholder="e.g. Customer busy, asked to callback tomorrow evening"
                            class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1"></textarea>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <button type="button" wire:click="$set('reschedulingFollowupId', null)" class="px-3 py-1.5 rounded-lg border border-border text-xs font-semibold text-ink hover:bg-canvas cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" wire:click="saveReschedule" class="px-4 py-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white font-bold text-xs cursor-pointer shadow-xs">
                        Save Rescheduled Follow-up
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Cancel Modal -->
    @if($cancellingFollowupId)
        <div class="fixed inset-0 bg-ink/40 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto overscroll-contain">
            <div class="bg-surface rounded-card border border-border p-6 shadow-xl max-w-md w-full space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-rose-900">Cancel Scheduled Follow-up</h3>
                    <button type="button" wire:click="$set('cancellingFollowupId', null)" class="text-muted hover:text-ink text-sm cursor-pointer">&times;</button>
                </div>
                <p class="text-xs text-muted">This will remove the follow-up from the active queue and record the cancellation in the activity timeline.</p>
                <div class="text-xs space-y-2">
                    <label class="font-bold text-ink">Reason for Cancellation</label>
                    <textarea wire:model="cancellationReason" rows="3" placeholder="e.g. Customer uncontactable, cancelled by mutual agreement, or moving to direct site visit"
                        class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <button type="button" wire:click="$set('cancellingFollowupId', null)" class="px-3 py-1.5 rounded-lg border border-border text-xs font-semibold text-ink hover:bg-canvas cursor-pointer">
                        Back
                    </button>
                    <button type="button" wire:click="saveCancel" class="px-4 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs cursor-pointer shadow-xs">
                        Confirm Cancellation
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Reassign Modal -->
    @if($reassigningFollowupId)
        <div class="fixed inset-0 bg-ink/40 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto overscroll-contain">
            <div class="bg-surface rounded-card border border-border p-6 shadow-xl max-w-md w-full space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-ink">Reassign Follow-Up</h3>
                    <button type="button" wire:click="$set('reassigningFollowupId', null)" class="text-muted hover:text-ink text-sm cursor-pointer">&times;</button>
                </div>
                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-bold text-ink">Assign To Executive <span class="text-rose-500">*</span></label>
                        <x-ui.themed-select wire:model="reassigneeId" placeholder="-- Select Executive --" :searchable="true" class="w-full mt-1">
                            <option value="">-- Select Executive --</option>
                            @foreach($executives as $exec)
                                <option value="{{ $exec->id }}">{{ $exec->name }}</option>
                            @endforeach
                        </x-ui.themed-select>
                        @error('reassigneeId') <span class="text-rose-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="font-bold text-ink">Scheduled Date &amp; Time</label>
                        <input type="datetime-local" wire:model="reassignDueAt" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
                    </div>
                    <div>
                        <label class="font-bold text-ink">Instructions / Notes</label>
                        <textarea wire:model="reassignNote" rows="2" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1"></textarea>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <button type="button" wire:click="$set('reassigningFollowupId', null)" class="px-3 py-1.5 rounded-lg border border-border text-xs font-semibold text-ink hover:bg-canvas cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" wire:click="saveReassign" class="px-4 py-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white font-bold text-xs cursor-pointer shadow-xs">
                        Save Reassignment
                    </button>
                </div>
            </div>
        </div>
    @endif

    <livewire:leads.lead-detail />

</div>
