@props([
    'columns' => [],
    'rows' => null,
    'quickFilters' => [],
    'activeStatus' => 'all',
    'searchPlaceholder' => 'Search records...',
    'showSearch' => true,
    'searchModel' => 'search',
    'sortAction' => 'sortBy',
    'showFilterDropdown' => true,
    'showConfigurations' => true,
    'iconOnlyActions' => false,
    'showCheckboxes' => false,
    'stickySelection' => false,
    'selectedRows' => [],
    'selectAll' => false,
    'visibleColumns' => [],
    'sortField' => '',
    'sortDirection' => 'asc',
    'emptyMessage' => 'No records found matching your filters.',
    'emptyTitle' => 'No Records Found',
    'filterCount' => 0,
    'tableName' => 'table',
    'tableClass' => '',
    'showSkeleton' => true,
    'skeletonRows' => 5,
    'loading' => false,
    'loadingTarget' => null,
    'reorderableColumns' => false,
])

@php
    if (!\App\Support\FinancialVisibility::allowed())
        $columns = array_values(array_filter($columns, fn($column) => !\App\Support\FinancialVisibility::sensitive($column['key'])));
    $visibleCols = !empty($visibleColumns) ? $visibleColumns : array_map(fn($c) => $c['key'], $columns);
    $activeColumns = array_values(array_filter($columns, fn($c) => in_array($c['key'], $visibleCols)));
    $hasFilterConfig = isset($this) && method_exists($this, 'filterConfig') && !empty($this->filterConfig());
    $hasToolbar = $showSearch || ($showFilterDropdown && (isset($filters) || $hasFilterConfig)) || ($showConfigurations && !empty($columns)) || isset($action);
    $effectiveLoadingTarget = $loadingTarget ?: ($searchModel . ', ' . $sortAction . ', setStatusFilter, resetTableFilters, gotoPage, previousPage, nextPage, setPerPage');
@endphp

