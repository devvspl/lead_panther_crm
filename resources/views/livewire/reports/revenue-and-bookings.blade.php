<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-ink">Revenue, Bookings & Client ROI</h2>
            <p class="text-xs text-muted">Monthly bookings velocity, revenue total, and client credit expenditure vs ROI return.</p>
        </div>
        <x-ui.export-button target="exportExcel" />
    </div>

    <!-- Monthly Line Chart -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm">
        <h3 class="text-xs font-bold uppercase text-muted tracking-wider mb-4">Monthly Revenue Velocity (₹ Cr) & Bookings</h3>
        <div 
            x-data="{
                renderChart() {
                    const chartData = @json($monthlyChart) || { labels: [], revenues: [], bookings: [] };
                    window.initSafeChart('revenueChart', {
                        type: 'line',
                        data: {
                            labels: chartData.labels || [],
                            datasets: [
                                {
                                    label: 'Revenue (₹ Cr)',
                                    data: chartData.revenues || [],
                                    borderColor: '#0A0A0A',
                                    backgroundColor: 'transparent',
                                    borderWidth: 2.5,
                                    tension: 0.3,
                                    pointRadius: 4
                                },
                                {
                                    label: 'Bookings Count',
                                    data: chartData.bookings || [],
                                    borderColor: '#3B82F6',
                                    backgroundColor: 'transparent',
                                    borderWidth: 2,
                                    borderDash: [5, 5],
                                    tension: 0.3,
                                    pointRadius: 3
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'top', labels: { font: { size: 10 } } } },
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
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Client ROI Table Component -->
    <div class="space-y-2">
        <h3 class="text-base font-bold text-ink">Client Credit Return on Investment (ROI)</h3>

        <x-ui.advanced-table 
            :columns="$this->tableColumns()"
            :rows="$roiData"
            :showSearch="false"
            :showFilterDropdown="false"
            :showConfigurations="false"
            emptyTitle="No ROI Data"
            emptyMessage="No client ROI records calculated yet."
        />
    </div>
</div>
