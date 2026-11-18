<div>
    <x-modal name="org-member-details" :show="$show" maxWidth="2xl">
        <div x-init="$watch('show', value => { if (!value &amp;&amp; $wire.show) $wire.close() })" class="p-6 space-y-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-ink">Team Member Details</h2>
                <button type="button" wire:click="close" aria-label="Close member details" class="text-muted px-2 py-1">✕</button>
            </div>
            @if($member)
                <div class="rounded-lg border border-border bg-canvas p-4 space-y-1">
                    <h3 class="font-semibold text-ink">{{ $member->name }}</h3>
                    <p class="text-xs text-muted break-all">{{ $member->email }}</p>
                    <div class="text-xs text-muted">ID #{{ $member->id }} · {{ $member->getRoleNames()->join(', ') }} · Joined {{ $member->created_at?->format('d M Y') }}</div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="$toggle('editing')" class="px-3 py-2 border border-border rounded-lg text-xs text-ink">{{ $editing ? 'Cancel Edit' : 'Edit Details' }}</button>
                    <button type="button" wire:click="$toggle('changingPassword')" class="px-3 py-2 border border-border rounded-lg text-xs text-ink">Change Password</button>
                </div>
                @if($editing)
                    <form wire:submit="saveDetails" class="grid sm:grid-cols-2 gap-4">
                        @foreach(['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone'] as $field => $label)
                            <div><label for="member-{{ $field }}" class="block text-xs font-semibold text-ink mb-1">{{ $label }}</label>
                                <x-text-input id="member-{{ $field }}" variant="compact" wire:model="{{ $field }}" />
                                <x-input-error :messages="$errors->get($field)" /></div>
                        @endforeach
                        <div><label class="block text-xs font-semibold text-ink mb-1">Account Status</label>
                            <livewire:shared.searchable-select wire:model.live="status" :value="$status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :show-clear-option="false" class="block w-full" :key="'member-status-'.$memberId.'-'.$status" />
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
                @if($member->hasAnyRole(['Executive', 'Sales Executive', 'sales-executive', 'Team Leader', 'team-leader']) && !$member->hasAnyRole(['Super Admin', 'super-admin', 'Builder/Developer', 'Builder']))
                    <form wire:submit="assignTeamLeader" class="border-t border-border pt-4 space-y-3">
                        <h3 class="text-sm font-semibold text-ink">Set Team Leader</h3>
                        <label class="block text-xs text-muted">Select the team this member will lead. Its current leader will be replaced.</label>
                        <livewire:shared.searchable-select wire:model.live="leadershipTeamId" :value="$leadershipTeamId" :options="$teams->pluck('name', 'id')->all()" placeholder="Choose team" class="block w-full" :key="'member-leader-team-'.$memberId" />
                        <x-input-error :messages="$errors->get('leadershipTeamId')" />
                        @if($teams->isEmpty())<p class="text-xs text-muted">Create a team for this client first.</p>@endif
                        <button type="submit" wire:loading.attr="disabled" @disabled($teams->isEmpty() || $member->status !== 'active') class="px-4 py-2 bg-ink text-white rounded-lg text-xs disabled:opacity-50">Assign as Team Leader</button>
                    </form>
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
