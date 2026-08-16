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
                        const ctx = document.getElementById('leadsBreakdownChart')?.getContext('2d');
                        if (!ctx) return;
                        if (this.chart) {
                            this.chart.destroy();
                        }
                        if (typeof Chart === 'undefined') return;
                        this.chart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: labels && labels.length ? labels : ['No Data'],
                                datasets: [{
                                    label: 'Total Leads',
                                    data: counts && counts.length ? counts : [0],
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
                x-effect="renderChart(@js($analytics['chartLabels']), @js($analytics['chartCounts']))"
                class="h-64 relative"
            >
                <canvas id="leadsBreakdownChart"></canvas>
            </div>

            <!-- Breakdown Performance Table -->
            <div class="overflow-x-auto border-t border-border pt-4">
                <table class="w-full text-left text-xs text-ink border-collapse">
                    <thead>
                        <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                            <th class="py-3 px-4">Group Name</th>
                            <th class="py-3 px-4">Total Leads</th>
                            <th class="py-3 px-4">Conversion Rate</th>
                            <th class="py-3 px-4">Avg SLA Response Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($analytics['breakdownRows'] as $row)
                            <tr class="hover:bg-canvas/50 transition">
                                <td class="py-3 px-4 font-bold text-ink">{{ $row['name'] }}</td>
                                <td class="py-3 px-4 font-mono font-bold">{{ number_format($row['total_leads']) }}</td>
                                <td class="py-3 px-4 font-mono font-bold text-emerald-600">{{ $row['conversion_rate'] }}%</td>
                                <td class="py-3 px-4 font-mono text-muted">{{ $row['avg_sla_time'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-muted">No breakdown data available for this range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
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
        <!-- Table View -->
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-ink border-collapse">
                    <thead>
                        <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                            <th class="py-3 px-4">Lead ID</th>
                            <th class="py-3 px-4">Name & Contact</th>
                            <th class="py-3 px-4">Project</th>
                            <th class="py-3 px-4">Stage</th>
                            <th class="py-3 px-4">Source</th>
                            <th class="py-3 px-4">Budget</th>
                            <th class="py-3 px-4">Assigned To</th>
                            <th class="py-3 px-4">SLA Status</th>
                            <th class="py-3 px-4">Score</th>
                            <th class="py-3 px-4">Created Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($tableLeads as $lead)
                            <tr 
                                @click="$dispatch('open-lead-detail', { id: {{ $lead->id }} })" 
                                class="hover:bg-canvas/50 transition cursor-pointer"
                            >
                                <td class="py-3 px-4 font-mono font-bold text-ink">{{ $lead->lead_code }}</td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-ink">{{ $lead->name }}</div>
                                    <div class="text-[10px] text-muted">{{ $lead->city ?: 'Location N/A' }} • {{ $lead->mobile }}</div>
                                </td>
                                <td class="py-3 px-4 font-medium">{{ $lead->project?->name ?: 'N/A' }}</td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill bg-canvas border border-border text-ink">
                                        {{ ucfirst(str_replace('_', ' ', $lead->current_stage)) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-muted">{{ $lead->leadSource?->name ?: 'Direct' }}</td>
                                <td class="py-3 px-4 font-mono font-bold">₹{{ number_format($lead->budget / 100000, 1) }}L</td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-ink">{{ $lead->assignedTo?->name ?: 'Unassigned' }}</div>
                                    @if($lead->assignedTo?->salesTeamMembers?->first()?->salesTeam)
                                        <div class="text-[10px] text-muted font-medium flex items-center space-x-1 mt-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-info flex-shrink-0"></span>
                                            <span class="truncate">{{ $lead->assignedTo->salesTeamMembers->first()->salesTeam->name }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    @if($lead->first_response_at)
                                        <span class="px-2 py-0.5 rounded-pill bg-green-50 text-green-700 font-bold text-[10px]">SLA Met</span>
                                    @elseif(\Carbon\Carbon::parse($lead->created_at)->diffInMinutes(now()) > 30)
                                        <span class="px-2 py-0.5 rounded-pill bg-red-50 text-red-700 font-bold text-[10px]">SLA Breached</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-pill bg-amber-50 text-amber-700 font-semibold text-[10px]">Pending</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 font-mono font-bold text-info">⚡ {{ $lead->lead_score ?? 0 }}</td>
                                <td class="py-3 px-4 text-muted">{{ $lead->created_at ? $lead->created_at->format('M d, Y H:i') : '' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-8 text-center text-muted">No leads found matching current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pt-2">
                {{ $tableLeads->links('vendor.pagination.tailwind') }}
            </div>
        </div>
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
                                            ₹{{ number_format($lead->budget / 100000, 1) }}L
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
                                                <div class="text-[10px] text-muted">{{ $oLead->lead_code }} • ₹{{ number_format($oLead->budget / 100000, 1) }}L</div>
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

    <!-- CDN Script for Chart.js & SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</div>
