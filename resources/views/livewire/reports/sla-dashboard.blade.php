<div wire:poll.60s class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-ink">Real-time SLA Response Monitoring</h2>
            <p class="text-xs text-muted">First-response windows: Excellent (&le;15m), Acceptable (15-30m), Breached (&gt;30m). Auto-refreshes every 60s.</p>
        </div>
        <div class="flex items-center space-x-3">
            <span class="px-2.5 py-1 text-[10px] font-bold rounded-pill bg-green-50 text-green-700 animate-pulse">● Live Poll (60s)</span>
            <x-ui.export-button target="exportExcel" />
        </div>
    </div>

    <!-- Donut Chart & Stat summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-1 bg-surface rounded-card border border-border p-6 shadow-sm flex flex-col items-center justify-center">
            <h3 class="text-xs font-bold uppercase text-muted tracking-wider mb-2">SLA Distribution</h3>
            <div 
                x-data="{
                    renderChart() {
                        const b = @json($buckets) || { excellent: 0, acceptable: 0, breached: 0 };
                        window.initSafeChart('slaDonutChart', {
                            type: 'doughnut',
                            data: {
                                labels: ['Excellent', 'Acceptable', 'Breached'],
                                datasets: [{
                                    data: [b.excellent || 0, b.acceptable || 0, b.breached || 0],
                                    backgroundColor: ['#16A34A', '#F59E0B', '#DC2626'],
                                    borderWidth: 2,
                                    borderColor: '#FFFFFF'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } }
                            }
                        });
                    }
                }"
                x-init="$nextTick(function() { renderChart(); })"
                class="w-48 h-48 relative"
            >
                <canvas id="slaDonutChart"></canvas>
            </div>
        </div>

        <div class="md:col-span-2 grid grid-cols-3 gap-4">
            <div class="bg-surface rounded-card border border-border p-6 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-bold text-muted uppercase">Excellent (&le;15m)</span>
                <span class="text-3xl font-black text-success">{{ $buckets['excellent'] }}</span>
                <span class="text-[10px] text-muted">Met target response window</span>
            </div>
            <div class="bg-surface rounded-card border border-border p-6 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-bold text-muted uppercase">Acceptable (15-30m)</span>
                <span class="text-3xl font-black text-warning">{{ $buckets['acceptable'] }}</span>
                <span class="text-[10px] text-muted">Acceptable grace window</span>
            </div>
            <div class="bg-surface rounded-card border border-border p-6 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-bold text-muted uppercase">Breached (&gt;30m)</span>
                <span class="text-3xl font-black text-danger">{{ $buckets['breached'] }}</span>
                <span class="text-[10px] text-muted">Immediate escalation needed</span>
            </div>
        </div>
    </div>

    <!-- Breached Leads Table Component -->
    <div class="space-y-2">
        <h3 class="text-base font-bold text-ink">Breached & Delayed Lead Operations</h3>

        <x-ui.advanced-table 
            :columns="$this->tableColumns()"
            :rows="$breachedLeads"
            :showSearch="false"
            :showFilterDropdown="false"
            :showConfigurations="false"
            emptyTitle="No Breached Leads"
            emptyMessage="No breached leads in queue! All response SLAs met."
        />
    </div>
</div>
