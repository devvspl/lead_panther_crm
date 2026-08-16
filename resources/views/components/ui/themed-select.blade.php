@props([
    'options' => [],
    'placeholder' => 'Select Option',
    'class' => '',
    'searchable' => false,
])

@php
    $wireModel = $attributes->wire('model')->value();

    $normalizedOptions = [];
    foreach ($options as $key => $val) {
        if (is_array($val)) {
            $normalizedOptions[] = [
                'value' => (string)($val['value'] ?? $key),
                'label' => (string)($val['label'] ?? $val['name'] ?? $val['value'] ?? $key),
            ];
        } else {
            $normalizedOptions[] = [
                'value' => (string)$key,
                'label' => (string)$val,
            ];
        }
    }
@endphp

<div 
    x-data="{ 
        open: false, 
        search: '',
        value: @if($wireModel) ($wire.entangle('{{ $wireModel }}') ?? '') @else '{{ $attributes->get('value', '') }}' @endif,
        options: {{ json_encode($normalizedOptions) }},
        get filteredOptions() {
            if (!this.search || !this.search.trim()) return this.options || [];
            let q = this.search.toLowerCase().trim();
            return (this.options || []).filter(o => (o.label || '').toLowerCase().includes(q) || (o.value || '').toLowerCase().includes(q));
        },
        get selectedLabel() {
            if (this.value === undefined || this.value === null) return '{{ $placeholder }}';
            let valStr = String(this.value);
            let found = (this.options || []).find(o => String(o.value) === valStr);
            return found ? found.label : '{{ $placeholder }}';
        },
        select(val) {
            this.value = val ?? '';
            this.open = false;
            this.search = '';
        }
    }" 
    class="relative inline-block text-left text-xs {{ $class }}"
>
    <!-- Trigger Button -->
    <button 
        type="button" 
        @click="open = !open" 
        class="inline-flex items-center justify-between gap-2 h-8 text-xs px-3.5 bg-canvas text-ink rounded-lg border border-border focus:outline-none focus:ring-2 focus:ring-ink hover:bg-canvas/80 transition w-full min-w-[110px] whitespace-nowrap"
    >
        <span class="truncate font-semibold" x-text="selectedLabel"></span>
        <svg class="w-3.5 h-3.5 text-muted transition-transform duration-150 shrink-0" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <!-- Dropdown Panel -->
    <div 
        x-show="open" 
        x-cloak 
        @click.outside="open = false; search = ''" 
        x-transition:enter="transition ease-out duration-100" 
        x-transition:enter-start="transform opacity-0 scale-95" 
        x-transition:enter-end="transform opacity-100 scale-100" 
        x-transition:leave="transition ease-in duration-75" 
        x-transition:leave-start="transform opacity-100 scale-100" 
        x-transition:leave-end="transform opacity-0 scale-95" 
        @if($searchable)
            x-effect="if (open) $nextTick(() => $refs.searchInput && $refs.searchInput.focus())"
        @endif
        class="absolute left-0 z-50 mt-1.5 min-w-full w-max max-w-[320px] bg-surface rounded-card border border-border shadow-xl p-1.5 text-xs space-y-1.5"
    >
        @if($searchable)
            <!-- Search Input Header -->
            <div class="relative">
                <input 
                    x-ref="searchInput"
                    x-model="search"
                    type="text" 
                    placeholder="Search..." 
                    class="w-full h-9 pl-8 pr-3.5 bg-canvas text-ink text-xs rounded-lg border border-border focus:ring-2 focus:ring-ink focus:outline-none placeholder:text-muted transition"
                />
                <svg class="w-3.5 h-3.5 text-muted absolute left-2.5 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        @endif

        <div class="max-h-52 overflow-y-auto sidebar-scroll space-y-0.5">
            <template x-for="opt in filteredOptions" :key="opt.value">
                <button 
                    type="button" 
                    @click="select(opt.value)" 
                    class="w-full text-left px-3 py-1.5 rounded-md hover:bg-canvas transition flex items-center justify-between gap-3"
                    :class="String(value) === String(opt.value) ? 'bg-canvas text-ink font-bold' : 'text-ink'"
                >
                    <span class="truncate" x-text="opt.label"></span>
                    <span x-show="String(value) === String(opt.value)" class="text-ink text-xs font-bold shrink-0">✓</span>
                </button>
            </template>
            <div x-show="filteredOptions.length === 0" class="py-2 text-center text-muted text-[11px]">
                No matching results
            </div>
        </div>
    </div>
</div>
