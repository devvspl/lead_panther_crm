<div class="space-y-6">
    <!-- Top Breadcrumbs & Back Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1.5">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-ink transition" wire:navigate>System
                    Administration</a>
                <span>/</span>
                <a href="{{ route('admin.organizations') }}" class="hover:text-ink transition" wire:navigate>Clients</a>
                <span>/</span>
                <span class="text-ink font-semibold truncate">{{ $organization->name }}</span>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.organizations') }}" wire:navigate
                    class="p-2 rounded-xl bg-surface border border-border hover:bg-canvas text-muted hover:text-ink transition shadow-2xs inline-flex items-center justify-center shrink-0"
                    title="Back to Clients Directory">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>

                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                        <span>{{ $organization->name }}</span>
                        @if($organization->status === 'active')
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-950 border border-emerald-300">
                                <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                                Active
                            </span>
                        @else
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-950 border border-red-300">
                                <span class="w-2 h-2 rounded-full bg-red-600"></span>
                                Suspended
                            </span>
                        @endif
                    </h1>
                    <div class="flex items-center gap-2 text-xs text-muted mt-0.5">
                        <span
                            class="font-mono text-[11px] font-bold text-ink">#ORG-{{ str_pad($organization->id, 4, '0', STR_PAD_LEFT) }}</span>
                        <span>•</span>
                        <span>Registered
                            {{ $organization->created_at ? $organization->created_at->format('M d, Y') : '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header Actions -->
        <div class="flex flex-wrap items-center gap-2.5 min-w-0 max-w-full">
            <!-- Full Workbook Excel Export Button -->
            <button type="button" wire:click="exportFullExcel"
                class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white border border-emerald-700 text-xs font-bold transition shadow-xs inline-flex items-center gap-1.5 cursor-pointer"
                title="Export All Clients Tabs into Multi-Sheet Excel">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Export</span>
            </button>

            <!-- Suspend / Activate -->
            <button type="button" wire:click="toggleOrganizationStatus"
                wire:confirm="Are you sure you want to change this organization's status?"
                class="px-3 py-2 rounded-xl border border-border bg-surface hover:bg-canvas text-ink text-xs font-semibold transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer">
                @if($organization->status === 'active')
                    <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Suspend</span>
                @else
                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Activate</span>
                @endif
            </button>

            @if(!$isEditing)
                <button type="button" wire:click="startEditing"
                    class="px-3 py-2 rounded-xl border border-border bg-surface hover:bg-canvas text-ink text-xs font-semibold transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                    <span>Edit Profile</span>
                </button>
            @endif

            <button type="button" wire:click="openAddMemberModal"
                class="px-3.5 py-2 rounded-xl bg-ink text-white hover:bg-neutral-800 text-xs font-semibold transition shadow-xs inline-flex items-center gap-1.5 cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
                <span>Add Member</span>
            </button>
        </div>
    </div>

    <!-- Quick Edit Form (when isEditing is true) -->
    @if($isEditing)
        <div class="p-5 rounded-2xl bg-surface border border-primary/30 shadow-sm space-y-4 transition-all">
            <div class="flex items-center justify-between pb-3 border-b border-border">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-primary/10 text-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </span>
                    <h3 class="text-sm font-bold text-ink">Edit Clients Details</h3>
                </div>
                <button type="button" wire:click="cancelEditing" class="text-xs text-muted hover:text-ink cursor-pointer">
                    Cancel
                </button>
            </div>

            <form wire:submit.prevent="updateOrganization" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="font-bold text-ink">Clients Name <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="editName"
                        class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1"
                        placeholder="Enter clients name">
                    @error('editName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="font-bold text-ink">Status <span class="text-red-500">*</span></label>
                    <x-ui.themed-select wire:model="editStatus" :options="['active' => 'Active', 'suspended' => 'Suspended']"
                        placeholder="Select Status" class="w-full mt-1" />
                    @error('editStatus') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div class="sm:col-span-2 flex items-center justify-end gap-2 pt-2">
                    <button type="button" wire:click="cancelEditing"
                        class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary/90 transition shadow-xs cursor-pointer flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- Organization KPIs: tab counts and workflows remain independent. -->
    @php
        $overviewMetrics = [
            ['label' => 'Team Members', 'value' => $activeTeamMembersCount, 'description' => 'Active members', 'icon' => 'team'],
            ['label' => 'Active Projects', 'value' => $activeProjectsCount, 'description' => 'Currently active', 'icon' => 'project'],
            ['label' => 'Total Leads', 'value' => $totalLeadsCount, 'description' => 'Client leads', 'icon' => 'leads'],
            ['label' => 'Assigned Leads', 'value' => $directlyAssignedLeadsCount, 'description' => 'With active members', 'icon' => 'check'],
            ['label' => 'Available Lead Credits', 'value' => $totalWalletBalance, 'description' => 'Remaining credits', 'icon' => 'wallet', 'balance' => true],
        ];
    @endphp
    <div data-organization-kpis class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5 items-stretch">
        @foreach($overviewMetrics as $metric)
            @php $isBalance = $metric['balance'] ?? false; @endphp
            <div class="min-w-0 p-4 rounded-2xl bg-surface border border-border shadow-2xs flex flex-col gap-2">
                <div class="flex items-center justify-between gap-2 text-muted text-[11px] font-semibold uppercase tracking-wider">
                    <span>{{ $metric['label'] }}</span>
                    <x-ui.team-icon :name="$metric['icon']" class="text-primary" />
                </div>
                <div class="text-2xl font-bold font-mono break-words {{ $isBalance ? ($totalWalletBalance >= 50 ? 'text-success' : 'text-warning') : 'text-ink' }}">{{ number_format($metric['value']) }}</div>
                <div class="mt-auto flex flex-wrap items-center justify-between gap-1 text-[10px] text-muted">
                    <span>{{ $metric['description'] }}</span>
                    @if($isBalance)
                        <span class="w-full">Total: {{ number_format($totalLeadCredits) }} / Used: {{ number_format($usedLeadCredits) }} / Remaining: {{ number_format($totalWalletBalance) }} Credits</span>
                        <span class="px-1.5 py-0.5 rounded border text-[9px] font-bold {{ $totalWalletBalance >= 50 ? 'bg-success/10 text-success border-success/20' : 'bg-warning/10 text-warning border-warning/20' }}">{{ $totalWalletBalance >= 50 ? 'Sufficient' : 'Low Credits' }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- Tabbed Navigation Bar -->
    <div class="border-b border-border">
        <nav class="flex space-x-2 sm:space-x-5 -mb-px overflow-x-auto text-xs font-semibold">
            <!-- Tab 1: Overview -->
            <a href="{{ route('admin.organizations.show', ['organization' => $organization->id, 'tab' => 'overview']) }}"
                wire:click.prevent="setTab('overview')"
                class="py-3 px-2 border-b-2 transition flex items-center gap-2 cursor-pointer whitespace-nowrap {{ $activeTab === 'overview' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink hover:border-border' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
                <span>Overview & Profile</span>
            </a>

            <!-- Tab 2: Team Members -->
            <a href="{{ route('admin.organizations.show', ['organization' => $organization->id, 'tab' => 'team']) }}"
                wire:click.prevent="setTab('team')"
                class="py-3 px-2 border-b-2 transition flex items-center gap-2 cursor-pointer whitespace-nowrap {{ $activeTab === 'team' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink hover:border-border' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>Team Members</span>
                <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono {{ $activeTab === 'team' ? 'bg-primary/10 text-primary border border-primary/30' : 'bg-canvas text-muted border border-border' }}">
                    {{ $totalUsersCount }}
                </span>
            </a>

            <!-- Tab 3: Projects -->
            <a href="{{ route('admin.organizations.show', ['organization' => $organization->id, 'tab' => 'clients']) }}"
                wire:click.prevent="setTab('clients')"
                class="py-3 px-2 border-b-2 transition flex items-center gap-2 cursor-pointer whitespace-nowrap {{ $activeTab === 'clients' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink hover:border-border' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <span>Projects</span>
                <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono {{ $activeTab === 'clients' ? 'bg-primary/10 text-primary border border-primary/30' : 'bg-canvas text-muted border border-border' }}">
                    {{ $totalProjectsCount }}
                </span>
            </a>

            <a href="{{ route('admin.organizations.show', ['organization' => $organization->id, 'tab' => 'leads']) }}"
                wire:click.prevent="setTab('leads')"
                class="py-3 px-2 border-b-2 transition flex items-center gap-2 whitespace-nowrap {{ $activeTab === 'leads' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink' }}">Leads</a>

            <!-- Tab 4: Lead Credits -->
            <a href="{{ route('admin.organizations.show', ['organization' => $organization->id, 'tab' => 'credits']) }}"
                wire:click.prevent="setTab('credits')"
                class="py-3 px-2 border-b-2 transition flex items-center gap-2 cursor-pointer whitespace-nowrap {{ $activeTab === 'credits' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink hover:border-border' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <span>Lead Credits</span>
                <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono {{ $activeTab === 'credits' ? 'bg-primary/10 text-primary border border-primary/30' : 'bg-canvas text-muted border border-border' }}">
                    {{ number_format($totalWalletBalance) }}
                </span>
            </a>


            <a href="{{ route('admin.organizations.show', ['organization' => $organization->id, 'tab' => 'campaigns']) }}"
                wire:click.prevent="setTab('campaigns')"
                class="py-3 px-2 border-b-2 whitespace-nowrap {{ $activeTab === 'campaigns' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted' }}">Campaign Details & Delivery</a>
            <!-- Tab 6: Activity Log -->
            <a href="{{ route('admin.organizations.show', ['organization' => $organization->id, 'tab' => 'activity']) }}"
                wire:click.prevent="setTab('activity')"
                class="py-3 px-2 border-b-2 transition flex items-center gap-2 cursor-pointer whitespace-nowrap {{ $activeTab === 'activity' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink hover:border-border' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Activity & Audit</span>
            </a>
        </nav>
    </div>

    @if($activeTab === 'leads')
        <livewire:leads.client-lead-manager :organization-id="$organization->id" :key="'client-leads-'.$organization->id" />
    @endif

    @if($activeTab === 'campaigns')
        <section class="space-y-5" wire:poll.30s>
            @forelse($this->deliveryProjects as $row)
                @php
                    $project = $row['project'];
                    $projectKey = 'project_'.$project->id;
                @endphp
                <article id="delivery-project-{{ $project->id }}" class="bg-surface border border-border rounded-xl overflow-hidden" wire:key="delivery-project-{{ $project->id }}" x-data="{ open: false }" x-init="open = window.location.hash === '#' + $el.id || (!!window.location.hash && Array.from($el.querySelectorAll('[id]')).some(node => '#' + node.id === window.location.hash))">
                    <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="delivery-project-content-{{ $project->id }}" class="flex w-full items-center justify-between gap-4 p-4 sm:p-5 text-left hover:bg-canvas/50 transition">
                        <span class="min-w-0 space-y-2"><span class="block text-sm font-bold text-ink">{{ $project->name }}</span><span class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted"><span>{{ ucfirst($project->status) }} · {{ count($row['campaigns']) }} campaigns</span><span>Daily limit: {{ $project->delivery_daily_limit ? number_format($project->delivery_daily_limit) : 'Unlimited' }}</span><span>Interval: {{ $project->delivery_interval_minutes ? $project->delivery_interval_minutes.' min' : 'Immediate' }}</span><span>Delivered today: {{ number_format($row['stats']['today']) }} · Pending: {{ number_format($row['stats']['pending']) }}</span></span></span>
                        <span class="flex shrink-0 items-center gap-2 text-xs font-semibold text-primary"><span x-text="open ? 'Collapse' : 'Configure'"></span><svg class="h-4 w-4 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg></span>
                    </button>
                    <div id="delivery-project-content-{{ $project->id }}" x-show="open" x-cloak class="border-t border-border p-4 sm:p-5 space-y-5">
                    <h4 class="text-sm font-semibold text-ink">Project Default Settings</h4>
                    <form wire:submit="saveDeliverySettings('project', {{ $project->id }})" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        <label class="text-xs text-ink">Daily Lead Limit (0 = unlimited)
                            <input type="number" min="0" max="100000" wire:model="deliverySettings.{{ $projectKey }}.limit" class="block w-full mt-1 rounded-lg border border-border bg-canvas p-2">
                            @error('deliverySettings.'.$projectKey.'.limit') <span class="text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="text-xs text-ink">Delivery Interval (Minutes; 0 = immediate)
                            <input type="number" min="0" max="10080" wire:model="deliverySettings.{{ $projectKey }}.interval" class="block w-full mt-1 rounded-lg border border-border bg-canvas p-2">
                            @error('deliverySettings.'.$projectKey.'.interval') <span class="text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="text-xs text-ink">Delivery status
                            <x-ui.themed-select class="w-full" searchable  wire:model="deliverySettings.{{ $projectKey }}.enabled" trigger-class="block w-full mt-1 rounded-lg border border-border bg-canvas p-2"><option value="1">Active</option><option value="0">Inactive</option></x-ui.themed-select>
                        </label>
                        <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-primary text-white px-4 py-2 text-sm">Save Settings</button>
                    </form>
                    <details class="rounded-lg border border-border p-3"><summary class="text-xs font-semibold text-ink cursor-pointer">Live Delivery Statistics <span class="font-normal text-muted">· Project totals</span></summary><div class="mt-3"><x-ui.delivery-statistics :stats="$row['stats']" /></div><p class="text-xs text-muted mt-2">Daily capacity is summed across campaigns.</p></details>
                    <h4 class="text-sm font-semibold text-ink border-t border-border pt-4">Campaign Override Settings</h4>
                    @forelse($row['campaigns'] as $item)
                        @php
                            $campaign = $item['campaign'];
                            $key = 'campaign_'.$campaign->id;
                            $effective = $item['effective'];
                        @endphp
                        <div id="delivery-campaign-{{ $campaign->id }}" class="border border-border rounded-lg overflow-hidden" wire:key="delivery-campaign-{{ $campaign->id }}" x-data="{ expanded: false }" x-init="expanded = window.location.hash === '#' + $el.id">
                            <button type="button" @click="expanded = !expanded" :aria-expanded="expanded.toString()" aria-controls="delivery-campaign-content-{{ $campaign->id }}" class="flex items-center justify-between gap-3 w-full p-3 sm:p-4 text-left hover:bg-canvas/50 transition">
                                <span class="min-w-0 space-y-1"><span class="block text-sm font-semibold text-ink">{{ $campaign->name }}</span><span class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted"><span>{{ $campaign->leadSource?->name ?? 'Not linked' }} · {{ $project->delivery_enabled && $project->status === 'active' && $campaign->status === 'active' ? 'Active' : 'Paused' }}</span><span>Daily: {{ $effective['limit'] === 0 ? 'Unlimited' : number_format($effective['limit']) }} ({{ $effective['limit_source'] }})</span><span>Interval: {{ $effective['interval'] === 0 ? 'Immediate' : $effective['interval'].' min' }} ({{ $effective['interval_source'] }})</span></span></span>
                                <svg class="h-4 w-4 shrink-0 text-muted transition-transform" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                            <div id="delivery-campaign-content-{{ $campaign->id }}" x-show="expanded" x-cloak class="border-t border-border p-3 sm:p-4 space-y-3">
                            <p class="text-xs text-muted">Campaign status: {{ ucfirst($campaign->status) }}</p>
                            <form wire:submit="saveDeliverySettings('campaign', {{ $campaign->id }})" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                                <div class="text-xs text-ink">
                                    <label>Daily Lead Limit (0 = unlimited)
                                        <input type="number" min="0" max="100000" wire:model="deliverySettings.{{ $key }}.limit" @disabled($deliverySettings[$key]['inherit_limit'] ?? true) placeholder="Inherit project" class="block w-full mt-1 rounded-lg border border-border bg-canvas p-2 disabled:opacity-50">
                                    </label>
                                    <label class="flex gap-2 mt-2"><input type="checkbox" wire:model.live="deliverySettings.{{ $key }}.inherit_limit"> Inherit Project Default</label>
                                    @error('deliverySettings.'.$key.'.limit') <span class="text-red-600">{{ $message }}</span> @enderror
                                </div>
                                <div class="text-xs text-ink">
                                    <label>Delivery Interval (Minutes; 0 = immediate)
                                        <input type="number" min="0" max="10080" wire:model="deliverySettings.{{ $key }}.interval" @disabled($deliverySettings[$key]['inherit_interval'] ?? true) placeholder="Inherit project" class="block w-full mt-1 rounded-lg border border-border bg-canvas p-2 disabled:opacity-50">
                                    </label>
                                    <label class="flex gap-2 mt-2"><input type="checkbox" wire:model.live="deliverySettings.{{ $key }}.inherit_interval"> Inherit Project Default</label>
                                    @error('deliverySettings.'.$key.'.interval') <span class="text-red-600">{{ $message }}</span> @enderror
                                </div>
                                <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-primary text-white px-4 py-2 text-sm">Save Settings</button>
                            </form>
                            <div class="text-xs text-ink bg-canvas border border-border rounded-lg p-3">
                                Effective daily limit: <strong>{{ $effective['limit'] === 0 ? 'Unlimited' : $effective['limit'] }}</strong> ({{ $effective['limit_source'] }}) |
                                Effective interval: <strong>{{ $effective['interval'] === 0 ? 'Immediate' : $effective['interval'].' minutes' }}</strong> ({{ $effective['interval_source'] }})
                            </div>
                            <details class="rounded-lg border border-border p-3"><summary class="text-xs font-semibold text-ink cursor-pointer">Live Delivery Statistics · {{ number_format($item['stats']['today']) }} delivered today · {{ number_format($item['stats']['pending']) }} pending</summary><div class="mt-3"><x-ui.delivery-statistics :stats="$item['stats']" /></div></details>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-muted">No campaigns linked to this project.</p>
                    @endforelse
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-border bg-surface p-8 text-center space-y-2"><h3 class="text-sm font-semibold text-ink">No campaigns mapped to this organization</h3><p class="text-xs text-muted">Map a lead form to a campaign and a client in this organization under Integrations. Active mappings will appear here automatically.</p><a href="{{ route('settings.integrations') }}" class="inline-block text-xs font-semibold text-primary hover:underline">Manage Integration Mappings</a></div>
            @endforelse
        </section>
    @endif
    <!-- TAB 1: OVERVIEW -->
    @if($activeTab === 'overview')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Clients Summary Info -->
            <div class="lg:col-span-2 space-y-6">
                <div class="p-5 sm:p-6 rounded-2xl bg-surface border border-border shadow-2xs space-y-5">
                    <div class="flex items-center justify-between pb-3.5 border-b border-border">
                        <h3 class="text-sm font-bold text-ink flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-primary/10 text-primary">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </span>
                            <span>Clients Profile Details</span>
                        </h3>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="exportTabExcel('overview')"
                                class="px-2.5 py-1 text-xs rounded-lg border border-border hover:bg-canvas text-muted hover:text-ink transition cursor-pointer flex items-center gap-1"
                                title="Export Overview to Excel">
                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>Export Tab</span>
                            </button>
                            <span class="text-xs text-muted">ID: <strong
                                    class="text-ink font-mono">#{{ $organization->id }}</strong></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Legal / Business
                                Name</span>
                            <div class="text-sm font-bold text-ink">{{ $organization->name }}</div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Current System
                                Status</span>
                            <div>
                                @if($organization->status === 'active')
                                    <span
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Active
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                        Suspended
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Created Timestamp</span>
                            <div class="text-xs font-mono text-ink">
                                {{ $organization->created_at ? $organization->created_at->format('M d, Y • h:i A') : '—' }}
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Last Updated</span>
                            <div class="text-xs font-mono text-ink">
                                {{ $organization->updated_at ? $organization->updated_at->format('M d, Y • h:i A') : '—' }}
                            </div>
                        </div>
                    </div>

                    <!-- Quick Action Links within Overview -->
                    <div class="flex items-center justify-between pt-3 border-t border-border">
                        <div class="text-xs text-muted">
                            Need to update legal details or change status?
                        </div>
                        <button type="button" wire:click="startEditing"
                            class="px-3 py-1.5 bg-surface border border-border text-xs font-semibold text-ink rounded-lg hover:bg-canvas transition shadow-2xs cursor-pointer inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                            <span>Edit Organization</span>
                        </button>
                    </div>
                </div>

                <!-- Clients Per-Lead Pricing Tier Card -->
                <div class="p-5 sm:p-6 rounded-2xl bg-surface border border-border shadow-2xs space-y-4">
                    <div
                        class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-border">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-purple-100 text-purple-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-ink">Clients Per-Lead Pricing</h3>
                                <p class="text-[11px] text-muted">Tier 3 in 4-tier pricing hierarchy (Project → Team → Org →
                                    Global).</p>
                            </div>
                        </div>
                        @if($organization->lead_cost !== null)
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-950 border border-purple-300 w-fit">
                                Custom Override Active
                            </span>
                        @else
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-800 border border-slate-300 w-fit">
                                Inheriting Global Default
                            </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
                        <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Effective Lead
                                Cost</span>
                            <div class="text-2xl font-bold font-mono text-purple-700">
                                {{ $organization->lead_cost !== null ? number_format($organization->lead_cost, 0) : number_format($globalDefaultLeadCost, 0) }}
                                <span class="text-xs font-normal text-muted">Credits</span>
                            </div>
                            <div class="text-[10px] text-muted">
                                {{ $organization->lead_cost !== null ? 'Clients override active' : 'Global default: ' . number_format($globalDefaultLeadCost, 0) . ' Credits' }}
                            </div>
                        </div>

                        <div class="sm:col-span-2 space-y-2">
                            <label class="block text-xs font-bold text-ink">Set Clients Rate Override (Credits /
                                Lead)</label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input type="number" step="1" min="1" wire:model="orgLeadCost"
                                        placeholder="Inherit global default ({{ number_format($globalDefaultLeadCost, 0) }})"
                                        class="w-full h-9 px-3 text-xs bg-canvas border border-border rounded-lg text-ink font-mono focus:ring-2 focus:ring-primary/20 focus:border-primary transition">
                                </div>
                                <button type="button" wire:click="updateOrgLeadCost"
                                    class="h-9 px-4 bg-ink hover:bg-neutral-800 text-white text-xs font-bold rounded-lg transition shadow-xs cursor-pointer shrink-0">
                                    Save Override
                                </button>
                                @if($orgLeadCost !== null)
                                    <button type="button" wire:click="$set('orgLeadCost', null); updateOrgLeadCost()"
                                        class="h-9 px-3 border border-border bg-surface hover:bg-canvas text-xs font-semibold text-muted hover:text-ink rounded-lg transition cursor-pointer shrink-0"
                                        title="Clear override and inherit global default">
                                        Reset
                                    </button>
                                @endif
                            </div>
                            @error('orgLeadCost') <span class="text-red-500 text-[10px] block">{{ $message }}</span>
                            @enderror
                            <p class="text-[10px] text-muted">Applies to all clients &amp; projects under this org unless
                                overridden at project or team level.</p>
                        </div>
                    </div>
                </div>

                <details wire:ignore.self wire:key="overview-assignment-{{ $organization->id }}" class="rounded-2xl bg-surface border border-border p-5 sm:p-6">
                    <summary class="text-sm font-bold text-ink cursor-pointer">Lead Assignment Configuration</summary>
                    <div class="mt-4">
                <livewire:settings.lead-assignment-configuration :organization-id="$organization->id" :key="'lead-assignment-'.$organization->id" />
                    </div>
                </details>
                <details wire:ignore.self wire:key="overview-internal-processing-{{ $organization->id }}" class="rounded-2xl bg-surface border border-border p-5 sm:p-6">
                    <summary class="text-sm font-bold text-ink cursor-pointer">Lead Panther Internal Processing</summary>
                    <div class="mt-4">
                        <livewire:admin.internal-processing-settings :organization-id="$organization->id" :key="'internal-processing-settings-'.$organization->id" />
                    </div>
                </details>

            </div>

            <!-- Right Column: Projects Overview Sidebar -->
            <div class="space-y-6">
                <!-- Registered Clients -->
                <div class="p-5 sm:p-6 rounded-2xl bg-surface border border-border shadow-2xs space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-bold text-ink flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </span>
                            <span>Associated Clients</span>
                        </h3>
                        <button type="button" wire:click="openCreateClientModal"
                            class="text-xs font-semibold text-primary hover:underline cursor-pointer">
                            + Add Client
                        </button>
                    </div>

                    <div class="space-y-2.5">
                        @forelse($clients as $c)
                            <div
                                class="p-3 rounded-xl bg-canvas/40 border border-border flex items-center justify-between gap-3 shadow-2xs">
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-ink truncate">{{ $c->name }}</div>
                                    <div class="text-[10px] text-muted flex items-center gap-2">
                                        <span>{{ $c->projects_count }} projects</span>
                                        <span>•</span>
                                        <span
                                            class="font-mono text-ink font-semibold">{{ number_format($c->wallet?->balance ?? 0) }}
                                            credits</span>
                                    </div>
                                </div>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $c->status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                    {{ $c->status }}
                                </span>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-muted border border-dashed border-border rounded-xl">
                                No clients registered under this clients yet.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Projects Quick List -->
                <div class="p-5 sm:p-6 rounded-2xl bg-surface border border-border shadow-2xs space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-bold text-ink flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-900/30">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                            </span>
                            <span>Active Projects</span>
                        </h3>
                        <span class="text-xs font-bold font-mono text-ink">{{ $totalProjectsCount }} Total</span>
                    </div>

                    <div class="space-y-2">
                        @forelse($projects->take(5) as $p)
                            <div
                                class="p-2.5 rounded-xl bg-canvas/40 border border-border flex items-center justify-between gap-3 text-xs shadow-2xs">
                                <div class="min-w-0">
                                    <div class="font-bold text-ink truncate">{{ $p->name }}</div>
                                    <div class="text-[10px] text-muted truncate">{{ $p->client_name }}</div>
                                </div>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-surface text-ink border border-border">
                                    {{ $p->status ?? 'active' }}
                                </span>
                            </div>
                        @empty
                            <div class="p-4 text-center text-xs text-muted border border-dashed border-border rounded-xl">
                                No projects associated yet.
                            </div>
                        @endforelse
                    </div>
                </div>
                <section class="p-5 sm:p-6 rounded-2xl bg-surface border border-border shadow-2xs flex items-center justify-between gap-4">
                    <div>
                        <h3 id="builder-credit-visibility-label" class="text-sm font-bold text-ink">Show Credit Balance</h3>
                        <p class="text-xs text-muted mt-1">Show available credits and credits consumed on this client's Builder/Developer dashboard.</p>
                    </div>
                    <button type="button" role="switch" aria-labelledby="builder-credit-visibility-label" aria-checked="{{ $organization->show_builder_credit_balance ? 'true' : 'false' }}"
                        wire:click="setBuilderCreditBalanceVisibility({{ $organization->show_builder_credit_balance ? 'false' : 'true' }})" wire:loading.attr="disabled"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition focus-visible:outline-2 focus-visible:outline-primary disabled:opacity-50 {{ $organization->show_builder_credit_balance ? 'bg-primary' : 'bg-neutral-300' }}">
                        <span class="inline-block h-4 w-4 rounded-full bg-surface shadow transition {{ $organization->show_builder_credit_balance ? 'translate-x-6' : 'translate-x-1' }}"></span>
                    </button>
                </section>

                <form wire:submit="saveExecutiveResponseTime" class="p-5 rounded-2xl bg-surface border border-border space-y-3">
                    <label for="executive-response-minutes" class="block text-sm font-bold text-ink">Executive Response Time</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-text-input id="executive-response-minutes" type="number" min="1" max="10080" wire:model="executiveResponseMinutes" class="w-28" />
                        <span class="text-xs text-muted">minutes</span>
                        <button type="submit" wire:loading.attr="disabled" class="h-9 px-4 rounded-lg bg-primary text-white text-xs font-semibold">Save</button>
                    </div>
                    @error('executiveResponseMinutes')<p role="alert" class="text-xs text-danger">{{ $message }}</p>@enderror
                    <p class="text-xs text-muted">First response deadline from assignment. Applies to new assignments.</p>
                </form>
            </div>
        </div>
    @endif

    <!-- TAB 2: TEAM MEMBERS -->
    @if($activeTab === 'team')
        <div class="space-y-4">
            <!-- Team Filter & Actions Bar -->
            <div
                class="p-4 rounded-2xl bg-surface border border-border shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2.5 flex-1">
                    <!-- Search Input -->
                    <div class="relative flex-1 max-w-sm">
                        <svg class="w-4 h-4 text-muted absolute left-3 top-2.5" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input type="text" wire:model.live.debounce.300ms="userSearch"
                            placeholder="Search team members by name, email..."
                            class="w-full h-8 pl-9 pr-3 text-xs bg-canvas border border-border rounded-lg text-ink focus:ring-2 focus:ring-primary/20 focus:border-primary transition">
                    </div>

                    <!-- Role Filter -->
                    <div class="w-44">
                        <x-ui.themed-select class="w-full" searchable  wire:model.live="userRoleFilter"
                            trigger-class="w-full h-8 px-3 text-xs bg-canvas border border-border rounded-lg text-ink focus:ring-2 focus:ring-primary/20 focus:border-primary transition cursor-pointer">
                            <option value="all">All Roles</option>
                            @foreach($this->availableRoles as $roleKey => $roleLabel)
                                <option value="{{ $roleKey }}">{{ $roleLabel }}</option>
                            @endforeach
                        </x-ui.themed-select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 min-w-0 max-w-full">
                    <button type="button" wire:click="exportTabExcel('team')"
                        class="px-3 py-2 border border-border bg-surface hover:bg-canvas text-ink text-xs font-semibold rounded-lg transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer"
                        title="Export Team Members to Excel">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>Export Tab</span>
                    </button>

                    <button type="button" wire:click="openAddMemberModal"
                        class="px-3.5 py-2 bg-ink text-white rounded-lg hover:bg-neutral-800 text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span>Add New Member</span>
                    </button>
                </div>
            </div>

            <!-- Shareable Activation Token Banner if generated -->
            @if($generatedInviteLink)
                <div class="p-4 bg-surface border border-primary/30 rounded-2xl space-y-2 shadow-xs">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-xs font-bold text-ink flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                            <span>Activation Link Generated</span>
                        </span>
                        <button type="button" wire:click="$set('generatedInviteLink', null)"
                            class="text-xs text-muted hover:text-ink cursor-pointer">
                            Dismiss
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="text" readonly value="{{ $generatedInviteLink }}"
                            class="w-full h-8 px-3 text-[11px] font-mono bg-canvas border border-border rounded-lg text-ink select-all">
                        <button type="button" x-data="{ copied: false }"
                            @click="navigator.clipboard.writeText('{{ $generatedInviteLink }}'); copied = true; $wire.dispatch('toast', { type: 'success', title: 'Copied', message: 'Activation link copied to clipboard.' }); setTimeout(function() { copied = false; }, 2000)"
                            class="px-3 h-8 bg-ink text-white rounded-lg hover:bg-neutral-800 text-xs font-bold transition shrink-0 flex items-center gap-1.5 shadow-xs cursor-pointer">
                            <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <svg x-show="copied" class="w-3.5 h-3.5 text-green-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span x-text="copied ? 'Copied' : 'Copy'"></span>
                        </button>
                    </div>
                </div>
            @endif

            <!-- Team Members Table -->
            <div class="bg-surface rounded-2xl border border-border shadow-2xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="bg-canvas/60 text-muted uppercase tracking-wider text-[10px] font-bold border-b border-border">
                            <tr>
                                <th class="py-3 px-4">Member Name</th>
                                <th class="py-3 px-4">Email Address</th>
                                <th class="py-3 px-4">Phone</th>
                                <th class="py-3 px-4">Team</th>
                                <th class="py-3 px-4">Team Leader</th>
                                <th class="py-3 px-4">Role Badge</th>
                                <th class="py-3 px-4">Account Status</th>
                                <th class="py-3 px-4">Joined Date</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($teamMembers as $member)
                                <tr class="hover:bg-canvas/30 transition">
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-8 h-8 rounded-full bg-canvas dark:bg-canvas border border-border text-ink font-bold text-xs flex items-center justify-center shrink-0">
                                                {{ strtoupper(substr($member->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <button type="button" wire:click="$dispatch('open-org-member', { memberId: {{ $member->id }} })" class="font-bold text-ink hover:text-primary hover:underline text-left">{{ $member->name }}</button>
                                                <div class="text-[10px] text-muted font-mono">ID: #{{ $member->id }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-3 px-4 font-mono text-muted text-[11px]">
                                        {{ $member->email }}
                                    </td>

                                    <td class="py-3 px-4 font-mono text-muted whitespace-nowrap">{{ $member->phone ?: '—' }}</td>
                                    <td class="py-3 px-4 text-ink min-w-[140px]">{{ $teamMembershipDetails[$member->id]['teams'] ?: 'Not assigned' }}</td>
                                    <td class="py-3 px-4 text-ink min-w-[120px]">{{ $teamMembershipDetails[$member->id]['leaders'] ?: 'Not assigned' }}</td>

                                    <td class="py-3 px-4">
                                        @php
                                            $roleName = $member->roles->first()?->name;
                                        @endphp
                                        <span
                                            class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wider {{ in_array(strtolower($roleName ?? ''), ['super admin', 'super-admin']) ? 'bg-primary/10 text-primary border-primary/20' : (in_array(strtolower($roleName ?? ''), ['builder']) ? 'bg-primary/10 text-primary border-primary/20' : 'bg-canvas text-ink border-border') }}">
                                            {{ $roleName ? ucwords(str_replace(['-', '_'], ' ', $roleName)) : 'No Role' }}
                                        </span>
                                    </td>

                                    <td class="py-3 px-4">
                                        <button type="button" wire:click="toggleUserStatus({{ $member->id }})"
                                            class="cursor-pointer" title="Click to toggle user status">
                                            @if(($member->status ?? 'active') === 'active')
                                                <span
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-success/10 text-success border border-success/20">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-success animate-pulse"></span>
                                                    Active
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-danger/10 text-danger border border-danger/20">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-danger"></span>
                                                    Inactive
                                                </span>
                                            @endif
                                        </button>
                                    </td>

                                    <td class="py-3 px-4 font-mono text-muted text-[11px]">
                                        {{ $member->created_at ? $member->created_at->format('M d, Y') : '—' }}
                                    </td>

                                    <td class="py-3 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('admin.users.impersonate', $member->id) }}"
                                                class="p-1.5 rounded-lg border border-border bg-surface hover:bg-primary/10 text-purple-700 hover:border-primary/20 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                                title="Impersonate User">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                                </svg>
                                            </a>

                                            <button type="button" wire:click="generateUserActivationLink({{ $member->id }})"
                                                class="p-1.5 rounded-lg border border-border bg-surface hover:bg-primary/10 text-primary hover:border-primary/20 transition shadow-2xs cursor-pointer inline-flex items-center justify-center"
                                                title="Generate Reset / Activation Link">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                </svg>
                                            </button>

                                            <button type="button" wire:click="removeUserFromOrganization({{ $member->id }})"
                                                wire:confirm="Remove {{ $member->name }} from {{ $organization->name }}?"
                                                class="p-1.5 rounded-lg border border-border bg-surface hover:bg-danger/10 text-danger hover:border-danger/20 transition shadow-2xs cursor-pointer inline-flex items-center justify-center"
                                                title="Unassign from Organization">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="p-8 text-center text-xs text-muted">
                                        No team members found matching your search.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 3: PROJECT MAPPINGS -->
    @if($activeTab === 'clients')
        <div class="space-y-6">
            @if($clients->isEmpty())
                <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2.5">
                        <svg class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <span class="font-bold">No Billing Owner Registered Yet</span>
                            <p class="text-[11px] text-amber-700 dark:text-amber-400 mt-0.5">Saving a project mapping will automatically provision a main user for {{ $organization->name }}, or you can set one up now.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" wire:click="autoCreateMainUser" wire:loading.attr="disabled" class="px-3.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs cursor-pointer transition shadow-2xs">
                            Auto-Create Main User
                        </button>
                        <button type="button" wire:click="openCreateClientModal" class="px-3.5 py-1.5 rounded-lg border border-amber-300 dark:border-amber-700 bg-surface/50 hover:bg-surface text-amber-900 dark:text-amber-200 font-semibold text-xs cursor-pointer transition">
                            + Custom Client Details
                        </button>
                    </div>
                </div>
            @endif

            <div class="p-5 rounded-2xl bg-surface border border-border space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-ink">Project Mapping ({{ $totalProjectsCount }})</h3>
                        <p class="text-xs text-muted mt-1">Projects are automatically assigned to {{ $organization->name }}'s billing owner.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="openCreateClientModal" class="px-3 py-1.5 border border-border bg-surface hover:bg-canvas rounded-lg text-xs font-semibold text-ink flex items-center gap-1.5 cursor-pointer transition">
                            <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            <span>Add Client</span>
                        </button>
                        <x-ui.export-button target="exportTabExcel('clients')" label="Export Project Mappings" class="justify-center" />
                    </div>
                </div>
                <form wire:submit="saveProjectMapping" class="flex flex-col sm:flex-row sm:items-end gap-3">
                    <div class="flex-1 min-w-0"><label for="mapping-project-name" class="block text-xs mb-2">Project name</label><input id="mapping-project-name" wire:model="mappingProjectName" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs" required /></div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveProjectMapping" class="h-9 shrink-0 rounded-lg bg-ink text-white px-5 text-xs font-semibold disabled:opacity-50">Save Project Mapping</button>
                </form>
                @error('mappingProjectName')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="bg-surface rounded-2xl border border-border overflow-x-auto">
                <table class="w-full text-left text-xs"><thead class="bg-canvas text-muted"><tr><th class="p-4">Project</th><th class="p-4">Mapped Client</th><th class="p-4">Status</th><th class="p-4">Lead Cost</th><th class="p-4 text-right">Actions</th></tr></thead><tbody class="divide-y divide-border">
                    @forelse($projects as $project)
                        <tr>
                            <td class="p-4 font-bold">
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="openEditProjectModal({{ $project->id }})" class="font-bold text-ink hover:text-primary transition-colors cursor-pointer text-left inline-flex items-center gap-1.5 group" title="Click to edit project">
                                        <span class="group-hover:underline underline-offset-2">{{ $project->name }}</span>
                                        <svg class="w-3.5 h-3.5 text-muted group-hover:text-primary transition-colors opacity-60 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    @if(isset($project->leads_count) && $project->leads_count > 0)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-primary/10 text-primary">
                                            {{ $project->leads_count }} {{ Str::plural('lead', $project->leads_count) }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-4">{{ $project->client_name }}</td>
                            <td class="p-4">{{ ucfirst($project->status) }}</td>
                            <td class="p-4">{{ number_format($project->lead_cost ?? $organization->lead_cost ?? $globalDefaultLeadCost, 0) }} Credits</td>
                            <td class="p-4 text-right">
                                <div class="inline-flex items-center gap-2 justify-end">
                                    <button type="button" wire:click="openEditProjectModal({{ $project->id }})" title="Edit Project" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-border bg-surface hover:bg-canvas text-ink transition cursor-pointer text-xs font-semibold">
                                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                        <span>Edit</span>
                                    </button>
                                    <button type="button" wire:click="openRemoveProjectModal({{ $project->id }})" title="Remove Project" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-red-200 dark:border-red-900/40 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 transition cursor-pointer text-xs font-semibold">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        <span>Remove</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center text-muted">No projects mapped to this organization.</td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </div>
    @endif
    <!-- TAB 4: CREDITS & WALLET -->
    @if($activeTab === 'credits')
        <livewire:credits.reconciliation :organization-id="$organization->id" :key="'credits-reconcile-'.$organization->id" />
        <div class="space-y-6">
            <!-- Credits Header & KPI Strip (5-Card Strip with Opening, Recharged, Consumed, Refunded, and Available Lead Creditss) -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                <!-- 1. Opening Credits -->
                <div class="p-4 rounded-2xl bg-surface border border-border shadow-2xs space-y-1">
                    <div class="flex items-center justify-between text-muted text-[11px] font-semibold uppercase tracking-wider">
                        <span>Opening Credits</span>
                        <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div class="text-2xl font-bold font-mono text-ink">{{ number_format($totalOpeningBalance, 0) }}</div>
                    <div class="text-[10px] text-muted flex items-center justify-between">
                        <span>Baseline allocation</span>
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-sky-100 text-sky-900 border border-sky-200">Opening</span>
                    </div>
                </div>

                <!-- 2. Total Recharged -->
                <div class="p-4 rounded-2xl bg-surface border border-border shadow-2xs space-y-1">
                    <div class="flex items-center justify-between text-muted text-[11px] font-semibold uppercase tracking-wider">
                        <span>Total Recharged</span>
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                        </svg>
                    </div>
                    <div class="text-2xl font-bold font-mono text-emerald-600">
                        {{ number_format($totalCreditsRecharged, 0) }}
                    </div>
                    <div class="text-[10px] text-muted">Total purchased & deposited</div>
                </div>

                <!-- 3. Total Consumed -->
                <div class="p-4 rounded-2xl bg-surface border border-border shadow-2xs space-y-1">
                    <div class="flex items-center justify-between text-muted text-[11px] font-semibold uppercase tracking-wider">
                        <span>Total Consumed</span>
                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                        </svg>
                    </div>
                    <div class="text-2xl font-bold font-mono text-ink">{{ number_format($totalCreditsDeducted, 0) }}</div>
                    <div class="text-[10px] text-muted">Deducted for lead delivery</div>
                </div>

                <!-- 4. Total Refunded -->
                <div class="p-4 rounded-2xl bg-surface border border-border shadow-2xs space-y-1">
                    <div class="flex items-center justify-between text-muted text-[11px] font-semibold uppercase tracking-wider">
                        <span>Total Refunded</span>
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </div>
                    <div class="text-2xl font-bold font-mono text-blue-600">{{ number_format($totalCreditsRefunded, 0) }}</div>
                    <div class="text-[10px] text-muted">Returned for claims/SLA</div>
                </div>

                <!-- 5. Available Lead Credits -->
                <div class="p-4 rounded-2xl bg-surface border border-border shadow-2xs space-y-1 col-span-2 md:col-span-1">
                    <div class="flex items-center justify-between text-muted text-[11px] font-semibold uppercase tracking-wider">
                        <span>Available Lead Credits</span>
                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="text-2xl font-bold font-mono text-primary">{{ number_format($totalWalletBalance, 0) }}</div>
                    <div class="text-[10px] text-muted flex items-center justify-between">
                        <span>Live spendable</span>
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold {{ $totalWalletBalance >= 50 ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : 'bg-red-100 text-red-900 border border-red-300' }}">
                            {{ $totalWalletBalance >= 50 ? 'Sufficient' : 'Low Credits' }}
                        </span>
                    </div>
                </div>
            </div>

            <p class="text-xs text-muted rounded-lg border border-border p-3">Total: {{ number_format($totalLeadCredits) }} Credits / Used: {{ number_format($usedLeadCredits) }} Credits / Remaining: {{ number_format($totalWalletBalance) }} Credits. Reconciliations and corrections are recorded in the ledger.</p>

            <section class="bg-surface rounded-2xl border border-border p-5 space-y-4">
                <h3 class="text-sm font-bold text-ink">Project-wise Lead Pricing</h3>
                <p class="text-xs text-muted">Set a project override or leave it blank to inherit organization pricing. Team pricing, when configured for delivery, follows the existing pricing hierarchy. Wallets belong to billing clients.</p>
                @forelse($projects as $project)
                    <form wire:submit="saveProjectRate({{ $project->id }})" wire:key="project-rate-{{ $project->id }}" class="flex flex-col sm:flex-row sm:items-center gap-3 border-t border-border pt-3">
                        <div class="flex-1"><strong class="text-xs">{{ $project->name }}</strong><p class="text-xs text-muted">{{ $project->client_name }} · {{ $project->lead_cost !== null ? 'Project override' : 'Inherited organization/global rate' }}: {{ number_format($project->lead_cost ?? $organization->lead_cost ?? $globalDefaultLeadCost, 0) }} Credits</p></div>
                        <div><input type="number" step="1" min="1" wire:model="projectRates.{{ $project->id }}" aria-label="Lead cost for {{ $project->name }}" placeholder="Inherit organization rate" class="w-full sm:w-48 rounded-lg border border-border bg-canvas p-2 text-xs" />@error('projectRates.'.$project->id)<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        <button type="submit" class="rounded-lg bg-ink text-white px-4 py-2 text-xs font-semibold">Save Rate</button>
                    </form>
                @empty
                    <p class="text-xs text-muted">Map projects in the Projects tab to configure project pricing.</p>
                @endforelse
            </section>
            <!-- Controls bar for Credits Ledger -->
            <div
                class="p-4 rounded-2xl bg-surface border border-border shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs text-muted font-semibold">Filter Ledger:</span>
                    <div class="inline-flex p-0.5 rounded-lg bg-canvas border border-border text-xs flex-wrap">
                        <button type="button" wire:click="$set('creditFilterType', 'all')"
                            class="px-2.5 py-1 rounded-md font-semibold transition cursor-pointer {{ $creditFilterType === 'all' ? 'bg-surface text-ink shadow-2xs font-bold' : 'text-muted hover:text-ink' }}">
                            All
                        </button>
                        <button type="button" wire:click="$set('creditFilterType', 'opening_credit')"
                            class="px-2.5 py-1 rounded-md font-semibold transition cursor-pointer {{ $creditFilterType === 'opening_credit' ? 'bg-surface text-ink shadow-2xs font-bold' : 'text-muted hover:text-ink' }}">
                            Opening
                        </button>
                        <button type="button" wire:click="$set('creditFilterType', 'recharge')"
                            class="px-2.5 py-1 rounded-md font-semibold transition cursor-pointer {{ $creditFilterType === 'recharge' ? 'bg-surface text-ink shadow-2xs font-bold' : 'text-muted hover:text-ink' }}">
                            Recharges
                        </button>
                        <button type="button" wire:click="$set('creditFilterType', 'consumption')"
                            class="px-2.5 py-1 rounded-md font-semibold transition cursor-pointer {{ $creditFilterType === 'consumption' ? 'bg-surface text-ink shadow-2xs font-bold' : 'text-muted hover:text-ink' }}">
                            Consumption
                        </button>
                        <button type="button" wire:click="$set('creditFilterType', 'refund')"
                            class="px-2.5 py-1 rounded-md font-semibold transition cursor-pointer {{ $creditFilterType === 'refund' ? 'bg-surface text-ink shadow-2xs font-bold' : 'text-muted hover:text-ink' }}">
                            Refunds
                        </button>
                        <button type="button" wire:click="$set('creditFilterType', 'manual_adjustment')"
                            class="px-2.5 py-1 rounded-md font-semibold transition cursor-pointer {{ $creditFilterType === 'manual_adjustment' ? 'bg-surface text-ink shadow-2xs font-bold' : 'text-muted hover:text-ink' }}">
                            Adjustments
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" wire:click="exportTabExcel('credits')"
                        class="px-3 py-2 border border-border bg-surface hover:bg-canvas text-ink text-xs font-semibold rounded-lg transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer"
                        title="Export Credits History to Excel">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>Export Ledger</span>
                    </button>

                    <button type="button" wire:click="openCreditModal()"
                        class="px-3.5 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span>Adjust / Recharge</span>
                    </button>
                </div>
            </div>

            <!-- Complete Credit Transaction Ledger Table -->
            <div class="bg-surface rounded-2xl border border-border shadow-2xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="bg-canvas/60 text-muted uppercase tracking-wider text-[10px] font-bold border-b border-border">
                            <tr>
                                <th class="py-3 px-4">Tx ID</th>
                                <th class="py-3 px-4">Client</th>
                                <th class="py-3 px-4">Type</th>
                                <th class="py-3 px-4">Reference Lead</th>
                                <th class="py-3 px-4">Description / Note</th>
                                <th class="py-3 px-4">Credit Before</th>
                                <th class="py-3 px-4">Amount In / Out</th>
                                <th class="py-3 px-4">Balance After</th>
                                <th class="py-3 px-4">Created By</th>
                                <th class="py-3 px-4 text-right">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($creditTransactions as $tx)
                                <tr class="hover:bg-canvas/30 transition">
                                    <td class="py-3 px-4 font-mono text-[11px] text-muted">#{{ $tx->id }}</td>
                                    <td class="py-3 px-4 font-bold text-ink">{{ $tx->client?->name ?: '—' }}</td>
                                    <td class="py-3 px-4">
                                        @php
                                            $typeBadge = match ($tx->transaction_type) {
                                                'recharge' => 'bg-emerald-100 text-emerald-950 border-emerald-300',
                                                'opening_credit' => 'bg-sky-100 text-sky-950 border-sky-300',
                                                'refund' => 'bg-blue-100 text-blue-950 border-blue-300',
                                                'reserve', 'lead_consumption' => 'bg-amber-100 text-amber-950 border-amber-300',
                                                'deduct' => 'bg-purple-100 text-purple-950 border-purple-300',
                                                'manual_adjustment' => 'bg-indigo-100 text-indigo-950 border-indigo-300',
                                                default => 'bg-slate-100 text-slate-900 border-slate-300',
                                            };
                                        @endphp
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase border {{ $typeBadge }}">
                                            {{ $tx->transaction_type_label }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-muted text-[11px]">
                                        @if($tx->lead)
                                            <a href="{{ route('leads.index') }}" class="font-bold text-primary hover:underline">{{ $tx->lead->lead_code }}</a>
                                        @else
                                            <span class="text-muted">Direct</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-muted max-w-xs truncate" title="{{ $tx->description }}">
                                        {{ $tx->description ?: '—' }}
                                    </td>
                                    <td class="py-3 px-4 font-mono text-muted text-[11px]">
                                        {{ ($tx->credit_before === null ? 'Legacy entry' : number_format($tx->credit_before)) }}
                                    </td>
                                    <td class="py-3 px-4 font-mono text-xs font-bold {{ in_array($tx->transaction_type, ['recharge', 'refund', 'opening_credit']) || ($tx->transaction_type === 'manual_adjustment' && $tx->credit_after >= $tx->credit_before) ? 'text-emerald-600' : 'text-red-500' }}">
                                        {{ $tx->credit_used_formatted }}
                                    </td>
                                    <td class="py-3 px-4 font-mono text-xs font-bold text-primary">
                                        {{ ($tx->credit_after === null ? 'Legacy entry' : number_format($tx->credit_after)) }}
                                    </td>
                                    <td class="py-3 px-4 text-muted text-[11px]">
                                        {{ $tx->createdBy?->name ?: 'System Admin' }}
                                    </td>
                                    <td class="py-3 px-4 font-mono text-muted text-[11px] text-right">
                                        {{ $tx->created_at ? \Carbon\Carbon::parse($tx->created_at)->format('M d, Y • h:i A') : '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="p-8 text-center text-xs text-muted">
                                        No credit transactions recorded yet for this organization.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($creditTransactions->hasPages())
                    <div class="p-3 border-t border-border bg-canvas/30">
                        {{ $creditTransactions->links() }}
                    </div>
                @endif
            </div>

        </div>
    @endif

    <!-- TAB 6: ACTIVITY & AUDIT -->
    @if($activeTab === 'activity')
        <div class="space-y-4">
            <div class="p-4 rounded-2xl bg-surface border border-border shadow-2xs flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-ink">Clients Audit Trail</h3>
                    <p class="text-xs text-muted">Recent updates and actions recorded for this clients or its assigned
                        members.</p>
                </div>
                <button type="button" wire:click="exportTabExcel('activity')"
                    class="px-3 py-2 border border-border bg-surface hover:bg-canvas text-ink text-xs font-semibold rounded-lg transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer"
                    title="Export Audit Logs to Excel">
                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span>Export Tab</span>
                </button>
            </div>

            <x-ui.advanced-table
                :columns="$this->auditTableColumns()"
                :rows="$auditLogs"
                :showSearch="false"
                :showFilterDropdown="false"
                :showConfigurations="false"
                tableName="organization-audit"
                emptyTitle="No Audit Events"
                emptyMessage="No audit events recorded for this organization."
            />
        </div>
    @endif

    <!-- MODAL 1: ADD MEMBER TO THIS ORGANIZATION -->
    <livewire:admin.organization-member-details :organization-id="$organization->id" :key="'org-member-details-'.$organization->id" />
    <x-modal name="add-org-member-modal" :show="$showAddMemberModal" maxWidth="xl">
        <div class="p-5 sm:p-6 space-y-5">
            <div class="flex items-center justify-between pb-3.5 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-primary/10 text-primary border border-primary/20">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Add Team Member</h3>
                        <p class="text-xs text-muted">Assign directly to <strong
                                class="text-ink font-semibold">{{ $organization->name }}</strong></p>
                    </div>
                </div>
                <button type="button" wire:click="closeAddMemberModal"
                    class="text-muted hover:text-ink cursor-pointer p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="createUserForOrganization" class="space-y-4 text-xs">
                <div>
                    <label class="font-bold text-ink">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="newUserName" placeholder="e.g. Rahul Sharma"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                    @error('newUserName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="font-bold text-ink">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" wire:model="newUserEmail" placeholder="e.g. rahul@company.com"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                    @error('newUserEmail') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="font-bold text-ink">Assign Role <span class="text-red-500">*</span></label>
                        <x-ui.themed-select wire:model="newUserRole" :options="$this->availableRoles"
                            placeholder="Select Role" class="w-full mt-1" />
                        @error('newUserRole') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="font-bold text-ink">Password <span
                                class="text-muted font-normal">(Optional)</span></label>
                        <input type="text" wire:model="newUserPassword" placeholder="Auto-generated if left blank"
                            class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                    <button type="button" wire:click="closeAddMemberModal"
                        class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 transition shadow-xs cursor-pointer flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span>Create Member</span>
                    </button>
                </div>
            </form>
        </div>
    </x-modal>

    <!-- MODAL 2: REGISTER CLIENT -->
    <x-modal name="create-org-client-modal" :show="$showCreateClientModal" maxWidth="md">
        <div class="p-5 sm:p-6 space-y-5">
            <div class="flex items-center justify-between pb-3.5 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Register Client</h3>
                        <p class="text-xs text-muted">Register a new client under <strong
                                class="text-ink">{{ $organization->name }}</strong></p>
                    </div>
                </div>
                <button type="button" wire:click="closeCreateClientModal"
                    class="text-muted hover:text-ink cursor-pointer p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="createClientForOrganization" class="space-y-4 text-xs">
                <div>
                    <label class="font-bold text-ink">Client Name <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="newClientName" placeholder="e.g. Prestige Estates Projects"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                    @error('newClientName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="font-bold text-ink">Billing Email <span
                            class="text-muted font-normal">(Optional)</span></label>
                    <input type="email" wire:model="newClientBillingEmail" placeholder="e.g. accounts@prestige.com"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                    @error('newClientBillingEmail') <span
                    class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="font-bold text-ink">Status</label>
                    <x-ui.themed-select wire:model="newClientStatus" :options="['active' => 'Active', 'suspended' => 'Suspended']" placeholder="Select Status" class="w-full mt-1" />
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                    <button type="button" wire:click="closeCreateClientModal"
                        class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 transition shadow-xs cursor-pointer flex items-center gap-1.5">
                        <span>Create Client</span>
                    </button>
                </div>
            </form>
        </div>
    </x-modal>

    <!-- MODAL 3: ADD DIRECTLY CREATED & ASSIGNED LEAD (NEW) -->
    <x-modal name="create-org-lead-modal" :show="$showCreateLeadModal" maxWidth="xl">
        <div class="p-5 sm:p-6 space-y-5">
            <div class="flex items-center justify-between pb-3.5 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/30">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Add Directly Assigned Lead</h3>
                        <p class="text-xs text-muted">Create lead under <strong
                                class="text-ink">{{ $organization->name }}</strong> and assign directly to an agent</p>
                    </div>
                </div>
                <button type="button" wire:click="closeCreateLeadModal"
                    class="text-muted hover:text-ink cursor-pointer p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="createDirectlyAssignedLead" class="space-y-4 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="font-bold text-ink">Lead Name <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="newLeadName" placeholder="e.g. Ramesh Patel"
                            class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                        @error('newLeadName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="font-bold text-ink">Mobile / Phone <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="newLeadMobile" placeholder="e.g. +91 9876543210"
                            class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                        @error('newLeadMobile') <span
                        class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="font-bold text-ink">Email Address <span
                                class="text-muted font-normal">(Optional)</span></label>
                        <input type="email" wire:model="newLeadEmail" placeholder="e.g. ramesh@example.com"
                            class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                        @error('newLeadEmail') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="font-bold text-ink">Directly Assign To Agent</label>
                        <x-ui.themed-select class="w-full" searchable  wire:model="newLeadAssignedTo"
                            trigger-class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1 cursor-pointer">
                            <option value="">Unassigned Pool</option>
                            @foreach($teamMembers as $tm)
                                <option value="{{ $tm->id }}">{{ $tm->name }} ({{ $tm->roles->first()?->name ?: 'Member' }})
                                </option>
                            @endforeach
                        </x-ui.themed-select>
                        @error('newLeadAssignedTo') <span
                        class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="font-bold text-ink">Target Client <span class="text-red-500">*</span></label>
                        <x-ui.themed-select class="w-full" searchable  wire:model.live="newLeadClientId"
                            trigger-class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1 cursor-pointer">
                            <option value="">Select Client</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} (Bal:
                                    {{ number_format($c->wallet?->balance ?? 0) }})
                                </option>
                            @endforeach
                        </x-ui.themed-select>
                        @error('newLeadClientId') <span
                        class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="font-bold text-ink">Associated Project</label>
                        <x-ui.themed-select class="w-full" searchable  wire:model="newLeadProjectId"
                            trigger-class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1 cursor-pointer">
                            <option value="">Auto-select or General Project</option>
                            @foreach($clientProjects as $proj)
                                <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                            @endforeach
                        </x-ui.themed-select>
                    </div>
                </div>

                <div>
                    <label class="font-bold text-ink">Requirement Notes</label>
                    <textarea wire:model="newLeadRequirement" rows="2"
                        placeholder="e.g. Looking for 3 BHK in prime location, budget 1.5 Cr"
                        class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                    <button type="button" wire:click="closeCreateLeadModal"
                        class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 transition shadow-xs cursor-pointer flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span>Create & Direct Assign</span>
                    </button>
                </div>
            </form>
        </div>
    </x-modal>

    <!-- MODAL 4: RECHARGE / ADJUST CREDITS (NEW) -->
    <x-modal name="adjust-org-credits-modal" :show="$showCreditModal" maxWidth="md">
        <div class="p-5 sm:p-6 space-y-5">
            <div class="flex items-center justify-between pb-3.5 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-900/30">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Recharge / Adjust Credits</h3>
                        <p class="text-xs text-muted">Allocate or deduct credits for clients under <strong
                                class="text-ink">{{ $organization->name }}</strong></p>
                    </div>
                </div>
                <button type="button" wire:click="closeCreditModal"
                    class="text-muted hover:text-ink cursor-pointer p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="executeCreditAdjustment" class="space-y-4 text-xs">
                <div>
                    <label class="font-bold text-ink">Select Target Client <span class="text-red-500">*</span></label>
                    <x-ui.themed-select class="w-full" searchable  wire:model="selectedCreditClientId"
                        trigger-class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1 cursor-pointer">
                        <option value="">Select Client</option>
                        @foreach($clients as $cl)
                            <option value="{{ $cl->id }}">
                                {{ $cl->name }} (Current Balance: {{ number_format($cl->wallet?->balance ?? 0) }})
                            </option>
                        @endforeach
                    </x-ui.themed-select>
                    @error('selectedCreditClientId') <span
                    class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3.5">
                    <div>
                        <label class="font-bold text-ink">Action Type <span class="text-red-500">*</span></label>
                        <x-ui.themed-select class="w-full" searchable  wire:model="creditAdjustmentType"
                            trigger-class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1 cursor-pointer">
                            <option value="recharge">+ Recharge Credits</option>
                            <option value="opening_credit">+ Opening Credit (Baseline)</option>
                            <option value="refund">+ Refund Credits (Replacement / SLA)</option>
                            <option value="manual_adjustment">+ Manual Adjustment</option>
                            <option value="deduct">- Deduct / Debit Credits</option>
                        </x-ui.themed-select>
                    </div>

                    <div>
                        <label class="font-bold text-ink">Credit Amount <span class="text-red-500">*</span></label>
                        <input type="number" wire:model="creditAmount" step="1" min="1"
                            class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                        @error('creditAmount') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="font-bold text-ink">Reason / Reference <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="creditReason"
                        placeholder="e.g. Bank Transfer Ref #12345 or Monthly quota"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                    @error('creditReason') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                    <button type="button" wire:click="closeCreditModal"
                        class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 transition shadow-xs cursor-pointer flex items-center gap-1.5">
                        <span>Apply Credit Adjustment</span>
                    </button>
                </div>
            </form>
        </div>
    </x-modal>

    <!-- MODAL 4b: EDIT PROJECT -->
    <x-modal name="edit-org-project-modal" wire:model="showEditProjectModal" :show="$showEditProjectModal" maxWidth="md">
        <div class="p-5 sm:p-6 space-y-5">
            <div class="flex items-center justify-between pb-3.5 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-primary/10 text-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Edit Project</h3>
                        <p class="text-xs text-muted">Update project details under <strong class="text-ink">{{ $organization->name }}</strong></p>
                    </div>
                </div>
                <button type="button" wire:click="closeEditProjectModal"
                    class="text-muted hover:text-ink cursor-pointer p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="updateProject" class="space-y-4 text-xs">
                <div>
                    <label class="font-bold text-ink">Project Name <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="editingProjectName" placeholder="e.g. Boulevard walk by H&S"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                    @error('editingProjectName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="font-bold text-ink">Mapped Client / Billing Owner <span class="text-red-500">*</span></label>
                    <select wire:model="editingProjectClientId"
                        class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                        @forelse($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @empty
                            <option value="">No billing clients available</option>
                        @endforelse
                    </select>
                    @error('editingProjectClientId') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-ink">Status</label>
                        <select wire:model="editingProjectStatus"
                            class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                        @error('editingProjectStatus') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="font-bold text-ink">Lead Cost (Credits)</label>
                        <input type="number" min="1" step="1" wire:model="editingProjectLeadCost" placeholder="Inherit ({{ number_format($organization->lead_cost ?? $globalDefaultLeadCost, 0) }})"
                            class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                        @error('editingProjectLeadCost') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                    <button type="button" wire:click="closeEditProjectModal"
                        class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 transition shadow-xs cursor-pointer flex items-center gap-1.5">
                        <span wire:loading.remove wire:target="updateProject">Save Changes</span>
                        <span wire:loading wire:target="updateProject">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </x-modal>

    <!-- MODAL 5: REMOVE PROJECT & REASSIGN LEADS -->
    <x-modal name="remove-org-project-modal" wire:model="showRemoveProjectModal" :show="$showRemoveProjectModal" maxWidth="md">
        <div class="p-5 sm:p-6 space-y-5">
            <div class="flex items-center justify-between pb-3.5 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-red-50 text-red-600 dark:bg-red-900/30">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Remove Project</h3>
                        <p class="text-xs text-muted">Project: <strong class="text-ink">{{ $projectToRemoveName }}</strong></p>
                    </div>
                </div>
                <button type="button" wire:click="closeRemoveProjectModal"
                    class="text-muted hover:text-ink cursor-pointer p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            @if($projectAssignedLeadsCount > 0)
                <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/40 space-y-2">
                    <div class="flex items-center gap-2 text-amber-800 dark:text-amber-200 font-bold text-xs">
                        <svg class="w-4 h-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>{{ $projectAssignedLeadsCount }} Lead(s) Assigned</span>
                    </div>
                    <p class="text-xs text-amber-700 dark:text-amber-300">
                        This project currently has <strong class="font-bold">{{ $projectAssignedLeadsCount }} assigned lead(s)</strong>.
                        Please select which project these leads should be moved to before removing this project.
                    </p>
                </div>

                @if($this->otherProjects->isNotEmpty())
                    <div class="space-y-1.5">
                        <label class="font-bold text-ink text-xs">
                            Move Leads to Project <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="destinationProjectId"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition">
                            <option value="">-- Select Destination Project --</option>
                            @foreach($this->otherProjects as $otherP)
                                <option value="{{ $otherP->id }}">{{ $otherP->name }}</option>
                            @endforeach
                        </select>
                        @error('destinationProjectId')
                            <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                        @enderror
                    </div>
                @else
                    <div class="p-3 rounded-lg bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/40 text-xs text-red-700 dark:text-red-300">
                        There are no other projects mapped to this organization. Please create or map another project first so the assigned leads can be moved.
                    </div>
                @endif
            @else
                <div class="p-4 rounded-xl bg-canvas border border-border text-xs text-muted space-y-1">
                    <p>Are you sure you want to remove <strong class="text-ink">{{ $projectToRemoveName }}</strong>?</p>
                    <p class="text-[11px] text-muted">This project has <strong class="text-ink">0 leads</strong> assigned. Once removed, it will be unmapped from this organization.</p>
                </div>
            @endif

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                <button type="button" wire:click="closeRemoveProjectModal"
                    class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer">
                    Cancel
                </button>
                <button type="button" wire:click="confirmRemoveProject" wire:loading.attr="disabled"
                    @if($projectAssignedLeadsCount > 0 && $this->otherProjects->isEmpty()) disabled @endif
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5">
                    <span wire:loading.remove wire:target="confirmRemoveProject">
                        {{ $projectAssignedLeadsCount > 0 ? 'Move Leads & Remove Project' : 'Remove Project' }}
                    </span>
                    <span wire:loading wire:target="confirmRemoveProject">Removing...</span>
                </button>
            </div>
        </div>
    </x-modal>

    <!-- Lead Details Display Configuration Modal -->
    <livewire:leads.lead-display-config-modal />
</div>
