<section class="space-y-4 border-t border-border pt-6" wire:poll.30s>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="font-bold text-ink">Connected Ad Accounts</h3>
            <p class="text-xs text-muted">Fetch campaigns, ad sets, ads, creatives and insights. Map campaigns and fetch their associated form leads. Existing Page form routing remains available below.</p>
        </div>
        <button type="button" wire:click="edit" class="inline-flex items-center gap-2 rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white shadow-xs hover:bg-primary-hover transition">
            <x-ui.team-icon name="add" /> Add Ad Account
        </button>
    </div>

    @if($message)
        <p role="status" class="rounded-lg border border-border bg-canvas p-3 text-xs text-ink flex items-center justify-between">
            <span>{{ $message }}</span>
            <button type="button" wire:click="$set('message', '')" class="text-muted hover:text-ink"><x-ui.team-icon name="close" /></button>
        </p>
    @endif

    <div class="overflow-x-auto rounded-xl border border-border">
        <table class="w-full text-left text-xs">
            <thead class="bg-canvas text-muted">
                <tr>
                    @foreach(['Account Name', 'Ad Account ID', 'Business Portfolio', 'Facebook / Connection', 'Status', 'Last Synced', 'Actions'] as $heading)
                        <th class="p-3 whitespace-nowrap">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($accounts as $account)
                    <tr wire:key="meta-ad-{{ $account->id }}" class="hover:bg-canvas/40 transition">
                        <td class="p-3 font-semibold">
                            <span class="text-ink">{{ $account->name }}</span>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-muted font-normal">
                                <span>{{ $account->pages_count }} {{ Str::plural('Page', $account->pages_count) }}</span>
                                <span>·</span>
                                <span>{{ $account->sync_counts['campaigns'] }} Campaigns</span>
                                <span>·</span>
                                <span>{{ $account->sync_counts['adsets'] }} Ad Sets</span>
                                <span>·</span>
                                <span>{{ $account->sync_counts['ads'] }} Ads</span>
                                <span>·</span>
                                <span>{{ $account->sync_counts['insights'] }} Insights</span>
                            </div>
                        </td>
                        <td class="p-3 font-mono font-medium text-ink">act_{{ $account->ad_account_id }}</td>
                        <td class="p-3">
                            <span class="text-ink font-medium">{{ $account->business_name ?: '—' }}</span>
                            @if($account->business_id)
                                <span class="block text-muted font-mono text-[11px]">{{ $account->business_id }}</span>
                            @endif
                        </td>
                        <td class="p-3 text-ink">{{ $account->connection_name ?: 'Not tested' }}</td>
                        <td class="p-3">
                            @if($account->status === 'connected' && $account->sync_state)
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                                    <svg class="animate-spin w-3 h-3 text-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span>{{ $account->sync_stage_label }}</span>
                                </span>
                            @else
                                <span @class([
                                    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-emerald-50 text-emerald-700 border border-emerald-200' => $account->status === 'connected',
                                    'bg-rose-50 text-rose-700 border border-rose-200' => $account->status === 'error',
                                    'bg-amber-50 text-amber-700 border border-amber-200' => in_array($account->status, ['rate_limited', 'untested']),
                                    'bg-neutral-100 text-neutral-600 border border-neutral-200' => !in_array($account->status, ['connected', 'error', 'rate_limited', 'untested']),
                                ])>
                                    {{ str_replace('_', ' ', ucfirst($account->status)) }}
                                </span>
                            @endif
                            <span class="block mt-1 text-[11px] text-muted">{{ $account->token_expires_at ? 'Expires '.$account->token_expires_at->format('d M Y H:i') : 'Expiry not supplied' }}</span>
                            @if($account->last_error)
                                <span class="block mt-1 text-[11px] {{ $account->status === 'connected' ? 'text-warning' : 'text-danger' }} max-w-xs break-words" title="{{ $account->last_error }}">{{ Str::limit($account->last_error, 80) }}</span>
                            @endif
                        </td>
                        <td class="p-3 whitespace-nowrap text-muted">{{ $account->last_synced_at?->format('d M Y H:i') ?: 'Never' }}</td>
                        <td class="p-3">
                            <div class="flex items-center gap-1.5">
                                <button type="button" wire:click="sync({{ $account->id }})" wire:loading.attr="disabled" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-border bg-canvas hover:bg-surface text-ink text-xs font-semibold shadow-2xs transition">
                                    <svg wire:loading.remove wire:target="sync({{ $account->id }})" class="w-3 h-3 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                    <svg wire:loading wire:target="sync({{ $account->id }})" class="animate-spin w-3 h-3 text-ink" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span>Sync Now</span>
                                </button>
                                <button type="button" wire:click="showRecords({{ $account->id }})" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-primary/10 hover:bg-primary/20 text-primary border border-primary/20 text-xs font-bold transition">
                                    <span>Campaigns &amp; Reporting</span>
                                </button>
                                <x-ui.dropdown align="right" width="56">
                                    <x-slot:trigger>
                                        <button type="button" :aria-expanded="open" aria-haspopup="menu" title="Ad Account actions" aria-label="Ad Account actions" class="p-1.5 text-muted hover:text-primary transition rounded-lg hover:bg-canvas">
                                            <x-ui.team-icon name="more" />
                                        </button>
                                    </x-slot:trigger>
                                    <x-slot:content>
                                        <button type="button" wire:click="edit({{ $account->id }})" class="block w-full text-left p-2 hover:bg-canvas">Edit / Reconnect</button>
                                        <button type="button" wire:click="testConnection({{ $account->id }})" class="block w-full text-left p-2 hover:bg-canvas">Test Connection</button>
                                        <button type="button" wire:click="sync({{ $account->id }})" class="block w-full text-left p-2 hover:bg-canvas">Sync Now</button>
                                        <button type="button" wire:click="showRecords({{ $account->id }})" class="block w-full text-left p-2 hover:bg-canvas font-semibold text-primary">Campaigns &amp; Reporting</button>
                                        <button type="button" wire:click="disconnect({{ $account->id }})" wire:confirm="Disconnect this Ad Account? Existing Page integrations and records will be retained." class="block w-full text-left p-2 text-danger hover:bg-canvas">Disconnect</button>
                                    </x-slot:content>
                                </x-ui.dropdown>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center text-muted">No Ad Accounts connected. Your existing Page integration remains active.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($selected)
        @php
            $currCode = data_get($selected->capabilities, 'currency', 'USD');
            $currSign = ($currCode === 'INR' || $currCode === '₹') ? '₹' : ($currCode === 'USD' ? '$' : $currCode . ' ');
        @endphp
        <div class="space-y-4 rounded-xl border border-border p-4 bg-surface shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-border">
                <div>
                    <h4 class="font-bold text-sm text-ink">{{ $selected->name }} · Campaigns &amp; Reporting</h4>
                    <p class="text-xs text-muted mt-0.5">
                        Ad Account: <span class="font-mono font-medium text-ink">act_{{ $selected->ad_account_id }}</span> ·
                        {{ $selected->records_count }} total imported records
                        @if($selected->last_synced_at)
                            · Last synced: {{ $selected->last_synced_at->format('d M Y H:i') }}
                        @endif
                    </p>
                </div>
                <button type="button" wire:click="closeRecords" title="Close records" aria-label="Close records" class="p-1.5 rounded-lg border border-border hover:bg-canvas text-muted hover:text-ink transition">
                    <x-ui.team-icon name="close" />
                </button>
            </div>

            <!-- Controls & Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <nav aria-label="Meta record tabs" class="flex flex-wrap gap-1 rounded-lg bg-canvas p-1">
                    @foreach(['campaign' => 'Campaign Lead Management', 'adset' => 'Ad Sets', 'ad' => 'Ads & Forms', 'insight' => 'Insights'] as $tab => $label)
                        <button type="button" wire:click="$set('kind', '{{ $tab }}')" aria-pressed="{{ $kind === $tab ? 'true' : 'false' }}" class="rounded-md px-3 py-2 text-xs {{ $kind === $tab ? 'bg-surface text-primary font-bold shadow-xs' : 'text-muted' }}">{{ $label }}</button>
                    @endforeach
                </nav>

                @if($kind !== 'insight')
                    <div class="w-full sm:w-40">
                        <x-ui.themed-select wire:model.live="statusFilter" :options="['' => 'All Statuses', 'ACTIVE' => 'Active', 'PAUSED' => 'Paused', 'ARCHIVED' => 'Archived']" />
                    </div>
                @else
                    <div class="w-full sm:w-40">
                        <x-ui.themed-select wire:model.live="insightLevel" :options="['campaign' => 'Campaign Level', 'adset' => 'Ad Set Level', 'ad' => 'Ad Level']" />
                    </div>
                    <div class="w-full sm:w-40">
                        <x-ui.themed-select wire:model.live="insightDatePreset" :options="['last_7d' => 'Last 7 Days', 'last_30d' => 'Last 30 Days', 'last_90d' => 'Last 90 Days', 'this_month' => 'This Month']" />
                    </div>
                    <button type="button" wire:click="syncInsightsNow" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-canvas px-3 py-2 text-xs font-semibold hover:border-primary hover:text-primary transition disabled:opacity-50">
                        <svg wire:loading.remove wire:target="syncInsightsNow" class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        <svg wire:loading wire:target="syncInsightsNow" class="animate-spin w-3.5 h-3.5 text-ink" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>Fetch Insights</span>
                    </button>
                @endif

                <div class="flex-1 min-w-[200px]">
                    <input wire:model.live.debounce.300ms="search" aria-label="Search imported records" placeholder="Search by name, ID, or form ID..." class="w-full rounded-lg border border-border bg-canvas text-ink px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-ink" />
                </div>
            </div>

            @if($kind === 'campaign')
                <div class="rounded-xl border border-primary/20 bg-primary/5 p-3 text-xs space-y-1">
                    <p class="font-bold text-ink flex items-center gap-2">
                        <span class="inline-block w-2 h-2 rounded-full bg-primary"></span>
                        <span>Campaign Lead Management &amp; Client Routing</span>
                    </p>
                    <p class="text-muted">Map campaigns directly to Clients and CRM Projects. Use <strong>Fetch Leads</strong> to ingest historical leads from all associated lead forms, and let Meta webhooks route future leads automatically.</p>
                </div>
            @endif

            <!-- Records Table -->
            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-xs text-left">
                    <thead class="text-muted bg-canvas">
                        <tr>
                            @if($kind === 'campaign')
                                <th class="p-2.5">Campaign Name / ID</th>
                                <th class="p-2.5">Objective &amp; Type</th>
                                <th class="p-2.5">Status</th>
                                <th class="p-2.5">Budget</th>
                                <th class="p-2.5">Schedule / Dates</th>
                                <th class="p-2.5">Destination / Actions</th>
                            @elseif($kind === 'adset')
                                <th class="p-2.5">Ad Set Name / ID</th>
                                <th class="p-2.5">Campaign</th>
                                <th class="p-2.5">Status</th>
                                <th class="p-2.5">Optimization &amp; Billing</th>
                                <th class="p-2.5">Budget</th>
                            @elseif($kind === 'ad')
                                <th class="p-2.5">Ad Name / ID</th>
                                <th class="p-2.5">Campaign &amp; Ad Set</th>
                                <th class="p-2.5">Status</th>
                                <th class="p-2.5">Creative Preview</th>
                                <th class="p-2.5">Destination &amp; Form</th>
                            @elseif($kind === 'insight')
                                <th class="p-2.5">Entity / ID</th>
                                <th class="p-2.5">Level &amp; Date</th>
                                <th class="p-2.5">Impressions &amp; Reach</th>
                                <th class="p-2.5">Clicks &amp; CTR</th>
                                <th class="p-2.5">Spend &amp; CPC</th>
                                <th class="p-2.5">Leads &amp; Cost / Lead</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($records as $record)
                            <tr wire:key="meta-record-{{ $record->id }}" class="hover:bg-canvas/50 transition">
                                @if($kind === 'campaign')
                                    <td class="p-2.5">
                                        <span class="font-semibold text-ink block">{{ $record->name }}</span>
                                        <span class="text-muted font-mono text-[11px]">{{ $record->remote_id }}</span>
                                    </td>
                                    <td class="p-2.5">
                                        <span class="font-medium text-ink">{{ $record->objective ?: '—' }}</span>
                                        @if($record->buying_type)
                                            <span class="block text-muted text-[11px]">{{ $record->buying_type }}</span>
                                        @endif
                                    </td>
                                    <td class="p-2.5">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                            'bg-emerald-50 text-emerald-700 border border-emerald-200' => in_array($record->status, ['ACTIVE', 'active']),
                                            'bg-amber-50 text-amber-700 border border-amber-200' => in_array($record->status, ['PAUSED', 'paused']),
                                            'bg-neutral-100 text-neutral-600 border border-neutral-200' => !in_array($record->status, ['ACTIVE', 'active', 'PAUSED', 'paused']),
                                        ])>
                                            {{ ucfirst(strtolower($record->status)) }}
                                        </span>
                                        @if($record->effective_status && strtolower($record->effective_status) !== strtolower($record->status))
                                            <span class="block text-[10px] text-muted mt-0.5">Effective: {{ ucfirst(strtolower($record->effective_status)) }}</span>
                                        @endif
                                    </td>
                                    <td class="p-2.5">
                                        <span class="font-medium text-ink">{{ $record->budget_display }}</span>
                                    </td>
                                    <td class="p-2.5">
                                        @php
                                            $startTime = data_get($record->data, 'start_time');
                                            $stopTime = data_get($record->data, 'stop_time');
                                        @endphp
                                        @if($startTime || $stopTime)
                                            <span class="text-ink text-xs block font-medium">Start: {{ $startTime ? \Carbon\Carbon::parse($startTime)->format('d M Y') : 'Immediate' }}</span>
                                            <span class="text-muted text-[11px] block mt-0.5">End: {{ $stopTime ? \Carbon\Carbon::parse($stopTime)->format('d M Y') : 'Ongoing' }}</span>
                                        @elseif($record->synced_at)
                                            <span class="text-muted text-xs block">Synced: {{ $record->synced_at->format('d M Y, H:i') }}</span>
                                        @else
                                            <span class="text-muted text-xs block">—</span>
                                        @endif
                                    </td>
                                    <td class="p-2.5">
                                        <div class="space-y-1.5">
                                            @php
                                                $destClient = $record->client ?: $record->crmCampaign?->project?->client;
                                                $destProject = $record->project ?: $record->crmCampaign?->project;
                                            @endphp
                                            @if($destProject)
                                                <div class="flex items-center gap-1.5 text-xs">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                                    <span class="font-semibold text-ink">{{ $destClient?->name ?: $destProject->client?->name ?: 'Mapped Client' }}</span>
                                                </div>
                                                <p class="text-muted text-[11px] pl-3">
                                                    Project: <span class="font-medium text-ink">{{ $destProject->name }}</span>
                                                </p>
                                            @else
                                                <div class="flex items-center gap-1.5 text-xs">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0"></span>
                                                    <span class="font-medium text-amber-700">Not mapped</span>
                                                </div>
                                                <p class="text-muted text-[11px] pl-3">Assign client &amp; project to route leads</p>
                                            @endif
                                            <div class="flex items-center gap-1.5 pt-0.5">
                                                <button type="button" wire:click="openCampaignMapping({{ $record->id }})" class="px-2 py-1 rounded bg-canvas border border-border text-[11px] font-semibold text-ink hover:bg-surface hover:border-primary transition">
                                                    {{ ($record->project_id || $record->crm_campaign_id) ? 'Edit Mapping' : 'Map Campaign' }}
                                                </button>
                                                <button type="button" wire:click="fetchCampaignLeads({{ $record->id }})" wire:loading.attr="disabled" @disabled(data_get($record->lead_fetch_state, 'status') === 'running') class="px-2 py-1 rounded bg-primary/10 border border-primary/20 text-[11px] font-bold text-primary hover:bg-primary/20 transition disabled:opacity-50">
                                                    Fetch Leads
                                                </button>
                                                <x-ui.dropdown align="right" width="56">
                                                    <x-slot:trigger><button type="button" class="p-1 text-muted hover:text-primary" aria-label="Campaign actions"><x-ui.team-icon name="more" /></button></x-slot:trigger>
                                                    <x-slot:content>
                                                        <button type="button" wire:click="openCampaignMapping({{ $record->id }})" class="block w-full p-2 text-left hover:bg-canvas">Map Campaign</button>
                                                        <button type="button" wire:click="fetchCampaignLeads({{ $record->id }})" wire:loading.attr="disabled" @disabled(data_get($record->lead_fetch_state, 'status') === 'running') class="block w-full p-2 text-left text-primary hover:bg-canvas disabled:opacity-50">Fetch Leads</button>
                                                    </x-slot:content>
                                                </x-ui.dropdown>
                                            </div>
                                            @if($fetch = $record->lead_fetch_state)
                                                <p role="status" class="text-[11px] {{ ($fetch['status'] ?? '') === 'failed' ? 'text-danger' : 'text-muted' }}">{{ ucfirst($fetch['status'] ?? '') }} · {{ $fetch['phase'] ?? '' }} · {{ count($fetch['forms'] ?? []) }} forms · {{ $fetch['created'] ?? 0 }} created · {{ $fetch['duplicates'] ?? 0 }} duplicates · {{ $fetch['skipped'] ?? 0 }} unrelated / unattributed skipped</p>
                                                @if(!empty($fetch['error']))<p class="text-danger text-[11px] max-w-xs">{{ $fetch['error'] }}</p>@endif
                                                @if(($fetch['status'] ?? '') === 'completed' && empty($fetch['forms']))<p class="text-muted text-[11px]">No associated lead forms found. Website or messaging campaigns may not use instant forms.</p>@endif
                                            @endif
                                        </div>
                                    </td>
                                @elseif($kind === 'adset')
                                    <td class="p-2.5">
                                        <span class="font-semibold text-ink block">{{ $record->name }}</span>
                                        <span class="text-muted font-mono text-[11px]">{{ $record->remote_id }}</span>
                                    </td>
                                    <td class="p-2.5">
                                        <span class="font-medium text-ink block">{{ $record->campaignRecord?->name ?: 'Campaign' }}</span>
                                        <span class="text-muted font-mono text-[11px]">{{ $record->campaign_id ?: '—' }}</span>
                                    </td>
                                    <td class="p-2.5">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                            'bg-emerald-50 text-emerald-700 border border-emerald-200' => in_array($record->status, ['ACTIVE', 'active']),
                                            'bg-amber-50 text-amber-700 border border-amber-200' => in_array($record->status, ['PAUSED', 'paused']),
                                            'bg-neutral-100 text-neutral-600 border border-neutral-200' => !in_array($record->status, ['ACTIVE', 'active', 'PAUSED', 'paused']),
                                        ])>
                                            {{ ucfirst(strtolower($record->status)) }}
                                        </span>
                                        @if($record->effective_status && strtolower($record->effective_status) !== strtolower($record->status))
                                            <span class="block text-[10px] text-muted mt-0.5">Effective: {{ ucfirst(strtolower($record->effective_status)) }}</span>
                                        @endif
                                    </td>
                                    <td class="p-2.5">
                                        <span class="font-medium text-ink block">{{ str_replace('_', ' ', ucfirst(strtolower(data_get($record->data, 'optimization_goal', '—')))) }}</span>
                                        @if(data_get($record->data, 'billing_event') || data_get($record->data, 'bid_strategy'))
                                            <span class="text-muted text-[11px] block">{{ data_get($record->data, 'billing_event') }} {{ data_get($record->data, 'bid_strategy') ? '· '.data_get($record->data, 'bid_strategy') : '' }}</span>
                                        @endif
                                    </td>
                                    <td class="p-2.5">
                                        <span class="font-medium text-ink">{{ $record->budget_display }}</span>
                                    </td>
                                @elseif($kind === 'ad')
                                    <td class="p-2.5">
                                        <span class="font-semibold text-ink block">{{ $record->name }}</span>
                                        <span class="text-muted font-mono text-[11px]">{{ $record->remote_id }}</span>
                                    </td>
                                    <td class="p-2.5">
                                        <span class="text-ink block">C: {{ $record->campaignRecord?->name ?: ($record->campaign_id ?: '—') }}</span>
                                        <span class="text-muted block text-[11px]">Set: {{ $record->adsetRecord?->name ?: ($record->adset_id ?: '—') }}</span>
                                    </td>
                                    <td class="p-2.5">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                            'bg-emerald-50 text-emerald-700 border border-emerald-200' => in_array($record->status, ['ACTIVE', 'active']),
                                            'bg-amber-50 text-amber-700 border border-amber-200' => in_array($record->status, ['PAUSED', 'paused']),
                                            'bg-neutral-100 text-neutral-600 border border-neutral-200' => !in_array($record->status, ['ACTIVE', 'active', 'PAUSED', 'paused']),
                                        ])>
                                            {{ ucfirst(strtolower($record->status)) }}
                                        </span>
                                        @if($record->effective_status && strtolower($record->effective_status) !== strtolower($record->status))
                                            <span class="block text-[10px] text-muted mt-0.5">Effective: {{ ucfirst(strtolower($record->effective_status)) }}</span>
                                        @endif
                                    </td>
                                    <td class="p-2.5">
                                        <div class="flex items-start gap-2 max-w-xs">
                                            @if($record->creative_thumbnail)
                                                <img src="{{ $record->creative_thumbnail }}" alt="Creative thumbnail" class="w-9 h-9 object-cover rounded border border-border shrink-0" />
                                            @endif
                                            <div class="min-w-0">
                                                <span class="font-medium text-ink block truncate">{{ $record->creative_title ?: ($record->creative_id ? 'Creative: '.$record->creative_id : 'Standard Ad Creative') }}</span>
                                                @if($record->creative_body)
                                                    <span class="text-muted text-[11px] block truncate">{{ $record->creative_body }}</span>
                                                @endif
                                                @if($record->destination_url)
                                                    <a href="{{ $record->destination_url }}" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline text-[11px] inline-flex items-center gap-1 mt-0.5">Preview Ad &nearr;</a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-2.5">
                                        @if($record->lead_form_id)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-primary/10 text-primary font-semibold text-[11px] border border-primary/20">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                Form: {{ $record->lead_form_id }}
                                            </span>
                                            @if($record->leadForm)
                                                <span class="block text-muted text-[11px] truncate max-w-xs mt-0.5">{{ $record->leadForm->form_name }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted text-[11px]">Website / External Link</span>
                                        @endif
                                    </td>
                                @elseif($kind === 'insight')
                                    <td class="p-2.5">
                                        <span class="font-semibold text-ink block">{{ $record->name }}</span>
                                        <span class="text-muted font-mono text-[11px]">{{ $record->remote_id }}</span>
                                    </td>
                                    <td class="p-2.5">
                                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold uppercase bg-canvas border border-border text-ink">
                                            {{ $record->insight_level ?: 'campaign' }}
                                        </span>
                                        <span class="block text-muted text-[11px] mt-0.5">{{ $record->report_date?->format('d M Y') ?: '—' }}</span>
                                    </td>
                                    <td class="p-2.5">
                                        <span class="font-medium text-ink block">{{ number_format($record->impressions) }} imp</span>
                                        <span class="text-muted text-[11px] block">{{ number_format($record->reach) }} reach ({{ $record->frequency }}x)</span>
                                    </td>
                                    <td class="p-2.5">
                                        <span class="font-medium text-ink block">{{ number_format($record->clicks) }} clicks</span>
                                        <span class="text-muted text-[11px] block">CTR: {{ number_format($record->ctr, 2) }}% · {{ number_format($record->link_clicks) }} link</span>
                                    </td>
                                    <td class="p-2.5">
                                        <span class="font-medium text-ink block">{{ $currSign }}{{ number_format($record->spend, 2) }}</span>
                                        <span class="text-muted text-[11px] block">CPM: {{ $currSign }}{{ number_format($record->cpm, 2) }} · CPC: {{ $currSign }}{{ number_format($record->cpc, 2) }}</span>
                                    </td>
                                    <td class="p-2.5">
                                        @if($record->leads_count > 0)
                                            <span class="inline-flex items-center gap-1 font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded text-[11px]">
                                                {{ number_format($record->leads_count) }} {{ Str::plural('Lead', $record->leads_count) }}
                                            </span>
                                            @if($record->cost_per_lead !== null)
                                                <span class="block text-muted text-[11px] mt-0.5">CPL: {{ $currSign }}{{ number_format($record->cost_per_lead, 2) }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">0 Leads</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ in_array($kind, ['campaign', 'insight']) ? 6 : 5 }}" class="p-6 text-center text-muted">
                                    No {{ $kind }} records found for the selected criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $records->links() }}

            <div class="text-xs space-y-2 pt-2 border-t border-border">
                <h5 class="font-bold text-ink">Associated Facebook Pages</h5>
                @forelse($selected->pages as $page)
                    <p class="text-muted"><span class="font-semibold text-ink">{{ $page->name }}</span> · Page ID: <code class="font-mono text-ink">{{ $page->getCredential('page_id') }}</code></p>
                @empty
                    <p class="text-muted">No accessible Pages discovered. Ad Account access does not grant Page access.</p>
                @endforelse
            </div>

        </div>
    @endif

    <!-- Sync History & Diagnostics Section -->
    <details class="text-xs rounded-xl border border-border p-4 bg-surface shadow-2xs">
        <summary class="cursor-pointer font-bold text-ink flex items-center justify-between">
            <span class="flex items-center gap-2">
                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>Recent Sync &amp; Diagnostics History</span>
            </span>
            <span class="text-[11px] text-muted font-normal">Campaigns, Ad Sets, Ads &amp; Lead Forms</span>
        </summary>
        <div class="space-y-4 mt-3 pt-3 border-t border-border">
            <!-- Ad Account Sync History -->
            <div>
                <h5 class="font-bold text-ink uppercase tracking-wider text-[11px] mb-2">Campaigns &amp; Ads Synchronization History</h5>
                <div class="space-y-2">
                    @forelse($logs as $log)
                        @php($details = json_decode($log->to_value ?? '{}', true) ?: [])
                        <div class="rounded-lg border border-border p-2.5 bg-canvas">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold text-ink">{{ $log->created_at?->format('d M Y H:i:s') ?? $log->created_at }} · {{ str_replace('meta.ad_account.', '', $log->action) }}</p>
                                @if($details['stage'] ?? null)<span class="text-[10px] px-1.5 py-0.5 rounded bg-surface text-muted border border-border">Stage: {{ $details['stage'] }}</span>@endif
                            </div>
                            @if(isset($details['kind']))
                                <p class="text-muted mt-0.5">{{ ucfirst($details['kind']) }}: {{ $details['fetched'] ?? 0 }} fetched · {{ $details['new'] ?? 0 }} new · {{ $details['updated'] ?? 0 }} updated · {{ !empty($details['has_more']) ? 'More pages pending' : 'Edge complete' }}</p>
                            @endif
                            @if(isset($details['counts']))
                                <p class="text-muted mt-0.5">@foreach($details['counts'] as $k => $c){{ ucfirst($k) }}: {{ $c }} @endforeach</p>
                            @endif
                            @if(isset($details['message']))
                                <p class="text-ink mt-0.5">{{ $details['message'] }}</p>
                            @endif
                            @if($details['http_status'] ?? null)
                                <p class="text-muted text-[10px] mt-0.5">HTTP {{ $details['http_status'] }} · Meta code {{ $details['meta_code'] ?? '—' }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted text-xs p-2">No recent ad account synchronization logs.</p>
                    @endforelse
                </div>
            </div>

            <!-- Form Fetch History & Diagnostics -->
            <div>
                <h5 class="font-bold text-ink uppercase tracking-wider text-[11px] mb-2">Lead Form Fetch History &amp; Diagnostics</h5>
                <div class="overflow-x-auto rounded-lg border border-border">
                    <table class="w-full text-left text-xs bg-canvas">
                        <thead class="bg-surface text-muted">
                            <tr>
                                <th class="p-2">Time / Page</th>
                                <th class="p-2">Result</th>
                                <th class="p-2">Fetched / New / Saved</th>
                                <th class="p-2">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($formSyncHistory as $entry)
                                <tr>
                                    <td class="p-2 whitespace-nowrap">
                                        {{ $entry['at']->format('d M Y H:i:s') }}
                                        <span class="block text-muted text-[11px]">Page {{ $entry['page_id'] ?? '—' }} · {{ $entry['origin'] ?? '—' }}</span>
                                    </td>
                                    <td class="p-2">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                            'bg-emerald-50 text-emerald-700 border border-emerald-200' => ($entry['status'] ?? '') === 'completed',
                                            'bg-rose-50 text-rose-700 border border-rose-200' => ($entry['status'] ?? '') === 'failed',
                                            'bg-neutral-100 text-neutral-600 border border-neutral-200' => !in_array($entry['status'] ?? '', ['completed', 'failed']),
                                        ])>
                                            {{ ucfirst($entry['status'] ?? 'unknown') }}
                                        </span>
                                    </td>
                                    <td class="p-2 font-mono">{{ $entry['fetched'] ?? 0 }} / {{ $entry['new'] ?? 0 }} / {{ $entry['saved'] ?? 0 }}</td>
                                    <td class="p-2 min-w-64">
                                        {{ $entry['message'] ?? '' }}
                                        @if($entry['http_status'] ?? null)
                                            <span class="block text-muted text-[11px]">HTTP {{ $entry['http_status'] }} · Meta code {{ $entry['meta_code'] ?? '—' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="p-3 text-muted text-center">No lead form fetch attempts recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </details>

    <x-ui.modal name="meta-campaign-mapping" maxWidth="xl" centered>
        <form wire:submit="saveCampaignMapping" class="space-y-4 p-6"
            x-data="{ projectsRequestFailed: false }"
            x-init="$wire.$hook('commit', ({ succeed, fail }) => { succeed(() => { projectsRequestFailed = false }); fail(() => { projectsRequestFailed = true }) })">
            <h3 class="font-bold text-ink">Campaign Lead Destination</h3>
            <p class="text-xs text-muted">Historical and future attributed leads use this destination. Existing Page/form mappings stay unchanged.</p>
            
            <div>
                <label class="block text-xs font-semibold mb-1">Client / Organization <span class="text-danger">*</span></label>
                <x-ui.themed-select wire:model.live="mappingClientId" :options="$mappingClients" :searchable="true" />
                @error('mappingClientId')<p class="text-danger text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1">CRM Project <span class="text-danger">*</span></label>
                <p wire:loading.delay wire:target="mappingClientId,reloadMappingProjects,openCampaignMapping" role="status" class="text-xs text-muted mb-2">Loading projects…</p>
                <div x-show="projectsRequestFailed" x-cloak role="alert" class="text-xs text-danger mb-2">
                    Projects could not be loaded. Please retry.
                    <button type="button" wire:click="reloadMappingProjects" class="underline" wire:loading.attr="disabled">Retry</button>
                </div>
                @if($mappingProjectsFailed)
                    <p role="alert" class="text-xs text-danger mb-2">Projects could not be loaded. <button type="button" wire:click="reloadMappingProjects" wire:loading.attr="disabled" class="underline">Retry</button></p>
                @elseif($mappingClientValid && empty($projectsList))
                    <p role="status" class="text-xs text-muted mb-2">No projects available for this organization. No projects found for this organization.</p>
                @endif
                <div :inert="projectsRequestFailed" wire:loading.class="pointer-events-none opacity-50" wire:target="mappingClientId,reloadMappingProjects,openCampaignMapping">
                    <x-ui.themed-select
                        wire:model.live="mappingProjectId"
                        :options="$mappingProjects"
                        :searchable="true"
                        :disabled="!$mappingClientValid || $mappingProjectsFailed || empty($projectsList)"
                        wire:key="mapping-project-{{ $mappingClientId }}-{{ (int) $mappingProjectsFailed }}"
                        placeholder="{{ !$mappingClientValid ? 'Select client / organization first' : 'Select CRM project' }}"
                    />
                </div>
                @error('mappingProjectId')<p class="text-danger text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" x-on:click="$dispatch('close-modal', 'meta-campaign-mapping')" class="rounded-lg border border-border px-3 py-2 text-xs">Cancel</button>
                <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white">Save Mapping</button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal name="meta-ad-account-editor" maxWidth="2xl" centered>
        <form wire:submit="save" class="p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-ink">{{ $editingId ? 'Edit / Reconnect Ad Account' : 'Add Ad Account' }}</h3>
                <button type="button" @click="$dispatch('close-modal', 'meta-ad-account-editor')" title="Close" aria-label="Close" class="text-muted hover:text-ink"><x-ui.team-icon name="close" /></button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach(['name' => 'Account Display Name', 'adAccountId' => 'Ad Account ID', 'businessId' => 'Business Portfolio ID (optional)', 'tokenExpiry' => 'Token Expiry (optional)'] as $field => $label)
                    <div>
                        <label for="ad-{{ $field }}" class="block text-xs font-semibold mb-1 text-ink">{{ $label }}</label>
                        <input id="ad-{{ $field }}" wire:model="{{ $field }}" type="{{ $field === 'tokenExpiry' ? 'datetime-local' : 'text' }}" class="w-full rounded-lg border border-border bg-canvas text-ink text-sm px-3 py-2 focus:outline-none focus:ring-1 focus:ring-ink" />
                        @error($field)<p class="text-danger text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
            <div>
                <label for="ad-access-token" class="block text-xs font-semibold mb-1 text-ink">Ad Account Access Token</label>
                <input id="ad-access-token" type="password" autocomplete="new-password" wire:model="accessToken" class="w-full rounded-lg border border-border bg-canvas text-ink text-sm px-3 py-2 focus:outline-none focus:ring-1 focus:ring-ink" placeholder="{{ $editingId ? 'Leave blank to keep saved token' : 'User or system-user token with ads_read' }}" />
                @error('accessToken')<p class="text-danger text-xs mt-1">{{ $message }}</p>@enderror
                <p class="text-xs text-muted mt-1">Use a token authorized for this Ad Account. Page tokens are separate.</p>
            </div>
            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-primary text-white px-4 py-2 text-xs font-bold disabled:opacity-50 hover:bg-primary-hover transition">
                    <x-ui.team-icon name="check" /> Save &amp; Test
                </button>
            </div>
        </form>
    </x-ui.modal>
</section>
