<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-ink">Lead Replacement Rate & Dispute Prevention</h2>
            <p class="text-xs text-muted">Percentage of replaced leads per client & project with 6-month historical trend line.</p>
        </div>
        <x-ui.export-button target="exportExcel" />
    </div>

    <!-- 6-Month Trend Line Chart -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm">
        <h3 class="text-xs font-bold uppercase text-muted tracking-wider mb-4">6-Month Historical Replacement Rate Trend (%)</h3>
        <div 
            x-data="{
                renderChart() {
                    const trend = @json($monthlyTrend) || { labels: [], rates: [] };
                    window.initSafeChart('replacementTrendChart', {
                        type: 'line',
                        data: {
                            labels: trend.labels || [],
                            datasets: [{
                                label: 'Replacement Rate %',
                                data: trend.rates || [],
                                borderColor: '#0A0A0A',
                                backgroundColor: 'transparent',
                                borderWidth: 2.5,
                                tension: 0.3,
                                pointRadius: 4,
                                pointBackgroundColor: '#0A0A0A'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { grid: { color: '#E5E7EB' }, ticks: { font: { size: 10 }, callback: v => v + '%' } },
                                x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                            }
                        }
                    });
                }
            }"
            x-init="$nextTick(() => renderChart())"
            class="h-64 relative"
        >
            <canvas id="replacementTrendChart"></canvas>
        </div>
    </div>

    <!-- Replacement Rates Table Component -->
    <div class="space-y-2">
        <h3 class="text-base font-bold text-ink">Client Replacement Rate Breakdown</h3>

        <x-ui.advanced-table 
            :columns="$this->tableColumns()"
            :rows="$ratesData"
            :showSearch="false"
            :showFilterDropdown="false"
            :showConfigurations="false"
            emptyTitle="No Replacement Data"
            emptyMessage="No client replacement records calculated yet."
        />
    </div>
</div>
