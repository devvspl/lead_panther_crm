@props([
    'columns' => [],
    'rows' => null,
    'quickFilters' => [],
    'activeStatus' => 'all',
    'searchPlaceholder' => 'Search records...',
    'showSearch' => true,
    'showFilterDropdown' => true,
    'showConfigurations' => true,
    'showCheckboxes' => false,
    'selectedRows' => [],
    'selectAll' => false,
    'visibleColumns' => [],
    'sortField' => '',
    'sortDirection' => 'asc',
    'emptyMessage' => 'No records found matching your filters.',
    'emptyTitle' => 'No Records Found',
    'filterCount' => 0,
    'tableName' => 'table',
])

@php
    $visibleCols = !empty($visibleColumns) ? $visibleColumns : array_map(fn($c) => $c['key'], $columns);
    $activeColumns = array_filter($columns, fn($c) => in_array($c['key'], $visibleCols));
@endphp

<div class="space-y-4" x-data="{ 
    filterOpen: false, 
    configOpen: false,
    activeActionMenu: null 
}">
    <!-- 1. TOOLBAR: Search, Filter, Column Configuration & Add Action -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 bg-surface p-3 sm:p-4 rounded-card border border-border shadow-2xs">
        <!-- Search Input -->
        @if($showSearch)
            <div class="relative flex-1 max-w-md">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="{{ $searchPlaceholder }}"
                    class="w-full pl-9 pr-8 py-2 rounded-lg border border-border bg-canvas text-ink text-xs placeholder:text-muted focus:ring-2 focus:ring-primary/20 focus:border-primary transition"
                >
                @if(!empty($this->search ?? ''))
                    <button 
                        type="button" 
                        wire:click="$set('search', '')" 
                        class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-muted hover:text-ink cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>
        @endif

        <!-- Action Controls Group -->
        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
            <!-- Filter Dropdown Button & Popover -->
            @if($showFilterDropdown && (isset($filters) || !empty($this->filterConfig())))
                <div class="relative">
                    <button 
                        type="button" 
                        @click="filterOpen = !filterOpen; configOpen = false" 
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:bg-surface transition shadow-2xs cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        <span>Filter</span>
                        @if($filterCount > 0)
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-primary text-white">{{ $filterCount }}</span>
                        @endif
                    </button>

                    <!-- Filter Popover Panel -->
                    <div 
                        x-show="filterOpen" 
                        @click.outside="filterOpen = false" 
                        x-cloak 
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="transform opacity-100 scale-100"
                        x-transition:leave-end="transform opacity-0 scale-95"
                        class="absolute right-0 mt-2 w-72 sm:w-80 bg-surface rounded-card border border-border shadow-xl p-4 z-40 space-y-3"
                    >
                        <div class="flex items-center justify-between pb-2 border-b border-border">
                            <h4 class="text-xs font-bold text-ink">Custom Filters</h4>
                            <button 
                                type="button" 
                                wire:click="resetTableFilters" 
                                @click="filterOpen = false"
                                class="text-[11px] text-primary font-bold hover:underline cursor-pointer"
                            >
                                Reset All
                            </button>
                        </div>

                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            @if(isset($filters))
                                {{ $filters }}
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Column Configurations Dropdown -->
            @if($showConfigurations && !empty($columns))
                <div class="relative">
                    <button 
                        type="button" 
                        @click="configOpen = !configOpen; filterOpen = false" 
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:bg-surface transition shadow-2xs cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                        <span>Configuration</span>
                    </button>

                    <!-- Column Toggles Panel -->
                    <div 
                        x-show="configOpen" 
                        @click.outside="configOpen = false" 
                        x-cloak 
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        class="absolute right-0 mt-2 w-64 bg-surface rounded-card border border-border shadow-xl p-3.5 z-40 space-y-2.5"
                    >
                        <div class="flex items-center justify-between pb-2 border-b border-border">
                            <h4 class="text-xs font-bold text-ink">Column Visibility</h4>
                            <button 
                                type="button" 
                                wire:click="resetColumns" 
                                class="text-[10px] text-primary font-bold hover:underline cursor-pointer"
                            >
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
                                <label class="flex items-center justify-between px-2 py-1.5 rounded-lg hover:bg-canvas text-xs cursor-pointer select-none">
                                    <span class="text-ink font-medium">{{ $colLabel }}</span>
                                    <input 
                                        type="checkbox" 
                                        wire:click="toggleColumn('{{ $colKey }}')" 
                                        @checked($isChecked)
                                        class="rounded text-primary focus:ring-primary h-3.5 w-3.5 cursor-pointer"
                                    >
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- Add New Action Slot -->
            @if(isset($action))
                <div class="ml-auto sm:ml-0">
                    {{ $action }}
                </div>
            @endif
        </div>
    </div>

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
                <button 
                    type="button" 
                    wire:click="setStatusFilter('{{ $pillKey }}')" 
                    class="px-3 py-1 rounded-pill text-xs font-semibold transition flex items-center gap-1.5 flex-shrink-0 cursor-pointer {{ $isActive ? 'bg-ink text-white font-bold shadow-xs' : 'bg-surface text-muted border border-border hover:text-ink hover:border-ink/40' }}"
                >
                    <span>{{ $pillLabel }}</span>
                    @if($pillCount !== null)
                        <span class="px-1.5 py-0.2 text-[10px] rounded-full {{ $isActive ? 'bg-white/20 text-white' : 'bg-canvas text-muted border border-border' }}">
                            {{ $pillCount }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif

    <!-- 3. DESKTOP / TABLET DATA TABLE (Hidden below md) -->
    <div class="hidden md:block bg-surface rounded-card border border-border shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead class="bg-canvas border-b border-border text-[10px] uppercase font-bold text-muted tracking-wider select-none">
                    <tr>
                        @if($showCheckboxes)
                            <th class="py-3 px-3.5 w-10 text-center">
                                <input 
                                    type="checkbox" 
                                    wire:model.live="selectAll" 
                                    class="rounded text-primary focus:ring-primary h-3.5 w-3.5 cursor-pointer"
                                >
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
                            @endphp
                            <th class="py-3 px-3.5 {{ $alignClass }} {{ $priorityClass }}">
                                @if($isSortable)
                                    <button 
                                        type="button" 
                                        wire:click="sortBy('{{ $colKey }}')" 
                                        class="inline-flex items-center gap-1 hover:text-ink font-bold transition group cursor-pointer"
                                    >
                                        <span>{{ $colLabel }}</span>
                                        <span class="text-muted group-hover:text-ink">
                                            @if($sortField === $colKey)
                                                @if($sortDirection === 'asc')
                                                    <svg class="w-3 h-3 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                                @else
                                                    <svg class="w-3 h-3 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                                @endif
                                            @else
                                                <svg class="w-3 h-3 opacity-30 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
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

                <tbody class="divide-y divide-border">
                    @if(!empty($rows) && count($rows) > 0)
                        @foreach($rows as $row)
                            @php
                                $rowId = is_object($row) ? ($row->id ?? null) : ($row['id'] ?? null);
                                $isRowSelected = $rowId && in_array((string)$rowId, $selectedRows);
                            @endphp
                            <tr class="hover:bg-canvas/50 transition {{ $isRowSelected ? 'bg-primary/5' : '' }}">
                                @if($showCheckboxes && $rowId)
                                    <td class="py-3 px-3.5 text-center">
                                        <input 
                                            type="checkbox" 
                                            wire:click="toggleSelectRow('{{ $rowId }}')" 
                                            @checked($isRowSelected)
                                            class="rounded text-primary focus:ring-primary h-3.5 w-3.5 cursor-pointer"
                                        >
                                    </td>
                                @endif

                                @foreach($activeColumns as $col)
                                    @php
                                        $colKey = $col['key'];
                                        $type = $col['type'] ?? 'text';
                                        $priority = $col['priority'] ?? 1;
                                        $priorityClass = $priority === 2 ? 'hidden lg:table-cell' : ($priority === 3 ? 'hidden xl:table-cell' : '');
                                        $alignClass = ($col['align'] ?? 'left') === 'right' ? 'text-right' : (($col['align'] ?? 'left') === 'center' ? 'text-center' : 'text-left');
                                        
                                        // Retrieve raw value
                                        $val = is_object($row) ? data_get($row, $colKey) : ($row[$colKey] ?? null);
                                    @endphp
                                    <td class="py-3 px-3.5 {{ $alignClass }} {{ $priorityClass }}">
                                        @if(isset(${'cell_' . $colKey}))
                                            {{ ${'cell_' . $colKey}($row) }}
                                        @elseif($type === 'badge')
                                            @php
                                                $badgeStyle = $col['badgeStyle'] ?? null;
                                                $badgeClass = is_callable($badgeStyle) ? $badgeStyle($val, $row) : ($col['badgeMap'][$val] ?? 'bg-canvas text-ink border border-border');
                                                $badgeLabel = $col['labels'][$val] ?? ucfirst(str_replace('_', ' ', (string)$val));
                                            @endphp
                                            <span class="px-2 py-0.5 rounded-pill text-[10px] font-bold inline-flex items-center gap-1 {{ $badgeClass }}">
                                                {{ $badgeLabel }}
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
                                                    <div class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-slate-200 dark:bg-slate-700 text-ink text-[10px] font-bold border-2 border-surface" title="{{ $avName }}">
                                                        {{ $avInitial }}
                                                    </div>
                                                @empty
                                                    <span class="text-muted text-[11px]">{{ $col['emptyText'] ?? 'Unassigned' }}</span>
                                                @endforelse
                                                @if($overflow > 0)
                                                    <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-canvas text-muted text-[9px] font-bold border-2 border-surface font-mono">
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
                                                    <div class="h-1.5 rounded-full {{ $colorClass }}" style="width: {{ min(100, max(0, $numericVal)) }}%"></div>
                                                </div>
                                            </div>
                                        @elseif($type === 'link')
                                            @php
                                                $linkUrl = is_callable($col['url'] ?? null) ? ($col['url'])($row) : ($val);
                                            @endphp
                                            <a href="{{ $linkUrl }}" target="_blank" class="text-primary font-medium hover:underline inline-flex items-center gap-1">
                                                <span>{{ $val }}</span>
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </a>
                                        @elseif($type === 'date')
                                            <span class="text-muted font-mono text-[11px]" title="{{ $val ? \Carbon\Carbon::parse($val)->toDayDateTimeString() : '' }}">
                                                {{ $val ? \Carbon\Carbon::parse($val)->format($col['format'] ?? 'M d, Y H:i') : '—' }}
                                            </span>
                                        @elseif($type === 'lead_actions')
                                            <div class="flex items-center justify-end gap-1">
                                                <button 
                                                    type="button" 
                                                    @click="$dispatch('open-lead-detail', { id: {{ is_object($row) ? $row->id : $row['id'] }} })"
                                                    class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition cursor-pointer"
                                                    title="View Lead Details"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                </button>
                                            </div>
                                        @elseif($type === 'user_actions')
                                            <div class="flex items-center justify-end gap-2">
                                                @if(auth()->id() !== (is_object($row) ? $row->id : $row['id']))
                                                    <a href="{{ route('admin.users.impersonate', is_object($row) ? $row->id : $row['id']) }}" class="text-xs font-bold text-purple-700 hover:underline">
                                                        Impersonate
                                                    </a>
                                                @else
                                                    <span class="text-[10px] text-muted font-bold">(You)</span>
                                                @endif
                                            </div>
                                        @elseif($type === 'org_actions')
                                            <div class="flex items-center justify-end gap-2">
                                                <button 
                                                    type="button"
                                                    wire:click="openUserOffcanvas({{ is_object($row) ? $row->id : $row['id'] }})"
                                                    class="px-2 py-1 rounded text-xs font-bold text-primary hover:bg-primary/10 transition cursor-pointer"
                                                >
                                                    Manage Users
                                                </button>
                                                <button 
                                                    type="button"
                                                    wire:click="toggleStatus({{ is_object($row) ? $row->id : $row['id'] }})"
                                                    class="px-2 py-1 rounded text-xs font-semibold text-muted hover:text-ink hover:bg-canvas transition cursor-pointer"
                                                >
                                                    {{ (is_object($row) ? $row->status : $row['status']) === 'active' ? 'Suspend' : 'Activate' }}
                                                </button>
                                            </div>
                                        @elseif($type === 'replacement_actions')
                                            <div class="flex items-center justify-end gap-1.5">
                                                @if((is_object($row) ? $row->status : $row['status']) === 'pending')
                                                    <button 
                                                        type="button" 
                                                        wire:click="approveReplacement({{ is_object($row) ? $row->id : $row['id'] }})"
                                                        class="px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition cursor-pointer"
                                                    >
                                                        Approve
                                                    </button>
                                                    <button 
                                                        type="button" 
                                                        wire:click="openRejectModal({{ is_object($row) ? $row->id : $row['id'] }})"
                                                        class="px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-2xs transition cursor-pointer"
                                                    >
                                                        Reject
                                                    </button>
                                                @else
                                                    <span class="text-[11px] text-muted italic">Resolved</span>
                                                @endif
                                            </div>
                                        @elseif($type === 'recharge_actions')
                                            <div class="flex items-center justify-end gap-1.5">
                                                @if((is_object($row) ? $row->status : $row['status']) === 'pending')
                                                    <button 
                                                        type="button" 
                                                        wire:click="openApproveModal({{ is_object($row) ? $row->id : $row['id'] }})"
                                                        class="px-2.5 py-1 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-2xs transition cursor-pointer"
                                                    >
                                                        Approve
                                                    </button>
                                                    <button 
                                                        type="button" 
                                                        wire:click="openRejectModal({{ is_object($row) ? $row->id : $row['id'] }})"
                                                        class="px-2.5 py-1 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-2xs transition cursor-pointer"
                                                    >
                                                        Decline
                                                    </button>
                                                @else
                                                    <span class="text-[11px] text-muted italic">Processed</span>
                                                @endif
                                            </div>
                                        @elseif($type === 'webhook_actions')
                                            <div class="flex items-center justify-end">
                                                <button 
                                                    type="button" 
                                                    wire:click="retryLog({{ is_object($row) ? $row->id : $row['id'] }})"
                                                    class="px-2.5 py-1 text-xs font-bold text-ink bg-canvas hover:bg-neutral-200 rounded-lg border border-border transition cursor-pointer"
                                                >
                                                    ↻ Retry Job
                                                </button>
                                            </div>
                                        @elseif($type === 'upload_actions')
                                            <div class="flex items-center justify-end">
                                                @if((is_object($row) ? $row->failed_count : $row['failed_count']) > 0)
                                                    <a 
                                                        href="{{ route('leads.download-errors', is_object($row) ? $row->id : $row['id']) }}"
                                                        download="upload_errors_batch_{{ is_object($row) ? $row->id : $row['id'] }}.csv"
                                                        data-navigate-skip
                                                        wire:navigate.skip
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="px-2.5 py-1 bg-rose-50 border border-rose-200 hover:bg-rose-100 text-rose-700 font-bold text-[11px] rounded-lg transition shadow-2xs inline-flex items-center gap-1 cursor-pointer"
                                                    >
                                                        Error CSV
                                                    </a>
                                                @else
                                                    <span class="text-[11px] text-muted font-semibold">Clean Import</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="{{ $col['class'] ?? 'text-ink' }}">{{ $val ?: ($col['default'] ?? '—') }}</span>
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
                            <td colspan="{{ count($activeColumns) + ($showCheckboxes ? 1 : 0) + (isset($rowActions) ? 1 : 0) }}" class="py-12 text-center text-muted">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <svg class="w-8 h-8 opacity-40 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <h4 class="text-sm font-bold text-ink">{{ $emptyTitle }}</h4>
                                    <p class="text-xs text-muted max-w-sm">{{ $emptyMessage }}</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. MOBILE RESPONSIVE CARD STACK (Reflow on < md) -->
    <div class="block md:hidden space-y-3">
        @if(!empty($rows) && count($rows) > 0)
            @foreach($rows as $row)
                @php
                    $rowId = is_object($row) ? ($row->id ?? null) : ($row['id'] ?? null);
                    $isRowSelected = $rowId && in_array((string)$rowId, $selectedRows);
                @endphp
                <div class="bg-surface rounded-card border border-border p-4 shadow-sm space-y-3 {{ $isRowSelected ? 'border-primary bg-primary/5' : '' }}">
                    <!-- Card Top Row: Checkbox, Primary Title & Action Menu -->
                    <div class="flex items-start justify-between gap-2 pb-2 border-b border-border">
                        <div class="flex items-center gap-2">
                            @if($showCheckboxes && $rowId)
                                <input 
                                    type="checkbox" 
                                    wire:click="toggleSelectRow('{{ $rowId }}')" 
                                    @checked($isRowSelected)
                                    class="rounded text-primary focus:ring-primary h-4 w-4 cursor-pointer"
                                >
                            @endif

                            <div>
                                @php
                                    $primaryCol = $activeColumns[0] ?? null;
                                    $primaryVal = $primaryCol ? (is_object($row) ? data_get($row, $primaryCol['key']) : ($row[$primaryCol['key']] ?? null)) : 'Item';
                                @endphp
                                <div class="font-bold text-sm text-ink">{{ $primaryVal }}</div>
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
                            <div class="space-y-0.5 {{ in_array($type, ['lead_actions', 'user_actions', 'org_actions', 'replacement_actions', 'recharge_actions', 'webhook_actions', 'upload_actions']) ? 'col-span-2 pt-2 border-t border-border flex justify-end' : '' }}">
                                @if(!in_array($type, ['lead_actions', 'user_actions', 'org_actions', 'replacement_actions', 'recharge_actions', 'webhook_actions', 'upload_actions']))
                                    <span class="text-[10px] uppercase font-bold text-muted tracking-wider block">{{ $colLabel }}</span>
                                @endif
                                <div>
                                    @if(isset(${'cell_' . $colKey}))
                                        {{ ${'cell_' . $colKey} }}
                                    @elseif($type === 'badge')
                                        @php
                                            $badgeStyle = $col['badgeStyle'] ?? null;
                                            $badgeClass = is_callable($badgeStyle) ? $badgeStyle($val, $row) : ($col['badgeMap'][$val] ?? 'bg-canvas text-ink border border-border');
                                            $badgeLabel = $col['labels'][$val] ?? ucfirst(str_replace('_', ' ', (string)$val));
                                        @endphp
                                        <span class="px-2 py-0.5 rounded-pill text-[10px] font-bold inline-block {{ $badgeClass }}">
                                            {{ $badgeLabel }}
                                        </span>
                                    @elseif($type === 'avatar-stack')
                                        @php
                                            $avatars = is_array($val) ? $val : (is_object($val) ? [$val] : []);
                                        @endphp
                                        <span class="font-medium text-ink">{{ !empty($avatars) ? (is_object($avatars[0]) ? $avatars[0]->name : ($avatars[0]['name'] ?? 'Assigned')) : 'Unassigned' }}</span>
                                    @elseif($type === 'progress')
                                        <span class="font-mono font-bold text-ink">{{ (int)$val }}%</span>
                                    @elseif($type === 'date')
                                        <span class="font-mono text-muted text-[11px]">{{ $val ? \Carbon\Carbon::parse($val)->format('M d, Y') : '—' }}</span>
                                    @elseif($type === 'lead_actions')
                                        <div class="flex items-center justify-end gap-1">
                                            <button 
                                                type="button" 
                                                @click="$dispatch('open-lead-detail', { id: {{ is_object($row) ? $row->id : $row['id'] }} })"
                                                class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition cursor-pointer"
                                                title="View Lead Details"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </button>
                                        </div>
                                    @elseif($type === 'user_actions')
                                        <div class="flex items-center justify-end gap-2">
                                            @if(auth()->id() !== (is_object($row) ? $row->id : $row['id']))
                                                <a href="{{ route('admin.users.impersonate', is_object($row) ? $row->id : $row['id']) }}" class="text-xs font-bold text-purple-700 hover:underline">
                                                    Impersonate
                                                </a>
                                            @else
                                                <span class="text-[10px] text-muted font-bold">(You)</span>
                                            @endif
                                        </div>
                                    @elseif($type === 'org_actions')
                                        <div class="flex items-center justify-end gap-2">
                                            <button 
                                                type="button"
                                                wire:click="openUserOffcanvas({{ is_object($row) ? $row->id : $row['id'] }})"
                                                class="px-2 py-1 rounded text-xs font-bold text-primary hover:bg-primary/10 transition cursor-pointer"
                                            >
                                                Manage Users
                                            </button>
                                            <button 
                                                type="button"
                                                wire:click="toggleStatus({{ is_object($row) ? $row->id : $row['id'] }})"
                                                class="px-2 py-1 rounded text-xs font-semibold text-muted hover:text-ink hover:bg-canvas transition cursor-pointer"
                                            >
                                                {{ (is_object($row) ? $row->status : $row['status']) === 'active' ? 'Suspend' : 'Activate' }}
                                            </button>
                                        </div>
                                    @elseif($type === 'replacement_actions')
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if((is_object($row) ? $row->status : $row['status']) === 'pending')
                                                <button 
                                                    type="button" 
                                                    wire:click="approveReplacement({{ is_object($row) ? $row->id : $row['id'] }})"
                                                    class="px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition cursor-pointer"
                                                >
                                                    Approve
                                                </button>
                                                <button 
                                                    type="button" 
                                                    wire:click="openRejectModal({{ is_object($row) ? $row->id : $row['id'] }})"
                                                    class="px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-2xs transition cursor-pointer"
                                                >
                                                    Reject
                                                </button>
                                            @else
                                                <span class="text-[11px] text-muted italic">Resolved</span>
                                            @endif
                                        </div>
                                    @elseif($type === 'recharge_actions')
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if((is_object($row) ? $row->status : $row['status']) === 'pending')
                                                <button 
                                                    type="button" 
                                                    wire:click="openApproveModal({{ is_object($row) ? $row->id : $row['id'] }})"
                                                    class="px-2.5 py-1 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-2xs transition cursor-pointer"
                                                >
                                                    Approve
                                                </button>
                                                <button 
                                                    type="button" 
                                                    wire:click="openRejectModal({{ is_object($row) ? $row->id : $row['id'] }})"
                                                    class="px-2.5 py-1 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-2xs transition cursor-pointer"
                                                >
                                                    Decline
                                                </button>
                                            @else
                                                <span class="text-[11px] text-muted italic">Processed</span>
                                            @endif
                                        </div>
                                    @elseif($type === 'webhook_actions')
                                        <div class="flex items-center justify-end">
                                            <button 
                                                type="button" 
                                                wire:click="retryLog({{ is_object($row) ? $row->id : $row['id'] }})"
                                                class="px-2.5 py-1 text-xs font-bold text-ink bg-canvas hover:bg-neutral-200 rounded-lg border border-border transition cursor-pointer"
                                            >
                                                ↻ Retry Job
                                            </button>
                                        </div>
                                    @elseif($type === 'upload_actions')
                                        <div class="flex items-center justify-end">
                                            @if((is_object($row) ? $row->failed_count : $row['failed_count']) > 0)
                                                <a 
                                                    href="{{ route('leads.download-errors', is_object($row) ? $row->id : $row['id']) }}"
                                                    download="upload_errors_batch_{{ is_object($row) ? $row->id : $row['id'] }}.csv"
                                                    data-navigate-skip
                                                    wire:navigate.skip
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="px-2.5 py-1 bg-rose-50 border border-rose-200 hover:bg-rose-100 text-rose-700 font-bold text-[11px] rounded-lg transition shadow-2xs inline-flex items-center gap-1 cursor-pointer"
                                                >
                                                    Error CSV
                                                </a>
                                            @else
                                                <span class="text-[11px] text-muted font-semibold">Clean Import</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="{{ $col['class'] ?? 'text-ink' }}">{{ $val ?: '—' }}</span>
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

    <!-- 5. PAGINATION FOOTER -->
    @if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator || $rows instanceof \Illuminate\Pagination\Paginator)
        <div class="pt-2">
            {{ $rows->links('vendor.pagination.tailwind') }}
        </div>
    @endif
</div>
