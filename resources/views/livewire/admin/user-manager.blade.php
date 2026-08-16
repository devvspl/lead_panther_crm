<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Global User Management</h1>
            <p class="text-xs text-muted">Assign Spatie roles, manage access levels, and impersonate accounts with audited trace logging.</p>
        </div>
    </div>



    <!-- Filters Bar -->
    <div class="bg-surface rounded-card border border-border p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center space-x-3 w-full md:w-auto">
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search user name or email..." 
                class="h-8 text-xs px-3.5 rounded-lg border border-border bg-canvas text-ink w-64 focus:outline-none focus:ring-2 focus:ring-ink transition"
            >

            <x-ui.themed-select 
                wire:model.live="roleFilter"
                :options="['' => 'All Roles', 'super-admin' => 'Super Admin', 'builder' => 'Builder', 'channel-partner' => 'Channel Partner', 'sales-executive' => 'Sales Executive', 'account-manager' => 'Account Manager', 'client' => 'Client']"
                placeholder="All Roles"
                searchable="true"
            />

            <x-ui.export-button target="exportExcel" />
        </div>
    </div>

    <!-- Users List Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">User ID</th>
                        <th class="py-3 px-4">User Name</th>
                        <th class="py-3 px-4">Email</th>
                        <th class="py-3 px-4">Organization</th>
                        <th class="py-3 px-4">Current Role</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($users as $user)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-ink">#{{ $user->id }}</td>
                            <td class="py-3 px-4 font-bold text-ink">{{ $user->name }}</td>
                            <td class="py-3 px-4 font-mono text-muted">{{ $user->email }}</td>
                            <td class="py-3 px-4 text-muted">{{ $user->organization?->name ?? 'Platform' }}</td>
                            <td class="py-3 px-4">
                                <x-ui.themed-select 
                                    :value="$user->getRoleNames()->first()"
                                    :options="['super-admin' => 'Super Admin', 'builder' => 'Builder', 'channel-partner' => 'Channel Partner', 'sales-executive' => 'Sales Executive', 'account-manager' => 'Account Manager', 'client' => 'Client']"
                                    x-effect="$watch('value', val => @this.call('updateUserRole', {{ $user->id }}, val))"
                                />
                            </td>
                            <td class="py-3 px-4 text-right space-x-2">
                                @if(auth()->id() !== $user->id)
                                    <a href="{{ route('admin.users.impersonate', $user->id) }}" class="text-xs font-bold text-purple-700 hover:underline">
                                        Impersonate
                                    </a>
                                @else
                                    <span class="text-[10px] text-muted font-bold">(You)</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $users->links('vendor.pagination.tailwind') }}
        </div>
    </div>
</div>
