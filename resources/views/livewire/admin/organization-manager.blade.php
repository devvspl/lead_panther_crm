<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1">
                <span>System Administration</span>
                <span>/</span>
                <span class="text-ink font-semibold">Organizations</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                <svg class="w-6 h-6 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span>Organizations Management</span>
            </h1>
            <p class="text-xs text-muted">Manage Real Estate Builder, Channel Partner, and Platform multi-tenant organizations.</p>
        </div>
    </div>

    <!-- Create Organization Inline Card -->
    <div class="bg-surface rounded-card border border-border p-5 shadow-2xs space-y-3">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Quick Register Organization</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
            <div>
                <label class="font-bold text-ink">Organization Name</label>
                <input 
                    type="text" 
                    wire:model="name" 
                    placeholder="e.g. Prestige Developers Ltd"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1"
                >
                @error('name') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="font-bold text-ink">Organization Type</label>
                <x-ui.themed-select 
                    wire:model="type" 
                    :options="['builder' => 'Builder / Developer', 'channel_partner' => 'Channel Partner', 'platform' => 'Platform Admin Org']"
                    placeholder="Organization Type" 
                    class="w-full mt-1" 
                />
                @error('type') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="font-bold text-ink">Initial Status</label>
                <x-ui.themed-select 
                    wire:model="status" 
                    :options="['active' => 'Active', 'suspended' => 'Suspended']"
                    placeholder="Initial Status" 
                    class="w-full mt-1" 
                />
            </div>
        </div>

        <div class="flex justify-end pt-1">
            <x-ui.button wire:click="createOrganization" variant="primary" class="text-xs">
                Save Organization
            </x-ui.button>
        </div>
    </div>

    <!-- Advanced Table Component -->
    <x-ui.advanced-table 
        :columns="$this->tableColumns()"
        :rows="$organizations"
        :quickFilters="$this->quickFilters()"
        :activeStatus="$statusFilter"
        :visibleColumns="$visibleColumns"
        :sortField="$sortField"
        :sortDirection="$sortDirection"
        :filterCount="$this->activeFilterCount"
        searchPlaceholder="Search organization name or type..."
        emptyTitle="No Organizations Found"
        emptyMessage="No organizations match your current search and filters."
    >
        <!-- Primary Action Slot -->
        <x-slot:action>
            <x-ui.export-button target="exportExcel" class="text-xs" />
        </x-slot:action>
    </x-ui.advanced-table>

    <!-- Offcanvas Slide-over Drawer for Organization User Management -->
    <x-ui.offcanvas 
        wire:model="showUserOffcanvas"
        name="org-users-drawer" 
        :title="$selectedOrg ? 'Manage Users — ' . $selectedOrg->name : 'Manage Organization Users'"
        :subtitle="$selectedOrg ? 'Register new users or manage access levels for this ' . str_replace('_', ' ', $selectedOrg->type) . ' organization.' : ''"
        maxWidth="xl"
    >
        <x-slot:headerIcon>
            <div class="p-1.5 rounded-lg bg-canvas text-ink border border-border">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
        </x-slot:headerIcon>

        @if($selectedOrg)
            <!-- Section 1: Create New User for this Organization -->
            <div class="bg-canvas/40 border border-border rounded-xl p-4.5 space-y-3.5">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-ink flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                        <span>Create &amp; Assign New User</span>
                    </h4>
                    <span class="text-[10px] text-muted uppercase font-mono font-bold">{{ str_replace('_', ' ', $selectedOrg->type) }}</span>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-bold text-ink">Full Name <span class="text-red-500">*</span></label>
                        <input 
                            type="text" 
                            wire:model="newUserName" 
                            placeholder="e.g. Rahul Sharma"
                            class="w-full h-8 px-3.5 rounded-lg border border-border bg-white text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink transition mt-1"
                        >
                        @error('newUserName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="font-bold text-ink">Email Address <span class="text-red-500">*</span></label>
                        <input 
                            type="email" 
                            wire:model="newUserEmail" 
                            placeholder="e.g. rahul@prestige.com"
                            class="w-full h-8 px-3.5 rounded-lg border border-border bg-white text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink transition mt-1"
                        >
                        @error('newUserEmail') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    @php
                        $dbRoles = [];
                        foreach ($roles as $r) {
                            $dbRoles[$r->name] = $r->name;
                        }
                    @endphp
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-ink">Assign Role <span class="text-red-500">*</span></label>
                            <x-ui.themed-select 
                                wire:model="newUserRole" 
                                :options="$dbRoles"
                                placeholder="Select Role" 
                                class="w-full mt-1" 
                            />
                            @error('newUserRole') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="font-bold text-ink">Custom Password <span class="text-muted font-normal">(Optional)</span></label>
                            <input 
                                type="text" 
                                wire:model="newUserPassword" 
                                placeholder="Auto-generated if blank"
                                class="w-full h-8 px-3.5 rounded-lg border border-border bg-white text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink transition mt-1"
                            >
                        </div>
                    </div>

                    <div class="pt-1 flex justify-end">
                        <button 
                            type="button"
                            wire:click="createUserForOrganization"
                            class="px-4 py-2 bg-ink text-white text-xs font-bold rounded-lg hover:bg-neutral-800 transition shadow-xs flex items-center gap-1.5 cursor-pointer"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <span>Create &amp; Assign User</span>
                        </button>
                    </div>

                    @if($generatedInviteLink)
                        <div class="p-3.5 bg-surface border border-border rounded-xl space-y-2 mt-2 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-ink flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                    </svg>
                                    <span>Shareable Activation Link</span>
                                </span>
                                <span class="text-[10px] text-muted">Single-use reset token</span>
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
                </div>
            </div>

            <!-- Section 2: Current Assigned Members -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-ink flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span>Assigned Members ({{ $selectedOrg->users->count() }})</span>
                    </h4>
                </div>

                <div class="space-y-2">
                    @forelse($selectedOrg->users as $member)
                        <div class="p-3 bg-surface border border-border rounded-xl flex items-center justify-between gap-3 shadow-xs hover:border-ink/20 transition">
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-ink truncate">{{ $member->name }}</div>
                                <div class="text-[11px] text-muted font-mono truncate">{{ $member->email }}</div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill bg-canvas text-ink border border-border uppercase">
                                    {{ $member->roles->first()?->name ?? 'No Role' }}
                                </span>

                                <a 
                                    href="{{ route('admin.users.impersonate', $member->id) }}" 
                                    class="p-1.5 rounded-lg text-purple-700 hover:bg-purple-50 transition" 
                                    title="Impersonate User"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                    </svg>
                                </a>

                                <button 
                                    type="button"
                                    wire:click="removeUserFromOrganization({{ $member->id }})"
                                    class="p-1.5 rounded-lg text-red-600 hover:bg-red-50 transition cursor-pointer"
                                    title="Unassign from Organization"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-xs text-muted bg-canvas/30 rounded-xl border border-dashed border-border">
                            No users currently assigned to this organization. Create the first one above!
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        <x-slot:footer>
            <button 
                type="button" 
                wire:click="closeUserOffcanvas" 
                class="px-4 py-2 border border-border bg-white text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer"
            >
                Done
            </button>
        </x-slot:footer>
    </x-ui.offcanvas>
</div>