<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-ink">Sales Executive Performance Leaderboard</h2>
            <p class="text-xs text-muted">Comparative breakdown: Assigned volume, SLA response rate, site visits, and closed bookings.</p>
        </div>
        <x-ui.export-button target="exportExcel" />
    </div>

    <!-- Leaderboard Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4 cursor-pointer" wire:click="sortBy('executive_name')">
                            Executive Name
                            @if($sortField === 'executive_name') {{ $sortDirection === 'asc' ? '↑' : '↓' }} @endif
                        </th>
                        <th class="py-3 px-4 cursor-pointer" wire:click="sortBy('assigned_leads')">
                            Leads Assigned
                            @if($sortField === 'assigned_leads') {{ $sortDirection === 'asc' ? '↑' : '↓' }} @endif
                        </th>
                        <th class="py-3 px-4 cursor-pointer" wire:click="sortBy('sla_contacted_rate')">
                            Contacted Within SLA (%)
                            @if($sortField === 'sla_contacted_rate') {{ $sortDirection === 'asc' ? '↑' : '↓' }} @endif
                        </th>
                        <th class="py-3 px-4 cursor-pointer" wire:click="sortBy('site_visits')">
                            Site Visits Logged
                            @if($sortField === 'site_visits') {{ $sortDirection === 'asc' ? '↑' : '↓' }} @endif
                        </th>
                        <th class="py-3 px-4 cursor-pointer" wire:click="sortBy('bookings_closed')">
                            Bookings Closed
                            @if($sortField === 'bookings_closed') {{ $sortDirection === 'asc' ? '↑' : '↓' }} @endif
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($executives as $index => $row)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-bold text-ink flex items-center space-x-2">
                                <span class="w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-black {{ $index === 0 ? 'bg-amber-100 text-amber-800' : ($index === 1 ? 'bg-gray-200 text-gray-800' : ($index === 2 ? 'bg-amber-50 text-amber-700' : 'bg-canvas text-muted')) }}">
                                    {{ $index + 1 }}
                                </span>
                                <span>{{ $row['executive_name'] }}</span>
                            </td>
                            <td class="py-3 px-4 font-mono font-semibold">{{ number_format($row['assigned_leads']) }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-success">{{ $row['sla_contacted_rate'] }}%</td>
                            <td class="py-3 px-4 font-mono font-semibold">{{ number_format($row['site_visits']) }}</td>
                            <td class="py-3 px-4 font-mono font-black text-ink text-sm">{{ number_format($row['bookings_closed']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
