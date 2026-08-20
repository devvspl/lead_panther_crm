<div class="space-y-6">
    <!-- PART 1: ANALYTICS SUMMARY BAR & BREAKDOWN -->
    <div class="space-y-6">
        <!-- Date Selector & Section Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-ink">Leads Performance & Pipeline</h1>
                <p class="text-xs text-muted">Role-scoped analytics, pipeline metrics, and lead management directory.</p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="text-xs font-semibold text-muted">Analytics Range:</span>
                <x-ui.date-range-picker 
                    wire:model.live="analyticsRange"
                    wire:custom-from="analyticsCustomFrom"
                    wire:custom-to="analyticsCustomTo"
                    placeholder="This Month"
                />
            </div>
        </div>

        <!-- 8 Stat Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Total Leads -->
            <div class="bg-surface rounded-card border border-border p-5 shadow-sm flex items-start justify-between transition hover:shadow-md">
                <div>
                    <span class="text-xs font-semibold text-muted uppercase tracking-wider">Total Leads</span>
                    <div class="text-2xl font-bold text-ink mt-2 tracking-tight">{{ number_format($analytics['totalLeads']) }}</div>
                    <div class="mt-2 text-[11px] text-muted">In selected analytics scope</div>
                </div>
                <div class="h-8 w-8 rounded-full bg-canvas border border-border flex items-center justify-center text-ink flex-shrink-0">
                    <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
            </div>

            <!-- 2. New Leads Today -->
            <div class="bg-surface rounded-card border border-border p-5 shadow-sm flex items-start justify-between transition hover:shadow-md">
                <div>
                    <span class="text-xs font-semibold text-muted uppercase tracking-wider">New Leads Today</span>
                    <div class="text-2xl font-bold text-ink mt-2 tracking-tight">{{ number_format($analytics['newLeadsToday']) }}</div>
                    <div class="mt-2 text-[11px] text-muted">Created since 12:00 AM</div>
                </div>
                <div class="h-8 w-8 rounded-full bg-canvas border border-border flex items-center justify-center text-ink flex-shrink-0">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>

            <!-- 3. Assigned -->
            <div class="bg-surface rounded-card border border-border p-5 shadow-sm flex items-start justify-between transition hover:shadow-md">
                <div>
                    <span class="text-xs font-semibold text-muted uppercase tracking-wider">Assigned</span>
                    <div class="text-2xl font-bold text-ink mt-2 tracking-tight">{{ number_format($analytics['assignedCount']) }}</div>
                    <div class="mt-2 text-[11px] text-muted">Currently in Assigned stage</div>
                </div>
                <div class="h-8 w-8 rounded-full bg-canvas border border-border flex items-center justify-center text-ink flex-shrink-0">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
            </div>

            <!-- 4. Pending Response -->
            <div class="bg-surface rounded-card border border-border p-5 shadow-sm flex items-start justify-between transition hover:shadow-md">
                <div>
                    <span class="text-xs font-semibold text-muted uppercase tracking-wider">Pending Response</span>
                    <div class="text-2xl font-bold text-ink mt-2 tracking-tight">{{ number_format($analytics['pendingResponse']) }}</div>
                    <div class="mt-2 text-[11px] text-muted">Assigned, uncontacted</div>
                </div>
                <div class="h-8 w-8 rounded-full bg-canvas border border-border flex items-center justify-center text-ink flex-shrink-0">
                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>

            <!-- 5. SLA Breached -->
            <div class="bg-surface rounded-card border border-border p-5 shadow-sm flex items-start justify-between transition hover:shadow-md">
                <div>
                    <span class="text-xs font-semibold text-muted uppercase tracking-wider">SLA Breached</span>
                    <div class="text-2xl font-bold text-danger mt-2 tracking-tight">{{ number_format($analytics['slaBreachedCount']) }}</div>
                    <div class="mt-2 text-[11px] text-danger font-semibold">&gt; 30 min first response</div>
                </div>
                <div class="h-8 w-8 rounded-full bg-red-50 border border-red-200 flex items-center justify-center text-danger flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
            </div>

            <!-- 6. Site Visits Scheduled -->
            <div class="bg-surface rounded-card border border-border p-5 shadow-sm flex items-start justify-between transition hover:shadow-md">
                <div>
                    <span class="text-xs font-semibold text-muted uppercase tracking-wider">Site Visits (This Week)</span>
                    <div class="text-2xl font-bold text-ink mt-2 tracking-tight">{{ number_format($analytics['siteVisitsThisWeek']) }}</div>
                    <div class="mt-2 text-[11px] text-muted">Scheduled site visits</div>
                </div>
                <div class="h-8 w-8 rounded-full bg-canvas border border-border flex items-center justify-center text-ink flex-shrink-0">
                    <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>

            <!-- 7. Bookings This Month -->
            <div class="bg-surface rounded-card border border-border p-5 shadow-sm flex items-start justify-between transition hover:shadow-md">
                <div>
                    <span class="text-xs font-semibold text-muted uppercase tracking-wider">Bookings (This Month)</span>
                    <div class="text-2xl font-bold text-emerald-600 mt-2 tracking-tight">{{ number_format($analytics['bookingsThisMonth']) }}</div>
                    <div class="mt-2 text-[11px] text-muted">Bookings / Closed Won</div>
                </div>
                <div class="h-8 w-8 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>

            <!-- 8. Conversion Rate -->
            <div class="bg-surface rounded-card border border-border p-5 shadow-sm flex items-start justify-between transition hover:shadow-md">
                <div>
                    <span class="text-xs font-semibold text-muted uppercase tracking-wider">Conversion Rate</span>
                    <div class="text-2xl font-bold text-ink mt-2 tracking-tight">{{ $analytics['conversionRate'] }}%</div>
                    <div class="mt-2 text-[11px] text-muted">Closed Won / Total scope</div>
                </div>
                <div class="h-8 w-8 rounded-full bg-canvas border border-border flex items-center justify-center text-ink flex-shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
            </div>
        </div>

        <!-- Breakdown Section (Tabs + Chart + Table) -->
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-border pb-4">
                <div>
                    <h2 class="text-sm font-bold text-ink uppercase tracking-wider">Breakdown Analytics</h2>
                    <p class="text-xs text-muted">Group lead volume, conversion rate, and average response times.</p>
                </div>

                <!-- Breakdown Dimension Tabs -->
                <div class="inline-flex p-1 bg-canvas rounded-lg border border-border flex-wrap gap-1">
                    <button wire:click="$set('breakdownTab', 'builder')" class="px-3 py-1 text-xs font-bold rounded-md transition {{ $breakdownTab === 'builder' ? 'bg-ink text-white shadow-xs' : 'text-muted hover:text-ink' }}">By Builder</button>
                    <button wire:click="$set('breakdownTab', 'project')" class="px-3 py-1 text-xs font-bold rounded-md transition {{ $breakdownTab === 'project' ? 'bg-ink text-white shadow-xs' : 'text-muted hover:text-ink' }}">By Project</button>
                    <button wire:click="$set('breakdownTab', 'source')" class="px-3 py-1 text-xs font-bold rounded-md transition {{ $breakdownTab === 'source' ? 'bg-ink text-white shadow-xs' : 'text-muted hover:text-ink' }}">By Source</button>
                    <button wire:click="$set('breakdownTab', 'executive')" class="px-3 py-1 text-xs font-bold rounded-md transition {{ $breakdownTab === 'executive' ? 'bg-ink text-white shadow-xs' : 'text-muted hover:text-ink' }}">By Sales Exec</button>
                    <button wire:click="$set('breakdownTab', 'team')" class="px-3 py-1 text-xs font-bold rounded-md transition {{ $breakdownTab === 'team' ? 'bg-ink text-white shadow-xs' : 'text-muted hover:text-ink' }}">By Team</button>
                </div>
            </div>

            <!-- Chart.js Horizontal Bar Chart Container -->
            <div 
                x-data="{
                    chart: null,
                    renderChart(labels, counts) {
                        const l = (labels && labels.length) ? labels : ['No Data'];
                        const c = (counts && counts.length) ? counts : [0];

                        this.chart = window.initSafeChart('leadsBreakdownChart', {
                            type: 'bar',
                            data: {
                                labels: l,
                                datasets: [{
                                    label: 'Total Leads',
                                    data: c,
                                    backgroundColor: '#0A0A0A',
                                    borderRadius: 6,
                                    barThickness: 18
                                }]
                            },
                            options: {
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    x: { grid: { color: '#E5E7EB' }, ticks: { font: { size: 10 } } },
                                    y: { grid: { display: false }, ticks: { font: { size: 11, weight: 'bold' } } }
                                }
                            }
                        });
                    }
                }"
                x-init="$nextTick(() => renderChart(@js($analytics['chartLabels']), @js($analytics['chartCounts'])))"
                class="h-64 relative"
            >
                <canvas id="leadsBreakdownChart"></canvas>
            </div>

            <!-- Breakdown Performance Table Component -->
            <div class="border-t border-border pt-4">
                @php
                    $breakdownColumns = [
                        ['key' => 'name', 'label' => 'Group Name', 'class' => 'font-bold text-ink', 'sortable' => false, 'priority' => 1],
                        ['key' => 'total_leads', 'label' => 'Total Leads', 'class' => 'font-mono font-bold', 'formatter' => fn($v) => number_format((float)$v), 'sortable' => false, 'priority' => 1],
                        ['key' => 'conversion_rate', 'label' => 'Conversion Rate', 'suffix' => '%', 'class' => 'font-mono font-bold text-emerald-600', 'sortable' => false, 'priority' => 1],
                        ['key' => 'avg_sla_time', 'label' => 'Avg SLA Response Time', 'class' => 'font-mono text-muted', 'sortable' => false, 'priority' => 2],
                    ];
                @endphp

                <x-ui.advanced-table 
                    :columns="$breakdownColumns"
                    :rows="$analytics['breakdownRows']"
                    :showSearch="false"
                    :showFilterDropdown="false"
                    :showConfigurations="false"
                    emptyTitle="No Breakdown Data"
                    emptyMessage="No breakdown data available for this range."
                />
            </div>
        </div>
    </div>

    <!-- PART 2: TOOLBAR WITH KANBAN/TABLE TOGGLE -->
    <div class="bg-surface rounded-card border border-border p-4 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
         <!-- Filter Controls -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Search -->
            <div class="relative w-40 sm:w-48">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search leads..." class="w-full h-8 pl-3 pr-3.5 bg-canvas text-ink text-xs rounded-lg border border-border focus:ring-2 focus:ring-ink focus:bg-surface">
            </div>

            <!-- Sort By Filter -->
            <x-ui.themed-select 
                wire:model.live="sort_by"
                searchable="true"
                :options="[
                    'created_desc' => 'Latest Created',
                    'score_desc' => 'Score: High to Low',
                    'score_asc' => 'Score: Low to High'
                ]"
                placeholder="Sort By"
            />

            <!-- Project Filter -->
            <livewire:shared.searchable-select 
                :model="\App\Models\Project::class"
                :searchable="true"
                placeholder="All Projects"
                wire:model.live="project"
                key="select-project"
            />

            <!-- Sales Exec Filter -->
            <livewire:shared.searchable-select 
                :model="\App\Models\User::class"
                roleFilter="sales-executive, Sales Executive"
                :searchable="true"
                placeholder="All Agents"
                wire:model.live="executive"
                key="select-executive"
            />

            <!-- Team Filter (Role-Scoped) -->
            @php
                $user = auth()->user();
            @endphp

            @if($user && !$user->hasRole('sales-executive') && !$user->hasRole('Sales Executive') && count($availableTeams) > 0)
                @if(!$user->hasRole('channel-partner') && !$user->hasRole('Channel Partner') || count($availableTeams) > 1)
                    <livewire:shared.searchable-select 
                        :model="\App\Models\SalesTeam::class"
                        :searchable="true"
                        placeholder="All Teams"
                        wire:model.live="team"
                        key="select-team"
                    />
                @endif
            @endif

            <!-- Lead Source Filter -->
            <livewire:shared.searchable-select 
                :model="\App\Models\LeadSource::class"
                :searchable="true"
                placeholder="All Sources"
                wire:model.live="source"
                key="select-source"
            />

            <!-- SLA Breached Toggle -->
            <label class="flex items-center space-x-1.5 h-8 text-xs text-ink cursor-pointer bg-canvas px-3.5 rounded-lg border border-border select-none">
                <input type="checkbox" wire:model.live="sla_breached" class="rounded text-ink focus:ring-ink">
                <span class="font-medium text-danger">SLA Breached Only</span>
            </label>

            <!-- Pull / Manual Ingest Leads Action Button -->
            <button 
                type="button" 
                wire:click="openPullLeadsModal" 
                class="inline-flex items-center gap-1.5 px-3.5 h-8 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition shadow-xs shrink-0 cursor-pointer"
                title="Sync existing leads from Meta Lead Ads or create manually"
            >
                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span>+ Pull / Add Leads</span>
            </button>

            <x-ui.export-button target="exportExcel" />
        </div>

        <!-- View Mode Segmented Control Toggle -->
        <div class="inline-flex p-1 bg-canvas rounded-lg border border-border flex-shrink-0">
            <button wire:click="switchViewMode('kanban')" class="flex items-center space-x-1.5 px-3.5 py-1 text-xs font-bold rounded-md transition {{ $viewMode === 'kanban' ? 'bg-ink text-white shadow-xs' : 'text-muted hover:text-ink' }}">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
                <span>Kanban</span>
            </button>
            <button wire:click="switchViewMode('table')" class="flex items-center space-x-1.5 px-3.5 py-1 text-xs font-bold rounded-md transition {{ $viewMode === 'table' ? 'bg-ink text-white shadow-xs' : 'text-muted hover:text-ink' }}">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                <span>Table</span>
            </button>
        </div>
    </div>

    <!-- Confirmation Modal for Terminal / Exception Stage Transition -->
    @if($showConfirmModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-surface rounded-card border border-border p-6 max-w-md w-full shadow-2xl space-y-4">
                <div class="flex items-center space-x-3 text-warning">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
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

    <!-- PART 3: BOARD CONTENT (KANBAN OR TABLE) -->
    @if($viewMode === 'table')
        <!-- Advanced Table View -->
        <x-ui.advanced-table 
            :columns="$this->tableColumns()"
            :rows="$tableLeads"
            :quickFilters="$this->quickFilters()"
            :activeStatus="$statusFilter"
            :visibleColumns="$visibleColumns"
            :sortField="$sortField"
            :sortDirection="$sortDirection"
            searchPlaceholder="Search leads by code, name, phone, city..."
            emptyTitle="No Leads Found"
            emptyMessage="No leads matched your search and filter criteria."
        >
            <x-slot:action>
                <div class="flex items-center gap-2">
                    <x-ui.export-button action="export" class="text-xs" />
                    <x-ui.button wire:click="openPullLeadsModal" variant="primary" class="text-xs flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Add / Ingest Leads</span>
                    </x-ui.button>
                </div>
            </x-slot:action>
        </x-ui.advanced-table>
    @else
        <!-- PART 4: KANBAN BOARD GRID (CONTAINED HORIZONTAL SCROLL) -->
        <div 
            x-data="{
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
                onMouseUp() {
                    this.isMouseDown = false;
                },
                onMouseLeave() {
                    this.isMouseDown = false;
                }
            }"
            @mousedown="onMouseDown($event)"
            @mousemove="onMouseMove($event)"
            @mouseup="onMouseUp()"
            @mouseleave="onMouseLeave()"
            :class="isMouseDown ? 'cursor-grabbing select-none' : 'cursor-grab'"
            class="w-full overflow-x-auto overflow-y-hidden pb-6"
        >
            <div class="flex items-start space-x-4 min-w-max">
                <!-- Main Flow Columns -->
                @foreach($mainStages as $stageKey => $stageLabel)
                    <div class="w-72 bg-canvas/60 rounded-card border border-border p-3 flex flex-col max-h-[calc(100vh-220px)] flex-shrink-0">
                        <!-- Column Header -->
                        <div class="flex items-center justify-between pb-3 mb-3 border-b border-border">
                            <div class="flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full {{ in_array($stageKey, ['new', 'assigned']) ? 'bg-blue-500' : (in_array($stageKey, ['contacted', 'connected', 'qualified']) ? 'bg-amber-500' : (in_array($stageKey, ['closed_won', 'booking', 'payment']) ? 'bg-green-500' : 'bg-purple-500')) }}"></span>
                                <h3 class="text-xs font-bold text-ink tracking-tight">{{ $stageLabel }}</h3>
                            </div>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill bg-surface text-ink border border-border shadow-2xs">
                                {{ $stageCounts[$stageKey] ?? 0 }}
                            </span>
                        </div>

                        <!-- Cards Droppable Container -->
                        <div 
                            data-stage-key="{{ $stageKey }}"
                            x-data="{ 
                                initSortable() { 
                                    new Sortable($el, { 
                                        group: 'kanban', 
                                        animation: 150, 
                                        ghostClass: 'opacity-50',
                                        onEnd: (evt) => { 
                                            const leadId = evt.item.getAttribute('data-lead-id'); 
                                            const toStage = evt.to.getAttribute('data-stage-key'); 
                                            $wire.updateLeadStage(leadId, toStage); 
                                        } 
                                    }); 
                                } 
                            }" 
                            x-init="initSortable()"
                            class="flex-1 overflow-y-auto space-y-3 pr-1 min-h-[150px]"
                        >
                            @forelse($stageLeads[$stageKey] ?? [] as $lead)
                                @php
                                    $score = $lead->lead_score ?? 0;
                                    $scoreBadgeClass = $score >= 70 
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
                                        : ($score >= 40 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-50 text-slate-600 border-slate-200');
                                @endphp

                                <div 
                                    data-lead-id="{{ $lead->id }}"
                                    @click="$dispatch('open-lead-detail', { id: {{ $lead->id }} })"
                                    class="bg-surface rounded-xl border border-border p-3.5 shadow-2xs hover:shadow-md transition cursor-pointer space-y-2.5 group relative border-l-4 {{ $lead->first_response_at ? 'border-l-green-500' : (\Carbon\Carbon::parse($lead->created_at)->diffInMinutes(now()) > 15 ? 'border-l-red-500' : 'border-l-amber-500') }}"
                                >
                                    <!-- Top Bar inside Card -->
                                    <div class="flex items-center justify-between text-[10px]">
                                        <div class="flex items-center space-x-1.5">
                                            <span class="font-bold text-muted tracking-tight">{{ $lead->lead_code }}</span>
                                            <!-- Score Progress Badge -->
                                            <span class="px-1.5 py-0.5 rounded-full text-[9px] font-extrabold border {{ $scoreBadgeClass }}" title="Lead Priority Score: {{ $score }}/100">
                                                ⚡ {{ $score }}
                                            </span>
                                        </div>

                                        <!-- SLA Pill -->
                                        @if($lead->first_response_at)
                                            <span class="px-1.5 py-0.5 rounded-pill bg-green-50 text-green-700 font-semibold text-[9px]">SLA Met</span>
                                        @elseif(\Carbon\Carbon::parse($lead->created_at)->diffInMinutes(now()) > 15)
                                            <span class="px-1.5 py-0.5 rounded-pill bg-red-50 text-red-700 font-bold text-[9px]">SLA Breached</span>
                                        @else
                                            <span class="px-1.5 py-0.5 rounded-pill bg-amber-50 text-amber-700 font-semibold text-[9px]">SLA Pending</span>
                                        @endif
                                    </div>

                                    <!-- Lead Name & City -->
                                    <div>
                                        <h4 class="text-xs font-bold text-ink group-hover:text-info transition truncate">{{ $lead->name }}</h4>
                                        <p class="text-[11px] text-muted truncate">{{ $lead->city ?: 'Location N/A' }} • {{ $lead->property_type ?: 'Property' }}</p>
                                    </div>

                                    <!-- Budget & Source -->
                                    <div class="flex items-center justify-between pt-1">
                                        <span class="text-xs font-extrabold text-ink">
                                            {{ is_numeric($lead->budget) ? '₹' . number_format($lead->budget / 100000, 1) . 'L' : $lead->budget }}
                                        </span>
                                        <span class="px-2 py-0.5 text-[9px] font-bold uppercase rounded bg-canvas text-muted border border-border">
                                            {{ $lead->leadSource?->name ?: 'Direct' }}
                                        </span>
                                    </div>

                                    <!-- Bottom Row: Avatar & Days in Stage -->
                                    <div class="flex items-center justify-between border-t border-border pt-2 text-[10px] text-muted">
                                        <div class="flex items-center space-x-1.5 min-w-0">
                                            <div class="h-5 w-5 rounded-full bg-accent text-white flex items-center justify-center font-bold text-[9px] flex-shrink-0">
                                                {{ strtoupper(substr($lead->assignedTo?->name ?? 'A', 0, 1)) }}
                                            </div>
                                            <span class="truncate font-medium text-ink">{{ $lead->assignedTo?->name ?: 'Unassigned' }}</span>
                                        </div>
                                        <span class="font-semibold text-muted flex-shrink-0">
                                            {{ (int) \Carbon\Carbon::parse($lead->created_at)->diffInDays(now()) }}d
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-6 text-xs text-muted border-2 border-dashed border-border rounded-xl">
                                    No leads in {{ $stageLabel }}
                                </div>
                            @endforelse
                        </div>

                        <!-- Pagination Load More Button -->
                        @if(($stageCounts[$stageKey] ?? 0) > count($stageLeads[$stageKey] ?? []))
                            <div class="pt-2 text-center">
                                <button wire:click="loadMore('{{ $stageKey }}')" class="text-[11px] font-bold text-muted hover:text-ink transition">
                                    Load More (+20)
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach

                <!-- Collapsed / Expandable "Other" Column at Far Right -->
                <div class="w-72 bg-canvas/60 rounded-card border border-border p-3 flex flex-col max-h-[calc(100vh-220px)] flex-shrink-0">
                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-border">
                        <div class="flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>
                            <h3 class="text-xs font-bold text-ink tracking-tight">Other / Closed</h3>
                        </div>
                        <button wire:click="$toggle('otherColumnExpanded')" class="text-xs text-muted hover:text-ink font-semibold">
                            {{ $otherColumnExpanded ? 'Collapse' : 'Expand' }}
                        </button>
                    </div>

                    @if($otherColumnExpanded)
                        <div class="space-y-4 overflow-y-auto flex-1">
                            @foreach($otherStages as $oStageKey => $oStageLabel)
                                <div class="border-t border-border pt-2">
                                    <div class="text-[10px] font-bold text-muted uppercase mb-2 flex items-center justify-between">
                                        <span>{{ $oStageLabel }}</span>
                                        <span class="px-1.5 py-0.5 rounded bg-surface border border-border">{{ $stageCounts[$oStageKey] ?? 0 }}</span>
                                    </div>
                                    <div 
                                        data-stage-key="{{ $oStageKey }}"
                                        x-data="{ initSortable() { new Sortable($el, { group: 'kanban', animation: 150, onEnd: (evt) => { const leadId = evt.item.getAttribute('data-lead-id'); const toStage = evt.to.getAttribute('data-stage-key'); $wire.updateLeadStage(leadId, toStage); } }); } }"
                                        x-init="initSortable()"
                                        class="space-y-2 min-h-[60px]"
                                    >
                                        @foreach($stageLeads[$oStageKey] ?? [] as $oLead)
                                            <div data-lead-id="{{ $oLead->id }}" @click="$dispatch('open-lead-detail', { id: {{ $oLead->id }} })" class="p-2 bg-surface rounded-lg border border-border text-xs cursor-pointer hover:bg-canvas">
                                                <div class="font-bold text-ink flex items-center justify-between">
                                                    <span>{{ $oLead->name }}</span>
                                                    <span class="text-[9px] px-1 rounded bg-canvas font-mono font-bold">⚡ {{ $oLead->lead_score ?? 0 }}</span>
                                                </div>
                                                <div class="text-[10px] text-muted">{{ $oLead->lead_code }} • {{ is_numeric($oLead->budget) ? '₹' . number_format($oLead->budget / 100000, 1) . 'L' : $oLead->budget }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex-1 flex flex-col items-center justify-center text-center p-6 text-muted space-y-2">
                            <svg class="w-8 h-8 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            <p class="text-xs">Contains Closed Lost, Wrong Number, Duplicate, Rental, Replaced</p>
                            <button wire:click="$set('otherColumnExpanded', true)" class="text-xs font-bold text-ink underline">
                                View {{ array_sum(array_intersect_key($stageCounts, $otherStages)) }} Leads
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Include Slide-over Detail Component -->
    <livewire:leads.lead-detail />

    <!-- Manual Lead Ingestion & Meta Sync Offcanvas Drawer -->
    <x-ui.offcanvas 
        wire:model="showPullLeadsModal"
        name="pull-leads-drawer" 
        title="Pull & Ingest Leads" 
        subtitle="Sync existing historical leads from Meta Lead Ads or create new CRM leads manually."
        maxWidth="lg"
    >
        <x-slot:headerIcon>
            <div class="p-1.5 rounded-lg bg-canvas text-ink border border-border">
                <svg class="w-4 h-4 text-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
            </div>
        </x-slot:headerIcon>

        <div class="space-y-5 text-xs">
            <!-- Mode Switcher Tabs -->
            <div class="flex p-1 bg-canvas rounded-lg border border-border">
                <button 
                    type="button"
                    wire:click="$set('pullMode', 'meta')" 
                    class="flex-1 py-1.5 text-xs font-semibold rounded-md transition flex items-center justify-center gap-1.5 {{ $pullMode === 'meta' ? 'bg-surface text-ink shadow-xs border border-border' : 'text-muted hover:text-ink' }}"
                >
                    <svg class="w-3.5 h-3.5 text-primary" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    <span>Meta Ads Sync</span>
                </button>
                <button 
                    type="button"
                    wire:click="$set('pullMode', 'manual')" 
                    class="flex-1 py-1.5 text-xs font-semibold rounded-md transition flex items-center justify-center gap-1.5 {{ $pullMode === 'manual' ? 'bg-surface text-ink shadow-xs border border-border' : 'text-muted hover:text-ink' }}"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    <span>Single Manual Lead</span>
                </button>
                <a 
                    href="{{ route('leads.upload') }}" 
                    class="flex-1 py-1.5 text-xs font-semibold rounded-md transition flex items-center justify-center gap-1.5 text-muted hover:text-ink"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <span>Bulk CSV Upload</span>
                </a>
            </div>

            <!-- Tab 1: Meta Ads Pull Options -->
            @if($pullMode === 'meta')
                <div class="space-y-4">
                    <div>
                        <label class="font-bold text-ink">Select Connected Meta Account</label>
                        @php
                            $accOptions = [];
                            foreach($portalAccounts as $acc) {
                                $accOptions[(string)$acc->id] = $acc->name . ' (' . ucfirst($acc->type) . ')';
                            }
                        @endphp
                        @if(empty($accOptions))
                            <div class="p-3 bg-canvas rounded-lg border border-border text-muted text-[11px] mt-1 space-y-1">
                                <p>No Meta Portal Accounts configured yet.</p>
                                <a href="{{ route('settings.integrations') }}" class="font-bold text-primary hover:underline block">
                                    &rarr; Configure Meta Page Access Token in Integrations
                                </a>
                            </div>
                        @else
                            <x-ui.themed-select 
                                wire:model.live="selectedPortalAccountId" 
                                :options="$accOptions"
                                placeholder="Choose Account"
                                class="w-full mt-1" 
                            />
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-ink">Leadgen Form ID <span class="text-muted font-normal">(Optional)</span></label>
                            <input 
                                type="text" 
                                wire:model="customFormId" 
                                placeholder="e.g. 123456789 (Leave blank for all)" 
                                class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs font-mono mt-1"
                            >
                        </div>
                        <div>
                            <label class="font-bold text-ink">Max Leads to Pull</label>
                            <select 
                                wire:model="pullLimit" 
                                class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1 font-medium"
                            >
                                <option value="10">10 Most Recent Leads</option>
                                <option value="25">25 Leads</option>
                                <option value="50">50 Leads</option>
                                <option value="100">100 Leads</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-ink">Assign to Project <span class="text-muted font-normal">(Optional Override)</span></label>
                            @php
                                $projOptions = ['' => 'Default / Mapped Project'];
                                foreach($allProjects as $p) {
                                    $projOptions[(string)$p->id] = $p->name;
                                }
                            @endphp
                            <x-ui.themed-select 
                                wire:model="syncProjectId" 
                                :options="$projOptions"
                                placeholder="Auto from Mapping"
                                class="w-full mt-1" 
                            />
                        </div>
                        <div>
                            <label class="font-bold text-ink">Assign to Campaign <span class="text-muted font-normal">(Optional Override)</span></label>
                            @php
                                $campOptions = ['' => 'Default / Mapped Campaign'];
                                foreach($allCampaigns as $c) {
                                    $campOptions[(string)$c->id] = $c->name;
                                }
                            @endphp
                            <x-ui.themed-select 
                                wire:model="syncCampaignId" 
                                :options="$campOptions"
                                placeholder="Auto from Mapping"
                                class="w-full mt-1" 
                            />
                        </div>
                    </div>

                    <!-- Custom Access Token Override -->
                    <div>
                        <label class="font-bold text-ink">Custom Page Access Token <span class="text-muted font-normal">(Optional Override)</span></label>
                        <input 
                            type="password" 
                            wire:model="customPageAccessToken" 
                            placeholder="Use saved token from account or paste direct Graph token" 
                            class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs font-mono mt-1"
                        >
                    </div>

                    @if($pullSummary)
                        <div class="p-4 bg-canvas rounded-xl border border-border space-y-2 mt-3">
                            <div class="font-bold text-ink flex items-center justify-between text-xs">
                                <span>Sync Summary Results</span>
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
            @endif

            <!-- Tab 2: Single Manual Lead Entry -->
            @if($pullMode === 'manual')
                <div class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-ink">Lead Full Name <span class="text-red-500">*</span></label>
                            <input 
                                type="text" 
                                wire:model="manualName" 
                                placeholder="e.g. Vikram Singhania" 
                                class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1"
                            >
                            @error('manualName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="font-bold text-ink">Mobile Number <span class="text-red-500">*</span></label>
                            <input 
                                type="text" 
                                wire:model="manualMobile" 
                                placeholder="e.g. 9876543210" 
                                class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs font-mono mt-1"
                            >
                            @error('manualMobile') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-ink">Email Address</label>
                            <input 
                                type="email" 
                                wire:model="manualEmail" 
                                placeholder="e.g. vikram@example.com" 
                                class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1"
                            >
                            @error('manualEmail') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="font-bold text-ink">Target Project <span class="text-red-500">*</span></label>
                            @php
                                $mProjOptions = ['' => 'Select Project'];
                                foreach($allProjects as $p) {
                                    $mProjOptions[(string)$p->id] = $p->name;
                                }
                            @endphp
                            <x-ui.themed-select 
                                wire:model="manualProjectId" 
                                :options="$mProjOptions"
                                placeholder="Select Project"
                                class="w-full mt-1" 
                            />
                            @error('manualProjectId') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="font-bold text-ink">Property Type</label>
                            <input 
                                type="text" 
                                wire:model="manualPropertyType" 
                                placeholder="2 BHK / 3 BHK / Villa" 
                                class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1"
                            >
                        </div>
                        <div>
                            <label class="font-bold text-ink">Budget</label>
                            <input 
                                type="text" 
                                wire:model="manualBudget" 
                                placeholder="e.g. ₹85.0L" 
                                class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1"
                            >
                        </div>
                        <div>
                            <label class="font-bold text-ink">City</label>
                            <input 
                                type="text" 
                                wire:model="manualCity" 
                                placeholder="e.g. Mumbai" 
                                class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-ink">Requirement Notes</label>
                        <textarea 
                            wire:model="manualRequirement" 
                            rows="2" 
                            placeholder="Client inquiry notes, specific preferences, or channel source..."
                            class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink text-xs mt-1 focus:ring-2 focus:ring-ink"
                        ></textarea>
                    </div>
                </div>
            @endif
        </div>

        <x-slot:footer>
            <button 
                type="button" 
                wire:click="closePullLeadsModal" 
                class="px-4 py-2 border border-border bg-white text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition"
            >
                Close
            </button>

            @if($pullMode === 'meta')
                <button 
                    type="button" 
                    wire:click="pullMetaLeads" 
                    wire:loading.attr="disabled"
                    class="px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition shadow-xs flex items-center gap-2 disabled:opacity-60 cursor-pointer"
                >
                    <svg wire:loading wire:target="pullMetaLeads" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="pullMetaLeads">Pull &amp; Ingest Meta Leads</span>
                    <span wire:loading wire:target="pullMetaLeads">Fetching from Graph API...</span>
                </button>
            @elseif($pullMode === 'manual')
                <button 
                    type="button" 
                    wire:click="createManualLead" 
                    wire:loading.attr="disabled"
                    class="px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition shadow-xs flex items-center gap-2 cursor-pointer"
                >
                    <span>+ Add Lead to CRM</span>
                </button>
            @endif
        </x-slot:footer>
    </x-ui.offcanvas>

    <!-- CDN Script for SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</div>
