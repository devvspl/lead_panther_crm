@props(['id'])

@php
    $actionUser = auth()->user();
    $canArchive = $actionUser && $actionUser->hasAnyRole(['Super Admin', 'super-admin']);
    $canConfigDisplay = $actionUser && ($actionUser->hasAnyRole(['Super Admin', 'super-admin', 'Client', 'client', 'Builder', 'builder', 'Builder/Developer', 'Admin', 'admin']) || $actionUser->organization_id);
    $hasAnyAction = $canArchive || $canConfigDisplay;
@endphp

@if($hasAnyAction)
<div class="inline-flex" x-id="['lead-actions']" x-data="{
    open: false, left: 0, top: 0,
    show() {
        const r=this.$refs.trigger.getBoundingClientRect();
        this.left=Math.max(8,Math.min(r.right-192,innerWidth-200));
        this.top=Math.max(8,Math.min(r.bottom+4,innerHeight-100));
        this.open=true;
        this.$nextTick(()=>this.$refs.firstAction?.focus());
    },
    close() { this.open=false; this.$refs.trigger.focus(); }
}" @keydown.escape.stop.prevent="close()" @scroll.window.capture="open=false" @resize.window="open=false" x-on:livewire:navigating.window="open=false">
    <button x-ref="trigger" type="button" @click.stop="open ? close() : show()" @keydown.arrow-down.prevent="show()" :aria-expanded="open" :aria-controls="$id('lead-actions')" aria-haspopup="menu" title="Lead options" aria-label="Lead options" class="p-1.5 text-muted hover:text-ink hover:bg-canvas rounded-lg transition focus-visible:outline-2 focus-visible:outline-primary cursor-pointer"><x-ui.team-icon name="more" /></button>
    <template x-teleport="body">
        <div x-show="open" x-cloak :id="$id('lead-actions')" @click.outside="open=false" @keydown.escape.stop.prevent="close()" @keydown.tab="open=false" :style="{left:left+'px',top:top+'px'}" role="menu" aria-label="Lead options" class="fixed z-[70] w-48 rounded-lg border border-border bg-surface p-1 shadow-xl space-y-0.5">
            @if($canConfigDisplay)
            <button x-ref="firstAction" type="button" role="menuitem"
                @click.stop="open=false; $dispatch('open-display-config', { leadId: {{ (int)$id }} })"
                class="w-full flex items-center gap-2 rounded-md px-3 py-2 text-left text-xs font-semibold text-ink hover:bg-canvas hover:text-primary transition cursor-pointer">
                <svg class="w-3.5 h-3.5 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Configure Display</span>
            </button>
            @endif
            @if($canArchive)
            <button @if(!$canConfigDisplay) x-ref="firstAction" @endif type="button" role="menuitem"
                @click.stop="open=false; $dispatch('archive-lead', {id:{{ (int)$id }}})"
                class="w-full flex items-center gap-2 rounded-md px-3 py-2 text-left text-xs font-semibold text-rose-600 hover:bg-rose-50 hover:text-rose-700 transition cursor-pointer">
                <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
                <span>Archive Lead</span>
            </button>
            @endif
        </div>
    </template>
</div>
@endif
