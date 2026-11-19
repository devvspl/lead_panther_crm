<div>
    <x-modal name="archive-lead-confirmation" maxWidth="lg">
        <form wire:submit="archive" class="p-6 space-y-4">
            <h2 class="text-lg font-bold text-ink">Archive Lead</h2>
            <p class="text-sm text-muted">Hide this lead from active lists, client panels and scheduled processing. Its history and assignments will be preserved.</p>
            <div><label for="archive-reason" class="block text-sm font-semibold text-ink">Reason (optional)</label><textarea id="archive-reason" wire:model="reason" maxlength="2000" class="w-full rounded-lg border-border bg-surface text-ink"></textarea>@error('reason')<p class="text-danger">{{ $message }}</p>@enderror</div>
            <label class="flex items-center gap-2 text-sm text-ink"><input type="checkbox" wire:model="confirmed"> Confirm archive</label>
            @error('confirmed')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-2 border-t border-border pt-4">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'archive-lead-confirmation')">Cancel</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled">Archive Lead</x-ui.button>
            </div>
        </form>
    </x-modal>
</div>
