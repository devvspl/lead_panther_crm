<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Bulk Upload History & Audit Log</h1>
            <p class="text-xs text-muted">Auditable record of all CSV/Excel lead imports with duplicate and error metrics.</p>
        </div>

        <div class="flex items-center space-x-3">
            <x-ui.export-button target="exportExcel" />
            <a href="{{ route('leads.upload') }}" class="px-3 py-1.5 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary-hover transition shadow-sm">
                + New Bulk Import
            </a>
        </div>
    </div>

    <!-- Batches Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Batch ID</th>
                        <th class="py-3 px-4">Filename</th>
                        <th class="py-3 px-4">Uploaded By</th>
                        <th class="py-3 px-4">Target Project</th>
                        <th class="py-3 px-4">Total</th>
                        <th class="py-3 px-4">Imported</th>
                        <th class="py-3 px-4">Skipped</th>
                        <th class="py-3 px-4">Failed</th>
                        <th class="py-3 px-4">Timestamp</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($batches as $b)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-ink">#{{ $b->id }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-ink">{{ $b->filename }}</td>
                            <td class="py-3 px-4 font-medium text-ink">{{ $b->uploader?->name ?: 'System' }}</td>
                            <td class="py-3 px-4 font-medium text-muted">{{ $b->project?->name ?: 'N/A' }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-ink">{{ $b->total_rows }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-emerald-600">{{ $b->imported_count }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-amber-600">{{ $b->skipped_count }}</td>
                            <td class="py-3 px-4 font-mono font-bold text-red-600">{{ $b->failed_count }}</td>
                            <td class="py-3 px-4 font-mono text-muted whitespace-nowrap">{{ $b->created_at->format('Y-m-d H:i') }}</td>
                            <td class="py-3 px-4 text-right">
                                @if($b->failed_count > 0)
                                    <x-ui.button wire:click="downloadErrorCsv({{ $b->id }})" variant="secondary" class="text-[10px]">
                                        Error CSV
                                    </x-ui.button>
                                @else
                                    <span class="text-[10px] text-muted font-semibold">Clean Import</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-6 text-center text-muted">No bulk upload batches recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $batches->links('vendor.pagination.tailwind') }}
        </div>
    </div>
</div>
