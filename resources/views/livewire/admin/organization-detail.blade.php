<div class="space-y-6">
    <!-- Top Breadcrumbs & Back Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1.5">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-ink transition" wire:navigate>System Administration</a>
                <span>/</span>
                <a href="{{ route('admin.organizations') }}" class="hover:text-ink transition" wire:navigate>Organizations</a>
                <span>/</span>
                <span class="text-ink font-semibold truncate">{{ $organization->name }}</span>
            </div>

            <div class="flex items-center gap-3">
                <a 
                    href="{{ route('admin.organizations') }}" 
                    wire:navigate 
                    class="p-2 rounded-xl bg-surface border border-border hover:bg-canvas text-muted hover:text-ink transition shadow-2xs inline-flex items-center justify-center shrink-0"
                    title="Back to Organizations Directory"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>

                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                        <span>{{ $organization->name }}</span>
                        @if($organization->status === 'active')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Active
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                Suspended
                            </span>
                        @endif
                    </h1>
                    <div class="flex items-center gap-2 text-xs text-muted mt-0.5">
                        <span class="font-mono text-[11px] font-bold text-ink">#ORG-{{ str_pad($organization->id, 4, '0', STR_PAD_LEFT) }}</span>
                        <span>•</span>
                        <span>Registered {{ $organization->created_at ? $organization->created_at->format('M d, Y') : '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header Actions -->
        <div class="flex items-center gap-2.5 shrink-0">
            <button 
                type="button" 
                wire:click="toggleOrganizationStatus" 
                wire:confirm="Are you sure you want to change this organization's status?"
                class="px-3.5 py-2 rounded-xl border border-border bg-surface hover:bg-canvas text-ink text-xs font-semibold transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer"
            >
                @if($organization->status === 'active')
                    <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Suspend Org</span>
                @else
                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Activate Org</span>
                @endif
            </button>

            @if(!$isEditing)
                <button 
                    type="button" 
                    wire:click="startEditing" 
                    class="px-3.5 py-2 rounded-xl border border-border bg-surface hover:bg-canvas text-ink text-xs font-semibold transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                    <span>Edit Profile</span>
                </button>
            @endif

            <button 
                type="button" 
                wire:click="openAddMemberModal" 
                class="px-3.5 py-2 rounded-xl bg-ink text-white hover:bg-neutral-800 text-xs font-semibold transition shadow-xs inline-flex items-center gap-1.5 cursor-pointer"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </span>
                    <h3 class="text-sm font-bold text-ink">Edit Organization Details</h3>
                </div>
                <button type="button" wire:click="cancelEditing" class="text-xs text-muted hover:text-ink cursor-pointer">
                    Cancel
                </button>
            </div>

            <form wire:submit.prevent="updateOrganization" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <label class="font-bold text-ink">Organization Name <span class="text-red-500">*</span></label>
                    <input 
                        type="text" 
                        wire:model="editName" 
                        class="w-full h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1"
                        placeholder="Enter organization name"
                    >
                    @error('editName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="font-bold text-ink">Status <span class="text-red-500">*</span></label>
                    <x-ui.themed-select 
                        wire:model="editStatus" 
                        :options="['active' => 'Active', 'suspended' => 'Suspended']"
                        placeholder="Select Status" 
                        class="w-full mt-1" 
                    />
                    @error('editStatus') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div class="sm:col-span-2 flex items-center justify-end gap-2 pt-2">
                    <button 
                        type="button" 
                        wire:click="cancelEditing" 
                        class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        class="px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary/90 transition shadow-xs cursor-pointer flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- KPI Metric Cards Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5">
        <!-- Card 1: Users Count -->
        <div class="p-4 rounded-2xl bg-surface border border-border shadow-2xs space-y-1">
            <div class="flex items-center justify-between text-muted text-[11px] font-semibold uppercase tracking-wider">
                <span>Team Members</span>
                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
            <div class="text-2xl font-bold font-mono text-ink">{{ $totalUsersCount }}</div>
            <div class="text-[10px] text-muted">Across all assigned roles</div>
        </div>

        <!-- Card 2: Clients Count -->
        <div class="p-4 rounded-2xl bg-surface border border-border shadow-2xs space-y-1">
            <div class="flex items-center justify-between text-muted text-[11px] font-semibold uppercase tracking-wider">
                <span>Clients</span>
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <div class="text-2xl font-bold font-mono text-ink">{{ $totalClientsCount }}</div>
            <div class="text-[10px] text-muted">Direct registered clients</div>
        </div>

        <!-- Card 3: Projects Count -->
        <div class="p-4 rounded-2xl bg-surface border border-border shadow-2xs space-y-1">
            <div class="flex items-center justify-between text-muted text-[11px] font-semibold uppercase tracking-wider">
                <span>Projects</span>
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
            </div>
            <div class="text-2xl font-bold font-mono text-ink">{{ $totalProjectsCount }}</div>
            <div class="text-[10px] text-muted">Active real estate projects</div>
        </div>

        <!-- Card 4: Leads Count -->
        <div class="p-4 rounded-2xl bg-surface border border-border shadow-2xs space-y-1">
            <div class="flex items-center justify-between text-muted text-[11px] font-semibold uppercase tracking-wider">
                <span>Total Leads</span>
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div class="text-2xl font-bold font-mono text-ink">{{ number_format($totalLeadsCount) }}</div>
            <div class="text-[10px] text-muted">Managed across clients</div>
        </div>

        <!-- Card 5: Wallet Balance -->
        <div class="p-4 rounded-2xl bg-surface border border-border shadow-2xs space-y-1 col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between text-muted text-[11px] font-semibold uppercase tracking-wider">
                <span>Credit Balance</span>
                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
            </div>
            <div class="text-2xl font-bold font-mono text-ink">{{ number_format($totalWalletBalance) }}</div>
            <div class="text-[10px] text-muted">Total available credits</div>
        </div>
    </div>

    <!-- Tabbed Navigation Bar -->
    <div class="border-b border-border">
        <nav class="flex space-x-2 sm:space-x-6 -mb-px overflow-x-auto text-xs font-semibold">
            <!-- Tab 1: Overview -->
            <button 
                type="button" 
                wire:click="setTab('overview')"
                class="py-3 px-2 border-b-2 transition flex items-center gap-2 cursor-pointer whitespace-nowrap {{ $activeTab === 'overview' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink hover:border-border' }}"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
                <span>Overview & Profile</span>
            </button>

            <!-- Tab 2: Team Members -->
            <button 
                type="button" 
                wire:click="setTab('team')"
                class="py-3 px-2 border-b-2 transition flex items-center gap-2 cursor-pointer whitespace-nowrap {{ $activeTab === 'team' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink hover:border-border' }}"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <span>Team Members</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-canvas border border-border {{ $activeTab === 'team' ? 'text-primary' : 'text-muted' }}">
                    {{ $totalUsersCount }}
                </span>
            </button>

            <!-- Tab 3: Clients & Projects -->
            <button 
                type="button" 
                wire:click="setTab('clients')"
                class="py-3 px-2 border-b-2 transition flex items-center gap-2 cursor-pointer whitespace-nowrap {{ $activeTab === 'clients' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink hover:border-border' }}"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span>Clients & Projects</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-canvas border border-border {{ $activeTab === 'clients' ? 'text-primary' : 'text-muted' }}">
                    {{ $totalClientsCount }}
                </span>
            </button>

            <!-- Tab 4: Activity Log -->
            <button 
                type="button" 
                wire:click="setTab('activity')"
                class="py-3 px-2 border-b-2 transition flex items-center gap-2 cursor-pointer whitespace-nowrap {{ $activeTab === 'activity' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink hover:border-border' }}"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Activity & Audit</span>
            </button>
        </nav>
    </div>

    <!-- TAB 1: OVERVIEW -->
    @if($activeTab === 'overview')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Organization Summary Info -->
            <div class="lg:col-span-2 space-y-6">
                <div class="p-5 sm:p-6 rounded-2xl bg-surface border border-border shadow-2xs space-y-5">
                    <div class="flex items-center justify-between pb-3.5 border-b border-border">
                        <h3 class="text-sm font-bold text-ink flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-primary/10 text-primary">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </span>
                            <span>Organization Profile Details</span>
                        </h3>
                        <span class="text-xs text-muted">ID: <strong class="text-ink font-mono">#{{ $organization->id }}</strong></span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Legal / Business Name</span>
                            <div class="text-sm font-bold text-ink">{{ $organization->name }}</div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Current System Status</span>
                            <div>
                                @if($organization->status === 'active')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
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
                        <button 
                            type="button" 
                            wire:click="startEditing" 
                            class="px-3 py-1.5 bg-surface border border-border text-xs font-semibold text-ink rounded-lg hover:bg-canvas transition shadow-2xs cursor-pointer inline-flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                            <span>Edit Organization</span>
                        </button>
                    </div>
                </div>

                <!-- Recent Members Quick Snapshot -->
                <div class="p-5 sm:p-6 rounded-2xl bg-surface border border-border shadow-2xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-ink flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-900/30">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </span>
                            <span>Key Team Members</span>
                        </h3>
                        <button 
                            type="button" 
                            wire:click="setTab('team')" 
                            class="text-xs font-semibold text-primary hover:underline cursor-pointer inline-flex items-center gap-1"
                        >
                            <span>View All ({{ $totalUsersCount }})</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @forelse($teamMembers->take(4) as $member)
                            <div class="p-3 rounded-xl bg-canvas/40 border border-border flex items-center justify-between gap-3 shadow-2xs">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 border border-border text-ink font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($member->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold text-ink truncate">{{ $member->name }}</div>
                                        <div class="text-[11px] text-muted font-mono truncate">{{ $member->email }}</div>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-surface border border-border text-ink shrink-0 uppercase">
                                    {{ $member->roles->first()?->name ? ucwords(str_replace(['-', '_'], ' ', $member->roles->first()->name)) : 'Member' }}
                                </span>
                            </div>
                        @empty
                            <div class="sm:col-span-2 p-6 text-center text-xs text-muted border border-dashed border-border rounded-xl">
                                No team members assigned yet. Click "Add Member" above to create users for this organization.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Right Column: Clients & Projects Overview Sidebar -->
            <div class="space-y-6">
                <!-- Registered Clients -->
                <div class="p-5 sm:p-6 rounded-2xl bg-surface border border-border shadow-2xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-ink flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </span>
                            <span>Associated Clients</span>
                        </h3>
                        <button 
                            type="button" 
                            wire:click="openCreateClientModal" 
                            class="text-xs font-semibold text-primary hover:underline cursor-pointer"
                        >
                            + Add Client
                        </button>
                    </div>

                    <div class="space-y-2.5">
                        @forelse($clients as $c)
                            <div class="p-3 rounded-xl bg-canvas/40 border border-border flex items-center justify-between gap-3 shadow-2xs">
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-ink truncate">{{ $c->name }}</div>
                                    <div class="text-[10px] text-muted flex items-center gap-2">
                                        <span>{{ $c->projects_count }} projects</span>
                                        <span>•</span>
                                        <span class="font-mono text-ink font-semibold">{{ number_format($c->wallet?->balance ?? 0) }} credits</span>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $c->status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                    {{ $c->status }}
                                </span>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-muted border border-dashed border-border rounded-xl">
                                No clients registered under this organization yet.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Projects Quick List -->
                <div class="p-5 sm:p-6 rounded-2xl bg-surface border border-border shadow-2xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-ink flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-900/30">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                            </span>
                            <span>Active Projects</span>
                        </h3>
                        <span class="text-xs font-bold font-mono text-ink">{{ $totalProjectsCount }} Total</span>
                    </div>

                    <div class="space-y-2">
                        @forelse($projects->take(5) as $p)
                            <div class="p-2.5 rounded-xl bg-canvas/40 border border-border flex items-center justify-between gap-3 text-xs shadow-2xs">
                                <div class="min-w-0">
                                    <div class="font-bold text-ink truncate">{{ $p->name }}</div>
                                    <div class="text-[10px] text-muted truncate">{{ $p->client_name }}</div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-surface text-ink border border-border">
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
            </div>
        </div>
    @endif

    <!-- TAB 2: TEAM MEMBERS -->
    @if($activeTab === 'team')
        <div class="space-y-4">
            <!-- Team Filter & Actions Bar -->
            <div class="p-4 rounded-2xl bg-surface border border-border shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2.5 flex-1">
                    <!-- Search Input -->
                    <div class="relative flex-1 max-w-sm">
                        <svg class="w-4 h-4 text-muted absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input 
                            type="text" 
                            wire:model.live.debounce.300ms="userSearch" 
                            placeholder="Search team members by name, email..."
                            class="w-full h-8 pl-9 pr-3 text-xs bg-canvas border border-border rounded-lg text-ink focus:ring-2 focus:ring-primary/20 focus:border-primary transition"
                        >
                    </div>

                    <!-- Role Filter -->
                    <div class="w-44">
                        <select 
                            wire:model.live="userRoleFilter" 
                            class="w-full h-8 px-3 text-xs bg-canvas border border-border rounded-lg text-ink focus:ring-2 focus:ring-primary/20 focus:border-primary transition cursor-pointer"
                        >
                            <option value="all">All Roles</option>
                            @foreach($this->availableRoles as $roleKey => $roleLabel)
                                <option value="{{ $roleKey }}">{{ $roleLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button 
                        type="button" 
                        wire:click="openAddMemberModal" 
                        class="px-3.5 py-2 bg-ink text-white rounded-lg hover:bg-neutral-800 text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        <span>Add New Member</span>
                    </button>
                </div>
            </div>

            <!-- Shareable Activation Token Banner if generated -->
            @if($generatedInviteLink)
                <div class="p-4 bg-surface border border-primary/30 rounded-2xl space-y-2 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-ink flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                            <span>Activation Link Generated</span>
                        </span>
                        <button type="button" wire:click="$set('generatedInviteLink', null)" class="text-xs text-muted hover:text-ink cursor-pointer">
                            Dismiss
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <input 
                            type="text" 
                            readonly 
                            value="{{ $generatedInviteLink }}" 
                            class="w-full h-8 px-3 text-[11px] font-mono bg-canvas border border-border rounded-lg text-ink select-all"
                        >
                        <button 
                            type="button" 
                            x-data="{ copied: false }" 
                            @click="navigator.clipboard.writeText('{{ $generatedInviteLink }}'); copied = true; $wire.dispatch('toast', { type: 'success', title: 'Copied', message: 'Activation link copied to clipboard.' }); setTimeout(function() { copied = false; }, 2000)" 
                            class="px-3 h-8 bg-ink text-white rounded-lg hover:bg-neutral-800 text-xs font-bold transition shrink-0 flex items-center gap-1.5 shadow-xs cursor-pointer"
                        >
                            <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <svg x-show="copied" class="w-3.5 h-3.5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
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
                        <thead class="bg-canvas/60 text-muted uppercase tracking-wider text-[10px] font-bold border-b border-border">
                            <tr>
                                <th class="py-3 px-4">Member Name</th>
                                <th class="py-3 px-4">Email Address</th>
                                <th class="py-3 px-4">Role Badge</th>
                                <th class="py-3 px-4">Account Status</th>
                                <th class="py-3 px-4">Joined Date</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($teamMembers as $member)
                                <tr class="hover:bg-canvas/30 transition">
                                    <!-- Name & Avatar -->
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 border border-border text-ink font-bold text-xs flex items-center justify-center shrink-0">
                                                {{ strtoupper(substr($member->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="font-bold text-ink">{{ $member->name }}</div>
                                                <div class="text-[10px] text-muted font-mono">ID: #{{ $member->id }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Email -->
                                    <td class="py-3 px-4 font-mono text-muted text-[11px]">
                                        {{ $member->email }}
                                    </td>

                                    <!-- Role -->
                                    <td class="py-3 px-4">
                                        @php
                                            $roleName = $member->roles->first()?->name;
                                        @endphp
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wider {{ in_array(strtolower($roleName ?? ''), ['super admin', 'super-admin']) ? 'bg-purple-50 text-purple-700 border-purple-200' : (in_array(strtolower($roleName ?? ''), ['builder']) ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-canvas text-ink border-border') }}">
                                            {{ $roleName ? ucwords(str_replace(['-', '_'], ' ', $roleName)) : 'No Role' }}
                                        </span>
                                    </td>

                                    <!-- Status -->
                                    <td class="py-3 px-4">
                                        <button 
                                            type="button" 
                                            wire:click="toggleUserStatus({{ $member->id }})" 
                                            class="cursor-pointer"
                                            title="Click to toggle user status"
                                        >
                                            @if(($member->status ?? 'active') === 'active')
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    Active
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700 border border-red-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                                    Inactive
                                                </span>
                                            @endif
                                        </button>
                                    </td>

                                    <!-- Joined -->
                                    <td class="py-3 px-4 font-mono text-muted text-[11px]">
                                        {{ $member->created_at ? $member->created_at->format('M d, Y') : '—' }}
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-3 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <!-- Impersonate -->
                                            <a 
                                                href="{{ route('admin.users.impersonate', $member->id) }}" 
                                                class="p-1.5 rounded-lg border border-border bg-surface hover:bg-purple-50 text-purple-700 hover:border-purple-300 transition shadow-2xs inline-flex items-center justify-center cursor-pointer"
                                                title="Impersonate User"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                                </svg>
                                            </a>

                                            <!-- Activation Token -->
                                            <button 
                                                type="button" 
                                                wire:click="generateUserActivationLink({{ $member->id }})" 
                                                class="p-1.5 rounded-lg border border-border bg-surface hover:bg-blue-50 text-blue-600 hover:border-blue-300 transition shadow-2xs cursor-pointer inline-flex items-center justify-center"
                                                title="Generate Reset / Activation Link"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                                </svg>
                                            </button>

                                            <!-- Remove from Org -->
                                            <button 
                                                type="button" 
                                                wire:click="removeUserFromOrganization({{ $member->id }})" 
                                                wire:confirm="Remove {{ $member->name }} from {{ $organization->name }}?"
                                                class="p-1.5 rounded-lg border border-border bg-surface hover:bg-red-50 text-red-600 hover:border-red-300 transition shadow-2xs cursor-pointer inline-flex items-center justify-center"
                                                title="Unassign from Organization"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-xs text-muted">
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

    <!-- TAB 3: CLIENTS & PROJECTS -->
    @if($activeTab === 'clients')
        <div class="space-y-6">
            <!-- Header bar for clients -->
            <div class="p-4 rounded-2xl bg-surface border border-border shadow-2xs flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-ink">Registered Clients ({{ $totalClientsCount }})</h3>
                    <p class="text-xs text-muted">Clients managed under {{ $organization->name }} with their credit wallets & projects.</p>
                </div>
                <button 
                    type="button" 
                    wire:click="openCreateClientModal" 
                    class="px-3.5 py-2 bg-ink text-white rounded-lg hover:bg-neutral-800 text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    <span>Register Client</span>
                </button>
            </div>

            <!-- Clients Grid Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($clients as $client)
                    <div class="p-5 rounded-2xl bg-surface border border-border shadow-2xs space-y-4 hover:border-ink/20 transition">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h4 class="text-sm font-bold text-ink truncate">{{ $client->name }}</h4>
                                <div class="text-[11px] text-muted font-mono truncate">{{ $client->billing_email ?: 'No billing email' }}</div>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase shrink-0 {{ $client->status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                {{ $client->status }}
                            </span>
                        </div>

                        <!-- Stats Strip -->
                        <div class="grid grid-cols-3 gap-2 p-3 bg-canvas/50 rounded-xl border border-border text-center">
                            <div>
                                <div class="text-[10px] font-bold text-muted uppercase">Projects</div>
                                <div class="text-xs font-bold font-mono text-ink">{{ $client->projects_count }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold text-muted uppercase">Leads</div>
                                <div class="text-xs font-bold font-mono text-ink">{{ number_format($client->leads_count) }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold text-muted uppercase">Credits</div>
                                <div class="text-xs font-bold font-mono text-primary">{{ number_format($client->wallet?->balance ?? 0) }}</div>
                            </div>
                        </div>

                        <!-- Client Projects preview list -->
                        @if($client->projects->isNotEmpty())
                            <div class="space-y-1.5 pt-1">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-muted">Projects:</div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($client->projects->take(4) as $proj)
                                        <span class="px-2 py-0.5 text-[10px] font-semibold bg-canvas border border-border rounded-md text-ink">
                                            {{ $proj->name }}
                                        </span>
                                    @endforeach
                                    @if($client->projects->count() > 4)
                                        <span class="px-1.5 py-0.5 text-[10px] text-muted">+{{ $client->projects->count() - 4 }} more</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="col-span-3 p-12 text-center text-xs text-muted border border-dashed border-border rounded-2xl">
                        No clients registered under this organization yet. Click "Register Client" to create one.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- TAB 4: ACTIVITY & AUDIT -->
    @if($activeTab === 'activity')
        <div class="space-y-4">
            <div class="p-4 rounded-2xl bg-surface border border-border shadow-2xs">
                <h3 class="text-sm font-bold text-ink">Organization Audit Trail</h3>
                <p class="text-xs text-muted">Recent updates and actions recorded for this organization or its assigned members.</p>
            </div>

            <div class="bg-surface rounded-2xl border border-border shadow-2xs divide-y divide-border overflow-hidden">
                @forelse($auditLogs as $log)
                    <div class="p-4 hover:bg-canvas/30 transition flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                        <div class="space-y-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold font-mono uppercase bg-canvas border border-border text-ink">
                                    {{ $log->action }}
                                </span>
                                <span class="font-bold text-ink truncate">{{ $log->subject_summary }}</span>
                            </div>
                            <div class="text-muted text-[11px] truncate">
                                By <strong class="text-ink font-semibold">{{ $log->user_name }}</strong> • IP: {{ $log->formatted_ip }}
                            </div>
                        </div>

                        <div class="text-right text-[11px] font-mono text-muted shrink-0">
                            {{ $log->created_at ? $log->created_at->format('M d, Y • h:i A') : '—' }}
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center text-xs text-muted">
                        No audit events recorded for this organization yet.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- MODAL 1: ADD MEMBER TO THIS ORGANIZATION -->
    <x-modal name="add-org-member-modal" :show="$showAddMemberModal" maxWidth="xl">
        <div class="p-5 sm:p-6 space-y-5">
            <div class="flex items-center justify-between pb-3.5 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-primary/10 text-primary border border-primary/20">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Add Team Member</h3>
                        <p class="text-xs text-muted">Assign directly to <strong class="text-ink font-semibold">{{ $organization->name }}</strong></p>
                    </div>
                </div>
                <button type="button" wire:click="closeAddMemberModal" class="text-muted hover:text-ink cursor-pointer p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit.prevent="createUserForOrganization" class="space-y-4 text-xs">
                <div>
                    <label class="font-bold text-ink">Full Name <span class="text-red-500">*</span></label>
                    <input 
                        type="text" 
                        wire:model="newUserName" 
                        placeholder="e.g. Rahul Sharma"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1"
                    >
                    @error('newUserName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="font-bold text-ink">Email Address <span class="text-red-500">*</span></label>
                    <input 
                        type="email" 
                        wire:model="newUserEmail" 
                        placeholder="e.g. rahul@company.com"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1"
                    >
                    @error('newUserEmail') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="font-bold text-ink">Assign Role <span class="text-red-500">*</span></label>
                        <x-ui.themed-select 
                            wire:model="newUserRole" 
                            :options="$this->availableRoles"
                            placeholder="Select Role" 
                            class="w-full mt-1" 
                        />
                        @error('newUserRole') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="font-bold text-ink">Password <span class="text-muted font-normal">(Optional)</span></label>
                        <input 
                            type="text" 
                            wire:model="newUserPassword" 
                            placeholder="Auto-generated if left blank"
                            class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1"
                        >
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                    <button 
                        type="button" 
                        wire:click="closeAddMemberModal" 
                        class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 transition shadow-xs cursor-pointer flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Register Client</h3>
                        <p class="text-xs text-muted">Register a new client under <strong class="text-ink">{{ $organization->name }}</strong></p>
                    </div>
                </div>
                <button type="button" wire:click="closeCreateClientModal" class="text-muted hover:text-ink cursor-pointer p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit.prevent="createClientForOrganization" class="space-y-4 text-xs">
                <div>
                    <label class="font-bold text-ink">Client Name <span class="text-red-500">*</span></label>
                    <input 
                        type="text" 
                        wire:model="newClientName" 
                        placeholder="e.g. Prestige Estates Projects"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1"
                    >
                    @error('newClientName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="font-bold text-ink">Billing Email <span class="text-muted font-normal">(Optional)</span></label>
                    <input 
                        type="email" 
                        wire:model="newClientBillingEmail" 
                        placeholder="e.g. accounts@prestige.com"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1"
                    >
                    @error('newClientBillingEmail') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="font-bold text-ink">Status</label>
                    <x-ui.themed-select 
                        wire:model="newClientStatus" 
                        :options="['active' => 'Active', 'suspended' => 'Suspended']"
                        placeholder="Select Status" 
                        class="w-full mt-1" 
                    />
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                    <button 
                        type="button" 
                        wire:click="closeCreateClientModal" 
                        class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 transition shadow-xs cursor-pointer flex items-center gap-1.5"
                    >
                        <span>Create Client</span>
                    </button>
                </div>
            </form>
        </div>
    </x-modal>
</div>
