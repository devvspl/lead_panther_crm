<div>
    <x-modal name="org-member-details" :show="$show" maxWidth="xl">
        <div x-init="$watch('show', value => { if (!value &amp;&amp; $wire.show) $wire.close() })" class="p-6 space-y-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-ink">Team Member Details</h2>
                <button type="button" wire:click="close" aria-label="Close member details" class="text-muted px-2 py-1">✕</button>
            </div>
            @if($member)
                <div class="text-xs text-muted">ID #{{ $member->id }} · {{ $member->getRoleNames()->join(', ') }} · Joined {{ $member->created_at?->format('d M Y') }}</div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="$toggle('editing')" class="px-3 py-2 border border-border rounded-lg text-xs text-ink">{{ $editing ? 'Cancel Edit' : 'Edit Details' }}</button>
                    <button type="button" wire:click="$toggle('changingPassword')" class="px-3 py-2 border border-border rounded-lg text-xs text-ink">Change Password</button>
                </div>
                @if($editing)
                    <form wire:submit="saveDetails" class="space-y-3">
                        @foreach(['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone'] as $field => $label)
                            <div><label for="member-{{ $field }}" class="block text-xs font-semibold text-ink mb-1">{{ $label }}</label>
                                <x-text-input id="member-{{ $field }}" variant="compact" wire:model="{{ $field }}" />
                                <x-input-error :messages="$errors->get($field)" /></div>
                        @endforeach
                        <div><label class="block text-xs font-semibold text-ink mb-1">Account Status</label>
                            <livewire:shared.searchable-select wire:model="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :key="'member-status-'.$memberId" />
                            <x-input-error :messages="$errors->get('status')" /></div>
                        <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 bg-ink text-white rounded-lg text-xs">Save Details</button>
                    </form>
                @else
                    <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                        @foreach(['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'status' => 'Status'] as $field => $label)
                            <div><dt class="text-xs text-muted">{{ $label }}</dt><dd class="text-ink break-all mt-1">{{ $member->$field ?: '—' }}</dd></div>
                        @endforeach
                    </dl>
                @endif
                @if($changingPassword)
                    <form wire:submit="changePassword" class="space-y-3 border-t border-border pt-4">
                        <div><label for="member-new-password" class="block text-xs font-semibold text-ink mb-1">New Password</label>
                            <x-ui.password-input id="member-new-password" wire:model="password" autocomplete="new-password" />
                            <x-input-error :messages="$errors->get('password')" /></div>
                        <div><label for="member-confirm-password" class="block text-xs font-semibold text-ink mb-1">Confirm Password</label>
                            <x-ui.password-input id="member-confirm-password" wire:model="password_confirmation" autocomplete="new-password" /></div>
                        <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 bg-ink text-white rounded-lg text-xs">Save New Password</button>
                    </form>
                @endif
            @endif
        </div>
    </x-modal>
</div>
