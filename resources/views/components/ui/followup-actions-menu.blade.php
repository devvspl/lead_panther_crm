@props(['followup'])

@php
    $fu = $followup;
    $leadRecord = $fu->lead;
    $rawPhone = $leadRecord?->mobile ?: ($leadRecord?->phone ?: '');
    $digitsOnly = preg_replace('/[^0-9]/', '', (string)$rawPhone);
    $waNumber = $digitsOnly;
    if (strlen($waNumber) === 10) {
        $waNumber = '91' . $waNumber;
    }
    $hasPhone = !empty($rawPhone) && strlen($digitsOnly) >= 5;
    $leadId = (int) ($leadRecord?->id ?: $fu->lead_id);

    $isPending = $fu->status === 'pending';
    $canAccept = $isPending && $fu->isRequested() && in_array($fu->request_status, ['pending', null])
        && (auth()->id() === ($fu->assigned_to ?: $leadRecord?->assigned_to) || auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']));
@endphp

<div class="inline-flex" x-id="['followup-actions']" x-data="{
    open: false, left: 0, top: 0, isMobile: false,
    checkMobile() { this.isMobile = window.innerWidth < 640; },
    show() {
        this.checkMobile();
        const r = this.$refs.trigger.getBoundingClientRect();
        if (this.isMobile) {
            this.left = 0;
            this.top = 0;
        } else {
            this.left = Math.max(8, Math.min(r.right - 220, window.innerWidth - 230));
            this.top = Math.max(8, Math.min(r.bottom + 4, window.innerHeight - 380));
        }
        this.open = true;
        this.$nextTick(() => this.$refs.firstAction?.focus());
    },
    close() { this.open = false; this.$refs.trigger.focus(); }
}" @keydown.escape.stop.prevent="close()" @resize.window="checkMobile()" x-on:livewire:navigating.window="open=false">
    <button x-ref="trigger" type="button" @click.stop="open ? close() : show()" @keydown.arrow-down.prevent="show()" :aria-expanded="open" :aria-controls="$id('followup-actions')" aria-haspopup="menu" title="Follow-up options" aria-label="Follow-up options for {{ $leadRecord?->name ?? 'Lead' }}" class="p-1.5 text-muted hover:text-ink hover:bg-canvas rounded-lg transition focus-visible:outline-2 focus-visible:outline-primary cursor-pointer">
        <x-ui.team-icon name="more" />
    </button>
    <template x-teleport="body">
        <div>
            <!-- Mobile Backdrop -->
            <div x-show="open" x-cloak @click="open=false"
                class="fixed inset-0 bg-ink/40 z-[85] sm:hidden backdrop-blur-xs transition-opacity"></div>

            <!-- Menu Container (Bottom sheet on mobile, anchored popover on desktop) -->
            <div x-show="open" x-cloak :id="$id('followup-actions')"
                @click.outside="if (!isMobile) open=false"
                @keydown.escape.stop.prevent="close()"
                @keydown.tab="open=false"
                :style="!isMobile ? { left: left + 'px', top: top + 'px' } : {}"
                role="menu"
                aria-label="Follow-up options"
                class="fixed z-[90] max-h-[85vh] overflow-y-auto bg-surface text-ink shadow-2xl transition-all
                       inset-x-0 bottom-0 rounded-t-2xl border-t border-border p-3 space-y-1
                       sm:inset-auto sm:w-56 sm:rounded-xl sm:border sm:p-1.5 sm:space-y-0.5">

                <!-- Mobile Header -->
                <div class="sm:hidden flex items-center justify-between pb-2 border-b border-border mb-1 px-1">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                        <span class="text-xs font-bold text-ink uppercase tracking-wider">Follow-up Actions</span>
                        @if($leadRecord?->lead_code)
                            <span class="text-[11px] font-mono font-bold text-muted">#{{ $leadRecord->lead_code }}</span>
                        @endif
                    </div>
                    <button type="button" @click="open=false" class="p-1 rounded-md text-muted hover:text-ink">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- 1. View Lead Details Drawer -->
                <button x-ref="firstAction" type="button" role="menuitem"
                    @click.stop="open=false; $dispatch('open-lead-detail', { id: {{ $leadId }} }); if (window.Livewire) Livewire.dispatch('open-lead-detail', { id: {{ $leadId }} });"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold hover:bg-canvas hover:text-primary transition cursor-pointer">
                    <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>View Lead Details</span>
                </button>

                <!-- 2. Open Lead in Leads Pipeline -->
                <a href="{{ route('leads.index') }}?search={{ $leadRecord?->lead_code ?: $fu->lead_id }}&notification_lead={{ $leadRecord?->id }}" wire:navigate
                    role="menuitem"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold hover:bg-canvas hover:text-primary transition cursor-pointer">
                    <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                    </svg>
                    <span>Open in Pipeline</span>
                </a>

                @if($hasPhone)
                    <!-- Call -->
                    <a href="tel:{{ $rawPhone }}" role="menuitem"
                        class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-emerald-700 hover:bg-emerald-50 transition cursor-pointer">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span>Call ({{ $rawPhone }})</span>
                    </a>

                    <!-- WhatsApp -->
                    <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener noreferrer" role="menuitem"
                        class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-emerald-700 hover:bg-emerald-50 transition cursor-pointer">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                        <span>WhatsApp Chat</span>
                    </a>
                @endif

                @if($isPending)
                    <div class="border-t border-border my-1"></div>

                    @if($canAccept)
                        <button type="button" role="menuitem"
                            @click.stop="open=false" wire:click="acknowledgeRequest({{ $fu->id }}, 'accepted')"
                            class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-blue-700 hover:bg-blue-50 transition cursor-pointer">
                            <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Accept Request</span>
                        </button>
                    @endif

                    <!-- Complete Follow-up -->
                    <button type="button" role="menuitem"
                        @click.stop="open=false" wire:click="markDone({{ $fu->id }})"
                        class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-emerald-700 hover:bg-emerald-50 transition cursor-pointer">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Complete Follow-up</span>
                    </button>

                    <!-- Reschedule -->
                    <button type="button" role="menuitem"
                        @click.stop="open=false" wire:click="openReschedule({{ $fu->id }})"
                        class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold hover:bg-canvas hover:text-primary transition cursor-pointer">
                        <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>Reschedule</span>
                    </button>

                    <!-- Reassign -->
                    <button type="button" role="menuitem"
                        @click.stop="open=false" wire:click="openReassign({{ $fu->id }})"
                        class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold hover:bg-canvas hover:text-primary transition cursor-pointer">
                        <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        <span>Reassign</span>
                    </button>

                    <div class="border-t border-border my-1"></div>

                    <!-- Cancel -->
                    <button type="button" role="menuitem"
                        @click.stop="open=false" wire:click="openCancel({{ $fu->id }})"
                        class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-rose-600 hover:bg-rose-50 hover:text-rose-700 transition cursor-pointer">
                        <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Cancel Follow-up</span>
                    </button>
                @else
                    <!-- Completed or Cancelled Follow-up -->
                    <div class="border-t border-border my-1"></div>

                    <button type="button" role="menuitem"
                        @click.stop="open=false; $dispatch('open-lead-detail', { id: {{ $leadId }}, tab: 'follow_up' }); if (window.Livewire) Livewire.dispatch('open-lead-detail', { id: {{ $leadId }}, tab: 'follow_up' });"
                        class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-primary hover:bg-primary/5 transition cursor-pointer">
                        <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Schedule Follow-up</span>
                    </button>
                @endif
            </div>
        </div>
    </template>
</div>
