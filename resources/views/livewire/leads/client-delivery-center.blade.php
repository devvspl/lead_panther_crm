<div class="space-y-4" wire:poll.5s="$refresh">
    <details wire:ignore.self wire:key="client-delivery-settings-{{ $organizationId }}" class="rounded-card border border-border bg-canvas p-4">
        <summary class="text-sm font-semibold text-ink cursor-pointer">
            Client Delivery
            <span class="block text-xs font-normal text-muted mt-1">{{ $plan ? $plan->frequency_label.' · '.($plan->settings['quantity_mode'] === 'all' ? 'Send All Available Leads' : $plan->settings['quantity'].' leads per cycle').' · '.ucfirst($plan->state) : 'Configure a destination and schedule to start pushing leads.' }}</span>
        </summary>
        <div class="mt-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2 text-xs">
                <button type="button" wire:click="$set('showConfiguration', true)" class="h-8 px-3 rounded-lg border border-border bg-surface font-semibold">Configure Delivery</button>
                @if($plan)
                    <button type="button" wire:click="runNow" @if($stats['Pending'] > 0) wire:confirm="Start one delivery cycle using the saved limits and working hours?" @endif class="h-8 px-3 rounded-lg bg-ink text-white font-semibold">Send Cycle Now</button>
                    @if($plan->state === 'active')<button type="button" wire:click="control('pause')" class="h-8 px-3 rounded-lg border border-border bg-surface">Pause</button>@else<button type="button" wire:click="control('resume')" class="h-8 px-3 rounded-lg border border-border bg-surface">Resume</button>@endif
                    <button type="button" wire:click="control('stop')" wire:confirm="Stop this delivery? Unsent leads will remain in the pending queue." class="h-8 px-3 rounded-lg border border-red-200 text-red-700 bg-surface">Stop</button>
                @endif
            </div>
        </div>
        @if($deliveryHealth)
            <div class="space-y-2 text-xs" aria-live="polite">
                <p class="font-semibold">Ready for delivery: {{ $deliveryHealth['ready'] }} · Awaiting internal approval: {{ $deliveryHealth['awaiting'] }}</p>
                <p class="text-muted">Available Lead Credits: {{ number_format($deliveryHealth['balance']) }} credits · Credits per delivered lead: {{ $deliveryHealth['cost'] !== null ? number_format($deliveryHealth['cost']) : 'Unavailable' }}</p>
                @foreach($deliveryHealth['alerts'] as $alert)<p role="status" class="rounded-lg border border-border bg-surface px-3 py-2 {{ $alert['type']==='error' ? 'text-red-700' : ($alert['type']==='warning' ? 'text-amber-700' : 'text-muted') }}">{{ $alert['message'] }}</p>@endforeach
                <a href="{{ route('admin.git-sync',['tab'=>'tasks']) }}" class="text-primary font-semibold">Open Scheduled Tasks &amp; Worker Monitoring</a>
            </div>
        @endif
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            @foreach($stats as $label => $value)
                @if($label === 'Delivered' && $run)
                    <button type="button" wire:click="$dispatch('open-delivered-batch', { organizationId: {{ $organizationId }}, batchId: {{ $run->id }} })" title="View delivered leads in batch #{{ $run->id }}" class="rounded-lg bg-surface border border-border p-3 text-left hover:border-primary focus-visible:ring-2 focus-visible:ring-primary">
                        <span class="block text-[10px] uppercase font-semibold text-muted">Delivered · Batch #{{ $run->id }}</span><span class="block mt-1 text-xs font-bold text-primary">{{ $successes }}</span><span class="block mt-1 text-[10px] text-muted">{{ $value }} delivered overall</span>
                    </button>
                @else
                    <div class="rounded-lg bg-surface border border-border p-3"><div class="text-[10px] uppercase font-semibold text-muted">{{ $label }}</div><div class="mt-1 text-xs font-bold text-ink break-words">{{ $value }}</div></div>
                @endif
            @endforeach
        </div>
        @if($run)
            <div class="text-xs text-ink space-y-2" role="status"><div class="flex flex-wrap gap-x-4 gap-y-1"><strong>Batch #{{ $run->id }} · {{ ucfirst($run->status) }}</strong><span>Total: {{ $run->total }}</span><span>Processed: {{ $processed }}</span><button type="button" wire:click="$dispatch('open-delivered-batch', { organizationId: {{ $organizationId }}, batchId: {{ $run->id }} })" title="View delivered leads in batch #{{ $run->id }}" class="font-semibold text-primary underline underline-offset-2">Successful / Delivered: {{ $successes }}</button><span>Failed: {{ $failures }}</span><span>Remaining: {{ $remaining }}</span></div><progress class="w-full h-2 accent-primary" max="{{ max(1, $run->total) }}" value="{{ $processed }}"></progress></div>
        @endif
        </div>
    </details>

    <livewire:leads.delivered-batch-leads :organization-id="$organizationId" :key="'delivered-batch-leads-'.$organizationId" />

    <!-- Revoke Delivered Leads Section -->
    <details wire:ignore.self wire:key="client-delivery-revoke-{{ $organizationId }}" class="rounded-card border border-border bg-surface p-4" open>
        <summary class="text-sm font-semibold text-ink cursor-pointer flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                </svg>
                <span>Revoke Delivered Leads</span>
            </div>
            <span class="text-xs font-normal text-muted">Filter delivered leads by date, client or project to return them to the CRM</span>
        </summary>
        <div class="mt-4 space-y-4">
            <!-- Filter toolbar -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
                <div>
                    <label class="block text-muted mb-1 font-medium">Delivered From</label>
                    <x-text-input variant="compact" type="date" wire:model.live="revokeFrom" class="w-full" />
                </div>
                <div>
                    <label class="block text-muted mb-1 font-medium">Delivered To</label>
                    <x-text-input variant="compact" type="date" wire:model.live="revokeTo" class="w-full" />
                </div>
                <div>
                    <label class="block text-muted mb-1 font-medium">Client / Organization</label>
                    <x-ui.themed-select wire:model.live="revokeClient" :options="$revokeClients->pluck('name','id')->all()" placeholder="All Clients" :searchable="true" class="w-full" />
                </div>
                <div>
                    <label class="block text-muted mb-1 font-medium">Project</label>
                    <x-ui.themed-select wire:model.live="revokeProject" :options="$revokeProjects->pluck('name','id')->all()" placeholder="All Projects" :searchable="true" class="w-full" />
                </div>
                <div>
                    <label class="block text-muted mb-1 font-medium">Delivery Status</label>
                    <x-ui.themed-select wire:model.live="revokeStatus" :options="['delivered' => 'Delivered (Sent/Accepted)', 'sent' => 'Sent', 'accepted' => 'Accepted', 'revoked' => 'Revoked', '' => 'All Statuses']" :searchable="true" class="w-full" />
                </div>
            </div>

            <!-- Selection Banner & Action Bar -->
            <div class="flex flex-wrap items-center justify-between gap-2.5 pt-1">
                <div class="flex items-center gap-2">
                    @if(count($selectedRevokeIds) > 0)
                        <button type="button"
                                wire:click="openRevokeBatch"
                                class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition shadow-xs flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                            </svg>
                            <span>Revoke Selected ({{ count($selectedRevokeIds) }})</span>
                        </button>
                        <button type="button"
                                wire:click="clearSelection"
                                class="px-2.5 py-1.5 rounded-lg border border-border text-muted hover:text-ink text-xs font-medium transition cursor-pointer">
                            Clear Selection
                        </button>
                    @endif
                    @if(!$revokeSelectAllMatching && $totalRevokeMatching > count($selectedRevokeIds) && count($selectedRevokeIds) > 0)
                        <button type="button"
                                wire:click="selectAllMatching"
                                class="text-xs text-primary font-semibold hover:underline cursor-pointer">
                            Select all {{ $totalRevokeMatching }} leads matching current filters
                        </button>
                    @elseif($revokeSelectAllMatching)
                        <span class="text-xs text-ink font-semibold">
                            All {{ $totalRevokeMatching }} matching delivered leads selected
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <x-ui.export-button target="exportRevokeList" label="Export List" />
                </div>
            </div>

            <!-- Advanced Table with checkboxes -->
            <x-ui.advanced-table
                :columns="$this->revokeColumns()"
                :rows="$revokeLeads"
                :show-checkboxes="true"
                :selected-rows="$selectedRevokeIds"
                :select-all="$selectAll"
                searchModel="revokeSearch"
                searchPlaceholder="Search lead ID, customer name or mobile..."
                :show-filter-dropdown="false"
                :show-configurations="false"
                :show-skeleton="false"
                table-name="client-delivery-revoke-table"
                empty-title="No delivered leads found"
                empty-message="No delivered leads match the selected filters or date range." />
        </div>
    </details>

    <!-- Revoke Confirmation Modal -->
    @if($showRevokeConfirmModal)
        @php
            $targetCount = count($revokeTargetItemIds);
            $singleTargetItem = $targetCount === 1 ? \App\Models\LeadDeliveryItem::with(['lead', 'client.organization', 'project'])->find($revokeTargetItemIds[0]) : null;
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4"
             wire:keydown.escape="closeRevokeConfirmModal">
            <div class="bg-surface rounded-2xl border border-border shadow-2xl max-w-lg w-full p-5 sm:p-6 space-y-4 max-h-[calc(100dvh-2rem)] flex flex-col animate-in fade-in zoom-in-95 duration-150">
                <!-- Header -->
                <div class="flex items-start justify-between pb-3.5 border-b border-border shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 border border-rose-200/80 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-ink">
                                    {{ $targetCount === 1 ? 'Revoke Delivered Lead' : 'Revoke ' . $targetCount . ' Delivered Leads' }}
                                </h3>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                    {{ $targetCount === 1 ? 'Single Lead' : 'Batch Revoke' }}
                                </span>
                            </div>
                            <p class="text-xs text-muted mt-0.5">
                                Return {{ $targetCount === 1 ? 'this delivered lead' : 'selected leads' }} to the CRM available lead pool.
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeRevokeConfirmModal" class="text-muted hover:text-ink p-1 rounded-lg hover:bg-canvas transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Target details -->
                @if($targetCount === 1 && $singleTargetItem)
                    <div class="bg-canvas rounded-xl border border-border p-3.5 space-y-2 text-xs">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="text-muted block text-[11px] font-medium">Lead ID</span>
                                <span class="font-mono font-bold text-ink text-sm">#{{ $singleTargetItem->lead?->lead_code ?? $singleTargetItem->lead_id }}</span>
                                @if($singleTargetItem->lead?->name)
                                    <span class="text-muted block text-[11px] truncate">{{ $singleTargetItem->lead->name }}</span>
                                @endif
                            </div>
                            <div>
                                <span class="text-muted block text-[11px] font-medium">Delivered Date</span>
                                <span class="font-semibold text-ink">
                                    {{ $singleTargetItem->sent_at ? $singleTargetItem->sent_at->format('d M Y, h:i A') : 'N/A' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-muted block text-[11px] font-medium">Client / Organization</span>
                                <span class="font-semibold text-ink truncate block">
                                    {{ $singleTargetItem->client?->organization?->name ?? $singleTargetItem->client?->name ?? 'N/A' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-muted block text-[11px] font-medium">Project</span>
                                <span class="font-semibold text-ink truncate block">
                                    {{ $singleTargetItem->project?->name ?? 'N/A' }}
                                </span>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-canvas rounded-xl border border-border p-3.5 space-y-1.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-ink">Selected Leads for Revocation</span>
                            <span class="px-2 py-0.5 rounded font-bold bg-primary/10 text-primary text-xs">
                                {{ $targetCount }} Lead{{ $targetCount > 1 ? 's' : '' }} Selected
                            </span>
                        </div>
                        <p class="text-muted text-[11px]">
                            All {{ $targetCount }} selected leads will be returned to the CRM, unassigned from client executives, and marked as excluded from automatic future client delivery.
                        </p>
                    </div>
                @endif

                <!-- Return reason -->
                <div class="space-y-1.5">
                    <label for="revoke-batch-reason-input" class="block text-xs font-semibold text-ink">
                        Return Reason <span class="text-rose-500">*</span>
                    </label>
                    <textarea id="revoke-batch-reason-input"
                              wire:model="revokeBatchReason"
                              rows="3"
                              placeholder="Provide the reason for returning {{ $targetCount === 1 ? 'this lead' : 'these leads' }} to the CRM..."
                              class="w-full rounded-xl border border-border bg-surface px-3 py-2 text-xs text-ink placeholder:text-muted focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none transition"></textarea>
                    @error('revokeBatchReason')
                        <p class="text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-2.5 pt-3.5 border-t border-border shrink-0">
                    <button type="button"
                            wire:click="closeRevokeConfirmModal"
                            class="px-3.5 py-2 text-xs font-semibold rounded-xl border border-border text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="confirmRevokeDelivered"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 text-xs font-bold rounded-xl bg-rose-600 text-white hover:bg-rose-700 active:bg-rose-800 transition cursor-pointer shadow-sm flex items-center gap-2">
                        <span wire:loading.remove wire:target="confirmRevokeDelivered">
                            Confirm Revoke ({{ $targetCount }})
                        </span>
                        <span wire:loading wire:target="confirmRevokeDelivered" class="inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            Revoking...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <x-ui.offcanvas wire:model="showConfiguration" name="client-delivery-configuration" title="Push Leads to Client" subtitle="Choose where to send the leads and when." maxWidth="2xl">
        <div class="space-y-5 text-xs">
            @if($selectedLeadIds)<p class="rounded-lg border border-border bg-canvas p-3 font-semibold">{{ count($selectedLeadIds) }} lead(s) selected</p>@endif
            <div class="grid sm:grid-cols-3 gap-3">
                <div><label class="font-semibold">Destination Client *</label><x-ui.themed-select wire:model.live="settings.client_id" :options="$clients->pluck('name','id')->all()" placeholder="Choose client" :searchable="true" /></div>
                <div wire:key="delivery-project-{{ $settings['client_id'] }}"><label class="font-semibold">Project *</label><x-ui.themed-select wire:model.live="settings.project_id" :options="$projects->pluck('name','id')->all()" placeholder="Choose project" :searchable="true" /></div>
                <div wire:key="delivery-campaign-{{ $settings['project_id'] }}"><label class="font-semibold">Campaign (Optional)</label><x-ui.themed-select wire:model.live="settings.campaign_id" :options="['' => 'Use project default'] + $campaigns->pluck('name','id')->all()" placeholder="Use project default" :searchable="true" /></div>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div><label>Delivery Frequency</label><x-ui.themed-select wire:model.live="settings.frequency" :options="['immediate'=>'Immediately','15_minutes'=>'Every 15 minutes','30_minutes'=>'Every 30 minutes','custom_minutes'=>'Every ' . ($settings['interval_minutes'] ?? 20) . ' minutes (Custom)','random_1_30'=>'Random (1–30 Minutes)','hourly'=>'Hourly','daily'=>'Daily','weekly'=>'Weekly','manual'=>'Manual Only']" :searchable="true" /></div>
                <div><label>Lead Quantity</label><x-ui.themed-select wire:model.live="settings.quantity_mode" :options="['fixed'=>'Fixed Batch Quantity','all'=>'Send All Available Leads']" /></div>
                @if($settings['frequency'] === 'custom_minutes')
                    <div><label class="font-semibold">Delivery Interval (Minutes) *</label><x-text-input variant="compact" type="number" min="1" max="1440" wire:model.live="settings.interval_minutes" placeholder="e.g. 20" /></div>
                @endif
                @if($settings['quantity_mode'] === 'fixed')<div><label>Leads to Send *</label><x-text-input variant="compact" type="number" min="1" wire:model="settings.quantity" /></div>@endif
                <div class="space-y-2"><label class="flex items-center gap-2 h-8"><input type="checkbox" wire:model.live="settings.daily_enabled">Set a daily limit</label>@if($settings['daily_enabled'])<x-text-input variant="compact" type="number" min="1" wire:model="settings.daily_limit" placeholder="Maximum leads per day" />@endif</div>
                @if(in_array($settings['frequency'], ['daily','weekly']))<div><label>Scheduled Time</label><x-text-input variant="compact" type="time" wire:model="settings.daily_time" /></div>@endif
                @if($settings['frequency'] === 'weekly')<div><label>Weekly Day</label><x-ui.themed-select wire:model="settings.weekly_day" :options="[0=>'Sunday',1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday']" /></div>@endif
                @if($settings['frequency'] === 'custom_minutes')
                    <div class="col-span-full p-2.5 bg-blue-50/70 border border-blue-200 rounded-lg text-blue-900 text-xs space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span>Custom Delivery Frequency (Every {{ $settings['interval_minutes'] ?: 20 }} Minutes)</span>
                        </div>
                        <p class="text-[11px] text-blue-800 leading-relaxed">
                            Delivers leads automatically every {{ $settings['interval_minutes'] ?: 20 }} minutes according to the configured interval.
                            @if($plan && ($plan->settings['frequency'] ?? '') === 'custom_minutes' && $plan->next_run_at)
                                <span class="font-semibold block mt-1">Next scheduled delivery: {{ $plan->next_run_at->format('d M Y H:i T') }} ({{ $plan->next_run_at->isPast() ? 'due now' : 'in ' . $plan->next_run_at->diffForHumans(null, true) }} · interval: {{ $plan->settings['interval_minutes'] ?? 20 }}m).</span>
                            @endif
                        </p>
                    </div>
                @elseif($settings['frequency'] === 'random_1_30')
                    <div class="col-span-full p-2.5 bg-blue-50/70 border border-blue-200 rounded-lg text-blue-900 text-xs space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span>Random Delivery Frequency (1–30 Minutes)</span>
                        </div>
                        <p class="text-[11px] text-blue-800 leading-relaxed">
                            Automatically picks a random delivery interval between 1 and 30 minutes, generating a new random interval after every delivery cycle.
                            @if($plan && ($plan->settings['frequency'] ?? '') === 'random_1_30' && $plan->next_run_at)
                                <span class="font-semibold block mt-1">Next scheduled delivery: {{ $plan->next_run_at->format('d M Y H:i T') }} ({{ $plan->next_run_at->isPast() ? 'due now' : 'in ' . $plan->next_run_at->diffForHumans(null, true) }}@if(!empty($plan->settings['random_interval_minutes'])) · interval: {{ $plan->settings['random_interval_minutes'] }}m @endif).</span>
                            @endif
                        </p>
                    </div>
                @elseif($plan && $plan->next_run_at && !in_array($settings['frequency'], ['manual', 'immediate']))
                    <div class="col-span-full p-2 bg-canvas border border-border rounded-lg text-ink text-xs">
                        <span class="text-muted text-[11px]">Next scheduled delivery:</span>
                        <span class="font-semibold">{{ $plan->next_run_at->format('d M Y H:i T') }} ({{ $plan->next_run_at->isPast() ? 'due now' : 'in ' . $plan->next_run_at->diffForHumans(null, true) }})</span>
                    </div>
                @endif
            </div>
            <details class="rounded-lg border border-border p-3"><summary class="font-semibold text-ink cursor-pointer">Advanced scheduling (optional)</summary>
                <div class="grid sm:grid-cols-2 gap-3 mt-4">
                    <div><label>Start Date</label><x-text-input variant="compact" type="date" wire:model="settings.start_date" /></div>
                    <div><label>End Date</label><x-text-input variant="compact" type="date" wire:model="settings.end_date" /></div>
                    <div><label>Daily Start Time</label><x-text-input variant="compact" type="time" wire:model="settings.window_start" /></div>
                    <div><label>Daily End Time</label><x-text-input variant="compact" type="time" wire:model="settings.window_end" /></div>
                    <div><label>Timezone</label><x-ui.themed-select wire:model="settings.timezone" :options="array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers())" :searchable="true" /></div>
                    @if($settings['quantity_mode'] === 'fixed')<div><label>Maximum per Cycle (optional)</label><x-text-input variant="compact" type="number" min="1" wire:model="settings.max_cycle" /></div>@endif
                </div>
                <fieldset class="mt-4"><legend class="font-semibold mb-2">Working Days</legend><div class="flex flex-wrap gap-3">@foreach([0=>'Sun',1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat'] as $day => $label)<label class="flex items-center gap-1"><input type="checkbox" wire:model="settings.working_days" value="{{ $day }}">{{ $label }}</label>@endforeach</div></fieldset>
            </details>
            <p class="text-muted">{{ $settings['frequency'] === 'manual' ? 'Push sends one cycle now. Use Send Cycle Now to send ready leads.' : 'Delivery runs automatically at the selected frequency.' }} {{ $settings['daily_enabled'] ? 'The daily limit applies.' : 'No daily limit.' }} Existing queues and duplicate protection are preserved.</p>
            @if($errors->any())<div role="alert" class="rounded-lg border border-red-200 p-3 text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <div class="flex flex-wrap gap-2 border-t border-border pt-4">
                <button type="button" wire:click="saveSettings" @if($settings['quantity_mode'] === 'all' || !$settings['daily_enabled']) wire:confirm="Save these settings? {{ $settings['quantity_mode'] === 'all' ? 'Send All is selected.' : '' }} {{ !$settings['daily_enabled'] ? 'The daily limit is off.' : '' }}" @endif class="px-4 h-9 rounded-lg border border-border font-semibold">Save Settings</button>
                <button type="button" wire:click="pushLeads" wire:confirm="Push the selected leads to this client? Confirm that you have permission to share them. {{ $settings['quantity_mode'] === 'all' ? 'Send All is selected.' : '' }} {{ !$settings['daily_enabled'] ? 'The daily limit is off.' : '' }}" class="px-4 h-9 rounded-lg bg-ink text-white font-semibold">Push Leads</button>
            </div>
        </div>
    </x-ui.offcanvas>
    <x-ui.offcanvas wire:model="showAttempts" name="delivery-attempt-history" title="Transfer History" subtitle="Actual attempt records are retained across retries and batch changes." maxWidth="xl">
        @if($inspectedItem)
            <div class="space-y-4 text-xs text-ink"><p class="font-bold">{{ $inspectedItem->lead?->lead_code }} · {{ $inspectedItem->lead?->name }}</p><p>{{ $inspectedItem->client?->name }} / {{ $inspectedItem->project?->name }} / {{ $inspectedItem->campaign?->name }}</p><p>Queued {{ $inspectedItem->created_at->format('d M Y H:i T') }} · {{ ucfirst($inspectedItem->status) }}</p>
                @forelse($inspectedItem->attemptHistory as $attempt)<div class="border border-border rounded-lg p-3 space-y-1"><strong>Batch #{{ $attempt->run_id }} · {{ ucfirst($attempt->status) }}</strong><p>{{ $attempt->created_at->format('d M Y H:i T') }}</p>@if($attempt->failure_reason)<p class="text-red-700">{{ $attempt->failure_reason }}</p>@endif</div>@empty<p class="text-muted">No delivery attempts yet. This transfer is waiting in the queue.</p>@endforelse
                @if($inspectedItem->accepted_at)<p>Client acceptance recorded {{ $inspectedItem->accepted_at->format('d M Y H:i T') }}.</p>@endif
            </div>
        @endif
    </x-ui.offcanvas>
</div>
