<div class="flex items-center gap-3 whitespace-nowrap text-sm">
    <button type="button" wire:click="details({{ (int)$lead->id }})" class="text-primary hover:underline" title="View archived lead" aria-label="View archived lead">Details</button>
    <button type="button" wire:click="prepare({{ (int)$lead->id }}, 'restore')" class="text-success hover:underline" title="Restore lead">Restore</button>
    <button type="button" wire:click="prepare({{ (int)$lead->id }}, 'delete')" class="text-danger hover:underline" title="Permanently delete lead">Delete</button>
</div>
