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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            <div>
                <label class="font-bold text-ink">Full Name</label>
                <input type="text" wire:model="memberName" placeholder="e.g. Rahul Sharma"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1">
            </div>

            <div>
                <label class="font-bold text-ink">Email Address</label>
                <input type="email" wire:model="memberEmail" placeholder="rahul@partner.com"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1">
            </div>

            <div>
                <label class="font-bold text-ink">Mobile Number</label>
                <input type="text" wire:model="mobile" placeholder="+91 9876543210"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1">
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <x-ui.button wire:click="addTeamMember" variant="primary" class="text-xs">
                Add Team Member
            </x-ui.button>
        </div>
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
                                <span
                                    class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-blue-50 text-blue-700 border border-blue-200">
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