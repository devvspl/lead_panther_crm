<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">System Audit Logs Browser</h1>
            <p class="text-xs text-muted">Immutable system audit trail tracking user mutations, role updates, credit balance changes, and impersonation sessions.</p>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-surface rounded-card border border-border p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3 w-full">
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search action type..." 
                class="text-xs p-2.5 rounded-lg border border-border bg-canvas text-ink w-64"
            >

            <livewire:shared.searchable-select 
                :model="\App\Models\User::class"
                placeholder="All Performing Users"
                wire:model.live="selectedUserId"
                key="audit-user"
            />

            <x-ui.themed-select 
                wire:model.live="subjectType"
                :options="['' => 'All Subject Models', 'Lead' => 'Lead', 'CreditTransaction' => 'CreditTransaction', 'LeadReplacement' => 'LeadReplacement', 'User' => 'User']"
                placeholder="All Subject Models"
            />

            <x-ui.export-button target="exportExcel" />
        </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Timestamp</th>
                        <th class="py-3 px-4">User</th>
                        <th class="py-3 px-4">Action</th>
                        <th class="py-3 px-4">Subject (Type / ID)</th>
                        <th class="py-3 px-4">From → To Changes</th>
                        <th class="py-3 px-4">IP / User Agent</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($logs as $log)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 whitespace-nowrap text-muted font-mono">
                                {{ $log->created_at ? $log->created_at->format('M d, Y H:i:s') : 'N/A' }}
                            </td>
                            <td class="py-3 px-4 font-bold text-ink">
                                {{ $log->user?->name ?? 'System / Anonymous' }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill uppercase bg-purple-50 text-purple-700 border border-purple-200">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono text-muted">
                                @if($log->subject_type)
                                    <span class="font-bold text-ink">{{ class_basename($log->subject_type) }}</span> #{{ $log->subject_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-3 px-4 max-w-xs truncate text-[11px] font-mono">
                                @if($log->from_value)
                                    <span class="text-red-600 truncate block">From: {{ Str::limit($log->from_value, 50) }}</span>
                                @endif
                                @if($log->to_value)
                                    <span class="text-green-600 truncate block">To: {{ Str::limit($log->to_value, 50) }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-muted text-[10px] font-mono">
                                <div>{{ $log->ip_address ?? '127.0.0.1' }}</div>
                                <div class="truncate max-w-[120px]">{{ Str::limit($log->user_agent, 20) }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-muted">No audit logs found.</td>
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
