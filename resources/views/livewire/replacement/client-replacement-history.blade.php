<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">My Lead Replacements</h1>
            <p class="text-xs text-muted">View replacement claims, SLA status, and resolution outcomes for your leads.</p>
        </div>
        <x-ui.export-button target="exportExcel" />
    </div>

    <!-- History Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Original Lead</th>
                        <th class="py-3 px-4">Reason</th>
                        <th class="py-3 px-4">Requested At</th>
                        <th class="py-3 px-4">SLA Status</th>
                        <th class="py-3 px-4">Outcome</th>
                        <th class="py-3 px-4">Replacement Lead / Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($replacements as $item)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-bold text-ink">
                                <div>{{ $item->lead?->name }}</div>
                                <div class="text-[10px] text-muted">{{ $item->lead?->lead_code }}</div>
                            </td>
                            <td class="py-3 px-4">{{ $item->reason?->label }}</td>
                            <td class="py-3 px-4 text-muted">{{ \Carbon\Carbon::parse($item->requested_at)->format('M d, Y H:i') }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill {{ $item->sla_met ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                    {{ $item->sla_met ? 'SLA Met (≤30m)' : 'SLA Missed' }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill {{ $item->status === 'approved' ? 'bg-green-50 text-green-700' : ($item->status === 'rejected' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700') }}">
                                    {{ ucfirst($item->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                @if($item->status === 'approved' && $item->replacementLead)
                                    <span class="font-bold text-success">New Lead: {{ $item->replacementLead->lead_code }}</span>
                                @elseif($item->status === 'rejected')
                                    <span class="text-danger italic">Reason: {{ $item->resolution_note ?: 'Ineligible per SLA policy' }}</span>
                                @else
                                    <span class="text-muted">Under Review</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-muted">No replacement requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $replacements->links('vendor.pagination.tailwind') }}
        </div>
    </div>
</div>
