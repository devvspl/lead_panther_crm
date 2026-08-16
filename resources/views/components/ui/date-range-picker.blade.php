@props([
    'placeholder' => 'Select Date Range',
    'class' => '',
    'align' => 'left',
])

@php
    $wireModel = $attributes->wire('model')->value();
    $wireCustomFrom = $attributes->wire('custom-from')->value();
    $wireCustomTo = $attributes->wire('custom-to')->value();
@endphp

<div 
    x-data="{ 
        open: false, 
        modalOpen: false,
        range: @if($wireModel) ($wire.entangle('{{ $wireModel }}') ?? 'month') @else 'month' @endif,
        customFrom: @if($wireCustomFrom) ($wire.entangle('{{ $wireCustomFrom }}') ?? '') @else '' @endif,
        customTo: @if($wireCustomTo) ($wire.entangle('{{ $wireCustomTo }}') ?? '') @else '' @endif,
        draftFrom: '',
        draftTo: '',
        fpInstance: null,

        formatDate(d) {
            if (!d) return '';
            let date = typeof d === 'string' ? new Date(d + 'T00:00:00') : d;
            if (isNaN(date.getTime())) return d;
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },

        formatShortDate(d) {
            if (!d) return '';
            let date = typeof d === 'string' ? new Date(d + 'T00:00:00') : d;
            if (isNaN(date.getTime())) return d;
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        },

        get triggerLabel() {
            if (this.range === 'today') return 'Today';
            if (this.range === 'yesterday') return 'Yesterday';
            if (this.range === 'week') return 'This Week';
            if (this.range === 'last7') return 'Last 7 Days';
            if (this.range === 'month') return 'This Month';
            if (this.range === 'lastMonth') return 'Last Month';
            if (this.range === 'quarter') return 'This Quarter';
            if (this.range === 'year') return 'This Year';
            if (this.range === 'all') return 'All Time';
            if (this.range === 'custom' && this.customFrom && this.customTo) {
                return `${this.formatShortDate(this.customFrom)} – ${this.formatDate(this.customTo)}`;
            }
            return 'This Month';
        },

        get draftLabel() {
            if (this.draftFrom && this.draftTo) {
                return `${this.formatDate(this.draftFrom)} – ${this.formatDate(this.draftTo)}`;
            }
            if (this.draftFrom) {
                return `${this.formatDate(this.draftFrom)} – Select End Date`;
            }
            return 'Please select a date range';
        },

        selectPreset(preset) {
            if (preset === 'custom') {
                this.open = false;
                this.openModal();
                return;
            }
            this.range = preset;
            this.open = false;
        },

        openModal() {
            this.draftFrom = this.customFrom || '';
            this.draftTo = this.customTo || '';
            this.modalOpen = true;

            this.$nextTick(() => {
                this.initFlatpickr();
            });
        },

        closeModal() {
            this.modalOpen = false;
            if (this.fpInstance) {
                this.fpInstance.destroy();
                this.fpInstance = null;
            }
        },

        initFlatpickr() {
            if (!this.$refs.calendar) return;
            if (this.fpInstance) {
                this.fpInstance.destroy();
            }

            let initialDates = [];
            if (this.draftFrom && this.draftTo) {
                initialDates = [this.draftFrom, this.draftTo];
            } else {
                // Default to current month
                let now = new Date();
                let start = new Date(now.getFullYear(), now.getMonth(), 1);
                let end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                let startStr = start.toISOString().split('T')[0];
                let endStr = end.toISOString().split('T')[0];
                initialDates = [startStr, endStr];
                this.draftFrom = startStr;
                this.draftTo = endStr;
            }

            if (typeof window.flatpickr === 'function') {
                let isMobile = window.innerWidth < 768;
                this.fpInstance = window.flatpickr(this.$refs.calendar, {
                    mode: 'range',
                    showMonths: isMobile ? 1 : 2,
                    inline: true,
                    dateFormat: 'Y-m-d',
                    defaultDate: initialDates,
                    onChange: (selectedDates, dateStr, instance) => {
                        if (selectedDates.length === 2) {
                            this.draftFrom = instance.formatDate(selectedDates[0], 'Y-m-d');
                            this.draftTo = instance.formatDate(selectedDates[1], 'Y-m-d');
                        } else if (selectedDates.length === 1) {
                            this.draftFrom = instance.formatDate(selectedDates[0], 'Y-m-d');
                            this.draftTo = '';
                        }
                    }
                });
            }
        },

        applyPresetInModal(presetKey) {
            let now = new Date();
            let start, end;

            switch (presetKey) {
                case 'today':
                    start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                    end = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                    break;
                case 'yesterday':
                    start = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
                    end = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
                    break;
                case 'week':
                    let day = now.getDay();
                    let diff = now.getDate() - day + (day === 0 ? -6 : 1);
                    start = new Date(now.getFullYear(), now.getMonth(), diff);
                    end = new Date(now.getFullYear(), now.getMonth(), diff + 6);
                    break;
                case 'last7':
                    start = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 6);
                    end = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                    break;
                case 'month':
                    start = new Date(now.getFullYear(), now.getMonth(), 1);
                    end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                    break;
                case 'lastMonth':
                    start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    end = new Date(now.getFullYear(), now.getMonth(), 0);
                    break;
                case 'quarter':
                    let qMonth = Math.floor(now.getMonth() / 3) * 3;
                    start = new Date(now.getFullYear(), qMonth, 1);
                    end = new Date(now.getFullYear(), qMonth + 3, 0);
                    break;
                case 'year':
                    start = new Date(now.getFullYear(), 0, 1);
                    end = new Date(now.getFullYear(), 11, 31);
                    break;
                default:
                    start = new Date(now.getFullYear(), now.getMonth(), 1);
                    end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            }

            let startStr = start.toISOString().split('T')[0];
            let endStr = end.toISOString().split('T')[0];

            this.draftFrom = startStr;
            this.draftTo = endStr;

            if (this.fpInstance) {
                this.fpInstance.setDate([startStr, endStr], true);
            }
        },

        applyCustomRange() {
            if (!this.draftFrom || !this.draftTo) {
                return;
            }

            this.customFrom = this.draftFrom;
            this.customTo = this.draftTo;
            this.range = 'custom';

            @if($wireCustomFrom)
                $wire.set('{{ $wireCustomFrom }}', this.draftFrom);
            @endif
            @if($wireCustomTo)
                $wire.set('{{ $wireCustomTo }}', this.draftTo);
            @endif
            @if($wireModel)
                $wire.set('{{ $wireModel }}', 'custom');
            @endif

            $wire.dispatch('date-range-applied', {
                range: 'custom',
                from: this.draftFrom,
                to: this.draftTo
            });

            this.closeModal();
        }
    }" 
    class="relative inline-block text-left text-xs {{ $class }}"
