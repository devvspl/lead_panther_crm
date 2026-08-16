<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">API & Webhook Integrations</h1>
            <p class="text-xs text-muted">Configure Meta Ads, Google Ads, 99acres, and MagicBricks credentials
                (Encrypted at rest).</p>
        </div>
    </div>



    <!-- Add Credential Card -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Add Connection Credential</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            <div>
                <label class="font-bold text-ink">Portal / Integration Source</label>
                <x-ui.themed-select wire:model="portalType" :options="['meta' => 'Meta Ads (Facebook & Instagram)', 'google' => 'Google Lead Form Ads', 'portal' => 'Property Portals (99acres / MagicBricks)', 'owned' => 'Owned Web Portals']" placeholder="Portal / Integration Source" searchable="true"
                    class="w-full mt-1" />
            </div>

            <div>
                <label class="font-bold text-ink">Account Display Name</label>
                <input type="text" wire:model="accountName" placeholder="e.g. Bandra Campaign Account"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1">
            </div>

            <div>
                <label class="font-bold text-ink">API Key / Access Secret (Encrypted)</label>
                <input type="password" wire:model="apiSecret" placeholder="••••••••••••••••••••••••"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1">
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <x-ui.button wire:click="addCredential" variant="primary" class="text-xs">
                Save Encrypted Credentials
            </x-ui.button>
        </div>
    </div>

    <!-- Active Connections Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Configured Portal Accounts</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-ink border-collapse">
                <thead>
                    <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                        <th class="py-3 px-4">Account ID</th>
                        <th class="py-3 px-4">Account Name</th>
                        <th class="py-3 px-4">Source Type</th>
                        <th class="py-3 px-4">Encryption Status</th>
                        <th class="py-3 px-4">Health Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($accounts as $acc)
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-ink">#{{ $acc->id }}</td>
                            <td class="py-3 px-4 font-bold text-ink">{{ $acc->name }}</td>
                            <td class="py-3 px-4">
                                <span
                                    class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill uppercase bg-canvas text-ink border border-border">
                                    {{ strtoupper($acc->type) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-green-700 font-semibold text-[11px]">
                                Encrypted (AES-256)
                            </td>
                            <td class="py-3 px-4">
                                @if(isset($connectionStatus[$acc->id]))
                                    <span
                                        class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-green-50 text-green-700 border border-green-200">
                                        {{ $connectionStatus[$acc->id] }}
                                    </span>
                                @else
                                    <span
                                        class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-canvas text-muted border border-border">
                                        Untested
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <button wire:click="testConnection({{ $acc->id }})"
                                    class="text-xs font-bold text-primary hover:underline">
                                    Test Connection 🔌
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-muted">No portal account credentials configured.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $accounts->links('vendor.pagination.tailwind') }}
        </div>
    </div>
</div>