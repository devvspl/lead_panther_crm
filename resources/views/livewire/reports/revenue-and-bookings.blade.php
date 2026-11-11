<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-ink">Bookings & Payments Received</h2>
            <p class="text-xs text-muted">Confirmed booking amounts, completed payments, and outstanding balances. Credit usage is reported separately from currency.</p>
        </div>
        <x-ui.export-button target="exportExcel" />
    </div>

    <!-- Monthly Line Chart -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm">
        <h3 class="text-xs font-bold uppercase text-muted tracking-wider mb-4">Monthly Payments Received (₹ Cr) & Confirmed Bookings</h3>
        <div 
            wire:key="revenue-and-bookings-chart-{{ md5(json_encode($monthlyChart)) }}"
            x-data="{
                renderChart() {
                    const chartData = @js($monthlyChart) || { labels: [], revenues: [], bookings: [] };
                    window.initSafeChart('revenueChart', {
                        type: 'line',
                        data: {
                            labels: chartData.labels || [],
                            datasets: [
                                {
                                    label: 'Payments Received (₹ Cr)',
                                    yAxisID: 'payments',
                                    data: chartData.revenues || [],
                                    borderColor: '#0A0A0A',
                                    backgroundColor: 'transparent',
                                    borderWidth: 2.5,
                                    tension: 0.3,
                                    pointRadius: 4
                                },
                                {
                                    label: 'Confirmed Bookings',
                                    yAxisID: 'bookings',
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
                                payments: { position: 'left', beginAtZero: true, title: { display: true, text: 'Payments (₹ Cr)' } },
                                bookings: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { precision: 0 }, title: { display: true, text: 'Bookings' } },
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
        <h3 class="text-base font-bold text-ink">Actual Bookings, Payments & Credit Usage</h3>

        <x-ui.advanced-table 
            :columns="$this->tableColumns()"
            :rows="$roiData"
            :showSearch="false"
            :showFilterDropdown="false"
            :showConfigurations="false"
            emptyTitle="No Booking or Lead Data"
            emptyMessage="No accessible records are available for this report."
        />
    </div>
</div>
