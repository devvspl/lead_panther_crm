<section class="space-y-4 border-t border-border pt-6" wire:poll.30s>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h3 class="font-bold text-ink">Connected Ad Accounts</h3><p class="text-xs text-muted">Fetch campaigns, ad sets, ads and insights here. Page lead forms are fetched separately below.</p></div>
        <button type="button" wire:click="edit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white"><x-ui.team-icon name="add" /> Add Ad Account</button>
    </div>
    @if($message)<p role="status" class="rounded-lg border border-border bg-canvas p-3 text-xs text-ink">{{ $message }}</p>@endif
    <div class="overflow-x-auto rounded-xl border border-border">
        <table class="w-full text-left text-xs">
            <thead class="bg-canvas text-muted"><tr>@foreach(['Account Name', 'Ad Account ID', 'Business Portfolio', 'Facebook / Connection', 'Status', 'Last Synced', 'Actions'] as $heading)<th class="p-3 whitespace-nowrap">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-border">
                @forelse($accounts as $account)
                    <tr wire:key="meta-ad-{{ $account->id }}">
                        <td class="p-3 font-semibold">{{ $account->name }}<span class="block text-muted font-normal">{{ $account->pages_count }} Pages · {{ $account->records_count }} records</span></td>
                        <td class="p-3 font-mono">act_{{ $account->ad_account_id }}</td>
                        <td class="p-3">{{ $account->business_name ?: '—' }}<span class="block text-muted">{{ $account->business_id }}</span></td>
                        <td class="p-3">{{ $account->connection_name ?: 'Not tested' }}</td>
                        <td class="p-3"><span @class(['rounded-full px-2 py-1 font-semibold', 'bg-success/10 text-success' => $account->status === 'connected', 'bg-warning/10 text-warning' => $account->status !== 'connected'])>{{ $account->sync_state ? 'Sync in progress' : str_replace('_', ' ', ucfirst($account->status)) }}</span>
                            <span class="block mt-2 text-muted">{{ $account->token_expires_at ? 'Expires '.$account->token_expires_at->format('d M Y H:i') : 'Expiry not supplied' }}</span>
                            @if($account->last_error)<span class="block mt-1 text-muted max-w-xs">{{ $account->last_error }}</span>@endif
                        </td>
                        <td class="p-3 whitespace-nowrap">{{ $account->last_synced_at?->format('d M Y H:i') ?: 'Never' }}</td>
                        <td class="p-3">
                            <x-ui.dropdown align="right" width="56">
                                <x-slot:trigger><button type="button" :aria-expanded="open" aria-haspopup="menu" title="Ad Account actions" aria-label="Ad Account actions" class="p-2 text-muted hover:text-primary"><x-ui.team-icon name="more" /></button></x-slot:trigger>
                                <x-slot:content>
                                    <button type="button" wire:click="edit({{ $account->id }})" class="block w-full text-left p-2 hover:bg-canvas">Edit / Reconnect</button>
                                    <button type="button" wire:click="testConnection({{ $account->id }})" class="block w-full text-left p-2 hover:bg-canvas">Test Connection</button>
                                    <button type="button" wire:click="sync({{ $account->id }})" class="block w-full text-left p-2 hover:bg-canvas">Sync Now</button>
                                    <button type="button" wire:click="showRecords({{ $account->id }})" class="block w-full text-left p-2 hover:bg-canvas">Campaigns &amp; Reporting</button>
                                    <button type="button" wire:click="disconnect({{ $account->id }})" wire:confirm="Disconnect this Ad Account? Existing Page integrations and records will be retained." class="block w-full text-left p-2 text-danger hover:bg-canvas">Disconnect</button>
                                </x-slot:content>
                            </x-ui.dropdown>
                        </td>
                    </tr>
                @empty<tr><td colspan="7" class="p-6 text-center text-muted">No Ad Accounts connected. Your existing Page integration remains active.</td></tr>@endforelse
            </tbody>
        </table>
    </div>

    @if($selected)
        <div class="space-y-3 rounded-xl border border-border p-4">
            <div class="flex flex-wrap items-center justify-between gap-3"><h4 class="font-bold">{{ $selected->name }} · Campaigns &amp; Reporting</h4><button type="button" wire:click="closeRecords" title="Close records" aria-label="Close records"><x-ui.team-icon name="close" /></button></div>
            <div class="flex flex-wrap gap-3">
                <div class="w-full sm:w-48"><x-ui.themed-select wire:model.live="kind" :options="['campaign' => 'Campaigns', 'adset' => 'Ad Sets', 'ad' => 'Ads', 'insight' => 'Daily Insights']" /></div>
                <input wire:model.live.debounce.300ms="search" aria-label="Search imported records" placeholder="Search name or ID" class="rounded-lg border border-border bg-canvas text-ink px-3 py-2 text-xs w-full sm:w-64" />
            </div>
            <div class="overflow-x-auto"><table class="w-full text-xs text-left"><thead class="text-muted bg-canvas"><tr><th class="p-2">Name / ID</th><th class="p-2">Campaign / Ad Set</th><th class="p-2">{{ $kind === 'insight' ? 'Date' : 'Status' }}</th><th class="p-2">{{ $kind === 'campaign' ? 'CRM Campaign' : 'Details' }}</th></tr></thead><tbody class="divide-y divide-border">
                @foreach($records as $record)
                    <tr wire:key="meta-record-{{ $record->id }}"><td class="p-2">{{ $record->name }}<span class="block text-muted font-mono">{{ $record->remote_id }}</span></td><td class="p-2">{{ $record->campaign_id ?: '—' }}<span class="block text-muted">{{ $record->adset_id }}</span></td><td class="p-2">{{ $record->report_date?->format('d M Y') ?: $record->status }}</td><td class="p-2">
                    @if($kind === 'campaign')
                        <div class="flex items-center gap-2 min-w-56"><x-ui.themed-select wire:model="campaignMap.{{ $record->id }}" :options="$campaignOptions" /><button type="button" wire:click="saveMapping({{ $record->id }})" title="Save mapping" aria-label="Save mapping" class="text-primary p-2"><x-ui.team-icon name="check" /></button></div>
                    @elseif($kind === 'insight')
                        Impressions: {{ number_format((int) data_get($record->data, 'impressions', 0)) }} · Clicks: {{ number_format((int) data_get($record->data, 'clicks', 0)) }} · Spend: {{ data_get($record->data, 'spend', '0') }}
                    @else {{ data_get($record->data, 'objective', '—') }} @endif
                    </td></tr>
                @endforeach
                @if($records->isEmpty())<tr><td colspan="4" class="p-4 text-muted">No records found.</td></tr>@endif
            </tbody></table></div>
            {{ $records->links() }}
            <div class="text-xs space-y-2"><h5 class="font-bold">Associated Pages</h5>@forelse($selected->pages as $page)<p>{{ $page->name }} · {{ $page->getCredential('page_id') }}</p>@empty<p class="text-muted">No accessible Pages discovered. Ad Account access does not grant Page access.</p>@endforelse</div>
            <details class="text-xs"><summary class="cursor-pointer font-bold">Recent Sync &amp; Connection History</summary><div class="space-y-2 mt-2">@foreach($logs as $log)@php($details = json_decode($log->to_value ?? '{}', true) ?: [])
                        <div class="rounded-lg border border-border p-2"><p class="font-semibold">{{ $log->created_at }} · {{ str_replace('meta.ad_account.', '', $log->action) }}</p>
                        @if(isset($details['kind']))<p>{{ ucfirst($details['kind']) }}: {{ $details['fetched'] ?? 0 }} fetched · {{ $details['new'] ?? 0 }} new · {{ $details['updated'] ?? 0 }} updated · {{ !empty($details['has_more']) ? 'More pages pending' : 'Edge complete' }}</p>@endif
                        @if(isset($details['counts']))<p>@foreach($details['counts'] as $kind => $count){{ ucfirst($kind) }}: {{ $count }} @endforeach</p>@endif
                        @if(isset($details['message']))<p>{{ $details['message'] }}</p>@endif
                        @if($details['http_status'] ?? null)<p class="text-muted">HTTP {{ $details['http_status'] }} · Meta code {{ $details['meta_code'] ?? '—' }}</p>@endif</div>@endforeach</div></details>
        </div>
    @endif

    <x-ui.modal name="meta-ad-account-editor" maxWidth="2xl" centered>
        <form wire:submit="save" class="p-5 space-y-4">
            <div class="flex items-center justify-between"><h3 class="font-bold">{{ $editingId ? 'Edit / Reconnect Ad Account' : 'Add Ad Account' }}</h3><button type="button" @click="$dispatch('close-modal', 'meta-ad-account-editor')" title="Close" aria-label="Close"><x-ui.team-icon name="close" /></button></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach(['name' => 'Account Display Name', 'adAccountId' => 'Ad Account ID', 'businessId' => 'Business Portfolio ID (optional)', 'tokenExpiry' => 'Token Expiry (optional)'] as $field => $label)
                    <div><label for="ad-{{ $field }}" class="block text-xs font-semibold mb-1">{{ $label }}</label><input id="ad-{{ $field }}" wire:model="{{ $field }}" type="{{ $field === 'tokenExpiry' ? 'datetime-local' : 'text' }}" class="w-full rounded-lg border border-border bg-canvas text-ink text-sm px-3 py-2" />@error($field)<p class="text-danger text-xs mt-1">{{ $message }}</p>@enderror</div>
                @endforeach
            </div>
            <div><label for="ad-access-token" class="block text-xs font-semibold mb-1">Ad Account Access Token</label><input id="ad-access-token" type="password" autocomplete="new-password" wire:model="accessToken" class="w-full rounded-lg border border-border bg-canvas text-ink text-sm px-3 py-2" placeholder="{{ $editingId ? 'Leave blank to keep saved token' : 'User or system-user token with ads_read' }}" />@error('accessToken')<p class="text-danger text-xs">{{ $message }}</p>@enderror<p class="text-xs text-muted mt-1">Use a token authorized for this Ad Account. Page tokens are separate.</p></div>
            <div class="flex justify-end"><button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-primary text-white px-4 py-2 text-xs font-bold disabled:opacity-50"><x-ui.team-icon name="check" /> Save &amp; Test</button></div>
        </form>
    </x-ui.modal>
</section>
