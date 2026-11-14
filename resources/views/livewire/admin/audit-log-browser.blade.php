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
                <label class="block text-xs text-ink">Organization
                    <select wire:model.live="organizationId" class="block w-full border border-border rounded-lg bg-canvas p-2"><option value="">All organizations</option>@foreach($organizations as $org)<option value="{{ $org->id }}">{{ $org->name }}</option>@endforeach</select>
                </label>
                <label class="block text-xs text-ink">Event Category
                    <select wire:model.live="eventCategory" class="block w-full border border-border rounded-lg bg-canvas p-2"><option value="">All categories</option>@foreach(['model', 'request', 'livewire', 'api', 'webhook', 'notification', 'access'] as $category)<option value="{{ $category }}">{{ ucfirst($category) }}</option>@endforeach</select>
                </label>
                <label class="block text-xs text-ink">Result
                    <select wire:model.live="eventStatus" class="block w-full border border-border rounded-lg bg-canvas p-2"><option value="">All results</option><option value="success">Success</option><option value="failure">Failure</option></select>
                </label>
                <div>
                    <label class="text-[11px] font-bold text-ink block mb-1">Performing User</label>
                    <livewire:shared.searchable-select 
                        :model="\App\Models\User::class" 
                        :searchable="true"
                        placeholder="All Performing Users" 
                        wire:model.live="selectedUserId" 
                        key="filter-user-audit-logs" 
                        class="w-full"
                    />
                </div>

                <div>
                    <label class="text-[11px] font-bold text-ink block mb-1">Action Type</label>
                    <livewire:shared.searchable-select 
                        :options="[
                            '' => 'All Action Types',
                            'route' => 'Route Invocations',
                            'controller' => 'Controller Actions',
                            'livewire' => 'Livewire Invocations',
                            'auth' => 'Auth & Sessions',
                            'created' => 'Model Created',
                            'updated' => 'Model Updated',
                            'deleted' => 'Model Deleted',
                            'git' => 'Git Operations'
                        ]" 
                        :searchable="true"
                        placeholder="All Action Types" 
                        wire:model.live="actionType" 
                        key="filter-action-type-audit-logs" 
                        class="w-full"
                    />
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

    <x-modal name="audit-log-details-modal" :show="$showDetailsModal" maxWidth="6xl">
        @if($selectedLog)
            @php $inspection = $this->inspection; @endphp
            <div class="p-5 sm:p-6 space-y-5 max-h-[90vh] overflow-y-auto">
                <header class="flex flex-wrap justify-between items-center gap-3 border-b border-border pb-4">
                    <h2 class="text-lg font-bold text-ink">Audit Entry #{{ $selectedLog->id }}</h2>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <button type="button" x-data @click="navigator.clipboard.writeText(String(@js($selectedLog->id))).then(() => $dispatch('toast', {type:'success', message:'Audit ID copied'})).catch(() => $dispatch('toast', {type:'error', message:'Clipboard unavailable'}))" class="border border-border rounded-lg px-3 py-2">Copy Audit ID</button>
                        <button type="button" x-data @click="navigator.clipboard.writeText(@js($this->formatPayload($inspection))).then(() => $dispatch('toast', {type:'success', message:'Sanitized JSON copied'})).catch(() => $dispatch('toast', {type:'error', message:'Clipboard unavailable'}))" class="border border-border rounded-lg px-3 py-2">Copy JSON</button>
                        <button type="button" wire:click="closeDetails" class="border border-border rounded-lg px-3 py-2">Close</button>
                    </div>
                </header>
                <section class="space-y-2">
                    <h3 class="font-semibold text-sm text-ink">Basic Information</h3>
                    <dl class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                        @foreach($inspection['basic'] as $label => $value)
                            <div class="border border-border rounded-lg p-3 min-w-0"><dt class="text-muted capitalize">{{ str_replace('_', ' ', $label) }}</dt><dd class="text-ink break-words font-medium mt-1">{{ is_array($value) ? implode(', ', $value) : ($value ?? 'Unavailable') }}</dd></div>
                        @endforeach
                    </dl>
                </section>
                <section class="space-y-2"><h3 class="font-semibold text-sm text-ink">Request Details</h3><x-ui.audit-json :value="$inspection['request']" /></section>
                <section class="space-y-3">
                    <h3 class="font-semibold text-sm text-ink">Change Details</h3>
                    <p class="text-xs text-muted">Related entity: {{ $inspection['entity']['type'] ?? 'Unavailable' }} | Record ID: {{ $inspection['entity']['id'] ?? 'Unavailable' }}</p>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="space-y-2"><h4 class="text-xs font-semibold text-ink">Previous Values</h4><x-ui.audit-json :value="$inspection['before']" /></div>
                        <div class="space-y-2"><h4 class="text-xs font-semibold text-ink">Updated Values</h4><x-ui.audit-json :value="$inspection['after']" /></div>
                    </div>
                    @if($inspection['changed_fields'])
                        <div class="overflow-x-auto border border-border rounded-lg"><table class="w-full text-xs text-left"><thead class="bg-canvas text-muted"><tr><th class="p-3">Changed Field</th><th class="p-3">Before</th><th class="p-3">After</th></tr></thead><tbody>
                        @foreach($inspection['changed_fields'] as $change)<tr class="border-t border-border"><td class="p-3 font-mono">{{ $change['field'] }}</td><td class="p-3"><x-ui.audit-json :value="$change['before']" /></td><td class="p-3"><x-ui.audit-json :value="$change['after']" /></td></tr>@endforeach
                        </tbody></table></div>
                    @else
                        <p class="text-xs text-muted">No field comparison was recorded for this event.</p>
                    @endif
                </section>
                <section class="space-y-2"><h3 class="font-semibold text-sm text-ink">System Details</h3><x-ui.audit-json :value="$inspection['system']" /></section>
                <footer class="flex justify-between border-t border-border pt-4 text-xs">
                    <div class="flex gap-2"><button type="button" wire:click="navigateRecord('previous')" class="border border-border px-3 py-2 rounded-lg">Previous record</button><button type="button" wire:click="navigateRecord('next')" class="border border-border px-3 py-2 rounded-lg">Next record</button></div>
                    <button type="button" wire:click="closeDetails" class="bg-primary text-white px-4 py-2 rounded-lg">Close</button>
                </footer>
            </div>
        @endif
    </x-modal>
</div>