<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-ink">Lead Source Performance</h2>
            <p class="text-xs text-muted">Actual lead counts, recorded credit charges, and closed-won conversion rates for the selected period.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 shrink-0">
            <x-ui.themed-select 
                wire:model.live="days"
                :options="['7' => 'Last 7 Days', '30' => 'Last 30 Days', '90' => 'Last 90 Days']"
                placeholder="Last 30 Days"
                searchable="true"
            />
            <x-ui.export-button target="exportExcel" />
        </div>
    </div>

    @if(count($chartReportData)>0)
    <!-- Chart Card -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm">
        <h3 class="text-xs font-bold uppercase text-muted tracking-wider mb-4">Leads Ingested by Source</h3>
        <div 
            wire:key="source-performance-chart-{{ md5(json_encode($chartReportData)) }}"
            x-data="{
                renderChart() {
                    const data = @js($chartReportData) || [];
                    window.initSafeChart('sourcePerfChart', {
                        type: 'bar',
                        data: {
                            labels: data.map(function(d) { return d.source_name; }),
                            datasets: [{
                                label: 'Total Leads',
                                data: data.map(function(d) { return d.total_leads; }),
                                backgroundColor: '#0A0A0A',
                                borderRadius: 6,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { grid: { color: '#E5E7EB' }, ticks: { font: { size: 10 } } },
                                x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                            }
                        }
                    });
                }
            }"
            x-init="$nextTick(function() { renderChart(); })"
            class="h-64 relative"
        >
            <canvas id="sourcePerfChart"></canvas>
        </div>
    </div>

    @endif

    <!-- Data Table Component -->
    <div class="space-y-2">
        <x-ui.advanced-table 
            :columns="$this->tableColumns()"
            :rows="$reportData"
            :showSearch="false"
            :showFilterDropdown="false"
            :showConfigurations="false"
            emptyTitle="No Source Data"
            emptyMessage="No lead source metrics recorded for this timeframe."
        />
    </div>
</div>
