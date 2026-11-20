@props(['organization'])
<div class="flex flex-wrap items-center justify-end gap-1 text-muted">
    <x-ui.dropdown align="right" width="56">
        <x-slot:trigger><button type="button" title="More Options" aria-label="More Options for {{ $organization->name }}" aria-haspopup="menu" :aria-expanded="open" class="p-2 rounded-lg hover:bg-canvas hover:text-primary focus-visible:outline-2 focus-visible:outline-primary"><x-ui.team-icon name="more" /></button></x-slot:trigger>
        <x-slot:content>
            <div role="menu" aria-label="Client options">
                <a role="menuitem" href="{{ route('admin.organizations.show', $organization->id) }}" wire:navigate class="flex items-center gap-2 px-3 py-2 text-xs text-ink hover:bg-canvas">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7C20.268 16.057 16.477 19 12 19s-8.268-2.943-9.542-7z" /></svg>
                    View Client
                </a>
                <button type="button" role="menuitem" wire:click="openOrgDetailsModal({{ $organization->id }}, 'edit')" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-ink hover:bg-canvas"><x-ui.team-icon name="edit" />Edit Client</button>
                <a role="menuitem" href="{{ route('admin.organizations.show', ['organization'=>$organization->id, 'tab'=>'team']) }}" wire:navigate class="flex items-center gap-2 px-3 py-2 text-xs text-ink hover:bg-canvas"><x-ui.team-icon name="team" />Manage Team</a>
                <button type="button" role="menuitem" wire:click="toggleStatus({{ $organization->id }})" wire:confirm="{{ $organization->status === 'active' ? 'Suspend' : 'Activate' }} {{ $organization->name }}?" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs hover:bg-canvas {{ $organization->status === 'active' ? 'text-danger' : 'text-success' }}"><x-ui.team-icon name="power" />{{ $organization->status === 'active' ? 'Suspend Client' : 'Activate Client' }}</button>
                <button type="button" role="menuitem" wire:click="openUserModal({{ $organization->id }})" class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-ink hover:bg-canvas"><x-ui.team-icon name="team" />Manage Users</button>
                <a role="menuitem" href="{{ route('admin.organizations.show', ['organization'=>$organization->id, 'tab'=>'credits']) }}" wire:navigate class="flex items-center gap-2 px-3 py-2 text-xs text-ink hover:bg-canvas"><x-ui.team-icon name="wallet" />Credits & Wallet</a>
            </div>
        </x-slot:content>
    </x-ui.dropdown>
</div>