>
    <!-- Trigger Button -->
    <button 
        type="button" 
        @click="open = !open" 
        class="inline-flex items-center justify-between gap-2 h-8 text-xs px-3.5 bg-canvas text-ink rounded-lg border border-border focus:outline-none focus:ring-2 focus:ring-ink hover:bg-canvas/80 transition min-w-[140px] whitespace-nowrap shadow-xs font-semibold"
    >
        <div class="flex items-center gap-1.5 truncate">
            <svg class="w-3.5 h-3.5 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="truncate" x-text="triggerLabel"></span>
        </div>
        <svg class="w-3.5 h-3.5 text-muted transition-transform duration-150 shrink-0" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <!-- Dropdown Panel -->
    <div 
        x-show="open" 
        x-cloak 
        @click.outside="open = false" 
        x-transition:enter="transition ease-out duration-100" 
        x-transition:enter-start="transform opacity-0 scale-95" 
        x-transition:enter-end="transform opacity-100 scale-100" 
        x-transition:leave="transition ease-in duration-75" 
        x-transition:leave-start="transform opacity-100 scale-100" 
        x-transition:leave-end="transform opacity-0 scale-95" 
        class="absolute {{ $align === 'right' ? 'right-0' : 'left-0' }} z-50 mt-1.5 w-48 bg-surface rounded-card border border-border shadow-xl p-1.5 text-xs space-y-0.5"
    >
        <button 
            type="button" 
            @click="selectPreset('today')" 
            class="w-full text-left px-3 py-1.5 rounded-md hover:bg-canvas transition flex items-center justify-between"
            :class="range === 'today' ? 'bg-canvas text-ink font-bold' : 'text-ink'"
        >
            <span>Today</span>
            <span x-show="range === 'today'" class="text-ink font-bold text-xs">✓</span>
        </button>

        <button 
            type="button" 
            @click="selectPreset('week')" 
            class="w-full text-left px-3 py-1.5 rounded-md hover:bg-canvas transition flex items-center justify-between"
            :class="range === 'week' ? 'bg-canvas text-ink font-bold' : 'text-ink'"
        >
            <span>This Week</span>
            <span x-show="range === 'week'" class="text-ink font-bold text-xs">✓</span>
        </button>

        <button 
            type="button" 
            @click="selectPreset('month')" 
            class="w-full text-left px-3 py-1.5 rounded-md hover:bg-canvas transition flex items-center justify-between"
            :class="range === 'month' ? 'bg-canvas text-ink font-bold' : 'text-ink'"
        >
            <span>This Month</span>
            <span x-show="range === 'month'" class="text-ink font-bold text-xs">✓</span>
        </button>

        <button 
            type="button" 
            @click="selectPreset('quarter')" 
            class="w-full text-left px-3 py-1.5 rounded-md hover:bg-canvas transition flex items-center justify-between"
            :class="range === 'quarter' ? 'bg-canvas text-ink font-bold' : 'text-ink'"
        >
            <span>This Quarter</span>
            <span x-show="range === 'quarter'" class="text-ink font-bold text-xs">✓</span>
        </button>

        <button 
            type="button" 
            @click="selectPreset('all')" 
            class="w-full text-left px-3 py-1.5 rounded-md hover:bg-canvas transition flex items-center justify-between"
            :class="range === 'all' ? 'bg-canvas text-ink font-bold' : 'text-ink'"
        >
            <span>All Time</span>
            <span x-show="range === 'all'" class="text-ink font-bold text-xs">✓</span>
        </button>

        <div class="border-t border-border my-1"></div>

        <button 
            type="button" 
            @click="selectPreset('custom')" 
            class="w-full text-left px-3 py-1.5 rounded-md hover:bg-canvas transition flex items-center justify-between font-medium text-ink"
            :class="range === 'custom' ? 'bg-canvas text-ink font-bold' : ''"
        >
            <div class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>Custom Range...</span>
            </div>
            <span x-show="range === 'custom'" class="text-ink font-bold text-xs">✓</span>
        </button>
    </div>

    <!-- Custom Date Range Modal -->
    <template x-teleport="body">
        <div 
            x-show="modalOpen" 
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
        >
            <!-- Backdrop -->
            <div 
                x-show="modalOpen"
                x-transition:enter="transition-opacity ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="closeModal()" 
                class="fixed inset-0 bg-black/50 backdrop-blur-xs transition-opacity"
            ></div>

            <!-- Modal Card -->
            <div 
                x-show="modalOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                @click.stop
                class="relative bg-surface rounded-card border border-border shadow-2xl w-full max-w-[820px] mx-auto overflow-hidden z-10"
            >
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-border bg-canvas/40">
                    <div>
                        <h3 class="text-sm font-bold text-ink">Select Custom Date Range</h3>
                        <p class="text-[11px] text-muted">Choose quick presets or click on calendar to select dates.</p>
                    </div>
                    <button 
                        type="button" 
                        @click="closeModal()" 
                        class="text-muted hover:text-ink p-1 rounded-lg transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Modal Body: 2 Columns -->
                <div class="flex flex-col md:flex-row divide-y md:divide-y-0 md:divide-x divide-border">
                    <!-- Left: Quick Presets Sidebar -->
                    <div class="w-full md:w-36 p-3 space-y-1 bg-canvas/20 shrink-0 text-xs">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted px-2 block mb-1.5">Presets</span>
                        <button type="button" @click="applyPresetInModal('today')" class="w-full text-left px-2 py-1.5 rounded-lg hover:bg-canvas text-ink font-medium transition text-xs">Today</button>
                        <button type="button" @click="applyPresetInModal('yesterday')" class="w-full text-left px-2 py-1.5 rounded-lg hover:bg-canvas text-ink font-medium transition text-xs">Yesterday</button>
                        <button type="button" @click="applyPresetInModal('week')" class="w-full text-left px-2 py-1.5 rounded-lg hover:bg-canvas text-ink font-medium transition text-xs">This Week</button>
                        <button type="button" @click="applyPresetInModal('last7')" class="w-full text-left px-2 py-1.5 rounded-lg hover:bg-canvas text-ink font-medium transition text-xs">Last 7 Days</button>
                        <button type="button" @click="applyPresetInModal('month')" class="w-full text-left px-2 py-1.5 rounded-lg hover:bg-canvas text-ink font-medium transition text-xs">This Month</button>
                        <button type="button" @click="applyPresetInModal('lastMonth')" class="w-full text-left px-2 py-1.5 rounded-lg hover:bg-canvas text-ink font-medium transition text-xs">Last Month</button>
                        <button type="button" @click="applyPresetInModal('quarter')" class="w-full text-left px-2 py-1.5 rounded-lg hover:bg-canvas text-ink font-medium transition text-xs">This Quarter</button>
                        <button type="button" @click="applyPresetInModal('year')" class="w-full text-left px-2 py-1.5 rounded-lg hover:bg-canvas text-ink font-medium transition text-xs">This Year</button>
                    </div>

                    <!-- Right: Flatpickr 2-month Calendar -->
                    <div class="flex-1 p-3 sm:p-4 flex items-center justify-center">
                        <div x-ref="calendar" class="w-full flex justify-center"></div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-5 py-3.5 border-t border-border bg-canvas/40">
                    <div class="flex items-center gap-2 text-xs">
                        <span class="text-muted font-medium">Selected Range:</span>
                        <span class="font-bold text-ink" x-text="draftLabel"></span>
                    </div>

                    <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end">
                        <button 
                            type="button" 
                            @click="closeModal()" 
                            class="px-4 py-2 border border-border bg-white text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="button" 
                            @click="applyCustomRange()" 
                            :disabled="!draftFrom || !draftTo"
                            class="px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition disabled:opacity-50 disabled:cursor-not-allowed shadow-xs"
                        >
                            Apply Range
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
