<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Lead Replacement Queue</h1>
            <p class="text-xs text-muted">Review, approve, or reject lead replacement claims.</p>
        </div>
    </div>



    <!-- Rejection Note Modal -->
    @if($showRejectModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-surface rounded-card border border-border p-6 max-w-md w-full shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <h3 class="text-base font-bold text-ink">Reject Replacement Claim</h3>
                    <button wire:click="closeRejectModal" class="text-muted hover:text-ink">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div>
                    <label class="text-xs font-bold text-ink">Rejection Reason / Note <span class="text-danger">*</span></label>
                    <textarea wire:model="rejectionNote" placeholder="Explain why this claim is rejected (required)..." class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-1 h-24"></textarea>
                    @error('rejectionNote') <span class="text-[10px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-border">
                    <x-ui.button wire:click="closeRejectModal" variant="secondary" class="text-xs">Cancel</x-ui.button>
                    <x-ui.button wire:click="confirmReject" variant="danger" class="text-xs">Confirm Rejection</x-ui.button>
                </div>
            </div>
        </div>
    @endif

    <!-- Queue Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <!-- Filters -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-bold text-ink">Replacement Requests</h2>

            <div class="flex flex-wrap items-center gap-3">
                <livewire:shared.searchable-select 
                    :model="\App\Models\Client::class"
                    placeholder="All Clients"
                    wire:model.live="filterClient"
                    key="filter-client"
                />

                <livewire:shared.searchable-select 
                    :model="\App\Models\Project::class"
                    placeholder="All Projects"
                    wire:model.live="filterProject"
                    key="filter-project"
                />

                <x-ui.themed-select 
                    wire:model.live="filterStatus"
                    :options="['' => 'All Statuses', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']"
                    placeholder="All Statuses"
                />

                <x-ui.themed-select 
                    wire:model.live="filterDateRange"
                    :options="['' => 'All Time', 'today' => 'Today', 'week' => 'Last 7 Days', 'month' => 'Last 30 Days']"
                    placeholder="All Time"
                />

                <x-ui.export-button target="exportExcel" />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Original Lead</th>
                        <th class="py-3 px-4">Reason</th>
                        <th class="py-3 px-4">Requested By</th>
                        <th class="py-3 px-4">Requested At</th>
                        <th class="py-3 px-4">SLA Met</th>
                        <th class="py-3 px-4">Eligible</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
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
                            <td class="py-3 px-4 text-muted">{{ $item->requestedBy?->name ?: 'System Agent' }}</td>
                            <td class="py-3 px-4 text-muted">{{ \Carbon\Carbon::parse($item->requested_at)->format('M d, H:i') }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill {{ $item->sla_met ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                    {{ $item->sla_met ? 'SLA Met' : 'Missed' }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill {{ $item->reason?->is_eligible ? 'bg-purple-50 text-purple-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $item->reason?->is_eligible ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill {{ $item->status === 'approved' ? 'bg-green-50 text-green-700' : ($item->status === 'rejected' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700') }}">
                                    {{ ucfirst($item->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                @if($item->status === 'pending')
                                    <div class="flex items-center justify-end space-x-2">
                                        <x-ui.button wire:click="approveReplacement({{ $item->id }})" variant="primary" class="text-[10px] px-2 py-1">Approve</x-ui.button>
                                        <x-ui.button wire:click="openRejectModal({{ $item->id }})" variant="danger" class="text-[10px] px-2 py-1">Reject</x-ui.button>
                                    </div>
                                @else
                                    <span class="text-[10px] text-muted italic">Resolved</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-muted">No replacement requests in queue.</td>
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
