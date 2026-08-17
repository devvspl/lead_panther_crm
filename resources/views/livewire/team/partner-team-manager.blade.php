<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Channel Partner Team Management</h1>
            <p class="text-xs text-muted">Manage your agency's sales team members and project allocation.</p>
        </div>
    </div>

    <!-- Add Member Form -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Add New Sales Executive</h2>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
            <div>
                <label class="font-bold text-ink">Full Name <span class="text-red-500">*</span></label>
                <input 
                    type="text" 
                    wire:model="memberName" 
                    placeholder="e.g. Rahul Sharma"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1"
                >
                @error('memberName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="font-bold text-ink">Email Address <span class="text-red-500">*</span></label>
                <input 
                    type="email" 
                    wire:model="memberEmail" 
                    placeholder="rahul@partner.com"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1"
                >
                @error('memberEmail') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="font-bold text-ink">Mobile Number <span class="text-red-500">*</span></label>
                <input 
                    type="text" 
                    wire:model="mobile" 
                    placeholder="+91 9876543210"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1"
                >
                @error('mobile') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="font-bold text-ink">Password <span class="text-muted font-normal">(Auto-set if blank)</span></label>
                <input 
                    type="text" 
                    wire:model="password" 
                    placeholder="Set custom password"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1"
                >
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <x-ui.button wire:click="addTeamMember" variant="primary" class="text-xs">
                Add Team Member
            </x-ui.button>
        </div>

        @if($generatedInviteLink)
            <div class="p-3.5 bg-surface border border-border rounded-xl space-y-2 mt-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-ink flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                        <span>Team Member Created — Shareable Activation Link</span>
                    </span>
                    <span class="text-[10px] text-muted">Single-use reset token</span>
                </div>
                <div class="flex items-center gap-2">
                    <input 
                        type="text" 
                        readonly 
                        value="{{ $generatedInviteLink }}" 
                        class="w-full h-8 px-3 text-[11px] font-mono bg-canvas border border-border rounded-lg text-ink select-all"
                    >
                    <button 
                        type="button" 
                        x-data="{ copied: false }" 
                        @click="navigator.clipboard.writeText('{{ $generatedInviteLink }}'); copied = true; $wire.dispatch('toast', { type: 'success', title: 'Copied', message: 'Activation link copied to clipboard.' }); setTimeout(() => copied = false, 2000)" 
                        class="px-3 h-8 bg-ink text-white rounded-lg hover:bg-neutral-800 text-xs font-bold transition shrink-0 flex items-center gap-1.5 shadow-xs"
                    >
                        <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <svg x-show="copied" class="w-3.5 h-3.5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span x-text="copied ? 'Copied' : 'Copy'"></span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    <!-- Team Members Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Active Agency Sales Executives</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Member ID</th>
                        <th class="py-3 px-4">Name</th>
                        <th class="py-3 px-4">Email</th>
                        <th class="py-3 px-4">Role</th>
                        <th class="py-3 px-4">Assigned Projects</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($members as $m)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-ink">#{{ $m->id }}</td>
                            <td class="py-3 px-4 font-bold text-ink">{{ $m->name }}</td>
                            <td class="py-3 px-4 font-mono text-muted">{{ $m->email }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-blue-50 text-blue-700 border border-blue-200">
                                    Sales Executive
                                </span>
                            </td>
                            <td class="py-3 px-4 text-muted">
                                {{ $projects->first()?->name ?? 'All Authorized Projects' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-muted">No sales team members registered yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $members->links('vendor.pagination.tailwind') }}
        </div>
    </div>
</div>