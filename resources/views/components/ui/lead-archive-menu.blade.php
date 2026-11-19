@props(['id'])
@if(auth()->user()?->hasAnyRole(['Super Admin','super-admin']))
<div class="inline-flex" x-id="['lead-actions']" x-data="{
    open: false, left: 0, top: 0,
    show() {
        const r=this.$refs.trigger.getBoundingClientRect();
        this.left=Math.max(8,Math.min(r.right-176,innerWidth-184));
        this.top=Math.max(8,Math.min(r.bottom+4,innerHeight-64));
        this.open=true;
        this.$nextTick(()=>this.$refs.action.focus());
    },
    close() { this.open=false; this.$refs.trigger.focus(); }
}" @keydown.escape.stop.prevent="close()" @scroll.window.capture="open=false" @resize.window="open=false" x-on:livewire:navigating.window="open=false">
    <button x-ref="trigger" type="button" @click.stop="open ? close() : show()" @keydown.arrow-down.prevent="show()" :aria-expanded="open" :aria-controls="$id('lead-actions')" aria-haspopup="menu" title="Lead actions" aria-label="Lead actions" class="p-1.5 text-muted hover:text-primary focus-visible:outline-2 focus-visible:outline-primary"><x-ui.team-icon name="more" /></button>
    <template x-teleport="body">
        <div x-show="open" x-cloak :id="$id('lead-actions')" @click.outside="open=false" @keydown.escape.stop.prevent="close()" @keydown.tab="open=false" :style="{left:left+'px',top:top+'px'}" role="menu" aria-label="Lead actions" class="fixed z-[60] w-44 rounded-lg border border-border bg-surface p-1 shadow-xl">
            <button x-ref="action" type="button" role="menuitem" @click.stop="open=false; $dispatch('archive-lead', {id:{{ (int)$id }}})" class="w-full rounded px-3 py-2 text-left text-sm text-ink hover:bg-canvas focus:bg-canvas">Archive</button>
        </div>
    </template>
</div>
@endif
