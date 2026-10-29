<div wire:poll.30s class="space-y-6">
    <!-- Role-Tailored Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b border-border">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight">{{ $headerInfo['title'] }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $headerInfo['badgeClass'] }}">
                    {{ $headerInfo['roleBadge'] }}
                </span>
            </div>
            <p class="text-xs text-muted mt-1">{{ $headerInfo['subtitle'] }}</p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">
            <span class="inline-flex items-center space-x-1.5 px-3 py-1.5 bg-surface border border-border rounded-full text-xs font-semibold text-ink shadow-2xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Live 30s Polling</span>
            </span>
        </div>
    </div>

    <!-- Quick Action Shortcuts Bar -->
    @if(!empty($quickActions))
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
            <span class="text-xs font-bold text-muted uppercase tracking-wider shrink-0 mr-1">Quick Actions:</span>
            @foreach($quickActions as $act)
                <a href="{{ $act['url'] }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition shrink-0 cursor-pointer {{ ($act['primary'] ?? false) ? 'bg-ink text-white hover:bg-neutral-800 shadow-2xs' : 'bg-surface border border-border text-ink hover:bg-canvas' }}">
                    @if(($act['icon'] ?? '') === 'upload')
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    @elseif(($act['icon'] ?? '') === 'leads')
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    @elseif(($act['icon'] ?? '') === 'wallet')
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    @elseif(($act['icon'] ?? '') === 'team' || ($act['icon'] ?? '') === 'users')
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    @elseif(($act['icon'] ?? '') === 'building')
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    @elseif(($act['icon'] ?? '') === 'reports')
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    @else
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    @endif
                    <span>{{ $act['label'] }}</span>
                </a>
            @endforeach
        </div>
    @endif

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
                :key="$stat['title'] . '-' . $stat['value']"
            />
        @endforeach
    </div>

    <!-- Main Section: Chart + Upcoming Panel -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Revenue & Lead Volume Line Chart (2 Cols) -->
        <div class="lg:col-span-2">
            <x-ui.card title="Lead Volume & Velocity Trends (6 Months)">
                <div class="relative h-72 w-full">
                    <canvas id="dashboardRevenueChart"></canvas>
                </div>
            </x-ui.card>
        </div>

        <!-- Upcoming Panel (1 Col: Next 5 follow-ups/meetings/site visits) -->
        <div>
            <x-ui.card title="Upcoming Schedule & Follow-ups">
                <div class="divide-y divide-border">
                    @forelse ($upcomingEvents as $event)
                        <div class="py-3 first:pt-0 last:pb-0 flex items-start justify-between">
                            <div class="space-y-1">
                                <div class="text-xs font-semibold text-ink">{{ $event['title'] }}</div>
                                <div class="text-[11px] text-muted">{{ $event['project'] }} &bull; <span class="font-medium text-ink">{{ $event['due_time'] }}</span></div>
                            </div>
                            <x-ui.badge :status="$event['status']" />
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-muted">
                            No immediate follow-ups scheduled.
                        </div>
                    @endforelse
                </div>  
            </x-ui.card>
        </div>
    </div>

    <!-- Recent Activity Feed -->
    <x-ui.card title="{{ $role === 'admin' ? 'Recent System Audit Stream' : 'Recent Lead Activity Stream' }}">
        <div class="space-y-3">
            @forelse ($auditLogs as $index => $log)
                <div class="flex items-center space-x-3 text-xs py-1 border-b border-border last:border-0">
                    <div class="h-6 w-6 rounded-full bg-canvas border border-border flex items-center justify-center font-bold text-muted flex-shrink-0 text-[10px]">
                        {{ $index + 1 }}
                    </div>
                    <div class="text-ink flex-1 leading-relaxed">
                        {!! $log !!}
                    </div>
                    <span class="text-[10px] text-muted flex-shrink-0">Recent</span>
                </div>
            @empty
                <div class="py-4 text-center text-xs text-muted">
                    No recent activity records found.
                </div>
            @endforelse
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
