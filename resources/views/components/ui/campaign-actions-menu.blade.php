@props(['row'])

@php
    $rowObj = is_object($row) ? $row : (object) $row;
    $mappingId = $rowObj->mapping_id ?? ($rowObj->id ?? 0);
    $sourceType = $rowObj->source_type ?? 'campaign';
    $metaRecId = $rowObj->meta_record_id ?? null;
@endphp

<div class="inline-flex" x-id="['campaign-actions']" x-data="{
    open: false, left: 0, top: 0, isMobile: false,
    checkMobile() { this.isMobile = window.innerWidth < 640; },
    show() {
        this.checkMobile();
        const r = this.$refs.trigger.getBoundingClientRect();
        if (this.isMobile) {
            this.left = 0;
            this.top = 0;
        } else {
            this.left = Math.max(8, Math.min(r.right - 190, window.innerWidth - 200));
            this.top = Math.max(8, Math.min(r.bottom + 4, window.innerHeight - 200));
        }
        this.open = true;
        this.$nextTick(() => this.$refs.firstAction?.focus());
    },
    close() { this.open = false; this.$refs.trigger.focus(); }
}" @keydown.escape.stop.prevent="close()" @resize.window="checkMobile()" x-on:livewire:navigating.window="open=false">
    <button x-ref="trigger" type="button" @click.stop="open ? close() : show()" @keydown.arrow-down.prevent="show()"
        :aria-expanded="open" :aria-controls="$id('campaign-actions')" aria-haspopup="menu"
        title="Campaign options" aria-label="Campaign options"
        class="p-1.5 text-muted hover:text-ink hover:bg-canvas rounded-lg transition focus-visible:outline-2 focus-visible:outline-primary cursor-pointer">
        <x-ui.team-icon name="more" />
    </button>
    <template x-teleport="body">
        <div>
            <!-- Mobile Backdrop -->
            <div x-show="open" x-cloak @click="open=false"
                class="fixed inset-0 bg-ink/40 z-[85] sm:hidden backdrop-blur-xs transition-opacity"></div>

            <!-- Menu Container (Bottom sheet on mobile, anchored popover on desktop) -->
            <div x-show="open" x-cloak :id="$id('campaign-actions')"
                @click.outside="if (!isMobile) open=false"
                @keydown.escape.stop.prevent="close()"
                @keydown.tab="open=false"
                :style="!isMobile ? { left: left + 'px', top: top + 'px' } : {}"
                role="menu"
                aria-label="Campaign options"
                class="fixed z-[90] max-h-[85vh] overflow-y-auto bg-surface text-ink shadow-2xl transition-all
                       inset-x-0 bottom-0 rounded-t-2xl border-t border-border p-3 space-y-1
                       sm:inset-auto sm:w-48 sm:rounded-xl sm:border sm:p-1.5 sm:space-y-0.5">

                <!-- Mobile Header -->
                <div class="sm:hidden flex items-center justify-between pb-2 border-b border-border mb-1 px-1">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                        <span class="text-xs font-bold text-ink uppercase tracking-wider">Campaign Options</span>
                    </div>
                    <button type="button" @click="open=false" class="p-1 rounded-md text-muted hover:text-ink">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- 1. Edit Mapping -->
                <button x-ref="firstAction" type="button" role="menuitem"
                    wire:click="editCampaignMapping({{ $mappingId }}, '{{ $sourceType }}')"
                    @click.stop="open=false"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-ink hover:bg-canvas hover:text-primary transition cursor-pointer">
                    <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span>Edit Mapping</span>
                </button>

                <!-- 2. Fetch Leads (if Meta record) -->
                @if(!empty($metaRecId))
                    <button type="button" role="menuitem"
                        wire:click="fetchMetaCampaignLeads({{ $metaRecId }})"
                        @click.stop="open=false"
                        class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-ink hover:bg-canvas hover:text-primary transition cursor-pointer">
                        <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Fetch Leads</span>
                    </button>
                @endif
            </div>
        </div>
    </template>
</div>