<div class="lp-data-view min-w-0 max-w-full space-y-4" x-data="{
    filterOpen: false,
    configOpen: false,
    activeActionMenu: null,
    filterStyle: {}, configStyle: {},
    place(which) { this[which+'Style'] = window.LeadPantherUI.position(this.$refs[which+'Trigger'],this.$refs[which+'Panel'],which==='filter'?320:280,'right'); }

}" @keydown.escape.stop="filterOpen=false; configOpen=false"
    @scroll.window.capture="if(filterOpen && !$refs.filterPanel.contains($event.target)) place('filter'); if(configOpen && !$refs.configPanel.contains($event.target)) place('config')"
    @resize.window="if(filterOpen) place('filter'); if(configOpen) place('config')"
    x-on:livewire:navigating.window="filterOpen=false; configOpen=false">
    <!-- 1. TOOLBAR: Search, Filter, Column Configuration & Add Action -->
    @if($hasToolbar)
        <div
            class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 bg-surface p-3 sm:p-4 rounded-card border border-border shadow-2xs">
            <!-- Search Input -->
            @if($showSearch)
                <div class="relative min-w-0 w-full flex-1 max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="search" name="table_search_query" id="table_search_{{ $tableName }}" autocomplete="off"
                        autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true"
                        data-form-type="other" wire:model.live.debounce.300ms="{{ $searchModel }}"
                        placeholder="{{ $searchPlaceholder }}"
                        class="w-full pl-9 pr-8 py-2 rounded-lg border border-border bg-canvas text-ink text-xs placeholder:text-muted focus:ring-2 focus:ring-primary/20 focus:border-primary transition">
                    @if(isset($this) && !empty($this->{$searchModel} ?? ''))
                        <button type="button" wire:click="$set('{{ $searchModel }}', '')"
                            class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-muted hover:text-ink cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    @endif
                </div>
            @endif

            <!-- Action Controls Group -->
            <div class="flex min-w-0 items-center gap-2 flex-wrap">
                <!-- Filter Dropdown Button & Popover -->
                @if($showFilterDropdown && (isset($filters) || $hasFilterConfig))
                    <div class="relative">
                        <button x-ref="filterTrigger" type="button" :aria-expanded="filterOpen"
                            @click="filterOpen = !filterOpen; configOpen = false; if(filterOpen) $nextTick(() => place('filter'))"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:bg-surface transition shadow-2xs cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            <span>Filter</span>
                            @if($filterCount > 0)
                                <span
                                    class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-primary text-white">{{ $filterCount }}</span>
                            @endif
                        </button>

                        <!-- Filter Popover Panel -->
                        <template x-teleport="body">
                            <div x-ref="filterPanel" :style="filterStyle" role="dialog" aria-label="Advanced filters"
                                @keydown.escape.stop="filterOpen=false; $refs.filterTrigger.focus()" x-show="filterOpen"
                                @click.outside="if(!$refs.filterTrigger.contains($event.target)) filterOpen = false" x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="fixed overflow-y-auto overscroll-contain bg-surface rounded-card border border-border shadow-xl p-4 z-[80] space-y-3">
                                <div
                                    class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-2 bg-surface pb-2 border-b border-border">
                                    <h4 class="text-xs font-bold text-ink">Custom Filters</h4><button type="button"
                                        @click="filterOpen=false; $refs.filterTrigger.focus()" title="Close filters"
                                        aria-label="Close filters" class="p-1 text-muted hover:text-ink"><x-ui.team-icon
                                            name="close" /></button>
                                    <button type="button" wire:click="resetTableFilters" @click="filterOpen = false"
                                        class="text-[11px] text-primary font-bold hover:underline cursor-pointer">
                                        Reset All
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    @if(isset($filters))
                                        {{ $filters }}
                                    @endif
                                </div>
                                <div class="sticky bottom-0 flex justify-end border-t border-border bg-surface pt-3"><button
                                        type="button" @click="filterOpen=false; $refs.filterTrigger.focus()"
                                        class="rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white">Done</button>
                                </div>
                            </div>
                        </template>
                    </div>
                @endif

                <!-- Column Configurations Dropdown -->
                @if($showConfigurations && !empty($columns))
                    <div class="relative">
                        <button x-ref="configTrigger" type="button" :aria-expanded="configOpen"
                            @click="configOpen = !configOpen; filterOpen = false; if(configOpen) $nextTick(() => place('config'))"
                            title="Configuration" aria-label="Configuration"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:bg-surface transition shadow-2xs cursor-pointer">
                            @if($iconOnlyActions)<x-ui.team-icon name="settings" />@else
                                <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                </svg>
                            @endif
                            @unless($iconOnlyActions)<span>Configuration</span>@endunless
                        </button>

                        <!-- Column Toggles Panel -->
                        <template x-teleport="body">
                            <div x-ref="configPanel" :style="configStyle" role="dialog" aria-label="Column visibility"
                                @keydown.escape.stop="configOpen=false; $refs.configTrigger.focus()" x-show="configOpen"
                                @click.outside="if(!$refs.configTrigger.contains($event.target)) configOpen = false" x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                class="fixed overflow-y-auto overscroll-contain bg-surface rounded-card border border-border shadow-xl p-3.5 z-[80] space-y-2.5">
                                <div
                                    class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-2 bg-surface pb-2 border-b border-border">
                                    <h4 class="text-xs font-bold text-ink">Column Visibility</h4>
                                    <button type="button" wire:click="resetColumns"
                                        class="text-[10px] text-primary font-bold hover:underline cursor-pointer">
                                        Reset Defaults
                                    </button>
                                </div>

                                <div class="space-y-1.5 max-h-60 overflow-y-auto">
                                    @foreach($columns as $col)
                                        @php
                                            $colKey = $col['key'];
                                            $colLabel = $col['label'] ?? ucfirst($colKey);
                                            $isChecked = in_array($colKey, $visibleCols);
                                        @endphp
                                        <div class="flex items-center gap-1">
                                            <label
                                                class="flex min-w-0 flex-1 items-center justify-between gap-2 px-2 py-1.5 rounded-lg hover:bg-canvas text-xs cursor-pointer select-none">
                                                <span class="text-ink font-medium">{{ $colLabel }}</span>
                                                <input type="checkbox" wire:click="toggleColumn('{{ $colKey }}')"
                                                    @checked($isChecked)
                                                    class="rounded text-primary focus:ring-primary h-3.5 w-3.5 cursor-pointer">
                                            </label>
                                            @if($reorderableColumns)
                                                <button type="button" wire:click="moveColumn('{{ $colKey }}', -1)"
                                                    @disabled($loop->first) title="Move {{ $colLabel }} up"
                                                    aria-label="Move {{ $colLabel }} up"
                                                    class="p-1 text-muted hover:text-primary disabled:opacity-30"><svg class="w-4 h-4"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                        aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 15l6-6 6 6" />
                                                    </svg></button>
                                                <button type="button" wire:click="moveColumn('{{ $colKey }}', 1)" @disabled($loop->last)
                                                    title="Move {{ $colLabel }} down" aria-label="Move {{ $colLabel }} down"
                                                    class="p-1 text-muted hover:text-primary disabled:opacity-30"><svg class="w-4 h-4"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                        aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                                                    </svg></button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </template>
                    </div>
                @endif

                <!-- Add New Action Slot -->
                @if(isset($action))
                    <div class="lp-table-actions min-w-0 max-w-full ml-auto sm:ml-0">
                        {{ $action }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- 2. "SHOW ONLY" QUICK-FILTER STATUS PILLS -->
    @if(!empty($quickFilters))
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none">
            <span class="text-[11px] font-bold text-muted uppercase tracking-wider mr-1 flex-shrink-0">Show only:</span>
            @foreach($quickFilters as $pill)
                @php
                    $pillKey = $pill['key'];
                    $pillLabel = $pill['label'] ?? ucfirst($pillKey);
                    $pillCount = $pill['count'] ?? null;
                    $isActive = ($activeStatus === $pillKey);
                @endphp
                <button type="button" wire:click="setStatusFilter('{{ $pillKey }}')"
                    class="px-3 py-1 rounded-pill text-xs font-semibold transition flex items-center gap-1.5 flex-shrink-0 cursor-pointer {{ $isActive ? 'bg-ink text-white font-bold shadow-xs' : 'bg-surface text-muted border border-border hover:text-ink hover:border-ink/40' }}">
                    <span>{{ $pillLabel }}</span>
                    @if($pillCount !== null)
                        <span
                            class="px-1.5 py-0.2 text-[10px] rounded-full {{ $isActive ? 'bg-surface/20 text-white' : 'bg-canvas text-muted border border-border' }}">
                            {{ $pillCount }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif

    <!-- 3. DESKTOP / TABLET DATA TABLE (Hidden below md) -->
    <div class="hidden md:block bg-surface rounded-card border border-border shadow-sm overflow-hidden">
        <div class="max-w-full overflow-x-auto overscroll-x-contain" tabindex="0" role="region"
            aria-label="Scrollable data table">
            <table class="w-full text-left text-xs text-ink border-collapse {{ $tableClass }}">
                <thead
                    class="bg-canvas border-b border-border text-[10px] uppercase font-bold text-muted tracking-wider select-none">
                    <tr>
                        @if($showCheckboxes)
                            <th
                                class="py-3 px-3.5 w-10 text-center {{ $stickySelection ? 'sticky left-0 z-20 bg-canvas' : '' }}">
                                <input type="checkbox" wire:model.live="selectAll"
                                    class="rounded text-primary focus:ring-primary h-3.5 w-3.5 cursor-pointer">
                            </th>
                        @endif

                        @foreach($activeColumns as $col)
                            @php
                                $colKey = $col['key'];
                                $colLabel = $col['label'] ?? ucfirst($colKey);
                                $isSortable = $col['sortable'] ?? false;
                                $priority = $col['priority'] ?? 1; // 1 = always, 2 = lg+, 3 = xl+
                                $priorityClass = $priority === 2 ? 'hidden lg:table-cell' : ($priority === 3 ? 'hidden xl:table-cell' : '');
                                $alignClass = ($col['align'] ?? 'left') === 'right' ? 'text-right' : (($col['align'] ?? 'left') === 'center' ? 'text-center' : 'text-left');
                                $colWidth = $col['width'] ?? null;
                                $widthStyle = $colWidth ? "width: {$colWidth};" : '';
                            @endphp
                            <th @if($colWidth) style="{{ $widthStyle }}" @endif
                                class="py-3 px-2.5 sm:px-3.5 {{ $alignClass }} {{ $priorityClass }} {{ $col['headerClass'] ?? '' }}">
                                @if($isSortable)
                                    <button type="button" wire:click="{{ $sortAction }}('{{ $colKey }}')"
                                        class="inline-flex items-center gap-1 hover:text-ink font-bold transition group cursor-pointer">
                                        <span>{{ $colLabel }}</span>
                                        <span class="text-muted group-hover:text-ink">
                                            @if($sortField === $colKey)
                                                @if($sortDirection === 'asc')
                                                    <svg class="w-3 h-3 text-primary" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M5 15l7-7 7 7" />
                                                    </svg>
                                                @else
                                                    <svg class="w-3 h-3 text-primary" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                @endif
                                            @else
                                                <svg class="w-3 h-3 opacity-30 group-hover:opacity-100" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                                </svg>
                                            @endif
                                        </span>
                                    </button>
                                @else
                                    <span>{{ $colLabel }}</span>
                                @endif
                            </th>
                        @endforeach

                        @if(isset($rowActions))
                            <th class="py-3 px-3.5 w-12 text-right">Action</th>
                        @endif
                    </tr>
                </thead>

                @if(!$loading)
                    <tbody class="divide-y divide-border" @if($showSkeleton) wire:loading.remove wire:target="{{ $effectiveLoadingTarget }}" @endif>
                        @if(!empty($rows) && count($rows) > 0)
                            @foreach($rows as $row)
                                @php
                                    $rowId = is_object($row) ? ($row->id ?? null) : ($row['id'] ?? null);
                                    $isRowSelected = $rowId && in_array((string) $rowId, $selectedRows);
                                @endphp
                                <tr wire:key="{{ $tableName }}-desktop-row-{{ $rowId ?? $loop->index }}"
                                    class="hover:bg-canvas/50 transition {{ $isRowSelected ? 'bg-primary/5' : '' }}">
                                    @if($showCheckboxes && $rowId)
                                        <td
                                            class="py-3 px-3.5 text-center {{ $stickySelection ? 'sticky left-0 z-10 bg-surface' : '' }}">
                                            <input type="checkbox" wire:click="toggleSelectRow('{{ $rowId }}')" @checked($isRowSelected)
                                                class="rounded text-primary focus:ring-primary h-3.5 w-3.5 cursor-pointer">
                                        </td>
                                    @endif

                                    @foreach($activeColumns as $col)
                                        @php
                                            $colKey = $col['key'];
                                            $type = $col['type'] ?? 'text';
                                            $priority = $col['priority'] ?? 1;
                                            $priorityClass = $priority === 2 ? 'hidden lg:table-cell' : ($priority === 3 ? 'hidden xl:table-cell' : '');
                                            $alignClass = ($col['align'] ?? 'left') === 'right' ? 'text-right' : (($col['align'] ?? 'left') === 'center' ? 'text-center' : 'text-left');
                                            $colWidth = $col['width'] ?? null;
                                            $widthStyle = $colWidth ? "width: {$colWidth};" : '';

                                            // Retrieve raw value
                                            $val = is_object($row) ? data_get($row, $colKey) : ($row[$colKey] ?? null);
                                        @endphp
                                        <td @if($colWidth) style="{{ $widthStyle }}" @endif
                                            class="py-3 px-2.5 sm:px-3.5 {{ $alignClass }} {{ $priorityClass }} {{ $col['cellClass'] ?? '' }}">
                                            @if(isset(${'cell_' . $colKey}))
                                                {{ ${'cell_' . $colKey}($row) }}
                                            @elseif($type === 'scheduled_task_actions')
                                                <div class="flex gap-2"><button type="button" wire:click="inspectTask('{{ $row->id }}')"
                                                        class="border border-border rounded-lg px-2 py-1 text-xs">Details</button><button
                                                        type="button" wire:click="runTask('{{ $row->id }}')"
                                                        wire:confirm="Run this registered CRM task now? It can process queued leads, send notifications or perform configured maintenance. Business limits and task locks still apply."
                                                        @disabled($row->busy || !$row->ready) wire:loading.attr="disabled"
                                                        class="bg-ink text-white rounded-lg px-2 py-1 text-xs disabled:opacity-50">Run
                                                        Now</button></div>
                                            @elseif($type === 'scheduled_run_actions')
                                                <button type="button" wire:click="inspectRun({{ $row->id }})"
                                                    class="border border-border rounded-lg px-2 py-1 text-xs">Inspect</button>
                                            @elseif($type === 'client_push')
                                                <button type="button" wire:click="pushLeadToClient({{ $row->id }})"
                                                    wire:confirm="Push this lead using the saved client delivery settings? Confirm that you have permission to share it. If settings are missing, the setup form will open."
                                                    wire:loading.attr="disabled"
                                                    class="px-2.5 py-1 rounded-lg border border-border text-xs font-semibold text-primary">Push</button>
                                            @elseif($type === 'delivery_item_actions')
                                                <button type="button" wire:click="inspectAttempts({{ $row->id }})"
                                                    class="px-2.5 py-1 rounded-lg border border-border text-xs font-semibold">History</button>
                                                @if($row->status === 'failed')<button type="button" wire:click="retry({{ $row->id }})"
                                                class="px-2.5 py-1 rounded-lg border border-border text-xs font-semibold">Retry</button>@elseif($row->status === 'sent')<button
                                                        type="button" wire:click="accept({{ $row->id }})"
                                                        wire:confirm="Record that the client has accepted this lead?"
                                                        class="px-2.5 py-1 rounded-lg border border-border text-xs font-semibold">Mark
                                                    Accepted</button>@else<span class="text-xs text-muted">—</span>@endif
                                            @elseif($type === 'lead_id')
                                                <button type="button"
                                                    @click.stop="$dispatch('open-lead-detail', { id: {{ is_object($row) ? $row->id : $row['id'] }} })"
                                                    class="font-mono font-bold text-ink hover:text-primary hover:underline text-left rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 cursor-pointer"
                                                    aria-label="View lead {{ $val }}">{{ $val }}</button>
                                            @elseif($type === 'badge')
                                                @php
                                                    $badgeStyle = $col['badgeStyle'] ?? null;
                                                    $badgeClass = is_callable($badgeStyle) ? $badgeStyle($val, $row) : ($col['badgeMap'][$val] ?? 'bg-canvas text-ink border border-border');
                                                    $badgeLabel = (isset($col['labelFormatter']) && is_callable($col['labelFormatter']))
                                                        ? $col['labelFormatter']($val, $row)
                                                        : ($col['labels'][$val] ?? ucfirst(str_replace('_', ' ', (string) $val)));
                                                @endphp
                                                <span
                                                    @if(is_object($row) && isset($row->first_response_sla_tooltip) && in_array($colKey, ['first_response_timing', 'first_response_time', 'sla_badge'])) title="{{ $row->first_response_sla_tooltip }}" @endif
                                                    class="px-1.5 py-0.5 rounded-pill text-[9px] leading-tight font-bold whitespace-nowrap inline-flex items-center gap-1 {{ $badgeClass }}">
                                                    {{ \App\Support\ClientFacingText::label($badgeLabel) }}
                                                </span>
                                            @elseif($type === 'avatar-stack')
                                                @php
                                                    $avatars = is_array($val) ? $val : (is_object($val) ? [$val] : []);
                                                    $maxDisplay = $col['max'] ?? 3;
                                                    $displayed = array_slice($avatars, 0, $maxDisplay);
                                                    $overflow = count($avatars) - count($displayed);
                                                @endphp
                                                <div class="flex items-center -space-x-1.5 overflow-hidden">
                                                    @forelse($displayed as $av)
                                                        @php
                                                            $avName = is_object($av) ? ($av->name ?? 'User') : ($av['name'] ?? 'User');
                                                            $avInitial = strtoupper(substr($avName, 0, 1));
                                                        @endphp
                                                        <div class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-slate-200 dark:bg-slate-700 text-ink text-[10px] font-bold border-2 border-surface"
                                                            title="{{ $avName }}">
                                                            {{ $avInitial }}
                                                        </div>
                                                    @empty
                                                        <span class="text-muted text-[11px]">{{ $col['emptyText'] ?? 'Unassigned' }}</span>
                                                    @endforelse
                                                    @if($overflow > 0)
                                                        <span
                                                            class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-canvas text-muted text-[9px] font-bold border-2 border-surface font-mono">
                                                            +{{ $overflow }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @elseif($type === 'progress')
                                                @php
                                                    $numericVal = (int) $val;
                                                    $colorClass = $numericVal >= 70 ? 'bg-emerald-500' : ($numericVal >= 40 ? 'bg-amber-500' : 'bg-slate-400');
                                                @endphp
                                                <div class="w-28 space-y-1">
                                                    <div class="flex items-center justify-between text-[10px] font-bold">
                                                        <span class="font-mono text-ink">{{ $numericVal }}%</span>
                                                    </div>
                                                    <div class="w-full bg-canvas rounded-full h-1.5 border border-border overflow-hidden">
                                                        <div class="h-1.5 rounded-full {{ $colorClass }}"
                                                            style="width: {{ min(100, max(0, $numericVal)) }}%"></div>
                                                    </div>
                                                </div>
                                            @elseif($type === 'link')
                                                @php
                                                    $linkUrl = is_callable($col['url'] ?? null) ? ($col['url'])($row) : ($val);
                                                @endphp
                                                <a href="{{ $linkUrl }}" target="_blank"
                                                    class="text-primary font-medium hover:underline inline-flex items-center gap-1">
                                                    <span>{{ $val }}</span>
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                    </svg>
                                                </a>
                                            @elseif($type === 'date')
                                                <span class="text-muted font-mono text-[11px]"
                                                    title="{{ $val ? \Carbon\Carbon::parse($val)->toDayDateTimeString() : '' }}">
                                                    {{ $val ? \Carbon\Carbon::parse($val)->format($col['format'] ?? 'M d, Y H:i') : '—' }}
                                                </span>
                                            @elseif($type === 'phone' || ($col['key'] ?? '') === 'mobile')
                                                @php
                                                    $rawPhone = $val ?? (is_object($row) ? ($row->mobile ?? '') : ($row['mobile'] ?? ''));
                                                    $digitsOnly = preg_replace('/[^0-9]/', '', (string)$rawPhone);
                                                    $waNumber = $digitsOnly;
                                                    if (strlen($waNumber) === 10) {
                                                        $waNumber = '91' . $waNumber;
                                                    }
                                                    $isValidPhone = !empty($rawPhone) && $rawPhone !== '—' && strlen($digitsOnly) >= 5;
                                                @endphp
                                                @if($isValidPhone)
                                                    <div class="flex items-center gap-1.5 whitespace-nowrap">
                                                        <a href="tel:{{ $rawPhone }}"
                                                           class="font-mono text-xs font-semibold text-ink hover:text-primary transition-colors cursor-pointer"
                                                           title="Call {{ $rawPhone }}"
                                                           @click.stop>
                                                            {{ $val ?: $rawPhone }}
                                                        </a>
                                                        <a href="https://wa.me/{{ $waNumber }}"
                                                           target="_blank"
                                                           rel="noopener noreferrer"
                                                           class="text-emerald-600 hover:text-emerald-700 p-0.5 rounded hover:bg-emerald-50 transition cursor-pointer shrink-0 inline-flex items-center"
                                                           title="Chat on WhatsApp"
                                                           @click.stop>
                                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                                            </svg>
                                                        </a>
                                                    </div>
                                                @else
                                                    <span class="text-muted font-mono text-xs">—</span>
                                                @endif
                                            @elseif($type === 'lead_actions')
                                                @php
                                                    $actionLeadId = is_object($row) ? $row->id : $row['id'];
                                                @endphp
                                                <div class="flex items-center justify-end">
                                                    <x-ui.lead-archive-menu :id="$actionLeadId" :lead="$row" />
                                                </div>
                                            @elseif($type === 'user_name')
                                                @php
                                                    $userRowId = is_object($row) ? $row->id : $row['id'];
                                                @endphp
                                                <button type="button" wire:click="openUserDetailsModal({{ $userRowId }})"
                                                    class="font-bold text-ink hover:text-primary hover:underline text-left cursor-pointer inline-flex items-center gap-1.5 group"
                                                    title="View &amp; Edit User Details">
                                                    <span>{{ $val }}</span>
                                                    <svg class="w-3.5 h-3.5 text-muted opacity-0 group-hover:opacity-100 transition-opacity"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                </button>
                                            @elseif($type === 'user_actions')
                                                @php
                                                    $userRowId = is_object($row) ? $row->id : $row['id'];
                                                @endphp
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <button type="button" wire:click="openUserDetailsModal({{ $userRowId }})"
                                                        class="p-1.5 rounded-lg border border-border bg-surface hover:bg-canvas text-ink hover:border-ink/30 transition shadow-2xs cursor-pointer inline-flex items-center justify-center"
                                                        title="View &amp; Edit User Details" aria-label="View &amp; Edit User Details">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                        </svg>
                                                    </button>

                                                    @if(auth()->id() !== $userRowId)
                                                        <a href="{{ route('admin.users.impersonate', $userRowId) }}"
                                                            class="p-1.5 rounded-lg border border-border bg-surface hover:bg-purple-50 text-purple-700 hover:border-purple-300 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                                            title="Impersonate User" aria-label="Impersonate User">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                            </svg>
                                                        </a>
                                                    @else
                                                        <span class="text-[10px] text-muted font-bold">(You)</span>
                                                    @endif
                                                </div>
                                            @elseif($type === 'org_name')
                                                @php
                                                    $orgRowId = is_object($row) ? $row->id : $row['id'];
                                                @endphp
                                                <a href="{{ route('admin.organizations.show', $orgRowId) }}" wire:navigate
                                                    class="font-bold text-ink hover:text-primary hover:underline text-left cursor-pointer inline-flex items-center gap-1.5 group"
                                                    title="View Clients Details">
                                                    <span>{{ $val }}</span>
                                                    <svg class="w-3.5 h-3.5 text-muted opacity-0 group-hover:opacity-100 transition-opacity"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                    </svg>
                                                </a>
                                            @elseif($type === 'org_actions')
                                                @php
                                                    $orgRowId = is_object($row) ? $row->id : $row['id'];
                                                @endphp
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <a href="{{ route('admin.organizations.show', $orgRowId) }}" wire:navigate
                                                        class="p-1.5 rounded-lg border border-border bg-surface hover:bg-canvas text-ink hover:border-ink/30 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                                        title="View Clients Details" aria-label="View Clients Details">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </a>

                                                    <button type="button" wire:click="openOrgDetailsModal({{ $orgRowId }})"
                                                        class="p-1.5 rounded-lg border border-border bg-surface hover:bg-canvas text-ink hover:border-ink/30 transition shadow-2xs cursor-pointer inline-flex items-center justify-center"
                                                        title="Quick Edit Organization" aria-label="Quick Edit Organization">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                        </svg>
                                                    </button>

                                                    <button type="button" wire:click="openUserModal({{ $orgRowId }})"
                                                        class="p-1.5 rounded-lg border border-border bg-surface hover:bg-primary/10 text-primary hover:border-primary/30 transition shadow-2xs cursor-pointer inline-flex items-center justify-center"
                                                        title="Manage Users" aria-label="Manage Users">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            @elseif($type === 'replacement_actions')
                                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                                    @if((is_object($row) ? $row->status : $row['status']) === 'pending')
                                                        <button type="button"
                                                            wire:click="approveReplacement({{ is_object($row) ? $row->id : $row['id'] }})"
                                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition cursor-pointer">
                                                            Approve &amp; Replace
                                                        </button>
                                                        <button type="button"
                                                            wire:click="openRejectModal({{ is_object($row) ? $row->id : $row['id'] }})"
                                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-2xs transition cursor-pointer">
                                                            Reject
                                                        </button>
                                                    @else
                                                        <button type="button"
                                                            wire:click="viewClaimLead({{ is_object($row) ? $row->id : $row['id'] }}, 'original')"
                                                            class="px-2.5 py-1 rounded-lg text-xs font-semibold border border-border bg-canvas text-ink hover:bg-surface cursor-pointer">View Original</button>
                                                        @if((is_object($row) ? $row->status : $row['status']) === 'approved')
                                                            @if(is_object($row) ? $row->replacement_lead_id : ($row['replacement_lead_id'] ?? null))
                                                                <button type="button"
                                                                    wire:click="viewClaimLead({{ is_object($row) ? $row->id : $row['id'] }}, 'replacement')"
                                                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold border border-emerald-200 bg-emerald-50 text-emerald-800 cursor-pointer">View Replacement</button>
                                                            @else
                                                                <span class="text-[11px] text-muted">No replacement linked</span>
                                                            @endif
                                                        @endif
                                                    @endif
                                                </div>
                                            @elseif($type === 'recharge_actions')
                                                <div class="flex items-center justify-end gap-1.5">
                                                    @if((is_object($row) ? $row->status : $row['status']) === 'pending')
                                                        <button type="button"
                                                            wire:click="openApproveModal({{ is_object($row) ? $row->id : $row['id'] }})"
                                                            class="px-2.5 py-1 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-2xs transition cursor-pointer">
                                                            Approve
                                                        </button>
                                                        <button type="button"
                                                            wire:click="openRejectModal({{ is_object($row) ? $row->id : $row['id'] }})"
                                                            class="px-2.5 py-1 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-2xs transition cursor-pointer">
                                                            Decline
                                                        </button>
                                                    @else
                                                        <span class="text-[11px] text-muted italic">Processed</span>
                                                    @endif
                                                </div>
                                            @elseif($type === 'webhook_actions')
                                                <div class="flex items-center justify-end">
                                                    <button type="button"
                                                        wire:click="retryLog({{ is_object($row) ? $row->id : $row['id'] }})"
                                                        class="px-2.5 py-1 text-xs font-bold text-ink bg-canvas hover:bg-neutral-200 rounded-lg border border-border transition cursor-pointer">
                                                        ↻ Retry Job
                                                    </button>
                                                </div>
                                            @elseif($type === 'upload_actions')
                                                @php
                                                    $batchId = is_object($row) ? $row->id : $row['id'];
                                                    $failedCount = is_object($row) ? ($row->failed_count ?? 0) : ($row['failed_count'] ?? 0);
                                                    $batchFilePath = is_object($row) ? ($row->file_path ?? null) : ($row['file_path'] ?? null);
                                                    $hasOriginalFile = !empty($batchFilePath) && !str_contains($batchFilePath, '..') && \Illuminate\Support\Facades\Storage::disk('local')->exists($batchFilePath);
                                                    $origName = is_object($row) ? ($row->original_filename ?? $row->filename) : ($row['original_filename'] ?? ($row['filename'] ?? ''));
                                                @endphp
                                                <div class="flex items-center justify-end gap-2 flex-wrap">
                                                    @if($failedCount > 0)
                                                        <a href="{{ route('leads.download-errors', $batchId) }}"
                                                            download="upload_errors_batch_{{ $batchId }}.csv" data-navigate-skip
                                                            wire:navigate.skip target="_blank" rel="noopener noreferrer"
                                                            class="px-2.5 py-1 bg-rose-50 border border-rose-200 hover:bg-rose-100 text-rose-700 font-bold text-[11px] rounded-lg transition shadow-2xs inline-flex items-center gap-1 cursor-pointer"
                                                            title="Download error CSV">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            <span>Error CSV</span>
                                                        </a>
                                                    @endif

                                                    @if($hasOriginalFile)
                                                        <a href="{{ route('leads.upload-batches.download', $batchId) }}" data-navigate-skip
                                                            wire:navigate.skip
                                                            class="px-2.5 py-1 bg-surface border border-border hover:bg-canvas hover:border-neutral-300 text-ink font-semibold text-[11px] rounded-lg transition shadow-2xs inline-flex items-center gap-1 cursor-pointer"
                                                            title="Download original file: {{ $origName }}">
                                                            <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                            </svg>
                                                            <span>Download</span>
                                                        </a>
                                                    @else
                                                        <span
                                                            class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-50 border border-slate-200 text-slate-400 font-medium text-[11px] rounded-lg cursor-not-allowed select-none"
                                                            title="Original file unavailable for this batch">
                                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                            </svg>
                                                            <span>Original file unavailable</span>
                                                        </span>
                                                    @endif
                                                </div>
                                            @elseif($type === 'audit_id')
                                                @php
                                                    $auditRowId = is_object($row) ? $row->id : $row['id'];
                                                @endphp
                                                <button type="button" wire:click="viewDetails({{ $auditRowId }})"
                                                    class="font-mono text-xs font-bold text-primary hover:underline hover:text-primary-dark cursor-pointer inline-flex items-center gap-1 group"
                                                    title="View Audit Log Details">
                                                    <span>#{{ $val }}</span>
                                                    <svg class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </button>
                                            @elseif($type === 'audit_actions')
                                                @php
                                                    $auditRowId = is_object($row) ? $row->id : $row['id'];
                                                @endphp
                                                <div class="flex items-center justify-end">
                                                    <button type="button" wire:click="viewDetails({{ $auditRowId }})"
                                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg border border-border bg-surface hover:bg-canvas text-ink hover:border-ink/30 transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer"
                                                        title="Inspect Details">
                                                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                        <span>Inspect</span>
                                                    </button>
                                                </div>
                                            @elseif($type === 'html' || isset($col['render']))
                                                {!! isset($col['render']) && is_callable($col['render']) ? $col['render']($row, $val, 'desktop') : $val !!}
                                            @else
                                                @php
                                                    $displayVal = $val;
                                                    if (isset($col['formatter']) && is_callable($col['formatter'])) {
                                                        $displayVal = $col['formatter']($val, $row);
                                                    }
                                                    $formattedText = ($col['prefix'] ?? '') . ($displayVal !== null && $displayVal !== '' ? $displayVal : ($col['default'] ?? '—')) . ($col['suffix'] ?? '');
                                                @endphp
                                                <span
                                                    class="{{ $col['class'] ?? 'text-ink' }}">{{ \App\Support\ClientFacingText::label($formattedText) }}</span>
                                            @endif
                                        </td>
                                    @endforeach

                                    @if(isset($rowActions))
                                        <td class="py-3 px-3.5 text-right relative">
                                            {{ $rowActions }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="{{ count($activeColumns) + ($showCheckboxes ? 1 : 0) + (isset($rowActions) ? 1 : 0) }}"
                                    class="py-12 text-center text-muted">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <svg class="w-8 h-8 opacity-40 text-muted" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <h4 class="text-sm font-bold text-ink">{{ $emptyTitle }}</h4>
                                        <p class="text-xs text-muted max-w-sm">{{ $emptyMessage }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @endif

                @if($loading || $showSkeleton)
                    {{-- Toggle visibility without overriding tbody's native table-row-group display. --}}
                    <tbody class="divide-y divide-border {{ !$loading ? 'hidden' : '' }}" @if(!$loading)
                        wire:loading.class.remove="hidden" wire:target="{{ $effectiveLoadingTarget }}"
                    @endif>
                        @for($i = 0; $i < (int) $skeletonRows; $i++)
                            @php
                                $widths = ['w-24', 'w-32', 'w-20', 'w-28', 'w-16', 'w-36'];
                            @endphp
                            <tr class="hover:bg-canvas/30 transition animate-pulse">
                                @if($showCheckboxes)
                                    <td class="py-3.5 px-3.5 text-center">
                                        <div class="w-3.5 h-3.5 mx-auto bg-slate-200 rounded"></div>
                                    </td>
                                @endif

                                @foreach($activeColumns as $colIndex => $col)
                                    @php
                                        $colKey = $col['key'];
                                        $type = $col['type'] ?? 'text';
                                        $priority = $col['priority'] ?? 1;
                                        $priorityClass = $priority === 2 ? 'hidden lg:table-cell' : ($priority === 3 ? 'hidden xl:table-cell' : '');
                                        $alignClass = ($col['align'] ?? 'left') === 'right' ? 'text-right' : (($col['align'] ?? 'left') === 'center' ? 'text-center' : 'text-left');
                                        $colWidth = $col['width'] ?? null;
                                        $widthStyle = $colWidth ? "width: {$colWidth};" : '';
                                        $itemWidth = $widths[($i + $colIndex) % count($widths)];
                                    @endphp
                                    <td @if($colWidth) style="{{ $widthStyle }}" @endif
                                        class="py-3.5 px-2.5 sm:px-3.5 {{ $alignClass }} {{ $priorityClass }}">
                                        @if($type === 'badge')
                                            <div class="inline-block h-5 w-16 bg-slate-200 rounded-pill"></div>
                                        @elseif($type === 'avatar-stack')
                                            <div
                                                class="flex items-center gap-2 {{ ($col['align'] ?? 'left') === 'center' ? 'justify-center' : (($col['align'] ?? 'left') === 'right' ? 'justify-end' : '') }}">
                                                <div class="w-6 h-6 rounded-full bg-slate-200 flex-shrink-0"></div>
                                                <div class="h-3.5 w-20 bg-slate-200 rounded"></div>
                                            </div>
                                        @elseif($type === 'progress')
                                            <div
                                                class="flex items-center gap-2 max-w-[120px] {{ ($col['align'] ?? 'left') === 'center' ? 'mx-auto' : (($col['align'] ?? 'left') === 'right' ? 'ml-auto' : '') }}">
                                                <div class="h-2 flex-1 bg-slate-200 rounded-full"></div>
                                                <div class="h-2.5 w-6 bg-slate-200 rounded"></div>
                                            </div>
                                        @elseif(in_array($type, ['lead_actions', 'user_actions', 'org_actions', 'replacement_actions', 'recharge_actions', 'webhook_actions', 'upload_actions', 'audit_actions']))
                                            <div class="flex items-center justify-end gap-1.5">
                                                <div class="w-6 h-6 rounded-lg bg-slate-200"></div>
                                                <div class="w-6 h-6 rounded-lg bg-slate-200"></div>
                                            </div>
                                        @elseif($type === 'date')
                                            <div class="inline-block h-3.5 w-20 bg-slate-200 rounded"></div>
                                        @else
                                            <div class="inline-block h-3.5 {{ $itemWidth }} bg-slate-200 rounded"></div>
                                        @endif
                                    </td>
                                @endforeach

                                @if(isset($rowActions))
                                    <td class="py-3.5 px-3.5 text-right">
                                        <div class="inline-block w-5 h-5 bg-slate-200 rounded"></div>
                                    </td>
                                @endif
                            </tr>
                        @endfor
                    </tbody>
                @endif
            </table>
        </div>
    </div>

    <!-- 4. MOBILE RESPONSIVE CARD STACK (Reflow on < md) -->
    @if(!$loading)
        <div class="block md:hidden">
            <div class="space-y-3" @if($showSkeleton) wire:loading.remove wire:target="{{ $effectiveLoadingTarget }}" @endif>
                @if(!empty($rows) && count($rows) > 0)
                    @foreach($rows as $row)
                        @php
                            $rowId = is_object($row) ? ($row->id ?? null) : ($row['id'] ?? null);
                            $isRowSelected = $rowId && in_array((string) $rowId, $selectedRows);
                        @endphp
                        <div wire:key="{{ $tableName }}-mobile-row-{{ $rowId ?? $loop->index }}"
                            class="bg-surface rounded-card border border-border p-4 shadow-sm space-y-3 {{ $isRowSelected ? 'border-primary bg-primary/5' : '' }}">
                            <!-- Card Top Row: Checkbox, Primary Title & Action Menu -->
                            <div class="flex items-start justify-between gap-2 pb-2 border-b border-border">
                                <div class="flex items-center gap-2 min-w-0">
                                    @if($showCheckboxes && $rowId)
                                        <input type="checkbox" wire:click="toggleSelectRow('{{ $rowId }}')" @checked($isRowSelected)
                                            class="rounded text-primary focus:ring-primary h-4 w-4 cursor-pointer">
                                    @endif

                                    <div>
                                        @php
                                            $primaryCol = $activeColumns[0] ?? null;
                                            $primaryVal = $primaryCol ? (is_object($row) ? data_get($row, $primaryCol['key']) : ($row[$primaryCol['key']] ?? null)) : 'Item';
                                        @endphp
                                        @if(($primaryCol['type'] ?? '') === 'lead_id')
                                            <button type="button" @click.stop="$dispatch('open-lead-detail', { id: {{ $rowId }} })"
                                                class="font-mono font-bold text-sm text-ink hover:text-primary hover:underline text-left rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 cursor-pointer"
                                                aria-label="View lead {{ $primaryVal }}">{{ $primaryVal }}</button>
                                        @else
                                            <div class="font-bold text-sm text-ink">{{ $primaryVal }}</div>
                                        @endif
                                    </div>
                                </div>

                                @if(isset($rowActions))
                                    <div>
                                        {{ $rowActions }}
                                    </div>
                                @endif
                            </div>

                            <!-- Card Body Key-Value Attributes Grid -->
                            <div class="grid grid-cols-2 gap-2.5 text-xs">
                                @foreach(array_slice($activeColumns, 1) as $col)
                                    @php
                                        $colKey = $col['key'];
                                        $colLabel = $col['label'] ?? ucfirst($colKey);
                                        $type = $col['type'] ?? 'text';
                                        $val = is_object($row) ? data_get($row, $colKey) : ($row[$colKey] ?? null);
                                    @endphp
                                    <div
                                        class="min-w-0 break-words space-y-0.5 {{ $col['mobileClass'] ?? '' }} {{ in_array($type, ['lead_actions', 'user_actions', 'org_actions', 'replacement_actions', 'recharge_actions', 'webhook_actions', 'upload_actions']) ? 'col-span-2 pt-2 border-t border-border flex justify-end' : '' }}">
                                        @if(!in_array($type, ['lead_actions', 'user_actions', 'org_actions', 'replacement_actions', 'recharge_actions', 'webhook_actions', 'upload_actions']))
                                            <span
                                                class="text-[10px] uppercase font-bold text-muted tracking-wider block">{{ $colLabel }}</span>
                                        @endif
                                        <div class="lp-mobile-value min-w-0 max-w-full">
                                            @if(isset(${'cell_' . $colKey}))
                                                {{ ${'cell_' . $colKey} }}
                                            @elseif($type === 'scheduled_task_actions')
                                                <div class="flex gap-2"><button type="button" wire:click="inspectTask('{{ $row->id }}')"
                                                        class="border border-border rounded-lg px-2 py-1 text-xs">Details</button><button
                                                        type="button" wire:click="runTask('{{ $row->id }}')"
                                                        wire:confirm="Run this registered CRM task now? It can process queued leads, send notifications or perform configured maintenance. Business limits and task locks still apply."
                                                        @disabled($row->busy || !$row->ready) wire:loading.attr="disabled"
                                                        class="bg-ink text-white rounded-lg px-2 py-1 text-xs disabled:opacity-50">Run
                                                        Now</button></div>
                                            @elseif($type === 'scheduled_run_actions')
                                                <button type="button" wire:click="inspectRun({{ $row->id }})"
                                                    class="border border-border rounded-lg px-2 py-1 text-xs">Inspect</button>
                                            @elseif($type === 'client_push')
                                                <button type="button" wire:click="pushLeadToClient({{ $row->id }})"
                                                    wire:confirm="Push this lead using the saved client delivery settings? Confirm that you have permission to share it. If settings are missing, the setup form will open."
                                                    wire:loading.attr="disabled"
                                                    class="px-2.5 py-1 rounded-lg border border-border text-xs font-semibold text-primary">Push</button>
                                            @elseif($type === 'delivery_item_actions')
                                                <div class="flex flex-wrap gap-2"><button type="button"
                                                        wire:click="inspectAttempts({{ $row->id }})"
                                                        class="px-2.5 py-1 rounded-lg border border-border text-xs font-semibold">History</button>@if($row->status === 'failed')<button
                                                            type="button" wire:click="retry({{ $row->id }})"
                                                        class="px-2.5 py-1 rounded-lg border border-border text-xs font-semibold">Retry</button>@elseif($row->status === 'sent')<button
                                                            type="button" wire:click="accept({{ $row->id }})"
                                                            wire:confirm="Record client acceptance?"
                                                            class="px-2.5 py-1 rounded-lg border border-border text-xs font-semibold">Mark
                                                        Accepted</button>@endif</div>
                                            @elseif($type === 'lead_id')
                                                <button type="button" @click.stop="$dispatch('open-lead-detail', { id: {{ $rowId }} })"
                                                    class="font-mono font-bold text-ink hover:text-primary hover:underline text-left rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 cursor-pointer"
                                                    aria-label="View lead {{ $val }}">{{ $val }}</button>
                                            @elseif($type === 'badge')
                                                @php
                                                    $badgeStyle = $col['badgeStyle'] ?? null;
                                                    $badgeClass = is_callable($badgeStyle) ? $badgeStyle($val, $row) : ($col['badgeMap'][$val] ?? 'bg-canvas text-ink border border-border');
                                                    $badgeLabel = (isset($col['labelFormatter']) && is_callable($col['labelFormatter']))
                                                        ? $col['labelFormatter']($val, $row)
                                                        : ($col['labels'][$val] ?? ucfirst(str_replace('_', ' ', (string) $val)));
                                                @endphp
                                                <span
                                                    @if(is_object($row) && isset($row->first_response_sla_tooltip) && in_array($colKey, ['first_response_timing', 'first_response_time', 'sla_badge'])) title="{{ $row->first_response_sla_tooltip }}" @endif
                                                    class="px-1.5 py-0.5 rounded-pill text-[9px] leading-tight font-bold whitespace-nowrap inline-flex items-center gap-1 {{ $badgeClass }}">
                                                    {{ \App\Support\ClientFacingText::label($badgeLabel) }}
                                                </span>
                                            @elseif($type === 'avatar-stack')
                                                @php
                                                    $avatars = is_array($val) ? $val : (is_object($val) ? [$val] : []);
                                                @endphp
                                                <span
                                                    class="font-medium text-ink">{{ !empty($avatars) ? (is_object($avatars[0]) ? $avatars[0]->name : ($avatars[0]['name'] ?? 'Assigned')) : 'Unassigned' }}</span>
                                            @elseif($type === 'progress')
                                                <span class="font-mono font-bold text-ink">{{ (int) $val }}%</span>
                                            @elseif($type === 'date')
                                                <span
                                                    class="font-mono text-muted text-[11px]">{{ $val ? \Carbon\Carbon::parse($val)->format('M d, Y') : '—' }}</span>
                                            @elseif($type === 'phone' || ($col['key'] ?? '') === 'mobile')
                                                @php
                                                    $rawPhone = $val ?? (is_object($row) ? ($row->mobile ?? '') : ($row['mobile'] ?? ''));
                                                    $digitsOnly = preg_replace('/[^0-9]/', '', (string)$rawPhone);
                                                    $waNumber = $digitsOnly;
                                                    if (strlen($waNumber) === 10) {
                                                        $waNumber = '91' . $waNumber;
                                                    }
                                                    $isValidPhone = !empty($rawPhone) && $rawPhone !== '—' && strlen($digitsOnly) >= 5;
                                                @endphp
                                                @if($isValidPhone)
                                                    <div class="flex items-center gap-1.5 whitespace-nowrap">
                                                        <a href="tel:{{ $rawPhone }}"
                                                           class="font-mono text-xs font-semibold text-ink hover:text-primary transition-colors cursor-pointer"
                                                           title="Call {{ $rawPhone }}"
                                                           @click.stop>
                                                            {{ $val ?: $rawPhone }}
                                                        </a>
                                                        <a href="https://wa.me/{{ $waNumber }}"
                                                           target="_blank"
                                                           rel="noopener noreferrer"
                                                           class="text-emerald-600 hover:text-emerald-700 p-0.5 rounded hover:bg-emerald-50 transition cursor-pointer shrink-0 inline-flex items-center"
                                                           title="Chat on WhatsApp"
                                                           @click.stop>
                                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                                            </svg>
                                                        </a>
                                                    </div>
                                                @else
                                                    <span class="text-muted font-mono text-xs">—</span>
                                                @endif
                                            @elseif($type === 'lead_actions')
                                                @php
                                                    $actionLeadIdMob = is_object($row) ? $row->id : $row['id'];
                                                    $actionUserMob = auth()->user();
                                                    $isSalesUserMob = $actionUserMob && (\App\Support\NavigationConfig::getRoleKey($actionUserMob) === 'sales-executive' || $actionUserMob->hasRole(['sales-executive', 'Sales Executive', 'Executive', 'sales']));
                                                @endphp
                                                <div class="flex items-center justify-end">
                                                    <x-ui.lead-archive-menu :id="$actionLeadIdMob" :lead="$row" />
                                                </div>
                                            @elseif($type === 'user_name')
                                                @php
                                                    $userRowIdMobile = is_object($row) ? $row->id : $row['id'];
                                                @endphp
                                                <button type="button" wire:click="openUserDetailsModal({{ $userRowIdMobile }})"
                                                    class="font-bold text-ink hover:text-primary hover:underline text-left cursor-pointer inline-flex items-center gap-1">
                                                    <span>{{ $val }}</span>
                                                    <svg class="w-3 h-3 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                </button>
                                            @elseif($type === 'user_actions')
                                                @php
                                                    $userRowIdMobile = is_object($row) ? $row->id : $row['id'];
                                                @endphp
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <button type="button" wire:click="openUserDetailsModal({{ $userRowIdMobile }})"
                                                        class="p-1.5 rounded-lg border border-border bg-surface hover:bg-canvas text-ink hover:border-ink/30 transition shadow-2xs cursor-pointer inline-flex items-center justify-center"
                                                        title="View &amp; Edit User Details" aria-label="View &amp; Edit User Details">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                        </svg>
                                                    </button>

                                                    @if(auth()->id() !== $userRowIdMobile)
                                                        <a href="{{ route('admin.users.impersonate', $userRowIdMobile) }}"
                                                            class="p-1.5 rounded-lg border border-border bg-surface hover:bg-purple-50 text-purple-700 hover:border-purple-300 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                                            title="Impersonate User" aria-label="Impersonate User">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                            </svg>
                                                        </a>
                                                    @else
                                                        <span class="text-[10px] text-muted font-bold">(You)</span>
                                                    @endif
                                                </div>
                                            @elseif($type === 'org_name')
                                                @php
                                                    $orgRowIdMobile = is_object($row) ? $row->id : $row['id'];
                                                @endphp
                                                <a href="{{ route('admin.organizations.show', $orgRowIdMobile) }}" wire:navigate
                                                    class="font-bold text-ink hover:text-primary hover:underline text-left inline-flex items-center gap-1 group cursor-pointer"
                                                    title="View Clients Details">
                                                    <span>{{ $val }}</span>
                                                    <svg class="w-3.5 h-3.5 text-muted opacity-0 group-hover:opacity-100 transition-opacity"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                    </svg>
                                                </a>
                                            @elseif($type === 'org_actions')
                                                @php
                                                    $orgRowIdMobile = is_object($row) ? $row->id : $row['id'];
                                                @endphp
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <a href="{{ route('admin.organizations.show', $orgRowIdMobile) }}" wire:navigate
                                                        class="p-1.5 rounded-lg border border-border bg-surface hover:bg-canvas text-ink hover:border-ink/30 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                                        title="View Clients Details" aria-label="View Clients Details">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    </a>

                                                    <button type="button" wire:click="openOrgDetailsModal({{ $orgRowIdMobile }})"
                                                        class="p-1.5 rounded-lg border border-border bg-surface hover:bg-canvas text-ink hover:border-ink/30 transition shadow-2xs cursor-pointer inline-flex items-center justify-center"
                                                        title="Quick Edit Organization" aria-label="Quick Edit Organization">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                        </svg>
                                                    </button>

                                                    <button type="button" wire:click="openUserModal({{ $orgRowIdMobile }})"
                                                        class="p-1.5 rounded-lg border border-border bg-surface hover:bg-primary/10 text-primary hover:border-primary/30 transition shadow-2xs cursor-pointer inline-flex items-center justify-center"
                                                        title="Manage Users" aria-label="Manage Users">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            @elseif($type === 'replacement_actions')
                                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                                    @if((is_object($row) ? $row->status : $row['status']) === 'pending')
                                                        <button type="button"
                                                            wire:click="approveReplacement({{ is_object($row) ? $row->id : $row['id'] }})"
                                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition cursor-pointer">
                                                            Approve &amp; Replace
                                                        </button>
                                                        <button type="button"
                                                            wire:click="openRejectModal({{ is_object($row) ? $row->id : $row['id'] }})"
                                                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-2xs transition cursor-pointer">
                                                            Reject
                                                        </button>
                                                    @else
                                                        <button type="button"
                                                            wire:click="viewClaimLead({{ is_object($row) ? $row->id : $row['id'] }}, 'original')"
                                                            class="px-2.5 py-1 rounded-lg text-xs font-semibold border border-border bg-canvas text-ink hover:bg-surface cursor-pointer">View Original</button>
                                                        @if((is_object($row) ? $row->status : $row['status']) === 'approved')
                                                            @if(is_object($row) ? $row->replacement_lead_id : ($row['replacement_lead_id'] ?? null))
                                                                <button type="button"
                                                                    wire:click="viewClaimLead({{ is_object($row) ? $row->id : $row['id'] }}, 'replacement')"
                                                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold border border-emerald-200 bg-emerald-50 text-emerald-800 cursor-pointer">View Replacement</button>
                                                            @else
                                                                <span class="text-[11px] text-muted">No replacement linked</span>
                                                            @endif
                                                        @endif
                                                    @endif
                                                </div>
                                            @elseif($type === 'recharge_actions')
                                                <div class="flex items-center justify-end gap-1.5">
                                                    @if((is_object($row) ? $row->status : $row['status']) === 'pending')
                                                        <button type="button"
                                                            wire:click="openApproveModal({{ is_object($row) ? $row->id : $row['id'] }})"
                                                            class="px-2.5 py-1 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-2xs transition cursor-pointer">
                                                            Approve
                                                        </button>
                                                        <button type="button"
                                                            wire:click="openRejectModal({{ is_object($row) ? $row->id : $row['id'] }})"
                                                            class="px-2.5 py-1 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-2xs transition cursor-pointer">
                                                            Decline
                                                        </button>
                                                    @else
                                                        <span class="text-[11px] text-muted italic">Processed</span>
                                                    @endif
                                                </div>
                                            @elseif($type === 'webhook_actions')
                                                <div class="flex items-center justify-end">
                                                    <button type="button"
                                                        wire:click="retryLog({{ is_object($row) ? $row->id : $row['id'] }})"
                                                        class="px-2.5 py-1 text-xs font-bold text-ink bg-canvas hover:bg-neutral-200 rounded-lg border border-border transition cursor-pointer">
                                                        ↻ Retry Job
                                                    </button>
                                                </div>
                                            @elseif($type === 'upload_actions')
                                                @php
                                                    $batchId = is_object($row) ? $row->id : $row['id'];
                                                    $failedCount = is_object($row) ? ($row->failed_count ?? 0) : ($row['failed_count'] ?? 0);
                                                    $batchFilePath = is_object($row) ? ($row->file_path ?? null) : ($row['file_path'] ?? null);
                                                    $hasOriginalFile = !empty($batchFilePath) && !str_contains($batchFilePath, '..') && \Illuminate\Support\Facades\Storage::disk('local')->exists($batchFilePath);
                                                    $origName = is_object($row) ? ($row->original_filename ?? $row->filename) : ($row['original_filename'] ?? ($row['filename'] ?? ''));
                                                @endphp
                                                <div class="flex items-center justify-end gap-2 flex-wrap">
                                                    @if($failedCount > 0)
                                                        <a href="{{ route('leads.download-errors', $batchId) }}"
                                                            download="upload_errors_batch_{{ $batchId }}.csv" data-navigate-skip
                                                            wire:navigate.skip target="_blank" rel="noopener noreferrer"
                                                            class="px-2.5 py-1 bg-rose-50 border border-rose-200 hover:bg-rose-100 text-rose-700 font-bold text-[11px] rounded-lg transition shadow-2xs inline-flex items-center gap-1 cursor-pointer"
                                                            title="Download error CSV">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            <span>Error CSV</span>
                                                        </a>
                                                    @endif

                                                    @if($hasOriginalFile)
                                                        <a href="{{ route('leads.upload-batches.download', $batchId) }}" data-navigate-skip
                                                            wire:navigate.skip
                                                            class="px-2.5 py-1 bg-surface border border-border hover:bg-canvas hover:border-neutral-300 text-ink font-semibold text-[11px] rounded-lg transition shadow-2xs inline-flex items-center gap-1 cursor-pointer"
                                                            title="Download original file: {{ $origName }}">
                                                            <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                            </svg>
                                                            <span>Download</span>
                                                        </a>
                                                    @else
                                                        <span
                                                            class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-50 border border-slate-200 text-slate-400 font-medium text-[11px] rounded-lg cursor-not-allowed select-none"
                                                            title="Original file unavailable for this batch">
                                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                            </svg>
                                                            <span>Original file unavailable</span>
                                                        </span>
                                                    @endif
                                                </div>
                                            @elseif($type === 'audit_id')
                                                @php
                                                    $auditRowIdMobile = is_object($row) ? $row->id : $row['id'];
                                                @endphp
                                                <button type="button" wire:click="viewDetails({{ $auditRowIdMobile }})"
                                                    class="font-mono text-xs font-bold text-primary hover:underline hover:text-primary-dark cursor-pointer inline-flex items-center gap-1">
                                                    <span>#{{ $val }}</span>
                                                    <svg class="w-3 h-3 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </button>
                                            @elseif($type === 'audit_actions')
                                                @php
                                                    $auditRowIdMobile = is_object($row) ? $row->id : $row['id'];
                                                @endphp
                                                <div class="flex items-center justify-end">
                                                    <button type="button" wire:click="viewDetails({{ $auditRowIdMobile }})"
                                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg border border-border bg-surface hover:bg-canvas text-ink hover:border-ink/30 transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer"
                                                        title="Inspect Details">
                                                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                        <span>Inspect</span>
                                                    </button>
                                                </div>
                                            @elseif($type === 'html' || isset($col['render']))
                                                {!! isset($col['render']) && is_callable($col['render']) ? $col['render']($row, $val, 'mobile') : $val !!}
                                            @else
                                                @php
                                                    $displayValMobile = $val;
                                                    if (isset($col['formatter']) && is_callable($col['formatter'])) {
                                                        $displayValMobile = $col['formatter']($val, $row);
                                                    }
                                                    $formattedTextMobile = ($col['prefix'] ?? '') . ($displayValMobile !== null && $displayValMobile !== '' ? $displayValMobile : ($col['default'] ?? '—')) . ($col['suffix'] ?? '');
                                                @endphp
                                                <span
                                                    class="{{ $col['class'] ?? 'text-ink' }}">{{ \App\Support\ClientFacingText::label($formattedTextMobile) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="bg-surface rounded-card border border-border p-8 text-center text-muted space-y-2">
                        <h4 class="text-sm font-bold text-ink">{{ $emptyTitle }}</h4>
                        <p class="text-xs text-muted">{{ $emptyMessage }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($loading || $showSkeleton)
        {{-- Keep responsive visibility outside Livewire's inline display changes. --}}
        <div class="block md:hidden">
            <div class="space-y-3" @if(!$loading) wire:loading.block wire:target="{{ $effectiveLoadingTarget }}" @endif>
                @for($i = 0; $i < (int) min($skeletonRows, 4); $i++)
                    <div class="bg-surface rounded-card border border-border p-4 shadow-sm space-y-3 animate-pulse">
                        <div
                            class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-2 bg-surface pb-2 border-b border-border">
                            <div class="flex items-center gap-2">
                                @if($showCheckboxes)
                                    <div class="w-4 h-4 bg-slate-200 rounded"></div>
                                @endif
                                <div class="h-4 w-32 bg-slate-200 rounded"></div>
                            </div>
                            <div class="h-5 w-16 bg-slate-200 rounded-pill"></div>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5 text-xs">
                            <div class="space-y-1">
                                <div class="h-2.5 w-12 bg-slate-200/60 rounded"></div>
                                <div class="h-3.5 w-24 bg-slate-200 rounded"></div>
                            </div>
                            <div class="space-y-1">
                                <div class="h-2.5 w-14 bg-slate-200/60 rounded"></div>
                                <div class="h-3.5 w-20 bg-slate-200 rounded"></div>
                            </div>
                            <div class="space-y-1">
                                <div class="h-2.5 w-10 bg-slate-200/60 rounded"></div>
                                <div class="h-3.5 w-28 bg-slate-200 rounded"></div>
                            </div>
                            <div class="space-y-1">
                                <div class="h-2.5 w-16 bg-slate-200/60 rounded"></div>
                                <div class="h-3.5 w-16 bg-slate-200 rounded"></div>
                            </div>
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    @endif

    <!-- 5. PAGINATION FOOTER -->
    @if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator || $rows instanceof \Illuminate\Pagination\Paginator)
        <div class="pt-2">
            {{ $rows->links('vendor.pagination.tailwind') }}
        </div>
    @endif
</div>