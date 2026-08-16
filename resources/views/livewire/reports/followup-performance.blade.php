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
        <div class="h-64 relative">
            <canvas id="followupChart"></canvas>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h3 class="text-base font-bold text-ink">Sales Executive Follow-up Metrics</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Sales Executive</th>
                        <th class="py-3 px-4">Completed On Time</th>
                        <th class="py-3 px-4">Overdue / Delayed</th>
                        <th class="py-3 px-4">On-Time Execution Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($followupData as $row)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-bold text-ink">{{ $row['executive_name'] }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-success">{{ number_format($row['on_time']) }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-danger">{{ number_format($row['overdue']) }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-ink">{{ $row['on_time_rate'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            const ctx = document.getElementById('followupChart')?.getContext('2d');
            if (ctx) {
                const data = @json($followupData);
                new Chart(ctx, {
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
        });
    </script>
</div>
