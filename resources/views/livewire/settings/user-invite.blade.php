<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">B2B Organization User Invites</h1>
            <p class="text-xs text-muted">Invite team members to your organization. Public self-registration is disabled.</p>
        </div>
    </div>



    <!-- Invite User Form -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Invite New User</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            <div>
                <label class="font-bold text-ink">Full Name</label>
                <input type="text" wire:model="name" placeholder="e.g. Priya Sharma" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
            </div>

            <div>
                <label class="font-bold text-ink">Work Email Address</label>
                <input type="email" wire:model="email" placeholder="priya@company.com" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
            </div>

            <div>
                <label class="font-bold text-ink">Assigned Role</label>
                <x-ui.themed-select 
                    wire:model="roleName"
                    :options="['sales-executive' => 'Sales Executive', 'account-manager' => 'Account Manager', 'builder' => 'Builder Manager', 'channel-partner' => 'Channel Partner Manager']"
                    placeholder="Assigned Role"
                    class="w-full mt-1"
                />
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <x-ui.button wire:click="inviteUser" variant="primary" class="text-xs">
                Send Invitation & Generate Link
            </x-ui.button>
        </div>

        @if($generatedInviteLink)
            <div class="p-4 bg-canvas rounded-card border border-border space-y-2 mt-4">
                <span class="text-xs font-bold text-ink">Temporary Password Activation Link:</span>
                <input type="text" readonly value="{{ $generatedInviteLink }}" class="w-full text-xs font-mono p-2.5 rounded-lg border border-border bg-surface text-ink">
                <p class="text-[10px] text-muted">Share this link with the invited user to set up their password.</p>
            </div>
        @endif
    </div>

    <!-- Invited Organization Users Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Organization Users</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">User ID</th>
                        <th class="py-3 px-4">Name</th>
                        <th class="py-3 px-4">Email</th>
                        <th class="py-3 px-4">Role</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($invitedUsers as $u)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-ink">#{{ $u->id }}</td>
                            <td class="py-3 px-4 font-bold text-ink">{{ $u->name }}</td>
                            <td class="py-3 px-4 font-mono text-muted">{{ $u->email }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-purple-50 text-purple-700 border border-purple-200">
                                    {{ $u->roles->first()?->name ?? 'User' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-muted">No users invited yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $invitedUsers->links('vendor.pagination.tailwind') }}
        </div>
    </div>
</div>
