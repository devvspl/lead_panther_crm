<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Team Availability & Duty Roster</h1>
            <p class="text-xs text-muted">Toggle sales executive online/offline availability status for real-time lead routing.</p>
        </div>
    </div>



    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Sales Executive</th>
                        <th class="py-3 px-4">Email</th>
                        <th class="py-3 px-4">Phone</th>
                        <th class="py-3 px-4">Current Duty Status</th>
                        <th class="py-3 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($members as $m)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-bold text-ink flex items-center space-x-2">
                                <span class="w-2.5 h-2.5 rounded-full {{ strtolower($m->status ?? 'active') === 'active' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                <span>{{ $m->name }}</span>
                            </td>
                            <td class="py-3 px-4 text-muted">{{ $m->email }}</td>
                            <td class="py-3 px-4 text-muted">{{ $m->phone ?: 'N/A' }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill {{ strtolower($m->status ?? 'active') === 'active' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ strtolower($m->status ?? 'active') === 'active' ? 'ONLINE (ON DUTY)' : 'OFFLINE' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <x-ui.button wire:click="toggleStatus({{ $m->id }})" variant="secondary" class="text-[10px] px-3 py-1">
                                    Toggle {{ strtolower($m->status ?? 'active') === 'active' ? 'Offline' : 'Online' }}
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $members->links('vendor.pagination.tailwind') }}
        </div>
    </div>
</div>
