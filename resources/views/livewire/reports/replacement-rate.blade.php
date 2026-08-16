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
        <div class="h-64 relative">
            <canvas id="replacementTrendChart"></canvas>
        </div>
    </div>

    <!-- Replacement Rates Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h3 class="text-base font-bold text-ink">Client Replacement Rate Breakdown</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Client Organization</th>
                        <th class="py-3 px-4">Total Ingested Leads</th>
                        <th class="py-3 px-4">Replacement Claims</th>
                        <th class="py-3 px-4">Approved Replacements</th>
                        <th class="py-3 px-4">Effective Replacement Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($ratesData as $row)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-bold text-ink">{{ $row['client_name'] }}</td>
                            <td class="py-3 px-4 font-mono">{{ number_format($row['total_leads']) }}</td>
                            <td class="py-3 px-4 font-mono">{{ number_format($row['total_claims']) }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-purple-700">{{ number_format($row['approved_claims']) }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-ink">{{ $row['replacement_rate'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            const ctx = document.getElementById('replacementTrendChart')?.getContext('2d');
            if (ctx) {
                const trend = @json($monthlyTrend);
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: trend.labels,
                        datasets: [{
                            label: 'Replacement Rate %',
                            data: trend.rates,
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
        });
    </script>
</div>
