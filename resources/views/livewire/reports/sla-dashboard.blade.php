<div wire:poll.60s class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-ink">Real-time SLA Response Monitoring</h2>
            <p class="text-xs text-muted">Target: respond within 15 minutes. Pending means the response window is still open. Auto-refreshes every 60 seconds.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 shrink-0">
            <span class="px-2.5 py-1 text-[10px] font-bold rounded-pill bg-green-50 text-green-700 animate-pulse">● Live Poll (60s)</span>
            <x-ui.export-button target="exportExcel" />
        </div>
    </div>

    <!-- Donut Chart & Stat summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-1 bg-surface rounded-card border border-border p-6 shadow-sm flex flex-col items-center justify-center">
            <h3 class="text-xs font-bold uppercase text-muted tracking-wider mb-2">SLA Distribution</h3>
            <div 
                wire:key="sla-dashboard-chart-{{ md5(json_encode($buckets)) }}"
            x-data="{
                    renderChart() {
                        const b = @js($buckets) || { excellent: 0, acceptable: 0, breached: 0 };
                        window.initSafeChart('slaDonutChart', {
                            type: 'doughnut',
                            data: {
                                labels: ['SLA Met', 'Pending', 'Breached'],
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

        <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-surface rounded-card border border-border p-6 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-bold text-muted uppercase">SLA Met (&le;15m)</span>
                <span class="text-3xl font-black text-success">{{ $buckets['excellent'] }}</span>
                <span class="text-[10px] text-muted">Met target response window</span>
            </div>
            <div class="bg-surface rounded-card border border-border p-6 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-bold text-muted uppercase">Pending Response</span>
                <span class="text-3xl font-black text-warning">{{ $buckets['acceptable'] }}</span>
                <span class="text-[10px] text-muted">Still within the 15-minute window</span>
            </div>
            <div class="bg-surface rounded-card border border-border p-6 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-bold text-muted uppercase">Breached (&gt;15m)</span>
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
            emptyMessage="No leads currently exceed the 15-minute response target."
        />
    </div>
</div>
