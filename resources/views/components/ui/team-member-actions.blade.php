@props(['member'])
@if($member->hasAnyRole(['Executive','Sales Executive','sales-executive']) && !$member->hasAnyRole(['Team Leader','team-leader','Super Admin','super-admin','Builder/Developer','Builder','builder','builder-admin']))
    <div class="inline-flex items-center" x-data="{
        open: false, left: 0, top: 0,
        close(restoreFocus = false) {
            this.open = false;
            if (restoreFocus) this.$refs.trigger.focus();
        },
        toggle() {
            if (this.open) { this.close(); return; }
            const rect = this.$refs.trigger.getBoundingClientRect();
            this.left = Math.max(8, Math.min(rect.right - 192, window.innerWidth - 200));
            this.top = rect.bottom + 4;
            this.open = true;
            this.$nextTick(() => {
                const height = this.$refs.menu.offsetHeight;
                if (this.top + height > window.innerHeight - 8) this.top = Math.max(8, rect.top - height - 4);
                this.$refs.menu.querySelector('button').focus();
            });
        },
        move(event, step) {
            const items = [...this.$refs.menu.querySelectorAll('button')];
            const index = items.indexOf(document.activeElement);
            items[(index + step + items.length) % items.length].focus();
        }
    }" @keydown.escape.stop.prevent="close(true)" @resize.window="close()" @scroll.window.capture="close()" x-on:livewire:navigating.window="close()">
        <button x-ref="trigger" type="button" @click.stop="toggle()" @keydown.arrow-down.prevent="if (!open) toggle()" :aria-expanded="open" aria-haspopup="menu" aria-controls="team-member-actions-{{ (int)$member->id }}" title="Member actions" aria-label="Actions for {{ $member->name }}" class="inline-flex items-center justify-center p-2 text-muted hover:text-primary focus-visible:outline-2 focus-visible:outline-primary">
            <x-ui.team-icon name="more" class="w-5 h-5" />
        </button>
        <template x-teleport="body">
            <div x-ref="menu" x-show="open" x-cloak id="team-member-actions-{{ (int)$member->id }}" role="menu" aria-label="Member actions" :style="{ left: left + 'px', top: top + 'px' }" @click.outside="if (open) close()" @click="close(true)" @keydown.escape.stop.prevent="close(true)" @keydown.arrow-down.prevent="move($event, 1)" @keydown.arrow-up.prevent="move($event, -1)" @keydown.tab="close()" class="fixed z-50 w-48 rounded-lg border border-border bg-surface py-1 text-sm text-ink shadow-xl">
                <button type="button" role="menuitem" wire:click="editTeamMember({{ (int)$member->id }})" class="block w-full px-4 py-2 text-left hover:bg-primary/10 focus:bg-primary/10 focus:outline-none">Edit / Reassign</button>
                <button type="button" role="menuitem" wire:click="toggleMember({{ (int)$member->id }})" class="block w-full px-4 py-2 text-left hover:bg-primary/10 focus:bg-primary/10 focus:outline-none">{{ $member->membership_status === 'Active' ? 'Deactivate' : 'Activate' }}</button>
                <button type="button" role="menuitem" wire:click="removeTeamMember({{ (int)$member->id }})" wire:confirm="Remove this member from the team? Their user account will remain." class="block w-full px-4 py-2 text-left text-danger hover:bg-danger/10 focus:bg-danger/10 focus:outline-none">Remove</button>
            </div>
        </template>
    </div>
@else
    <span class="text-muted">—</span>
@endif
