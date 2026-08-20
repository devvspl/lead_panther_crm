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



    <!-- Failed Jobs Table Component -->
    <div class="space-y-2">
        <x-ui.advanced-table 
            :columns="$this->tableColumns()"
            :rows="$failedJobs"
            :visibleColumns="$visibleColumns"
            :sortField="$sortField"
            :sortDirection="$sortDirection"
            :showFilterDropdown="false"
            searchPlaceholder="Search failed jobs by queue, payload, error..."
            emptyTitle="No Failed Queue Jobs"
            emptyMessage="All background workers and queue jobs are operating normally."
        />
    </div>
</div>
