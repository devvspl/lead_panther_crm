<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Organizations Management</h1>
            <p class="text-xs text-muted">Manage Real Estate Builder, Channel Partner, and Platform multi-tenant organizations.</p>
        </div>
        <x-ui.export-button target="exportExcel" />
    </div>



    <!-- Create Organization Card -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Register New Organization</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            <div>
                <label class="font-bold text-ink">Organization Name</label>
                <input type="text" wire:model="name" placeholder="e.g. Prestige Developers Ltd" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
            </div>

            <div>
                <label class="font-bold text-ink">Organization Type</label>
                <x-ui.themed-select 
                    wire:model="type"
                    :options="['builder' => 'Builder / Real Estate Developer', 'channel_partner' => 'Channel Partner Agency', 'platform' => 'Platform Admin Org']"
                    placeholder="Organization Type"
                    class="w-full mt-1"
                />
            </div>

            <div>
                <label class="font-bold text-ink">Initial Status</label>
                <x-ui.themed-select 
                    wire:model="status"
                    :options="['active' => 'Active', 'suspended' => 'Suspended']"
                    placeholder="Initial Status"
                    class="w-full mt-1"
                />
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <x-ui.button wire:click="createOrganization" variant="primary" class="text-xs">
                Save Organization
            </x-ui.button>
        </div>
    </div>

    <!-- Organizations List Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Registered Organizations</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Org ID</th>
                        <th class="py-3 px-4">Organization Name</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Clients Count</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($organizations as $org)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-ink">#{{ $org->id }}</td>
                            <td class="py-3 px-4 font-bold text-ink">{{ $org->name }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill uppercase bg-canvas text-ink border border-border">
                                    {{ str_replace('_', ' ', $org->type) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-bold text-ink">{{ $org->clients_count }} clients</td>
                            <td class="py-3 px-4">
                                @if($org->status === 'active')
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-green-50 text-green-700 border border-green-200">ACTIVE</span>
                                @else
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-red-50 text-red-700 border border-red-200">SUSPENDED</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right space-x-2">
                                <button wire:click="toggleStatus({{ $org->id }})" class="text-xs font-bold text-primary hover:underline">
                                    {{ $org->status === 'active' ? 'Suspend' : 'Activate' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $organizations->links('vendor.pagination.tailwind') }}
        </div>
    </div>
</div>
