@props(['id', 'lead' => null])

@php
    $actionLead = is_object($lead) ? $lead : (is_array($lead) ? (object)$lead : null);
    $leadId = (int) ($id ?? $actionLead?->id);
    if (! $actionLead && $leadId) {
        $actionLead = \App\Models\Lead::find($leadId);
    }

    $actionUser = auth()->user();
    $rawPhone = $actionLead?->mobile ?? '';
    $digitsOnly = preg_replace('/[^0-9]/', '', (string)$rawPhone);
    $waNumber = $digitsOnly;
    if (strlen($waNumber) === 10) {
        $waNumber = '91' . $waNumber;
    }
    $hasPhone = !empty($rawPhone) && strlen($digitsOnly) >= 10;

    $isSuperAdmin = $actionUser && $actionUser->hasAnyRole(['Super Admin', 'super-admin']);
    $isSalesUser = $actionUser && (\App\Support\NavigationConfig::getRoleKey($actionUser) === 'sales-executive' || $actionUser->hasRole(['sales-executive', 'Sales Executive', 'Executive', 'sales']));
    $isBuilder = $actionUser && (\App\Support\NavigationConfig::getRoleKey($actionUser) === 'builder' || $actionUser->hasRole(['Builder', 'builder']));
    $isAdmin = $actionUser && $actionUser->hasRole(['Admin', 'admin', 'Account Manager', 'account-manager', 'super-admin']);
    $canArchive = $isSuperAdmin;
    $canConfigDisplay = $isSuperAdmin;
    $canJourney = $isSuperAdmin;

    // Assign check
    $currentStage = $actionLead?->current_stage ?? '';
    $normalizedStage = \App\Models\Lead::normalizeStage($currentStage);
    $canAssign = !$isSalesUser && (!\App\Models\Lead::usesSalesPanel() || ($normalizedStage !== 'pending_credit' && !in_array($normalizedStage, ['closed_won', 'lost', 'invalid', 'unreachable', 'replaced'])));

    // Unassign check (Super Admin, Admin, Builder when lead is currently assigned)
    $isAssigned = !empty($actionLead?->assigned_to);
    $hasExecutiveAction = !empty($actionLead?->executive_first_response_at) || !empty($actionLead?->first_response_at);
    $canUnassign = $isAssigned && ($isSuperAdmin || $isAdmin || $isBuilder);

    // Replacement check
    $canReplace = !$isSalesUser && !\App\Support\TeamAccess::isLeader($actionUser) && (!\App\Models\Lead::usesSalesPanel() || (!in_array($normalizedStage, ['closed_won', 'booking', 'payment_received', 'replaced', 'pending_credit']) && !in_array($actionLead?->replacement_status ?? '', ['pending', 'approved'])));

    // Push to client check
    $canPush = $actionUser && ($isSuperAdmin || $actionUser->hasRole(['Admin', 'admin', 'Account Manager', 'account-manager', 'super-admin']));
@endphp

