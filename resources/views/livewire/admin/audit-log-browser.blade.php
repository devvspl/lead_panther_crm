<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1">
                <span>System Administration</span>
                <span>/</span>
                <span class="text-ink font-semibold">Audit Logs</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                <svg class="w-6 h-6 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>System Audit Logs Browser</span>
            </h1>
            <p class="text-xs text-muted">Immutable system audit trail tracking mutations, security events, role updates, and deployments.</p>
        </div>
    </div>

    <!-- Advanced Table Component -->
    <x-ui.advanced-table 
        :columns="$this->tableColumns()"
        :rows="$logs"
        :quickFilters="$this->quickFilters()"
        :activeStatus="$statusFilter"
        :visibleColumns="$visibleColumns"
        :sortField="$sortField"
        :sortDirection="$sortDirection"
        :filterCount="$this->activeFilterCount"
        searchPlaceholder="Search action, changes, IP address, user..."
        emptyTitle="No Audit Logs Found"
        emptyMessage="No system audit log records match your current search and filters."
    >
        <!-- Filter Dropdown Slot -->
        <x-slot:filters>
            <div class="space-y-3">
                <div>
                    <label class="text-[11px] font-bold text-ink block mb-1">Performing User</label>
                    <select wire:model.live="selectedUserId" class="w-full h-8 px-2.5 rounded-lg border border-border bg-canvas text-ink text-xs">
                        <option value="">All Performing Users</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-[11px] font-bold text-ink block mb-1">Action Type</label>
                    <select wire:model.live="actionType" class="w-full h-8 px-2.5 rounded-lg border border-border bg-canvas text-ink text-xs">
                        <option value="">All Action Types</option>
                        <option value="route">Route Invocations</option>
                        <option value="controller">Controller Actions</option>
                        <option value="livewire">Livewire Invocations</option>
                        <option value="auth">Auth &amp; Sessions</option>
                        <option value="created">Model Created</option>
                        <option value="updated">Model Updated</option>
                        <option value="deleted">Model Deleted</option>
                        <option value="git">Git Operations</option>
                    </select>
                </div>

                <div>
                    <label class="text-[11px] font-bold text-ink block mb-1">Subject Entity</label>
                    <select wire:model.live="subjectType" class="w-full h-8 px-2.5 rounded-lg border border-border bg-canvas text-ink text-xs">
                        <option value="">All Subjects &amp; Targets</option>
                        <optgroup label="Activity &amp; Execution">
                            <option value="Controller">Controllers</option>
                            <option value="Route">Routes</option>
                            <option value="Livewire">Livewire Actions</option>
                            <option value="Auth">Authentication</option>
                            <option value="git_sync">Git Deployments</option>
                        </optgroup>
                        <optgroup label="Core Business Entities">
                            <option value="Lead">Lead</option>
                            <option value="User">User</option>
                            <option value="Organization">Organization</option>
                            <option value="Project">Project</option>
                            <option value="Client">Client</option>
                            <option value="DistributionRule">DistributionRule</option>
                            <option value="SalesTeam">SalesTeam</option>
                        </optgroup>
                        <optgroup label="Finance &amp; Credits">
                            <option value="CreditWallet">CreditWallet</option>
                            <option value="CreditTransaction">CreditTransaction</option>
                            <option value="CreditPackage">CreditPackage</option>
                            <option value="RechargeRequest">RechargeRequest</option>
                            <option value="LeadReplacement">LeadReplacement</option>
                            <option value="Payment">Payment</option>
                        </optgroup>
                        <optgroup label="CRM Interactions">
                            <option value="Followup">Followup</option>
                            <option value="Meeting">Meeting</option>
                            <option value="Negotiation">Negotiation</option>
                            <option value="SiteVisit">SiteVisit</option>
                            <option value="Proposal">Proposal</option>
                            <option value="Campaign">Campaign</option>
                            <option value="UploadBatch">UploadBatch</option>
                        </optgroup>
                    </select>
                </div>

                <div>
                    <label class="text-[11px] font-bold text-ink block mb-1">Date Scope</label>
                    <x-ui.date-range-picker 
                        wire:model.live="dateRange"
                        wire:custom-from="customFrom"
                        wire:custom-to="customTo"
                        placeholder="All Time"
                    />
                </div>
            </div>
        </x-slot:filters>

        <!-- Primary Action Slot -->
        <x-slot:action>
            <x-ui.export-button target="exportExcel" class="text-xs" />
        </x-slot:action>
    </x-ui.advanced-table>

    <!-- Inspection / Details Modal -->
    <x-modal name="audit-log-details-modal" :show="$showDetailsModal" maxWidth="3xl">
        @if($selectedLog)
            <div class="p-5 sm:p-6 space-y-5 max-h-[88vh] overflow-y-auto">
                <!-- Modal Header -->
                <div class="flex items-start justify-between gap-3 border-b border-border pb-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-primary/10 text-primary border border-primary/20 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-ink">
                                    Audit Entry #{{ $selectedLog->id }}
                                </h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono {{ str_contains($selectedLog->action, 'delete') || str_contains($selectedLog->action, 'failed') ? 'bg-red-50 text-red-700 border border-red-200' : (str_contains($selectedLog->action, 'create') || str_contains($selectedLog->action, 'login') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-blue-700 border border-blue-200') }}">
                                    {{ $selectedLog->action }}
                                </span>
                            </div>
                            <p class="text-xs text-muted">
                                Recorded on {{ $selectedLog->created_at ? $selectedLog->created_at->format('M d, Y \a\t H:i:s T') : '—' }}
                            </p>
                        </div>
                    </div>

                    <button 
                        type="button" 
                        wire:click="closeDetails"
                        class="text-muted hover:text-ink transition p-1.5 rounded-lg hover:bg-canvas cursor-pointer"
                        title="Close Modal"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Metadata Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="p-3 rounded-xl bg-canvas/60 border border-border">
                        <span class="text-[10px] font-bold text-muted uppercase tracking-wider block mb-1">Performing User</span>
                        <div class="text-xs font-bold text-ink truncate">{{ $selectedLog->user?->name ?: 'System / Anonymous' }}</div>
                        <div class="text-[11px] text-muted truncate">{{ $selectedLog->user?->email ?: ($selectedLog->user_id ? 'ID #' . $selectedLog->user_id : 'System Routine') }}</div>
                    </div>

                    <div class="p-3 rounded-xl bg-canvas/60 border border-border">
                        <span class="text-[10px] font-bold text-muted uppercase tracking-wider block mb-1">Subject Entity</span>
                        <div class="text-xs font-bold font-mono text-ink truncate">{{ $selectedLog->subject_type ? class_basename($selectedLog->subject_type) : '—' }}</div>
                        <div class="text-[11px] text-muted font-mono truncate">{{ $selectedLog->subject_id ? 'ID #' . $selectedLog->subject_id : ($selectedLog->subject_type ? $selectedLog->subject_type : 'None') }}</div>
                    </div>

                    <div class="p-3 rounded-xl bg-canvas/60 border border-border">
                        <span class="text-[10px] font-bold text-muted uppercase tracking-wider block mb-1">IP Address</span>
                        <div class="text-xs font-bold font-mono text-ink">{{ $selectedLog->ip_address ?: '127.0.0.1' }}</div>
                        <div class="text-[11px] text-muted truncate">Client Remote IP</div>
                    </div>

                    <div class="p-3 rounded-xl bg-canvas/60 border border-border">
                        <span class="text-[10px] font-bold text-muted uppercase tracking-wider block mb-1">User Agent</span>
                        <div class="text-xs font-medium text-ink truncate" title="{{ $selectedLog->user_agent }}">{{ $selectedLog->user_agent ?: '—' }}</div>
                        <div class="text-[11px] text-muted truncate">Client Agent</div>
                    </div>
                </div>

                <!-- Payloads Section -->
                <div class="space-y-4">
                    @if($selectedLog->from_value)
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-ink flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                    <span>Previous State / Request Query Context</span>
                                </span>
                                <span class="text-[10px] text-muted uppercase font-mono">from_value</span>
                            </div>
                            <pre class="bg-canvas p-3.5 rounded-xl border border-border text-[11px] font-mono overflow-x-auto text-ink leading-relaxed max-h-56">{{ $this->formatPayload($selectedLog->from_value) }}</pre>
                        </div>
                    @endif

                    @if($selectedLog->to_value)
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-ink flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span>New State / Invocation Payload</span>
                                </span>
                                <span class="text-[10px] text-muted uppercase font-mono">to_value</span>
                            </div>
                            <pre class="bg-canvas p-3.5 rounded-xl border border-border text-[11px] font-mono overflow-x-auto text-ink leading-relaxed max-h-72">{{ $this->formatPayload($selectedLog->to_value) }}</pre>
                        </div>
                    @endif

                    @if(!$selectedLog->from_value && !$selectedLog->to_value)
                        <div class="p-6 text-center rounded-xl bg-canvas/50 border border-dashed border-border text-xs text-muted">
                            No additional payload or state snapshot was recorded for this entry.
                        </div>
                    @endif
                </div>

                <!-- Modal Footer -->
                <div class="pt-3 border-t border-border flex items-center justify-end">
                    <button 
                        type="button" 
                        wire:click="closeDetails"
                        class="px-4 py-2 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:bg-neutral-200 transition cursor-pointer"
                    >
                        Close
                    </button>
                </div>
            </div>
        @endif
    </x-modal>
</div>
