<div x-data="{ open: false }" class="relative inline-block text-left text-xs {{ $class }}">
    <!-- Trigger Button -->
    <button type="button" @click="open = !open"
        class="inline-flex items-center justify-between gap-2 h-8 text-xs px-3.5 bg-canvas text-ink rounded-lg border border-border focus:outline-none focus:ring-2 focus:ring-ink hover:bg-canvas/80 transition w-full min-w-[110px] whitespace-nowrap">
        <span class="truncate font-semibold {{ $value !== null && $value !== '' ? 'text-ink' : 'text-muted' }}">
            {{ $selectedLabel ?: $placeholder }}
        </span>

        <div class="flex items-center gap-1 shrink-0 text-muted">
            @if($value !== null && $value !== '')
                <span wire:click.stop="clearSelection" title="Clear filter"
                    class="hover:text-ink cursor-pointer text-xs font-bold leading-none p-0.5">✕</span>
            @endif
            <svg class="w-3.5 h-3.5 transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    </button>

    <!-- Dropdown Popover Panel -->
    <div x-show="open" x-cloak @click.outside="open = false" x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95" @if($searchable ?? true)
        x-effect="if (open) $nextTick(() => $refs.searchInput && $refs.searchInput.focus())" @endif
        class="absolute left-0 z-50 mt-1.5 min-w-full w-max max-w-[320px] bg-surface rounded-card border border-border shadow-xl p-1.5 text-xs space-y-1.5">
        @if($searchable ?? true)
            <!-- Search Input Header -->
            <div class="relative">
                <input x-ref="searchInput" type="text" wire:model.live.debounce.300ms="search" placeholder="Search..."
                    class="w-full h-8 pl-8 pr-3.5 bg-canvas text-ink text-xs rounded-lg border border-border focus:ring-2 focus:ring-ink focus:outline-none placeholder:text-muted transition" />
                <svg class="w-3.5 h-3.5 text-muted absolute left-2.5 top-2.5 pointer-events-none" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        @endif

        <!-- Results List -->
        <div class="max-h-52 overflow-y-auto sidebar-scroll space-y-0.5">
            <!-- Clear Selection / All Option -->
            <button type="button" wire:click="clearSelection" @click="open = false"
                class="w-full text-left px-3 py-1.5 rounded-md hover:bg-canvas transition flex items-center justify-between gap-3 font-semibold {{ $value === null || $value === '' ? 'bg-canvas text-ink font-bold' : 'text-muted' }}">
                <span class="truncate">{{ $placeholder }}</span>
                @if($value === null || $value === '')
                    <span class="text-ink text-xs shrink-0">✓</span>
                @endif
            </button>

            @forelse($items as $item)
                @php
                    $itemVal = $item->{$valueColumn};
                    $itemLabel = $item->{$displayColumn};
                    $isSelected = ($value == $itemVal);
                @endphp
                <button type="button" wire:click="selectOption('{{ $itemVal }}')" @click="open = false"
                    class="w-full text-left px-3 py-1.5 rounded-md hover:bg-canvas transition flex items-center justify-between gap-3 {{ $isSelected ? 'bg-canvas text-ink font-bold' : 'text-ink' }}">
                    <span class="truncate">{{ $itemLabel }}</span>
                    @if($isSelected)
                        <span class="text-ink text-xs shrink-0">✓</span>
                    @endif
                </button>
            @empty
                <div class="py-2 text-center text-muted text-[11px]">
                    No matching results
                </div>
            @endforelse

            @if($hasMore)
                <div class="pt-1 border-t border-border mt-1">
                    <button type="button" wire:click="loadMore"
                        class="w-full text-center py-1.5 text-[11px] font-semibold text-muted hover:text-ink hover:bg-canvas rounded-md transition">
                        Load more (+20)...
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>