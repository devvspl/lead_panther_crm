<div class="bg-surface rounded-2xl border border-border p-5 sm:p-6 space-y-5 shadow-2xs">
    <!-- Header of the Lead Filtering Rules section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-1">
        <div class="flex items-start gap-3">
            <div class="p-2.5 rounded-xl bg-canvas border border-border text-ink shrink-0">
                <svg class="w-5 h-5 text-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-bold text-ink">Lead Filtering Rules</h3>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-canvas border border-border/80 text-ink">
                        {{ $rules->where('is_active', true)->count() }} Active
                    </span>
                </div>
                <p class="text-xs text-muted mt-0.5">Automated lead qualification rules applied to Facebook campaigns and forms before leads enter CRM distribution.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
            <button type="button" wire:click="openTestModal"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-purple-200 text-purple-700 bg-purple-50/50 hover:bg-purple-100/50 text-xs font-semibold transition cursor-pointer shadow-2xs">
                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Test Rules Preview</span>
            </button>
            <button type="button" wire:click="openCreateRuleModal"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-ink hover:bg-black text-white text-xs font-bold transition shadow-xs cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span>Add Filtering Rule</span>
            </button>
        </div>
    </div>

    <!-- Navigation Sub-tabs: Configured Rules vs Filter Execution Logs -->
    <div class="flex items-center gap-5 text-xs font-semibold border-b border-border">
        <button type="button" wire:click="$set('subTab', 'rules')"
            @class([
                'pb-2.5 transition border-b-2 cursor-pointer flex items-center gap-2',
                'border-ink text-ink font-bold' => $subTab === 'rules',
                'border-transparent text-muted hover:text-ink' => $subTab !== 'rules',
            ])>
            <span>Configured Filter Rules</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-canvas border border-border text-ink">{{ $rules->count() }}</span>
        </button>

        <button type="button" wire:click="$set('subTab', 'logs')"
            @class([
                'pb-2.5 transition border-b-2 cursor-pointer flex items-center gap-2',
                'border-ink text-ink font-bold' => $subTab === 'logs',
                'border-transparent text-muted hover:text-ink' => $subTab !== 'logs',
            ])>
            <span>Filter Execution Logs</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-canvas border border-border text-ink">{{ $recentLogs->count() }}</span>
        </button>
    </div>

    @if($subTab === 'rules')
        <!-- Configured Filter Rules Table -->
        @if($rules->isEmpty())
            <div class="p-8 text-center rounded-2xl border border-dashed border-border bg-canvas/40 space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-canvas border border-border text-ink mx-auto flex items-center justify-center">
                    <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                </div>
                <div class="space-y-1">
                    <h4 class="text-sm font-bold text-ink">No Lead Filtering Rules Configured</h4>
                    <p class="text-xs text-muted max-w-md mx-auto">When no rules are active, all leads fetched from connected Facebook campaigns and forms will be ingested directly into the CRM. Click Add Filtering Rule to set up custom criteria.</p>
                </div>
                <button type="button" wire:click="openCreateRuleModal" class="px-4 py-2 rounded-xl bg-ink text-white text-xs font-bold hover:bg-black transition shadow-xs cursor-pointer inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Create Your First Rule</span>
                </button>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-border bg-surface">
                <table class="w-full text-left text-xs divide-y divide-border">
                    <thead class="bg-canvas/50 border-b border-border text-muted font-bold uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="py-3 px-3.5">Rule Name</th>
                            <th class="py-3 px-3.5">Campaign Scope</th>
                            <th class="py-3 px-3.5">Action</th>
                            <th class="py-3 px-3.5">Conditions</th>
                            <th class="py-3 px-3.5">Status</th>
                            <th class="py-3 px-3.5">Created By</th>
                            <th class="py-3 px-3.5">Created On</th>
                            <th class="py-3 px-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-surface">
                        @foreach($rules as $rule)
                            <tr class="hover:bg-canvas/40 transition">
                                <!-- 1. Rule Name -->
                                <td class="py-3 px-3.5 font-bold text-ink whitespace-nowrap">
                                    {{ $rule->name }}
                                </td>

                                <!-- 2. Campaign Scope -->
                                <td class="py-3 px-3.5 whitespace-nowrap text-ink">
                                    {{ $rule->scopeSummary() }}
                                </td>

                                <!-- 3. Action Badge -->
                                <td class="py-3 px-3.5 whitespace-nowrap">
                                    @if($rule->action === 'include')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Include
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                                            Exclude
                                        </span>
                                    @endif
                                </td>

                                <!-- 4. Conditions Pills -->
                                <td class="py-3 px-3.5">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        @php
                                            $groups = $rule->conditions['groups'] ?? [];
                                            $condItems = [];
                                            $gLogic = $rule->group_logic ?: ($rule->conditions['group_logic'] ?? 'AND');
                                            foreach ($groups as $g) {
                                                $conds = $g['conditions'] ?? [];
                                                foreach ($conds as $c) {
                                                    $op = match($c['operator'] ?? 'equals') {
                                                        'equals' => '=',
                                                        'not_equals' => '!=',
                                                        'contains' => 'contains',
                                                        'not_contains' => 'not contains',
                                                        'is_empty' => 'is empty',
                                                        'is_not_empty' => 'is not empty',
                                                        default => $c['operator'] ?? '=',
                                                    };
                                                    $val = isset($c['value']) && $c['value'] !== '' ? $c['value'] : '';
                                                    $condItems[] = trim(($c['field'] ?? 'field') . ' ' . $op . ' ' . $val);
                                                }
                                            }
                                        @endphp
                                        @foreach($condItems as $cIdx => $cText)
                                            @if($cIdx > 0)
                                                <span class="text-[10px] font-bold text-muted uppercase">{{ $gLogic }}</span>
                                            @endif
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-canvas border border-border text-ink font-mono text-[11px] font-medium">
                                                {{ $cText }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>

                                <!-- 5. Status -->
                                <td class="py-3 px-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $rule->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' }}">
                                        {{ $rule->is_active ? 'Active' : 'Disabled' }}
                                    </span>
                                </td>

                                <!-- 6. Created By -->
                                <td class="py-3 px-3.5 whitespace-nowrap text-muted text-xs">
                                    {{ $rule->creator?->name ?? 'Super Admin' }}
                                </td>

                                <!-- 7. Created On -->
                                <td class="py-3 px-3.5 whitespace-nowrap text-muted text-xs">
                                    {{ $rule->created_at?->format('M d, Y') ?? 'Nov 26, 2026' }}
                                </td>

                                <!-- 8. Actions (Edit pencil, toggle switch, delete trash) -->
                                <td class="py-3 px-3.5 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <!-- Edit Rule -->
                                        <button type="button" wire:click="openEditRuleModal({{ $rule->id }})"
                                            class="p-1 text-muted hover:text-ink transition cursor-pointer" title="Edit rule">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                            </svg>
                                        </button>

                                        <!-- Toggle Switch -->
                                        <button type="button" wire:click="toggleRuleActive({{ $rule->id }})"
                                            class="relative inline-flex h-4 w-7 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $rule->is_active ? 'bg-emerald-600' : 'bg-slate-300' }}"
                                            role="switch" aria-checked="{{ $rule->is_active ? 'true' : 'false' }}"
                                            title="{{ $rule->is_active ? 'Disable rule' : 'Enable rule' }}">
                                            <span class="pointer-events-none inline-block h-3 w-3 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $rule->is_active ? 'translate-x-3' : 'translate-x-0' }}"></span>
                                        </button>

                                        <!-- Delete Rule -->
                                        <button type="button" wire:click="deleteRule({{ $rule->id }})" wire:confirm="Are you sure you want to delete this rule?"
                                            class="p-1 text-red-500 hover:text-red-700 transition cursor-pointer" title="Delete rule">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @else
        <!-- Sub-tab 2: Filter Execution Logs -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs text-muted">Showing recent lead filtering evaluation events.</span>
            </div>

            @if($recentLogs->isEmpty())
                <div class="p-6 text-center rounded-xl bg-canvas border border-border text-xs text-muted">
                    No leads have triggered custom filtering rules yet.
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-border bg-surface">
                    <table class="w-full text-left text-xs divide-y divide-border">
                        <thead class="bg-canvas/50 border-b border-border text-muted font-bold uppercase text-[10px] tracking-wider">
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

    <!-- C. Create / Edit Lead Filtering Rule Modal (matches reference screenshot) -->
    <x-ui.modal name="lead-filter-rule-editor" maxWidth="xl" :centered="true">
        <form wire:submit="saveRule" class="p-5 sm:p-6 space-y-4">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3.5 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-canvas border border-border text-ink shrink-0">
                        <svg class="w-5 h-5 text-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-base font-bold text-ink">{{ $editingRuleId ? 'Edit Lead Filtering Rule' : 'Add Lead Filtering Rule' }}</h4>
                        <p class="text-xs text-muted">Create a custom rule to include or exclude leads based on lead form answers.</p>
                    </div>
                </div>
                <button type="button" x-on:click="$dispatch('close-modal', 'lead-filter-rule-editor')"
                    class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Row 1: Rule Name & Campaign Scope (2 columns) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="ruleName" class="text-xs font-semibold text-ink mb-1">
                        Rule Name <span class="text-red-500">*</span>
                    </x-input-label>
                    <x-text-input id="ruleName" wire:model="ruleName" variant="compact" placeholder="Studio Buy Only" required />
                    <x-input-error :messages="$errors->get('ruleName')" class="mt-1 text-xs" />
                </div>

                <div>
                    <x-input-label for="ruleScope" class="text-xs font-semibold text-ink mb-1">
                        Campaign Scope <span class="text-red-500">*</span>
                    </x-input-label>
                    <x-ui.themed-select id="ruleScope" wire:model.live="ruleScope" :options="$this->scopeOptions" placeholder="Select Campaign Scope" :searchable="true" class="w-full" />
                    <x-input-error :messages="$errors->get('ruleScope')" class="mt-1 text-xs" />
                </div>
            </div>

            <!-- Row 2: Description (Optional) -->
            <div>
                <x-input-label for="ruleDescription" class="text-xs font-semibold text-ink mb-1">
                    Description (Optional)
                </x-input-label>
                <textarea id="ruleDescription" wire:model="ruleDescription" rows="2"
                    class="w-full rounded-lg border border-border bg-canvas px-3 py-2 text-ink text-xs focus:ring-1 focus:ring-ink focus:outline-none placeholder:text-muted"
                    placeholder="Include only buy leads for studio campaigns."></textarea>
            </div>

            <!-- Row 3: Action & Condition Logic (2 columns) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Action: Include / Exclude -->
                <div>
                    <label class="block text-xs font-semibold text-ink mb-1.5">Action <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" wire:click="$set('ruleAction', 'include')"
                            @class([
                                'px-3 py-2 text-xs font-semibold rounded-xl border text-center transition cursor-pointer flex items-center justify-center gap-1.5',
                                'bg-emerald-50 border-emerald-300 text-emerald-700 font-bold' => $ruleAction === 'include',
                                'bg-surface border-border text-muted hover:text-ink' => $ruleAction !== 'include',
                            ])>
                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Include Lead</span>
                        </button>
                        <button type="button" wire:click="$set('ruleAction', 'exclude')"
                            @class([
                                'px-3 py-2 text-xs font-semibold rounded-xl border text-center transition cursor-pointer flex items-center justify-center gap-1.5',
                                'bg-red-50 border-red-300 text-red-700 font-bold' => $ruleAction === 'exclude',
                                'bg-surface border-border text-muted hover:text-ink' => $ruleAction !== 'exclude',
                            ])>
                            <svg class="w-3.5 h-3.5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span>Exclude Lead</span>
                        </button>
                    </div>
                </div>

                <!-- Condition Logic: All (AND) / Any (OR) -->
                <div>
                    <label class="block text-xs font-semibold text-ink mb-1.5">Condition Logic <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" wire:click="$set('ruleConditionLogic', 'AND')"
                            @class([
                                'px-3 py-2 text-xs font-semibold rounded-xl border text-center transition cursor-pointer',
                                'bg-surface border-border text-ink font-bold shadow-2xs' => $ruleConditionLogic === 'AND',
                                'bg-canvas border-border/70 text-muted hover:text-ink' => $ruleConditionLogic !== 'AND',
                            ])>
                            All (AND)
                        </button>
                        <button type="button" wire:click="$set('ruleConditionLogic', 'OR')"
                            @class([
                                'px-3 py-2 text-xs font-semibold rounded-xl border text-center transition cursor-pointer',
                                'bg-surface border-border text-ink font-bold shadow-2xs' => $ruleConditionLogic === 'OR',
                                'bg-canvas border-border/70 text-muted hover:text-ink' => $ruleConditionLogic !== 'OR',
                            ])>
                            Any (OR)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Row 4: Conditions Section -->
            <div class="space-y-3 pt-2">
                <h5 class="text-xs font-bold text-ink">Conditions</h5>

                <div class="space-y-2.5">
                    @foreach($conditions as $cIndex => $cond)
                        <div class="flex items-center gap-2 p-2.5 rounded-xl border border-border bg-canvas/40" wire:key="cond-row-{{ $cIndex }}">
                            <!-- Drag handle 6-dots -->
                            <div class="text-muted/60 shrink-0 cursor-grab px-0.5" title="Reorder">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M7 4a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm9-12a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                                </svg>
                            </div>

                            <!-- Lead Field dropdown -->
                            <div class="flex-1 min-w-0">
                                <label class="block text-[10px] font-semibold text-muted mb-0.5">Lead Field <span class="text-red-500">*</span></label>
                                <x-ui.themed-select wire:model.live="conditions.{{ $cIndex }}.field"
                                    :options="$this->leadFieldOptions" placeholder="Lead Field" :searchable="true" class="w-full" />
                            </div>

                            <!-- Operator dropdown -->
                            <div class="w-36 shrink-0">
                                <label class="block text-[10px] font-semibold text-muted mb-0.5">Operator <span class="text-red-500">*</span></label>
                                <x-ui.themed-select wire:model.live="conditions.{{ $cIndex }}.operator"
                                    :options="$this->operatorOptions" placeholder="Operator" class="w-full" />
                            </div>

                            <!-- Value input -->
                            <div class="w-40 shrink-0">
                                <label class="block text-[10px] font-semibold text-muted mb-0.5">Value <span class="text-red-500">*</span></label>
                                @if(in_array($cond['operator'] ?? '', ['is_empty', 'is_not_empty']))
                                    <x-text-input variant="compact" disabled placeholder="N/A" class="bg-canvas/50 cursor-not-allowed italic" />
                                @else
                                    <x-text-input wire:model="conditions.{{ $cIndex }}.value" variant="compact" placeholder="buy" />
                                @endif
                            </div>

                            <!-- Remove Condition action (red trash) -->
                            <div class="shrink-0 pt-4">
                                <button type="button" wire:click="removeConditionRow({{ $cIndex }})"
                                    class="p-1.5 text-red-500 hover:text-red-700 transition cursor-pointer" title="Remove condition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Add Condition action button -->
                <div>
                    <button type="button" wire:click="addConditionRow"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-purple-200 text-purple-700 bg-purple-50/50 hover:bg-purple-100/60 text-xs font-semibold transition cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        <span>Add Condition</span>
                    </button>
                </div>
            </div>

            <!-- Modal Footer (Cancel & Save Rule buttons) -->
            <div class="flex items-center justify-end gap-2 pt-4 border-t border-border">
                <button type="button" x-on:click="$dispatch('close-modal', 'lead-filter-rule-editor')"
                    class="px-4 py-2 rounded-xl border border-border text-xs font-semibold text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" wire:loading.attr="disabled"
                    class="px-4 py-2 bg-ink hover:bg-black text-white text-xs font-bold rounded-xl transition shadow-xs cursor-pointer inline-flex items-center gap-1.5">
                    <span wire:loading.remove wire:target="saveRule">Save Rule</span>
                    <span wire:loading wire:target="saveRule">Saving...</span>
                </button>
            </div>
        </form>
    </x-ui.modal>

    <!-- Test Rules Preview Modal -->
    <x-ui.modal name="lead-filter-test-preview" maxWidth="lg" :centered="true">
        <div class="p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between pb-3.5 border-b border-border">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-purple-50 text-purple-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-base font-bold text-ink">Test Rules Preview</h4>
                        <p class="text-xs text-muted">Preview whether sample lead answers will be accepted or skipped by active rules.</p>
                    </div>
                </div>
                <button type="button" x-on:click="$dispatch('close-modal', 'lead-filter-test-preview')"
                    class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition cursor-pointer">
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
                        <div class="grid grid-cols-12 gap-2 items-center" wire:key="test-field-{{ $index }}">
                            <div class="col-span-5">
                                <x-ui.themed-select
                                    wire:model.live="testFields.{{ $index }}.field"
                                    :options="$this->leadFieldOptions"
                                    placeholder="Field"
                                    :searchable="true"
                                    class="w-full" />
                            </div>
                            <div class="col-span-6">
                                <x-text-input wire:model="testFields.{{ $index }}.value" variant="compact" placeholder="Value (e.g. buy)" />
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
                    <button type="button" wire:click="runTestPreview"
                        class="w-full py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-xl shadow-xs transition cursor-pointer inline-flex items-center justify-center gap-1.5">
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
