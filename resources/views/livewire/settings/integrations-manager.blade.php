<div class="space-y-6" x-data="{ source: @entangle('portalType'), copied: false }">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">API & Webhook Integrations</h1>
            <p class="text-xs text-muted">Configure Meta Ads, Google Ads, 99acres, and MagicBricks credentials (Encrypted at rest).</p>
        </div>
    </div>

    <!-- Add / Edit Credential Card -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-5">
        <div class="flex items-center justify-between">
            <h2 class="text-xs font-bold text-ink uppercase tracking-wider">
                {{ $selectedAccountId ? 'Edit Connection Credential (Account #' . $selectedAccountId . ')' : 'Add Connection Credential' }}
            </h2>
            @if($selectedAccountId)
                <button wire:click="resetForm" class="text-xs font-medium text-muted hover:text-ink underline">
                    + Add New Connection
                </button>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <div>
                <label class="font-bold text-ink">Portal / Integration Source</label>
                <select 
                    wire:model.live="portalType" 
                    x-model="source"
                    class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1 font-medium"
                >
                    <option value="meta">Meta Ads (Facebook & Instagram Lead Ads)</option>
                    <option value="google">Google Lead Form Ads</option>
                    <option value="portal">Property Portals (99acres / MagicBricks)</option>
                    <option value="owned">Owned Web Portals</option>
                </select>
            </div>

            <div>
                <label class="font-bold text-ink">Account Display Name</label>
                <input 
                    type="text" 
                    wire:model="accountName" 
                    placeholder="e.g. Bandra Campaign Account"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1"
                />
                @error('accountName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
            </div>
        </div>

        <!-- Meta-Specific Fields -->
        <div x-show="source === 'meta'" class="space-y-4 pt-2 border-t border-border">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="font-bold text-ink">Facebook Page ID <span class="text-red-500">*</span></label>
                    <input 
                        type="text" 
                        wire:model="metaPageId" 
                        placeholder="e.g. 109283746501928"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1"
                    />
                    @error('metaPageId') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    <p class="text-[10px] text-muted mt-1">Found in your Meta Business Suite &gt; Page About info.</p>
                </div>

                <div>
                    <label class="font-bold text-ink">Meta App ID <span class="text-muted font-normal">(Optional)</span></label>
                    <input 
                        type="text" 
                        wire:model="metaAppId" 
                        placeholder="e.g. 987654321098765"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1"
                    />
                    @error('metaAppId') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    <p class="text-[10px] text-muted mt-1">Found in Meta for Developers app dashboard.</p>
                </div>

                <div>
                    <label class="font-bold text-ink">Meta App Secret <span class="text-muted font-normal">(Optional)</span></label>
                    <input 
                        type="password" 
                        wire:model="metaAppSecret" 
                        placeholder="••••••••••••••••••••••••"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1"
                    />
                    @error('metaAppSecret') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    <p class="text-[10px] text-muted mt-1">Used for webhook payload HMAC-SHA256 signature verification.</p>
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-ink">Page Access Token <span class="text-red-500">*</span></label>
                        <span class="text-[10px] text-primary hover:underline cursor-pointer inline-flex items-center gap-1" title="Generate via Business Manager > System Users > Generate New Token with leads_retrieval & pages_show_list">
                            <span>System User Token Guide</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><line x1="12" y1="16" x2="12" y2="12" stroke-width="2"></line><line x1="12" y1="8" x2="12.01" y2="8" stroke-width="2"></line></svg>
                        </span>
                    </div>
                    <input 
                        type="password" 
                        wire:model="metaAccessToken" 
                        placeholder="EAABw... (Never-expiring System User Token or Graph Token)"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1"
                    />
                    @error('metaAccessToken') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    <p class="text-[10px] text-muted mt-1">Requires <code class="font-bold text-ink">leads_retrieval</code> and <code class="font-bold text-ink">pages_show_list</code> permissions.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs pt-2">
                <div>
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-ink">Webhook Verify Token</label>
                        <button type="button" wire:click="regenerateVerifyToken" class="text-[10px] text-primary hover:underline inline-flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            <span>Generate New</span>
                        </button>
                    </div>
                    <input 
                        type="text" 
                        wire:model="metaVerifyToken" 
                        placeholder="Random verification token"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1"
                    />
                    @error('metaVerifyToken') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    <p class="text-[10px] text-muted mt-1">Paste this into Meta App Dashboard &gt; Webhooks &gt; Verify Token.</p>
                </div>

                <div>
                    <label class="font-bold text-ink">Webhook Callback URL</label>
                    <div class="flex items-center space-x-2 mt-1">
                        <input 
                            type="text" 
                            readonly 
                            value="{{ url('/api/webhooks/meta/' . ($selectedAccountId ?? ($accounts->first()?->id ?? 1))) }}"
                            class="w-full h-8 px-3 rounded-lg border border-border bg-canvas/60 text-muted font-mono text-[11px] select-all cursor-text focus:outline-none"
                            id="metaWebhookUrl"
                        />
                        <button 
                            type="button"
                            x-on:click="
                                navigator.clipboard.writeText(document.getElementById('metaWebhookUrl').value);
                                copied = true;
                                setTimeout(() => copied = false, 2000);
                            "
                            class="px-3 h-8 bg-surface border border-border rounded-lg text-ink font-semibold text-xs hover:bg-canvas transition flex items-center gap-1.5 shrink-0"
                        >
                            <span x-show="!copied" class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                                <span>Copy</span>
                            </span>
                            <span x-show="copied" class="text-emerald-700 font-bold flex items-center gap-1" style="display: none;">
                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                <span>Copied</span>
                            </span>
                        </button>
                    </div>
                    <p class="text-[10px] text-muted mt-1">Callback URL to configure in Meta App Webhooks subscription.</p>
                </div>
            </div>
        </div>

        <!-- Google-Specific Fields -->
        <div x-show="source === 'google'" class="space-y-4 pt-2 border-t border-border" style="display: none;">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="font-bold text-ink">Google Ads Customer ID</label>
                    <input type="text" wire:model="googleCustomerId" placeholder="123-456-7890" class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono mt-1" />
                </div>
                <div>
                    <label class="font-bold text-ink">Developer Token (Encrypted)</label>
                    <input type="password" wire:model="googleDeveloperToken" placeholder="••••••••••••••••" class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono mt-1" />
                </div>
            </div>
        </div>

        <!-- Property Portals Specific Fields -->
        <div x-show="source === 'portal'" class="space-y-4 pt-2 border-t border-border" style="display: none;">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="font-bold text-ink">Portal API Key (99acres / MagicBricks)</label>
                    <input type="password" wire:model="portalApiKey" placeholder="••••••••••••••••" class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono mt-1" />
                </div>
                <div>
                    <label class="font-bold text-ink">Vendor / Agency ID</label>
                    <input type="text" wire:model="portalVendorId" placeholder="e.g. VEND-99201" class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono mt-1" />
                </div>
            </div>
        </div>

        <!-- Owned Web Portals -->
        <div x-show="source === 'owned'" class="space-y-4 pt-2 border-t border-border" style="display: none;">
            <div class="text-xs">
                <label class="font-bold text-ink">Web API Key / Bearer Secret</label>
                <input type="password" wire:model="apiSecret" placeholder="••••••••••••••••" class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono mt-1" />
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end space-x-3 pt-3 border-t border-border">
            <button 
                type="button" 
                wire:click="testCurrentFormConnection" 
                wire:loading.attr="disabled"
                class="px-4 py-2 border border-border bg-white text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition flex items-center gap-2 disabled:opacity-60 cursor-pointer shadow-xs"
            >
                <svg wire:loading wire:target="testCurrentFormConnection" class="animate-spin h-3.5 w-3.5 text-ink" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Test Connection</span>
            </button>

            <button 
                type="button" 
                wire:click="saveAccount" 
                wire:loading.attr="disabled"
                class="px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition flex items-center gap-2 disabled:opacity-60 cursor-pointer shadow-xs"
            >
                <svg wire:loading wire:target="saveAccount" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Save Encrypted Credentials</span>
            </button>
        </div>
    </div>

    <!-- Lead Form Mapping Section (Meta Lead Forms) -->
    @if($selectedMappingAccountId)
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-bold text-ink uppercase tracking-wider">
                        Lead Form Mapping (Account #{{ $selectedMappingAccountId }})
                    </h2>
                    <p class="text-[11px] text-muted">
                        Map Meta Leadgen Forms directly to CRM Projects & Campaigns for automated lead attribution and distribution.
                    </p>
                </div>

                <button 
                    wire:click="fetchLeadForms({{ $selectedMappingAccountId }})" 
                    class="px-3 py-1.5 border border-border bg-white text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition flex items-center gap-1.5"
                >
                    <svg wire:loading.remove wire:target="fetchLeadForms" class="w-3.5 h-3.5 text-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <svg wire:loading wire:target="fetchLeadForms" class="animate-spin h-3.5 w-3.5 text-ink" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span>Refresh Lead Forms</span>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-ink border-collapse">
                    <thead>
                        <tr class="border-b border-border text-[10px] uppercase font-bold text-muted bg-canvas">
                            <th class="py-3 px-4">Form Name & ID</th>
                            <th class="py-3 px-4">Meta Status</th>
                            <th class="py-3 px-4">Assigned CRM Project</th>
                            <th class="py-3 px-4">Assigned CRM Campaign</th>
                            <th class="py-3 px-4">Mapping Status</th>
                            <th class="py-3 px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($availableForms as $form)
                            @php
                                $formId = $form['id'];
                                $formName = $form['name'] ?? 'Lead Form ' . $formId;
                                $isMapped = !empty($formProjectMap[$formId]);
                            @endphp
                            <tr class="hover:bg-canvas/50 transition">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-ink">{{ $formName }}</div>
                                    <div class="font-mono text-muted text-[10px] mt-0.5">ID: {{ $formId }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        {{ $form['status'] ?? 'ACTIVE' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <select 
                                        wire:model="formProjectMap.{{ $formId }}" 
                                        class="h-7 px-2.5 rounded-lg border border-border bg-white text-ink text-xs focus:ring-1 focus:ring-ink"
                                    >
                                        <option value="">-- Select Project --</option>
                                        @foreach($projects as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-3 px-4">
                                    <select 
                                        wire:model="formCampaignMap.{{ $formId }}" 
                                        class="h-7 px-2.5 rounded-lg border border-border bg-white text-ink text-xs focus:ring-1 focus:ring-ink"
                                    >
                                        <option value="">-- Optional Campaign --</option>
                                        @foreach($campaigns as $c)
                                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-3 px-4">
                                    @if($isMapped)
                                        <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-emerald-50 text-emerald-700 border border-emerald-200 inline-flex items-center gap-1">
                                            <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                            <span>Mapped</span>
                                        </span>
                                    @else
                                        <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-amber-50 text-amber-800 border border-amber-200 inline-flex items-center gap-1">
                                            <svg class="w-3 h-3 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            <span>Unmapped</span>
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <button 
                                        wire:click="saveFormMapping('{{ $formId }}', '{{ addslashes($formName) }}')" 
                                        class="px-3 py-1 bg-ink text-white font-semibold text-[11px] rounded-lg hover:bg-neutral-800 transition"
                                    >
                                        Save
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-muted">
                                    No lead forms found on this Meta Page. Click "Refresh Lead Forms" to query Graph API.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Configured Portal Accounts Table -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Configured Portal Accounts</h2>
            <span class="text-xs text-muted font-medium">{{ $accounts->total() }} Active Connections</span>
        </div>

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
                        @php
                            $statusText = $connectionStatus[$acc->id] ?? ($acc->health_message ?: 'Untested');
                            $isHealthy = str_starts_with($statusText, 'Connected');
                            $isError = str_starts_with($statusText, 'Error:');
                        @endphp
                        <tr class="hover:bg-canvas/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-ink">#{{ $acc->id }}</td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-ink">{{ $acc->name }}</div>
                                @if($acc->type === 'meta' && $acc->getCredential('page_id'))
                                    <div class="text-[10px] font-mono text-muted">Page ID: {{ $acc->getCredential('page_id') }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if($acc->type === 'meta')
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-blue-50 text-blue-700 border border-blue-200 inline-flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                        META ADS
                                    </span>
                                @elseif($acc->type === 'google')
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-red-50 text-red-700 border border-red-200">
                                        GOOGLE ADS
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill uppercase bg-canvas text-ink border border-border">
                                        {{ strtoupper($acc->type) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-emerald-700 font-semibold text-[11px] inline-flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    <span>Encrypted (AES-256)</span>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                @if($isHealthy)
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-emerald-50 text-emerald-700 border border-emerald-200 inline-flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <span>{{ $statusText }}</span>
                                    </span>
                                @elseif($isError)
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-red-50 text-red-700 border border-red-200 inline-flex items-center gap-1.5" title="{{ $statusText }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                        <span>{{ Str::limit($statusText, 35) }}</span>
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-canvas text-muted border border-border inline-flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>
                                        <span>Untested</span>
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="inline-flex items-center space-x-2">
                                    <button 
                                        wire:click="testConnection({{ $acc->id }})"
                                        class="px-2 py-1 text-[11px] font-bold text-primary hover:bg-canvas rounded transition"
                                        title="Test API connectivity"
                                    >
                                        Test
                                    </button>

                                    @if($acc->type === 'meta')
                                        <button 
                                            wire:click="fetchLeadForms({{ $acc->id }})"
                                            class="px-2 py-1 text-[11px] font-bold text-ink hover:bg-canvas rounded transition"
                                            title="Configure Lead Form mappings"
                                        >
                                            Map Forms
                                        </button>
                                    @endif

                                    <button 
                                        wire:click="editAccount({{ $acc->id }})"
                                        class="px-2 py-1 text-[11px] font-medium text-muted hover:text-ink hover:bg-canvas rounded transition"
                                    >
                                        Edit
                                    </button>

                                    <button 
                                        wire:click="deleteAccount({{ $acc->id }})"
                                        wire:confirm="Are you sure you want to remove this connection and its encrypted credentials?"
                                        class="px-2 py-1 text-[11px] font-medium text-red-600 hover:bg-red-50 rounded transition"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-muted">
                                No portal account credentials configured. Add your first Meta Ads connection above.
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