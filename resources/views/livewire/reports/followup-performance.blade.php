<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-ink">Follow-up Execution Performance</h2>
            <p class="text-xs text-muted">Lead follow-ups completed on time versus overdue by Sales Executive.</p>
        </div>
        <x-ui.export-button target="exportExcel" />
    </div>

    <!-- Leaderboard Chart -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm">
        <h3 class="text-xs font-bold uppercase text-muted tracking-wider mb-4">On-Time vs Overdue Leaderboard</h3>
        <div 
            x-data="{
                renderChart() {
                    const data = @json($followupData) || [];
                    window.initSafeChart('followupChart', {
                        type: 'bar',
                        data: {
                            labels: data.map(d => d.executive_name),
                            datasets: [
                                { label: 'On Time', data: data.map(d => d.on_time), backgroundColor: '#16A34A', borderRadius: 4 },
                                { label: 'Overdue', data: data.map(d => d.overdue), backgroundColor: '#DC2626', borderRadius: 4 }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'top', labels: { font: { size: 10 } } } },
                            scales: {
                                x: { grid: { color: '#E5E7EB' }, ticks: { font: { size: 10 } } },
                                y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                            }
                        }
                    });
                }
            }"
            x-init="$nextTick(() => renderChart())"
            class="h-64 relative"
        >
            <canvas id="followupChart"></canvas>
        </div>
    </div>

    <!-- Follow-up Table Component -->
    <div class="space-y-2">
        <h3 class="text-base font-bold text-ink">Sales Executive Follow-up Metrics</h3>

        <x-ui.advanced-table 
            :columns="$this->tableColumns()"
            :rows="$followupData"
            :showSearch="false"
            :showFilterDropdown="false"
            :showConfigurations="false"
            emptyTitle="No Follow-up Data"
            emptyMessage="No executive follow-up metrics recorded yet."
        />
    </div>
</div>
