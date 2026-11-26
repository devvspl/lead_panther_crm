<div class="bg-surface border border-border rounded-2xl p-5 sm:p-6 space-y-6 shadow-2xs">
    <!-- Header & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border">
        <div>
            <div class="flex items-center gap-2">
                <span class="p-2 rounded-xl bg-primary/10 text-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                </span>
                <h3 class="text-base font-bold text-ink">Lead Filtering Rules</h3>
                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-primary/10 text-primary border border-primary/20">
                    {{ $rules->where('is_active', true)->count() }} Active
                </span>
            </div>
            <p class="text-xs text-muted mt-1">Configure automated rules to include or exclude incoming leads from Facebook campaigns &amp; forms based on custom form answers and lead criteria.</p>
        </div>

        <div class="flex items-center gap-2 shrink-0 flex-wrap">
            <button type="button" wire:click="openTestModal" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200 text-xs font-semibold transition cursor-pointer shadow-2xs">
                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Test Rules Preview</span>
            </button>

            <button type="button" wire:click="openCreateRuleModal" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary text-white hover:bg-primary/90 text-xs font-bold transition cursor-pointer shadow-xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Add Filtering Rule</span>
            </button>
        </div>
    </div>

    <!-- Navigation Sub-tabs: Configured Rules vs Filter Audit Logs -->
    <div class="flex items-center gap-4 text-xs font-semibold border-b border-border">
        <button type="button" wire:click="$set('subTab', 'rules')"
            @class([
                'pb-2.5 transition border-b-2 cursor-pointer flex items-center gap-2',
                'border-primary text-primary font-bold' => $subTab === 'rules',
                'border-transparent text-muted hover:text-ink' => $subTab !== 'rules',
            ])>
            <span>Configured Filter Rules</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-canvas border border-border text-ink">{{ $rules->count() }}</span>
        </button>

        <button type="button" wire:click="$set('subTab', 'logs')"
            @class([
                'pb-2.5 transition border-b-2 cursor-pointer flex items-center gap-2',
                'border-primary text-primary font-bold' => $subTab === 'logs',
                'border-transparent text-muted hover:text-ink' => $subTab !== 'logs',
            ])>
            <span>Filter Execution Logs</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-canvas border border-border text-ink">{{ $recentLogs->count() }}</span>
        </button>
    </div>

    @if($subTab === 'rules')
        <!-- Search & Filter Controls -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="relative w-full sm:w-72">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input type="text" wire:model.live.debounce.250ms="search" placeholder="Search rules..."
                    class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-border bg-canvas text-ink focus:ring-1 focus:ring-primary focus:outline-none placeholder:text-muted" />
            </div>

            @if(count($campaigns) > 0)
                <div class="w-full sm:w-64">
                    <select wire:model.live="filterCampaignId" class="w-full py-1.5 px-3 text-xs rounded-lg border border-border bg-canvas text-ink focus:ring-1 focus:ring-primary focus:outline-none">
                        <option value="">All Campaigns Scope</option>
                        @foreach($campaigns as $cId => $cName)
                            <option value="{{ $cId }}">{{ $cName }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <!-- Rules List / Cards -->
        @if($rules->isEmpty())
            <div class="p-8 text-center rounded-2xl border border-dashed border-border bg-canvas/40 space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary mx-auto flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                </div>
                <div class="space-y-1">
                    <h4 class="text-sm font-bold text-ink">No Lead Filtering Rules Configured</h4>
                    <p class="text-xs text-muted max-w-md mx-auto">When no rules are active, all leads fetched from connected Facebook campaigns and forms will be ingested directly into the CRM. Click Add Filtering Rule to set up custom criteria.</p>
                </div>
                <button type="button" wire:click="openCreateRuleModal" class="px-4 py-2 rounded-xl bg-primary text-white text-xs font-bold hover:bg-primary/90 transition shadow-xs cursor-pointer inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Create Your First Rule</span>
                </button>
            </div>
        @else
            <div class="space-y-3">
                @foreach($rules as $rule)
                    <div class="p-4 sm:p-5 rounded-2xl border transition {{ $rule->is_active ? 'border-border bg-surface shadow-2xs hover:border-border-hover' : 'border-border/60 bg-canvas/40 opacity-75' }}">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                            <div class="space-y-2 flex-1 min-w-0">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <h4 class="text-sm font-bold text-ink">{{ $rule->name }}</h4>
                                    
                                    <!-- Action Badge -->
                                    @if($rule->action === 'exclude')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-red-50 text-red-700 border border-red-200">
                                            <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                            Exclude Lead
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            Include Lead
                                        </span>
                                    @endif

                                    <!-- Campaign Scope Badge -->
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-canvas border border-border text-muted">
                                        {{ $rule->campaign ? $rule->campaign->name : 'All Campaigns' }}
                                    </span>

                                    <!-- Active Pill -->
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $rule->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $rule->is_active ? 'Active' : 'Disabled' }}
                                    </span>
                                </div>

                                @if($rule->description)
                                    <p class="text-xs text-muted">{{ $rule->description }}</p>
                                @endif

                                <!-- Readable Summary of Configured Conditions -->
                                <div class="p-3 rounded-xl bg-canvas border border-border/80 text-xs font-mono text-ink space-y-1">
                                    <div class="flex items-center gap-2 text-[11px] font-sans font-bold text-muted uppercase tracking-wider">
                                        <span>Rule Logic Summary:</span>
                                    </div>
                                    <div class="text-xs text-ink break-words">
                                        {{ $rule->humanSummary() }}
                                    </div>
                                </div>
                            </div>

                            <!-- Rule Action Controls -->
                            <div class="flex items-center gap-1.5 shrink-0 pt-2 sm:pt-0">
                                <button type="button" wire:click="toggleRuleActive({{ $rule->id }})"
                                    title="{{ $rule->is_active ? 'Disable this rule' : 'Enable this rule' }}"
                                    class="p-2 rounded-lg border border-border text-xs font-semibold text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                                    <svg class="w-4 h-4 {{ $rule->is_active ? 'text-emerald-600' : 'text-muted' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v9m6.364-6.364a9 9 0 11-12.728 0" /></svg>
                                </button>

                                <button type="button" wire:click="openEditRuleModal({{ $rule->id }})"
                                    title="Edit rule"
                                    class="p-2 rounded-lg border border-border text-xs font-semibold text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </button>

                                <button type="button" wire:click="deleteRule({{ $rule->id }})"
                                    wire:confirm="Are you sure you want to delete this rule?"
                                    title="Delete rule"
                                    class="p-2 rounded-lg border border-border text-xs font-semibold text-muted hover:text-red-600 hover:bg-red-50 transition cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        <!-- Sub-tab 2: Filter Execution Logs -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs text-muted">Showing up to 20 recent lead filtering evaluation events.</span>
            </div>

            @if($recentLogs->isEmpty())
                <div class="p-6 text-center rounded-xl bg-canvas border border-border text-xs text-muted">
                    No leads have triggered custom filtering rules yet.
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-canvas border-b border-border text-muted font-bold uppercase text-[10px]">
                            <tr>
                                <th class="p-3">Time</th>
                                <th class="p-3">Lead</th>
                                <th class="p-3">Campaign</th>
                                <th class="p-3">Action</th>
                                <th class="p-3">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-surface">
                            @foreach($recentLogs as $log)
                                <tr>
                                    <td class="p-3 font-mono text-[11px] text-muted whitespace-nowrap">{{ $log->created_at?->format('M d, H:i') }}</td>
                                    <td class="p-3 font-semibold text-ink">{{ $log->lead_identifier ?: 'Inbound Lead' }}</td>
                                    <td class="p-3 text-muted">{{ $log->campaign?->name ?? '—' }}</td>
                                    <td class="p-3">
                                        @if($log->action === 'skipped')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700 border border-red-200">SKIPPED</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">INCLUDED</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-ink max-w-xs truncate" title="{{ $log->reason }}">{{ $log->reason }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    <!-- 1. Condition Builder Modal -->
    <x-ui.modal name="lead-filter-rule-editor" maxWidth="2xl" :centered="true">
        <form wire:submit="saveRule" class="p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between pb-3.5 border-b border-border">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-primary/10 text-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
                    </div>
                    <div>
                        <h4 class="text-base font-bold text-ink">{{ $editingRuleId ? 'Edit Lead Filtering Rule' : 'Create Lead Filtering Rule' }}</h4>
                        <p class="text-xs text-muted">Dynamically include or skip leads fetched from Meta forms.</p>
                    </div>
                </div>
                <button type="button" x-on:click="$dispatch('close-modal', 'lead-filter-rule-editor')" class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- General Rule Info -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div>
                    <label class="block font-semibold text-ink mb-1">Rule Name <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="ruleName" placeholder="e.g. Studio Buy Only Filter"
                        class="w-full rounded-lg border border-border bg-canvas p-2.5 text-ink focus:ring-1 focus:ring-primary focus:outline-none" />
                    @error('ruleName') <p class="text-red-500 text-[11px] mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block font-semibold text-ink mb-1">Campaign Scope</label>
                    <select wire:model="ruleCampaignId" class="w-full rounded-lg border border-border bg-canvas p-2.5 text-ink focus:ring-1 focus:ring-primary focus:outline-none">
                        <option value="">All Campaigns (Organization Wide)</option>
                        @foreach($campaigns as $cId => $cName)
                            <option value="{{ $cId }}">{{ $cName }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block font-semibold text-ink mb-1">Description (Optional)</label>
                    <input type="text" wire:model="ruleDescription" placeholder="Explain the purpose of this rule..."
                        class="w-full rounded-lg border border-border bg-canvas p-2 text-ink focus:ring-1 focus:ring-primary focus:outline-none" />
                </div>
            </div>

            <!-- Action & Logic Selector -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-border text-xs">
                <div>
                    <label class="block font-semibold text-ink mb-1.5">Action on Condition Match</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" wire:click="$set('ruleAction', 'include')"
                            @class([
                                'px-3 py-2 rounded-lg border text-center transition cursor-pointer font-bold',
                                'bg-emerald-50 text-emerald-700 border-emerald-300' => $ruleAction === 'include',
                                'bg-canvas text-muted border-border hover:text-ink' => $ruleAction !== 'include',
                            ])>
                            ✓ Include Lead
                        </button>
                        <button type="button" wire:click="$set('ruleAction', 'exclude')"
                            @class([
                                'px-3 py-2 rounded-lg border text-center transition cursor-pointer font-bold',
                                'bg-red-50 text-red-700 border-red-300' => $ruleAction === 'exclude',
                                'bg-canvas text-muted border-border hover:text-ink' => $ruleAction !== 'exclude',
                            ])>
                            ✕ Exclude Lead
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-ink mb-1.5">Logic Across Groups</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" wire:click="$set('ruleGroupLogic', 'OR')"
                            @class([
                                'px-3 py-2 rounded-lg border text-center transition cursor-pointer font-bold',
                                'bg-primary/10 text-primary border-primary' => $ruleGroupLogic === 'OR',
                                'bg-canvas text-muted border-border hover:text-ink' => $ruleGroupLogic !== 'OR',
                            ])>
                            ANY Group (OR)
                        </button>
                        <button type="button" wire:click="$set('ruleGroupLogic', 'AND')"
                            @class([
                                'px-3 py-2 rounded-lg border text-center transition cursor-pointer font-bold',
                                'bg-primary/10 text-primary border-primary' => $ruleGroupLogic === 'AND',
                                'bg-canvas text-muted border-border hover:text-ink' => $ruleGroupLogic !== 'AND',
                            ])>
                            ALL Groups (AND)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Dynamic Condition Groups -->
            <div class="space-y-3 pt-2 border-t border-border">
                <div class="flex items-center justify-between">
                    <h5 class="text-xs font-bold text-ink uppercase tracking-wider">Condition Groups</h5>
                    <button type="button" wire:click="addConditionGroup" class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Add Condition Group</span>
                    </button>
                </div>

                @foreach($conditionGroups as $gIndex => $group)
                    <div class="p-3.5 sm:p-4 rounded-xl border border-border bg-canvas/60 space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-border/80 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-ink">Group #{{ $gIndex + 1 }}</span>
                                <span class="text-muted">· Match</span>
                                <select wire:model="conditionGroups.{{ $gIndex }}.logic" class="rounded border border-border bg-surface px-2 py-0.5 text-xs font-bold text-primary">
                                    <option value="AND">ALL (AND)</option>
                                    <option value="OR">ANY (OR)</option>
                                </select>
                            </div>

                            @if(count($conditionGroups) > 1)
                                <button type="button" wire:click="removeConditionGroup({{ $gIndex }})" class="text-muted hover:text-red-600 text-xs font-semibold cursor-pointer">
                                    Remove Group
                                </button>
                            @endif
                        </div>

                        <!-- Conditions inside this group -->
                        <div class="space-y-2">
                            @foreach($group['conditions'] as $cIndex => $cond)
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <!-- Field Name -->
                                    <div class="col-span-12 sm:col-span-4">
                                        <input type="text" list="suggested-filter-fields"
                                            wire:model="conditionGroups.{{ $gIndex }}.conditions.{{ $cIndex }}.field"
                                            placeholder="Lead Field (e.g. want_to_buy_studio)"
                                            class="w-full rounded-lg border border-border bg-surface p-2 text-xs text-ink focus:ring-1 focus:ring-primary focus:outline-none font-mono" />
                                    </div>

                                    <!-- Operator -->
                                    <div class="col-span-12 sm:col-span-4">
                                        <select wire:model.live="conditionGroups.{{ $gIndex }}.conditions.{{ $cIndex }}.operator"
                                            class="w-full rounded-lg border border-border bg-surface p-2 text-xs text-ink focus:ring-1 focus:ring-primary focus:outline-none font-medium">
                                            @foreach($supportedOperators as $opKey => $opLabel)
                                                <option value="{{ $opKey }}">{{ $opLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Value (Hidden/disabled if is_empty or is_not_empty) -->
                                    <div class="col-span-10 sm:col-span-3">
                                        @if(in_array($cond['operator'] ?? '', ['is_empty', 'is_not_empty']))
                                            <input type="text" disabled placeholder="N/A" class="w-full rounded-lg border border-border bg-canvas/40 p-2 text-xs text-muted cursor-not-allowed italic" />
                                        @else
                                            <input type="text"
                                                wire:model="conditionGroups.{{ $gIndex }}.conditions.{{ $cIndex }}.value"
                                                placeholder="Value (e.g. buy)"
                                                class="w-full rounded-lg border border-border bg-surface p-2 text-xs text-ink focus:ring-1 focus:ring-primary focus:outline-none" />
                                        @endif
                                    </div>

                                    <!-- Remove condition button -->
                                    <div class="col-span-2 sm:col-span-1 text-right">
                                        @if(count($group['conditions']) > 1)
                                            <button type="button" wire:click="removeCondition({{ $gIndex }}, {{ $cIndex }})" class="p-2 text-muted hover:text-red-600 rounded-lg transition cursor-pointer" title="Remove condition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Add Condition within this group -->
                        <div class="pt-1">
                            <button type="button" wire:click="addCondition({{ $gIndex }})" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span>Add Condition</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <datalist id="suggested-filter-fields">
                <option value="want_to_buy_studio"></option>
                <option value="free_site_visit"></option>
                <option value="city"></option>
                <option value="location"></option>
                <option value="budget"></option>
                <option value="property_type"></option>
                <option value="requirement"></option>
                <option value="full_name"></option>
                <option value="phone_number"></option>
                <option value="email"></option>
                <option value="platform"></option>
                <option value="campaign_name"></option>
                <option value="form_name"></option>
            </datalist>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                <button type="button" x-on:click="$dispatch('close-modal', 'lead-filter-rule-editor')" class="px-3.5 py-2 rounded-lg border border-border text-xs font-semibold text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary/90 transition shadow-xs cursor-pointer inline-flex items-center gap-1.5">
                    <span wire:loading.remove wire:target="saveRule">Save Rule</span>
                    <span wire:loading wire:target="saveRule">Saving...</span>
                </button>
            </div>
        </form>
    </x-ui.modal>

    <!-- 2. Test Rules Preview Modal -->
    <x-ui.modal name="lead-filter-test-preview" maxWidth="lg" :centered="true">
        <div class="p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between pb-3.5 border-b border-border">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-purple-50 text-purple-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-base font-bold text-ink">Test Rules Preview</h4>
                        <p class="text-xs text-muted">Preview whether a sample lead will be accepted or skipped by your active rules.</p>
                    </div>
                </div>
                <button type="button" x-on:click="$dispatch('close-modal', 'lead-filter-test-preview')" class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Sample Lead Attributes Editor -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-ink uppercase tracking-wider">Sample Lead Form Answers</span>
                    <button type="button" wire:click="addTestField" class="text-xs font-bold text-primary hover:underline cursor-pointer">
                        + Add Attribute
                    </button>
                </div>

                <div class="space-y-2 max-h-52 overflow-y-auto pr-1">
                    @foreach($testFields as $index => $item)
                        <div class="grid grid-cols-12 gap-2 items-center">
                            <div class="col-span-5">
                                <input type="text" wire:model="testFields.{{ $index }}.field" placeholder="Field"
                                    class="w-full rounded-lg border border-border bg-canvas p-2 text-xs text-ink font-mono focus:ring-1 focus:ring-primary focus:outline-none" />
                            </div>
                            <div class="col-span-6">
                                <input type="text" wire:model="testFields.{{ $index }}.value" placeholder="Value"
                                    class="w-full rounded-lg border border-border bg-canvas p-2 text-xs text-ink focus:ring-1 focus:ring-primary focus:outline-none" />
                            </div>
                            <div class="col-span-1 text-right">
                                <button type="button" wire:click="removeTestField({{ $index }})" class="text-muted hover:text-red-600 text-xs cursor-pointer p-1">
                                    ✕
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div>
                    <button type="button" wire:click="runTestPreview" class="w-full py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-xl shadow-xs transition cursor-pointer inline-flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                        <span>Evaluate Against Active Rules</span>
                    </button>
                </div>
            </div>

            <!-- Evaluation Verdict Banner -->
            @if($testResult)
                <div class="p-4 rounded-xl border {{ $testResult['passed'] ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200' }} space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider {{ $testResult['passed'] ? 'text-emerald-800' : 'text-red-800' }}">Preview Verdict:</span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono {{ $testResult['passed'] ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white' }}">
                            {{ $testResult['verdict'] }}
                        </span>
                    </div>

                    <p class="text-xs font-medium {{ $testResult['passed'] ? 'text-emerald-900' : 'text-red-900' }}">
                        {{ $testResult['reason'] }}
                    </p>

                    @if(!empty($testResult['matched_conditions']))
                        <div class="pt-2 border-t {{ $testResult['passed'] ? 'border-emerald-200' : 'border-red-200' }} text-[11px] font-mono space-y-0.5">
                            <span class="font-bold">Conditions Triggered:</span>
                            @foreach($testResult['matched_conditions'] as $mCond)
                                <div>• {{ $mCond }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex items-center justify-end pt-2 border-t border-border">
                <button type="button" x-on:click="$dispatch('close-modal', 'lead-filter-test-preview')" class="px-4 py-2 rounded-lg border border-border text-xs font-semibold text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                    Close Preview
                </button>
            </div>
        </div>
    </x-ui.modal>
</div>
