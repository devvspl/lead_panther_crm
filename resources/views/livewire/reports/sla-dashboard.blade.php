<div wire:poll.60s class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-ink">Real-time SLA Response Monitoring</h2>
            <p class="text-xs text-muted">First-response windows: Excellent (&le;15m), Acceptable (15-30m), Breached (&gt;30m). Auto-refreshes every 60s.</p>
        </div>
        <div class="flex items-center space-x-3">
            <span class="px-2.5 py-1 text-[10px] font-bold rounded-pill bg-green-50 text-green-700 animate-pulse">● Live Poll (60s)</span>
            <x-ui.export-button target="exportExcel" />
        </div>
    </div>

    <!-- Donut Chart & Stat summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-1 bg-surface rounded-card border border-border p-6 shadow-sm flex flex-col items-center justify-center">
            <h3 class="text-xs font-bold uppercase text-muted tracking-wider mb-2">SLA Distribution</h3>
            <div class="w-48 h-48 relative">
                <canvas id="slaDonutChart"></canvas>
            </div>
        </div>

        <div class="md:col-span-2 grid grid-cols-3 gap-4">
            <div class="bg-surface rounded-card border border-border p-6 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-bold text-muted uppercase">Excellent (&le;15m)</span>
                <span class="text-3xl font-black text-success">{{ $buckets['excellent'] }}</span>
                <span class="text-[10px] text-muted">Met target response window</span>
            </div>
            <div class="bg-surface rounded-card border border-border p-6 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-bold text-muted uppercase">Acceptable (15-30m)</span>
                <span class="text-3xl font-black text-warning">{{ $buckets['acceptable'] }}</span>
                <span class="text-[10px] text-muted">Acceptable grace window</span>
            </div>
            <div class="bg-surface rounded-card border border-border p-6 shadow-sm flex flex-col justify-between">
                <span class="text-xs font-bold text-muted uppercase">Breached (&gt;30m)</span>
                <span class="text-3xl font-black text-danger">{{ $buckets['breached'] }}</span>
                <span class="text-[10px] text-muted">Immediate escalation needed</span>
            </div>
        </div>
    </div>

    <!-- Breached Leads Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h3 class="text-base font-bold text-ink">Breached & Delayed Lead Operations</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Lead Code</th>
                        <th class="py-3 px-4">Lead Name</th>
                        <th class="py-3 px-4">Assigned To</th>
                        <th class="py-3 px-4">Ingested At</th>
                        <th class="py-3 px-4">Minutes Over Target</th>
                        <th class="py-3 px-4">Current SLA Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($breachedLeads as $row)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-bold text-ink">{{ $row['lead_code'] }}</td>
                            <td class="py-3 px-4 font-semibold">{{ $row['name'] }}</td>
                            <td class="py-3 px-4 text-muted">{{ $row['assigned_name'] }}</td>
                            <td class="py-3 px-4 text-muted">{{ $row['created_at'] }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-danger">+{{ $row['minutes_over'] }} mins</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill {{ $row['is_unresponded'] ? 'bg-red-100 text-red-800 animate-pulse' : 'bg-amber-50 text-amber-800' }}">
                                    {{ $row['status_label'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-muted">No breached leads in queue! All response SLAs met.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $breachedLeads->links('vendor.pagination.tailwind') }}
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            const ctx = document.getElementById('slaDonutChart')?.getContext('2d');
            if (ctx) {
                const b = @json($buckets);
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Excellent', 'Acceptable', 'Breached'],
                        datasets: [{
                            data: [b.excellent, b.acceptable, b.breached],
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
        });
    </script>
</div>
