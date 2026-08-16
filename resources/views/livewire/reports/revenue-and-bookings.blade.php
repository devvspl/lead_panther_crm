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
        <div class="h-64 relative">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Client ROI Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h3 class="text-base font-bold text-ink">Client Credit Return on Investment (ROI)</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Client Organization</th>
                        <th class="py-3 px-4">Credits Consumed</th>
                        <th class="py-3 px-4">Credit Cost (₹)</th>
                        <th class="py-3 px-4">Bookings Closed</th>
                        <th class="py-3 px-4">Gross Revenue (₹)</th>
                        <th class="py-3 px-4">ROI Ratio</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($roiData as $row)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-bold text-ink">{{ $row['client_name'] }}</td>
                            <td class="py-3 px-4 font-mono">{{ number_format($row['credits_spent']) }}</td>
                            <td class="py-3 px-4 font-mono">₹{{ number_format($row['credits_spent_amount'], 2) }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-success">{{ number_format($row['bookings_closed']) }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-ink">₹{{ number_format($row['revenue'] / 100000, 1) }} Lakhs</td>
                            <td class="py-3 px-4 font-mono font-bold text-purple-700">{{ number_format($row['roi_ratio'], 1) }}x</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            const ctx = document.getElementById('revenueChart')?.getContext('2d');
            if (ctx) {
                const chartData = @json($monthlyChart);
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartData.labels,
                        datasets: [
                            {
                                label: 'Revenue (₹ Cr)',
                                data: chartData.revenues,
                                borderColor: '#0A0A0A',
                                backgroundColor: 'transparent',
                                borderWidth: 2.5,
                                tension: 0.3,
                                pointRadius: 4
                            },
                            {
                                label: 'Bookings Count',
                                data: chartData.bookings,
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
        });
    </script>
</div>
