<div>
    <x-modal name="org-member-details" :show="$show" maxWidth="2xl">
        <div x-init="$watch('show', value => { if (!value &amp;&amp; $wire.show) $wire.close() })" class="flex flex-col max-h-[calc(100dvh-4rem)]">
            <div class="flex items-center justify-between gap-3 p-4 sm:p-6 border-b border-border shrink-0">
                <h2 class="text-lg font-bold text-ink">Team Member Details</h2>
                <button type="button" wire:click="close" aria-label="Close member details" class="inline-flex items-center justify-center rounded-lg p-2 text-muted hover:text-ink hover:bg-canvas focus-visible:outline-2 focus-visible:outline-primary"><x-ui.team-icon name="close" /></button>
            </div>
            <div class="min-h-0 overflow-y-auto overscroll-contain p-4 sm:p-6 space-y-5">
            @if($member)
                <div class="rounded-lg border border-border bg-canvas p-4 space-y-1">
                    <h3 class="font-semibold text-ink">{{ $member->name }}</h3>
                    <p class="text-xs text-muted break-all">{{ $member->email }}</p>
                    <div class="text-xs text-muted">ID #{{ $member->id }} · {{ $member->getRoleNames()->join(', ') }} · Joined {{ $member->created_at?->format('d M Y') }}</div>
                </div>
                @if($editing)
                    <form id="org-member-details-form" wire:submit="saveDetails" class="grid sm:grid-cols-2 gap-4">
                        @foreach(['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone'] as $field => $label)
                            <div><label for="member-{{ $field }}" class="block text-xs font-semibold text-ink mb-1">{{ $label }}</label>
                                <x-text-input id="member-{{ $field }}" variant="compact" wire:model="{{ $field }}" />
                                <x-input-error :messages="$errors->get($field)" /></div>
                        @endforeach
                        <div><label class="block text-xs font-semibold text-ink mb-1">Account Status</label>
                            <livewire:shared.searchable-select wire:model.live="status" :value="$status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :show-clear-option="false" class="block w-full" :key="'member-status-'.$memberId.'-'.$status" />
                            <x-input-error :messages="$errors->get('status')" /></div>
                    </form>
                @else
                    <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                        @foreach(['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'status' => 'Status'] as $field => $label)
                            <div><dt class="text-xs text-muted">{{ $label }}</dt><dd class="text-ink break-all mt-1">{{ $member->$field ?: '—' }}</dd></div>
                        @endforeach
                    </dl>
                @endif
                @if($member->hasAnyRole(['Executive', 'Sales Executive', 'sales-executive', 'sales_executive']) && !$member->hasAnyRole(['Team Leader', 'team-leader', 'team_leader', 'Team-Leader', 'Super Admin', 'super-admin', 'Builder/Developer', 'Builder', 'builder']))
                    <form wire:submit="assignMemberToTeam" class="border-t border-border pt-4 space-y-3">
                        <h3 class="text-sm font-semibold text-ink">Team Leader</h3>
                        <label class="block text-xs text-muted">Select the Team Leader responsible for this executive.</label>
                        <livewire:shared.searchable-select wire:model.live="leadershipTeamId" :value="$leadershipTeamId" :options="$teams->mapWithKeys(fn ($team) => [$team->id => $team->leader->name])->all()" placeholder="Choose Team Leader" class="block w-full" :key="'member-leader-team-'.$memberId.'-'.count($teams).'-'.($leadershipTeamId ?? 0)" />
                        <x-input-error :messages="$errors->get('leadershipTeamId')" />
                        @if($teams->isEmpty())<p class="text-xs text-muted">Add an active Team Leader to this organization first.</p>@endif
                        <button type="submit" wire:loading.attr="disabled" @disabled($teams->isEmpty() || $member->status !== 'active') class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-xs disabled:opacity-50">Save Team Leader</button>
                    </form>
                @endif
                @if($changingPassword)
                    <form id="org-member-password-form" wire:submit="changePassword" class="space-y-3 border-t border-border pt-4">
                        <div><label for="member-new-password" class="block text-xs font-semibold text-ink mb-1">New Password</label>
                            <x-ui.password-input id="member-new-password" wire:model="password" autocomplete="new-password" />
                            <x-input-error :messages="$errors->get('password')" /></div>
                        <div><label for="member-confirm-password" class="block text-xs font-semibold text-ink mb-1">Confirm Password</label>
                            <x-ui.password-input id="member-confirm-password" wire:model="password_confirmation" autocomplete="new-password" /></div>
                    </form>
                @endif
            @endif
            </div>
            <footer class="shrink-0 border-t border-border bg-canvas p-4 sm:px-6 flex flex-wrap items-center justify-end gap-2">
                @if($member)
                    <button type="button" wire:click="$toggle('editing')" class="px-3 py-2 border border-border rounded-lg text-xs text-ink bg-surface">{{ $editing ? 'Cancel Edit' : 'Edit Details' }}</button>
                    <button type="button" wire:click="$toggle('changingPassword')" class="px-3 py-2 border border-border rounded-lg text-xs text-ink bg-surface">{{ $changingPassword ? 'Cancel Password Change' : 'Change Password' }}</button>
                    @if($editing)
                        <button type="submit" form="org-member-details-form" wire:loading.attr="disabled" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-xs disabled:opacity-50">Save Details</button>
                    @endif
                    @if($changingPassword)
                        <button type="submit" form="org-member-password-form" wire:loading.attr="disabled" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-xs disabled:opacity-50">Save New Password</button>
                    @endif
                @endif
            </footer>
        </div>
    </x-modal>
</div>
