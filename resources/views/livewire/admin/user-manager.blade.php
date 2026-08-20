@php
    $dbRoles = [];
    foreach ($roles as $r) {
        $dbRoles[$r->name] = $r->name;
    }
@endphp

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1">
                <span>System Administration</span>
                <span>/</span>
                <span class="text-ink font-semibold">Users &amp; Roles</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                <svg class="w-6 h-6 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span>Global User Management</span>
            </h1>
            <p class="text-xs text-muted">Assign database roles, create users across organizations, and manage role permissions.</p>
        </div>
    </div>

    <!-- Advanced Table Component -->
    <x-ui.advanced-table 
        :columns="$this->tableColumns()"
        :rows="$users"
        :quickFilters="$this->quickFilters()"
        :activeStatus="$statusFilter"
        :visibleColumns="$visibleColumns"
        :sortField="$sortField"
        :sortDirection="$sortDirection"
        :filterCount="$this->activeFilterCount"
        searchPlaceholder="Search user name or email..."
        emptyTitle="No Users Found"
        emptyMessage="No users matched your current search and filters."
    >
        <!-- Filter Dropdown Slot -->
        <x-slot:filters>
            <div class="space-y-3">
                <div>
                    <label class="text-[11px] font-bold text-ink block mb-1">Filter Organization</label>
                    <select wire:model.live="filterOrganizationId" class="w-full h-8 px-2.5 rounded-lg border border-border bg-canvas text-ink text-xs">
                        <option value="">All Organizations</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-slot:filters>

        <!-- Primary Action Slot -->
        <x-slot:action>
            <div class="flex items-center gap-2">
                <x-ui.export-button target="exportExcel" class="text-xs" />
                <button 
                    type="button" 
                    wire:click="openCreateUserOffcanvas"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition shadow-xs cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    <span>Create User</span>
                </button>
            </div>
        </x-slot:action>
    </x-ui.advanced-table>

    <!-- Create User Offcanvas Drawer -->
    <x-ui.offcanvas 
        wire:model="showCreateUserOffcanvas"
        name="create-user-drawer" 
        title="Create New User" 
        subtitle="Register a new user account, select their parent organization, and assign their CRM role."
        maxWidth="lg"
    >
        <x-slot:headerIcon>
            <div class="p-1.5 rounded-lg bg-canvas text-ink border border-border">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
            </div>
        </x-slot:headerIcon>

        <div class="space-y-4 text-xs">
            <div>
                <label class="font-bold text-ink">Full Name <span class="text-red-500">*</span></label>
                <input 
                    type="text" 
                    wire:model="newUserName" 
                    placeholder="e.g. Anand Kumar"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink transition mt-1"
                >
                @error('newUserName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="font-bold text-ink">Email Address <span class="text-red-500">*</span></label>
                <input 
                    type="email" 
                    wire:model="newUserEmail" 
                    placeholder="e.g. anand@company.com"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink transition mt-1"
                >
                @error('newUserEmail') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="font-bold text-ink">Parent Organization <span class="text-muted font-normal">(Optional for Platform HQ)</span></label>
                @php
                    $orgOptions = ['' => 'Platform HQ (No Organization)'];
                    foreach($organizations as $org) {
                        $orgOptions[(string)$org->id] = $org->name . ' (' . str_replace('_', ' ', $org->type) . ')';
                    }
                @endphp
                <x-ui.themed-select 
                    wire:model="newUserOrganizationId" 
                    :options="$orgOptions"
                    placeholder="Select Organization" 
                    searchable="true"
                    class="w-full mt-1" 
                />
                @error('newUserOrganizationId') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="font-bold text-ink">CRM Access Role <span class="text-red-500">*</span></label>
                <x-ui.themed-select 
                    wire:model="newUserRole" 
                    :options="$dbRoles"
                    placeholder="Select Role" 
                    searchable="true"
                    class="w-full mt-1" 
                />
                @error('newUserRole') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="font-bold text-ink">Password <span class="text-muted font-normal">(Auto-generated if empty)</span></label>
                <input 
                    type="text" 
                    wire:model="newUserPassword" 
                    placeholder="Set custom password or leave blank"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink transition mt-1"
                >
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
                            class="px-3 h-8 bg-ink text-white rounded-lg hover:bg-neutral-800 text-xs font-bold transition shrink-0 flex items-center gap-1.5 shadow-xs"
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

        <x-slot:footer>
            <button 
                type="button" 
                wire:click="closeCreateUserOffcanvas" 
                class="px-4 py-2 border border-border bg-white text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer"
            >
                Cancel
            </button>
            <button 
                type="button" 
                wire:click="createUser" 
                class="px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition shadow-xs flex items-center gap-1.5 cursor-pointer"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                <span>Create User</span>
            </button>
        </x-slot:footer>
    </x-ui.offcanvas>
</div>
