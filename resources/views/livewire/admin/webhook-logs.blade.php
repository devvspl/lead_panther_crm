<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Inbound Webhook Logs Inspector</h1>
            <p class="text-xs text-muted">Audit raw incoming payloads from Meta, Google, 99acres, MagicBricks, and direct landing portals.</p>
        </div>
    </div>



    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-bold text-ink">Raw Webhook Log History</h2>

            <div class="flex items-center space-x-3">
                <x-ui.themed-select 
                    wire:model.live="filterStatus"
                    :options="['' => 'All Statuses', 'processed' => 'Processed Cleanly', 'failed' => 'Failed / Errors', 'pending' => 'Pending']"
                    placeholder="All Statuses"
                />

                <x-ui.export-button target="exportExcel" />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Log ID</th>
                        <th class="py-3 px-4">Portal Account</th>
                        <th class="py-3 px-4">Received At</th>
                        <th class="py-3 px-4">Raw Payload Sample</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($logs as $log)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-bold text-ink">#{{ $log->id }}</td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-ink">{{ $log->portalAccount?->name ?: 'Portal Account #' . $log->portal_account_id }}</span>
                                <div class="text-[10px] text-muted uppercase">{{ $log->portalAccount?->type ?: 'webhook' }}</div>
                            </td>
                            <td class="py-3 px-4 text-muted">{{ \Carbon\Carbon::parse($log->received_at)->format('M d, H:i:s') }}</td>
                            <td class="py-3 px-4 font-mono text-[10px] text-muted max-w-xs truncate">
                                {{ Str::limit(is_string($log->payload) ? $log->payload : json_encode($log->payload), 60) }}
                            </td>
                            <td class="py-3 px-4">
                                @if($log->processed && !$log->error_message)
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill bg-green-50 text-green-700">Processed</span>
                                @elseif($log->error_message)
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill bg-red-50 text-red-700" title="{{ $log->error_message }}">
                                        Failed
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill bg-amber-50 text-amber-700">Pending</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <x-ui.button wire:click="retryLog({{ $log->id }})" variant="secondary" class="text-[10px] px-2 py-1">
                                    ↻ Retry Job
                                </x-ui.button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-muted">No webhook logs captured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $logs->links('vendor.pagination.tailwind') }}
        </div>
    </div>
</div>
