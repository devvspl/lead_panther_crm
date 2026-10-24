<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Multi-Channel Notification Center</h1>
            <p class="text-xs text-muted">Configure message templates, trigger test dispatches, and monitor live delivery statuses across all 5 channels.</p>
        </div>
        <div class="flex items-center gap-1.5 flex-wrap">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> WhatsApp
            </span>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span> SMS
            </span>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Email
            </span>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-sky-500/10 text-sky-700 dark:text-sky-300 border border-sky-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span> CRM
            </span>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span> Push
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Template List Table Component -->
        <div class="lg:col-span-2 space-y-2">
            <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Configured Message Templates</h2>

            <x-ui.advanced-table 
                :columns="$this->tableColumns()"
                :rows="$templates"
                :visibleColumns="$visibleColumns"
                :sortField="$sortField"
                :sortDirection="$sortDirection"
                :quickFilters="[
                    ['key' => 'all', 'label' => 'All Channels'],
                    ['key' => 'whatsapp', 'label' => 'WhatsApp'],
                    ['key' => 'sms', 'label' => 'SMS Gateway'],
                    ['key' => 'email', 'label' => 'Email (SMTP)'],
                    ['key' => 'crm', 'label' => 'CRM In-App'],
                    ['key' => 'push', 'label' => 'Push Notification'],
                ]"
                :activeStatus="$statusFilter"
                searchPlaceholder="Search key, channel, body..."
                emptyTitle="No Templates Found"
                emptyMessage="No notification templates defined yet. Default fallback copy will be used."
            />
        </div>

        <!-- Template Form -->
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-bold text-ink uppercase tracking-wider">{{ $selectedId ? 'Edit Template' : 'Create New Template' }}</h2>
                @if($selectedId)
                    <span class="text-[10px] bg-accent/10 text-accent font-bold px-2 py-0.5 rounded-pill">ID #{{ $selectedId }}</span>
                @endif
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="font-bold text-ink">Template Key</label>
                    <input type="text" wire:model="key" placeholder="e.g. lead_assigned_whatsapp" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1 font-mono text-xs">
                </div>

                <div>
                    <label class="font-bold text-ink">Channel</label>
                    <x-ui.themed-select 
                        wire:model="channel"
                        :options="[
                            'whatsapp' => 'WhatsApp Business',
                            'sms' => 'SMS Gateway',
                            'email' => 'Email (SMTP/SES)',
                            'crm' => 'CRM In-App Notification',
                            'push' => 'Push Notification',
                        ]"
                        placeholder="Select Channel"
                        class="w-full mt-1"
                    />
                </div>

                <div>
                    <label class="font-bold text-ink">Subject / Header (Optional)</label>
                    <input type="text" wire:model="subject" placeholder="Email / Push Subject or Notification Title" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
                </div>

                <div>
                    <label class="font-bold text-ink">Message Body</label>
                    <textarea wire:model="body" rows="4" placeholder="Hello @{{lead_name}}, your lead code is @{{lead_code}}. Our executive @{{assigned_to}} will assist you." class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1 leading-relaxed"></textarea>
                    <div class="flex flex-wrap gap-1 mt-1.5">
                        <span class="text-[10px] text-muted">Tokens:</span>
                        <code class="text-[10px] bg-canvas px-1 rounded border border-border text-ink">&#123;&#123;lead_name&#125;&#125;</code>
                        <code class="text-[10px] bg-canvas px-1 rounded border border-border text-ink">&#123;&#123;lead_code&#125;&#125;</code>
                        <code class="text-[10px] bg-canvas px-1 rounded border border-border text-ink">&#123;&#123;assigned_to&#125;&#125;</code>
                        <code class="text-[10px] bg-canvas px-1 rounded border border-border text-ink">&#123;&#123;project_name&#125;&#125;</code>
                    </div>
                </div>

                <div class="flex items-center space-x-2 pt-2">
                    <x-ui.button wire:click="saveTemplate" variant="primary" class="w-full text-xs py-2 justify-center">
                        Save Template
                    </x-ui.button>

                    @if($selectedId)
                        <button wire:click="resetForm" class="text-xs text-muted hover:underline px-3 py-2">Cancel</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Live Test Dispatcher & Notification Delivery Logs -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pt-2">
        <!-- Test Dispatcher Panel -->
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
            <div>
                <h3 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                    Test Notification Dispatch
                </h3>
                <p class="text-[11px] text-muted mt-1">Simulate real-time multi-channel delivery across SMS, WhatsApp, Email, CRM, or Push drivers.</p>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="font-bold text-ink">Channel</label>
                    <x-ui.themed-select 
                        wire:model="testChannel"
                        :options="[
                            'whatsapp' => 'WhatsApp Business',
                            'sms' => 'SMS Gateway',
                            'email' => 'Email (SMTP)',
                            'crm' => 'CRM In-App Notification',
                            'push' => 'Push Notification',
                        ]"
                        placeholder="Channel"
                        class="w-full mt-1"
                    />
                </div>

                <div>
                    <label class="font-bold text-ink">Recipient (Phone / Email)</label>
                    <input type="text" wire:model="testRecipient" placeholder="+91 98765 43210 or user@example.com" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
                </div>

                <div>
                    <label class="font-bold text-ink">Template Key (Optional)</label>
                    <input type="text" wire:model="testTemplateKey" placeholder="Leave blank to use default template" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1 font-mono text-[11px]">
                </div>

                <div class="pt-2">
                    <button 
                        wire:click="sendTestNotification" 
                        wire:loading.attr="disabled"
                        class="w-full py-2.5 px-4 bg-accent hover:bg-accent/90 text-white font-bold text-xs rounded-button flex items-center justify-center gap-2 transition cursor-pointer shadow-sm disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="sendTestNotification" class="flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                            Dispatch Test Notification
                        </span>
                        <span wire:loading wire:target="sendTestNotification" class="flex items-center gap-1.5">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            Dispatching...
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Notification Delivery Logs Table -->
        <div class="lg:col-span-2 bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h3 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Notification Delivery Logs & Retries
                    </h3>
                    <p class="text-[11px] text-muted">Audited multi-channel delivery attempts, execution statuses, and automated retry tracking.</p>
                </div>

                <!-- Channel filters -->
                <div class="flex items-center gap-1 bg-canvas p-1 rounded-lg border border-border text-[11px]">
                    @foreach(['all' => 'All', 'whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'Email', 'crm' => 'CRM', 'push' => 'Push'] as $chKey => $chLabel)
                        <button 
                            wire:click="filterLogsByChannel('{{ $chKey }}')"
                            class="px-2 py-0.5 rounded font-medium transition {{ $logChannelFilter === $chKey ? 'bg-surface text-ink font-bold shadow-xs' : 'text-muted hover:text-ink' }}"
                        >
                            {{ $chLabel }}
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Logs list -->
            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-canvas border-b border-border text-muted font-bold text-[10px] uppercase tracking-wider">
                            <th class="py-2.5 px-3">Log ID</th>
                            <th class="py-2.5 px-3">Channel</th>
                            <th class="py-2.5 px-3">Recipient / Details</th>
                            <th class="py-2.5 px-3">Delivery Status</th>
                            <th class="py-2.5 px-3 text-center">Retries</th>
                            <th class="py-2.5 px-3">Sent At</th>
                            <th class="py-2.5 px-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($logs as $log)
                            @php
                                $payload = json_decode($log->payload ?? '{}', true);
                                $recipient = $payload['recipient'] ?? 'N/A';
                                $tmplKey = $payload['template_key'] ?? 'N/A';
                            @endphp
                            <tr class="hover:bg-canvas/50 transition">
                                <td class="py-2.5 px-3 font-mono text-[11px] font-bold text-ink">#{{ $log->id }}</td>
                                <td class="py-2.5 px-3">
                                    @if($log->channel === 'whatsapp')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20">
                                            WhatsApp
                                        </span>
                                    @elseif($log->channel === 'sms')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-500/20">
                                            SMS
                                        </span>
                                    @elseif($log->channel === 'email')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20">
                                            Email
                                        </span>
                                    @elseif($log->channel === 'crm')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-sky-500/10 text-sky-700 dark:text-sky-300 border border-sky-500/20">
                                            CRM
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/10 text-purple-700 dark:text-purple-300 border border-purple-500/20">
                                            Push
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="font-medium text-ink">{{ $recipient }}</div>
                                    <div class="text-[10px] text-muted font-mono">{{ $tmplKey }}</div>
                                </td>
                                <td class="py-2.5 px-3">
                                    @if($log->status === 'delivered')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Delivered
                                        </span>
                                    @elseif($log->status === 'sent')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/10 text-sky-700 dark:text-sky-300 border border-sky-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span> Sent
                                        </span>
                                    @elseif($log->status === 'failed')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-500/20" title="{{ $log->last_error }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Failed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> {{ ucfirst($log->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="font-mono font-bold {{ ($log->retries ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted' }}">
                                        {{ $log->retries ?? 0 }}
                                    </span>
                                    @if(!empty($log->last_error))
                                        <div class="text-[9px] text-rose-500 truncate max-w-[120px] mx-auto" title="{{ $log->last_error }}">
                                            {{ $log->last_error }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-muted text-[11px] whitespace-nowrap">
                                    {{ $log->sent_at ? \Carbon\Carbon::parse($log->sent_at)->format('M d, H:i') : $log->created_at->format('M d, H:i') }}
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <button 
                                        wire:click="retryNotification({{ $log->id }})"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border border-border bg-canvas hover:bg-surface text-ink hover:text-accent transition cursor-pointer"
                                        title="Retry dispatching notification"
                                    >
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Retry
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-muted">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-8 h-8 text-muted/50 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                        <p class="font-bold text-ink text-xs">No notification logs recorded</p>
                                        <p class="text-[11px] text-muted">Use the test dispatcher on the left to send your first notification.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