<div class="inline-flex" x-id="['lead-actions']" x-data="{
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
    <button x-ref="trigger" type="button" @click.stop="open ? close() : show()" @keydown.arrow-down.prevent="show()" :aria-expanded="open" :aria-controls="$id('lead-actions')" aria-haspopup="menu" title="Lead options" aria-label="Lead options" class="p-1.5 text-muted hover:text-ink hover:bg-canvas rounded-lg transition focus-visible:outline-2 focus-visible:outline-primary cursor-pointer"><x-ui.team-icon name="more" /></button>
    <template x-teleport="body">
        <div>
            <!-- Mobile Backdrop -->
            <div x-show="open" x-cloak @click="open=false"
                class="fixed inset-0 bg-ink/40 z-[85] sm:hidden backdrop-blur-xs transition-opacity"></div>

            <!-- Menu Container (Bottom sheet on mobile, anchored popover on desktop) -->
            <div x-show="open" x-cloak :id="$id('lead-actions')"
                @click.outside="if (!isMobile) open=false"
                @keydown.escape.stop.prevent="close()"
                @keydown.tab="open=false"
                :style="!isMobile ? { left: left + 'px', top: top + 'px' } : {}"
                role="menu"
                aria-label="Lead options"
                class="fixed z-[90] max-h-[85vh] overflow-y-auto bg-surface text-ink shadow-2xl transition-all
                       inset-x-0 bottom-0 rounded-t-2xl border-t border-border p-3 space-y-1
                       sm:inset-auto sm:w-56 sm:rounded-xl sm:border sm:p-1.5 sm:space-y-0.5">

                <!-- Mobile Header -->
                <div class="sm:hidden flex items-center justify-between pb-2 border-b border-border mb-1 px-1">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                        <span class="text-xs font-bold text-ink uppercase tracking-wider">Lead Options</span>
                        @if($actionLead?->lead_code)
                            <span class="text-[11px] font-mono font-bold text-muted">#{{ $actionLead->lead_code }}</span>
                        @endif
                    </div>
                    <button type="button" @click="open=false" class="p-1 rounded-md text-muted hover:text-ink">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- 1. View Details & Super Admin Editor -->
                @if($isSuperAdmin)
                <button type="button" role="menuitem"
                    @click.stop="open=false; $dispatch('open-super-admin-lead-modal', { id: {{ $leadId }} }); if (window.Livewire) Livewire.dispatch('open-super-admin-lead-modal', { id: {{ $leadId }} });"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-purple-700 hover:bg-purple-50 hover:text-purple-900 transition cursor-pointer"
                    title="Master Inspector &amp; Full Data Editor (Super Admin)">
                    <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span>Edit &amp; All Lead Data</span>
                </button>
                @endif

                <button x-ref="firstAction" type="button" role="menuitem"
                    @click.stop="open=false; $dispatch('open-lead-detail', { id: {{ $leadId }} }); if (window.Livewire) Livewire.dispatch('open-lead-detail', { id: {{ $leadId }} });"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold hover:bg-canvas hover:text-primary transition cursor-pointer">
                    <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>View Details</span>
                </button>

                <button type="button" role="menuitem"
                    @click.stop="open=false; $dispatch('open-lead-detail', { id: {{ $leadId }}, tab: 'stage_details' }); if (window.Livewire) Livewire.dispatch('open-lead-detail', { id: {{ $leadId }}, tab: 'stage_details' });"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold hover:bg-canvas hover:text-primary transition cursor-pointer">
                    <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>Follow-up</span>
                </button>

                @if($canAssign)
                <button type="button" role="menuitem"
                    @click.stop="open=false; $dispatch('open-lead-assign', { id: {{ $leadId }} }); if (window.Livewire) Livewire.dispatch('open-lead-assign', { id: {{ $leadId }} });"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold hover:bg-canvas hover:text-emerald-600 transition cursor-pointer"
                    title="{{ $isAssigned ? 'Reassign Lead' : 'Assign Lead' }}">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    <span>{{ $isAssigned ? 'Reassign Lead' : 'Assign Lead' }}</span>
                    <span class="sr-only">Assign Lead</span>
                </button>
                @endif

                @if($canUnassign)
                <button type="button" role="menuitem"
                    @click.stop="open=false; $dispatch('open-unassign-lead', { id: {{ $leadId }} }); if (window.Livewire) Livewire.dispatch('open-unassign-lead', { id: {{ $leadId }} });"
                    class="w-full flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-amber-700 hover:bg-amber-50 hover:text-amber-800 transition cursor-pointer"
                    title="{{ !$hasExecutiveAction ? 'Executive has not taken action. Click to remove assignment.' : 'Remove executive assignment from this lead' }}">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6" />
                        </svg>
                        <span class="truncate">Unassign Executive</span>
                    </div>
                    @if(!$hasExecutiveAction)
                        <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-800 shrink-0">No Action</span>
                    @endif
                </button>
                @endif

                <!-- 2. Call & WhatsApp -->
                @if($hasPhone)
                <div class="h-px bg-border my-1"></div>

                <a href="tel:{{ $rawPhone }}" role="menuitem"
                    @click="open=false"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold hover:bg-canvas hover:text-primary transition cursor-pointer">
                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    <span>Call ({{ $rawPhone }})</span>
                </a>

                <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener noreferrer" role="menuitem"
                    @click="open=false"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800 transition cursor-pointer">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                    </svg>
                    <span>WhatsApp</span>
                </a>
                @endif

                <!-- 3. Pipeline / Delivery / Replacement -->
                <div class="h-px bg-border my-1"></div>

                @if($canJourney)
                <button type="button" role="menuitem"
                    @click.stop="open=false; $dispatch('open-lead-journey', { id: {{ $leadId }} }); if (window.Livewire) Livewire.dispatch('open-lead-journey', { id: {{ $leadId }} });"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold hover:bg-canvas hover:text-primary transition cursor-pointer">
                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Lead Journey</span>
                </button>
                @endif

                @if($canPush)
                <button type="button" role="menuitem"
                    @click.stop="open=false; if (confirm('Push this lead using the saved client delivery settings? Confirm that you have permission to share it.')) { if ($wire && typeof $wire.pushLeadToClient === 'function') { $wire.pushLeadToClient({{ $leadId }}); } else if (window.Livewire) { Livewire.dispatch('push-lead-to-client', { id: {{ $leadId }} }); } }"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold hover:bg-canvas hover:text-primary transition cursor-pointer">
                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                    <span>Push to Client</span>
                </button>
                @endif

                @if($canReplace)
                <button type="button" role="menuitem"
                    @click.stop="open=false; $dispatch('open-return-lead', { id: {{ $leadId }} }); if (window.Livewire) Livewire.dispatch('open-return-lead', { id: {{ $leadId }} });"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold hover:bg-rose-50 text-rose-600 transition cursor-pointer">
                    <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H4m0 0l4-4m-4 4l4 4m6 4v1a3 3 0 003 3h3a3 3 0 003-3V7a3 3 0 00-3-3h-3a3 3 0 00-3 3v1" />
                    </svg>
                    <span>Replacement</span>
                </button>
                @endif

                <!-- 4. Configuration & Archive -->
                @if($canConfigDisplay || $canArchive)
                <div class="h-px bg-border my-1"></div>
                @endif

                @if($canConfigDisplay)
                <button type="button" role="menuitem"
                    @click.stop="open=false; $dispatch('open-display-config', { leadId: {{ $leadId }} }); if (window.Livewire) Livewire.dispatch('open-display-config', { leadId: {{ $leadId }} });"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold hover:bg-canvas hover:text-primary transition cursor-pointer">
                    <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Configure Display</span>
                </button>
                @endif

                @if($canArchive)
                <button type="button" role="menuitem"
                    @click.stop="open=false; $dispatch('archive-lead', { id: {{ $leadId }} }); if (window.Livewire) Livewire.dispatch('archive-lead', { id: {{ $leadId }} });"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-rose-600 hover:bg-rose-50 hover:text-rose-700 transition cursor-pointer">
                    <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                    <span>Archive Lead</span>
                </button>
                @endif

                <!-- Mobile Close Button -->
                <div class="sm:hidden pt-2 border-t border-border mt-1">
                    <button type="button" @click="open=false"
                        class="w-full py-2 px-3 rounded-lg bg-canvas text-center text-xs font-semibold text-muted hover:text-ink">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
