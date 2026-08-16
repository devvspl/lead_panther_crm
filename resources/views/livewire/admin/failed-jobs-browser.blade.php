<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Failed Queue Jobs Browser</h1>
            <p class="text-xs text-muted">Inspect, retry, or flush failed asynchronous queue jobs and webhooks.</p>
        </div>

        <div>
            <x-ui.button wire:click="retryAll" variant="primary" class="text-xs">
                Retry All Failed Jobs
            </x-ui.button>
        </div>
    </div>



    <!-- Failed Jobs Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Job ID</th>
                        <th class="py-3 px-4">Connection / Queue</th>
                        <th class="py-3 px-4">Payload Summary</th>
                        <th class="py-3 px-4">Exception Snippet</th>
                        <th class="py-3 px-4">Failed At</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($failedJobs as $job)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-ink">#{{ $job->id }}</td>
                            <td class="py-3 px-4 font-mono text-muted">
                                <span class="font-bold text-ink">{{ $job->connection }}</span> / {{ $job->queue }}
                            </td>
                            <td class="py-3 px-4 max-w-xs truncate font-mono text-[11px]">
                                {{ Str::limit($job->payload, 60) }}
                            </td>
                            <td class="py-3 px-4 max-w-xs text-red-600 font-mono text-[10px] truncate">
                                {{ Str::limit($job->exception, 80) }}
                            </td>
                            <td class="py-3 px-4 font-mono text-muted whitespace-nowrap">
                                {{ $job->failed_at }}
                            </td>
                            <td class="py-3 px-4 text-right space-x-2 whitespace-nowrap">
                                <button wire:click="retryJob({{ $job->id }})" class="text-xs font-bold text-primary hover:underline">
                                    Retry 🔄
                                </button>
                                <button wire:click="forgetJob({{ $job->id }})" class="text-xs font-bold text-danger hover:underline">
                                    Delete 🗑️
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-muted">No failed queue jobs found. All background workers are operating normally.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $failedJobs->links('vendor.pagination.tailwind') }}
        </div>
    </div>
</div>
