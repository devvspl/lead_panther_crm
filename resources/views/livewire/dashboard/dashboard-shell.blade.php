<div wire:poll.30s class="space-y-6">
    <!-- Stat Cards Grid (Row of 4) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        @foreach ($stats as $stat)
            <livewire:dashboard.stat-card
                :title="$stat['title']"
                :label="$stat['label']"
                :value="$stat['value']"
                :delta="$stat['delta']"
                :isPositive="$stat['isPositive']"
                :icon="$stat['icon']"
                :key="$stat['title']"
            />
        @endforeach
    </div>

    <!-- Main Section: Chart + Upcoming Panel -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Revenue & Lead Volume Line Chart (2 Cols) -->
        <div class="lg:col-span-2">
            <x-ui.card title="Lead Volume &amp; Revenue Velocity (6 Months)">
                <div class="relative h-72 w-full">
                    <canvas id="dashboardRevenueChart"></canvas>
                </div>
            </x-ui.card>
        </div>

        <!-- Upcoming Panel (1 Col: Next 5 follow-ups/meetings/site visits) -->
        <div>
            <x-ui.card title="Upcoming Schedule">
                <div class="divide-y divide-border">
                    @foreach ($upcomingEvents as $event)
                        <div class="py-3 first:pt-0 last:pb-0 flex items-start justify-between">
                            <div class="space-y-1">
                                <div class="text-xs font-semibold text-ink">{{ $event['title'] }}</div>
                                <div class="text-[11px] text-muted">{{ $event['project'] }} &bull; <span class="font-medium text-ink">{{ $event['due_time'] }}</span></div>
                            </div>
                            <x-ui.badge :status="$event['status']" />
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </div>
    </div>

    <!-- Recent System Activity Feed (Last 10 Audit Log Entries) -->
    <x-ui.card title="Recent Audit Stream">
        <div class="space-y-3">
            @foreach ($auditLogs as $index => $log)
                <div class="flex items-center space-x-3 text-xs py-1 border-b border-border last:border-0">
                    <div class="h-6 w-6 rounded-full bg-canvas border border-border flex items-center justify-center font-bold text-muted flex-shrink-0 text-[10px]">
                        {{ $index + 1 }}
                    </div>
                    <div class="text-ink flex-1 leading-relaxed">
                        {{ $log }}
                    </div>
                    <span class="text-[10px] text-muted flex-shrink-0">Just now</span>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    <!-- Chart.js Safe Initialization Script -->
    <script>
        function renderDashboardRevenueChart() {
            const chartData = @json($chartData);
            window.initSafeChart('dashboardRevenueChart', {
                type: 'line',
                data: {
                    labels: chartData.labels || [],
                    datasets: [
                        {
                            label: 'Lead Ingestion',
                            data: chartData.leads || [],
                            borderColor: '#0A0A0A',
                            backgroundColor: 'rgba(10, 10, 10, 0.05)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3,
                            pointBackgroundColor: '#0A0A0A',
                        },
                        {
                            label: 'Closed Revenue (₹ Lacs)',
                            data: chartData.revenue || [],
                            borderColor: '#6B7280',
                            borderWidth: 2,
                            borderDash: [4, 4],
                            fill: false,
                            tension: 0.3,
                            pointRadius: 3,
                            pointBackgroundColor: '#6B7280',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                font: { family: 'Inter', size: 11 },
                                color: '#6B7280',
                                boxWidth: 12
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: '#F5F5F5' },
                            ticks: { font: { family: 'Inter', size: 11 }, color: '#6B7280' }
                        },
                        y: {
                            grid: { color: '#E5E7EB' },
                            ticks: { font: { family: 'Inter', size: 11 }, color: '#6B7280' }
                        }
                    }
                }
            });
        }

        document.addEventListener('livewire:navigated', renderDashboardRevenueChart);
        document.addEventListener('DOMContentLoaded', renderDashboardRevenueChart);
        document.addEventListener('livewire:initialized', renderDashboardRevenueChart);
    </script>
</div>
