<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1">
                <span>Leads &amp; Pipeline</span>
                <span>/</span>
                <span class="text-ink font-semibold">Bulk Import History</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                <svg class="w-6 h-6 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <span>Bulk Upload History &amp; Audit Log</span>
            </h1>
            <p class="text-xs text-muted">Auditable record of all CSV/Excel lead imports with duplicate and error metrics.</p>
        </div>
    </div>

    @if(session()->has('error'))
        <div class="p-3.5 bg-red-50 border border-red-200 rounded-lg flex items-center justify-between text-xs text-red-700">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif
    @if(session()->has('success'))
        <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center justify-between text-xs text-emerald-700">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- Advanced Table Component -->
    <x-ui.advanced-table 
        :columns="$this->tableColumns()"
        :rows="$batches"
        :quickFilters="$this->quickFilters()"
        :activeStatus="$statusFilter"
        :visibleColumns="$visibleColumns"
        :sortField="$sortField"
        :sortDirection="$sortDirection"
        :filterCount="$this->activeFilterCount"
        searchPlaceholder="Search filename, uploaded by, or target project..."
        emptyTitle="No Upload Batches Found"
        emptyMessage="No bulk lead import batches match your current search and filters."
    >
        <!-- Primary Action Slot -->
        <x-slot:action>
            <div class="flex items-center gap-2">
                <x-ui.export-button target="exportExcel" class="text-xs" />
                <a 
                    href="{{ route('leads.upload') }}" 
                    wire:navigate
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition shadow-xs cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    <span>New Bulk Import</span>
                </a>
            </div>
        </x-slot:action>
    </x-ui.advanced-table>
</div>