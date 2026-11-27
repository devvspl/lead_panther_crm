@props(['member', 'organization'])

@php
    $memberId = (int) $member->id;
    $memberName = $member->name ?? 'Team Member';
    $orgName = $organization->name ?? 'Organization';
@endphp

<div class="inline-flex" x-id="['org-member-actions']" x-data="{
    open: false, left: 0, top: 0, isMobile: false,
    checkMobile() { this.isMobile = window.innerWidth < 640; },
    show() {
        this.checkMobile();
        const r = this.$refs.trigger.getBoundingClientRect();
        if (this.isMobile) {
            this.left = 0;
            this.top = 0;
        } else {
            this.left = Math.max(8, Math.min(r.right - 210, window.innerWidth - 220));
            this.top = Math.max(8, Math.min(r.bottom + 4, window.innerHeight - 240));
        }
        this.open = true;
        this.$nextTick(() => this.$refs.firstAction?.focus());
    },
    close() { this.open = false; this.$refs.trigger.focus(); }
}" @keydown.escape.stop.prevent="close()" @resize.window="checkMobile()" x-on:livewire:navigating.window="open=false">
    <button x-ref="trigger" type="button" @click.stop="open ? close() : show()" @keydown.arrow-down.prevent="show()"
        :aria-expanded="open" :aria-controls="$id('org-member-actions')" aria-haspopup="menu"
        title="Member options" aria-label="Options for {{ $memberName }}"
        class="p-1.5 text-muted hover:text-ink hover:bg-canvas rounded-lg transition focus-visible:outline-2 focus-visible:outline-primary cursor-pointer">
        <x-ui.team-icon name="more" />
    </button>
    <template x-teleport="body">
        <div>
            <!-- Mobile Backdrop -->
            <div x-show="open" x-cloak @click="open=false"
                class="fixed inset-0 bg-ink/40 z-[85] sm:hidden backdrop-blur-xs transition-opacity"></div>

            <!-- Menu Container (Bottom sheet on mobile, anchored popover on desktop) -->
            <div x-show="open" x-cloak :id="$id('org-member-actions')"
                @click.outside="if (!isMobile) open=false"
                @keydown.escape.stop.prevent="close()"
                @keydown.tab="open=false"
                :style="!isMobile ? { left: left + 'px', top: top + 'px' } : {}"
                role="menu"
                aria-label="Options for {{ $memberName }}"
                class="fixed z-[90] max-h-[85vh] overflow-y-auto bg-surface text-ink shadow-2xl transition-all
                       inset-x-0 bottom-0 rounded-t-2xl border-t border-border p-3 space-y-1
                       sm:inset-auto sm:w-52 sm:rounded-xl sm:border sm:p-1.5 sm:space-y-0.5">

                <!-- Mobile Header -->
                <div class="sm:hidden flex items-center justify-between pb-2 border-b border-border mb-1 px-1">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                        <span class="text-xs font-bold text-ink uppercase tracking-wider truncate">{{ $memberName }}</span>
                    </div>
                    <button type="button" @click="open=false" class="p-1 rounded-md text-muted hover:text-ink">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- 1. Edit Details -->
                <button x-ref="firstAction" type="button" role="menuitem"
                    wire:click="$dispatch('open-org-member', { memberId: {{ $memberId }} })"
                    @click.stop="open=false"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-ink hover:bg-canvas hover:text-primary transition cursor-pointer">
                    <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span>Edit Details</span>
                </button>

                <!-- 2. Impersonate User -->
                <a href="{{ route('admin.users.impersonate', $memberId) }}" role="menuitem"
                    @click="open=false"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-ink hover:bg-canvas hover:text-purple-700 transition cursor-pointer">
                    <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    <span>Impersonate User</span>
                </a>

                <!-- 3. Generate Reset / Activation Link -->
                <button type="button" role="menuitem"
                    wire:click="generateUserActivationLink({{ $memberId }})"
                    @click.stop="open=false"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-ink hover:bg-canvas hover:text-primary transition cursor-pointer">
                    <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                    <span>Activation Link</span>
                </button>

                <!-- 4. Remove / Unassign -->
                <button type="button" role="menuitem"
                    wire:click="removeUserFromOrganization({{ $memberId }})"
                    wire:confirm="Remove {{ $memberName }} from {{ $orgName }}?"
                    @click.stop="open=false"
                    class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 sm:py-1.5 text-left text-xs font-semibold text-danger hover:bg-danger/10 transition cursor-pointer">
                    <svg class="w-4 h-4 text-danger shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    <span>Remove from Client</span>
                </button>
            </div>
        </div>
    </template>
</div>
