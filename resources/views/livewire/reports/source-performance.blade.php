<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-ink">Lead Source & Campaign Performance</h2>
            <p class="text-xs text-muted">Ingestion volume, cost per lead, and closed won conversion metrics.</p>
        </div>
        <div class="flex items-center space-x-3">
            <x-ui.themed-select 
                wire:model.live="days"
                :options="['7' => 'Last 7 Days', '30' => 'Last 30 Days', '90' => 'Last 90 Days']"
                placeholder="Last 30 Days"
                searchable="true"
            />
            <x-ui.export-button target="exportExcel" />
        </div>
    </div>

    <!-- Chart Card -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm">
        <h3 class="text-xs font-bold uppercase text-muted tracking-wider mb-4">Leads Ingested by Source</h3>
        <div class="h-64 relative">
            <canvas id="sourcePerfChart"></canvas>
        </div>
    </div>

    <!-- Data Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Lead Source</th>
                        <th class="py-3 px-4">Total Leads</th>
                        <th class="py-3 px-4">Est. Cost Per Lead</th>
                        <th class="py-3 px-4">Closed Won</th>
                        <th class="py-3 px-4">Conversion Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($reportData as $row)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-bold text-ink">{{ $row['source_name'] }}</td>
                            <td class="py-3 px-4 font-mono">{{ number_format($row['total_leads']) }}</td>
                            <td class="py-3 px-4 font-mono">₹{{ number_format($row['cost_per_lead'], 2) }}</td>
                            <td class="py-3 px-4 font-mono text-success font-bold">{{ number_format($row['closed_won']) }}</td>
                            <td class="py-3 px-4 font-mono font-bold">{{ $row['conversion_rate'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $reportData->links('vendor.pagination.tailwind') }}
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            const ctx = document.getElementById('sourcePerfChart')?.getContext('2d');
            if (ctx) {
                const data = @json($chartReportData);
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(d => d.source_name),
                        datasets: [{
                            label: 'Total Leads',
                            data: data.map(d => d.total_leads),
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
        });
    </script>
</div>
