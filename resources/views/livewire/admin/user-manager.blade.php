@php
    $dbRoles = $this->availableRoles;
    $orgOptions = $this->organizationOptions;
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span>Global User Management</span>
            </h1>
            <p class="text-xs text-muted">Assign database roles, create users across organizations, and manage role
                permissions.</p>
        </div>
    </div>

    <!-- Advanced Table Component -->
    <x-ui.advanced-table :columns="$this->tableColumns()" :rows="$users" :quickFilters="$this->quickFilters()"
        :activeStatus="$statusFilter" :visibleColumns="$visibleColumns" :sortField="$sortField"
        :sortDirection="$sortDirection" :filterCount="$this->activeFilterCount"
        searchPlaceholder="Search user name or email..." emptyTitle="No Users Found"
        emptyMessage="No users matched your current search and filters.">
        <!-- Filter Dropdown Slot -->
        <x-slot:filters>
            <div class="space-y-3">
                <div>
                    <label class="text-[11px] font-bold text-ink block mb-1">Filter Client</label>
                    <select wire:model.live="filterOrganizationId"
                        class="w-full h-8 px-2.5 rounded-lg border border-border bg-canvas text-ink text-xs">
                        <option value="">All Clients</option>
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
                <button type="button" wire:click="openCreateUserModal"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition shadow-xs cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    <span>Create User</span>
                </button>
            </div>
        </x-slot:action>
    </x-ui.advanced-table>

    <!-- 1. USER DETAILS MODAL (VIEW & EDIT MODES) -->
    <x-modal name="user-details-modal" :show="$showUserDetailsModal" maxWidth="2xl">
        <div class="p-5 sm:p-6 space-y-5 max-h-[88vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="flex items-start justify-between gap-3 border-b border-border pb-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-primary/10 text-primary border border-primary/20 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-ink">
                                {{ $userDetailsMode === 'view' ? 'User Details' : 'Edit User' }}
                            </h3>
                            @if($selectedUser)
                                @if(($selectedUser->status ?? 'active') === 'active')
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Active
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700 border border-red-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                        Suspended
                                    </span>
                                @endif
                            @endif
                        </div>
                        <p class="text-xs text-muted">
                            {{ $userDetailsMode === 'view' ? 'Read-only profile and role permissions information.' : 'Update permitted user fields, clients assignment, and role permissions.' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    @if($userDetailsMode === 'edit')
                        <button type="button" wire:click="switchUserDetailsMode('view')"
                            class="text-xs font-semibold px-2.5 py-1 rounded-lg border border-border bg-canvas text-ink hover:bg-neutral-200 transition cursor-pointer">
                            View
                        </button>
                    @endif
                    <button type="button" wire:click="closeUserDetailsModal"
                        class="text-muted hover:text-ink transition p-1.5 rounded-lg hover:bg-canvas cursor-pointer"
                        title="Close Modal">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            @if($selectedUser)
                @if($userDetailsMode === 'view')
                    <!-- View (READ ONLY) -->
                    <div class="space-y-4">
                        <!-- User Hero Card -->
                        <div class="p-4 rounded-xl bg-canvas/60 border border-border flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5 min-w-0">
                                <div
                                    class="w-12 h-12 rounded-full bg-slate-200 dark:bg-slate-700 text-ink font-bold text-base flex items-center justify-center shrink-0 border border-border">
                                    {{ strtoupper(substr($selectedUser->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-sm text-ink truncate">{{ $selectedUser->name }}</div>
                                    <div class="text-xs text-muted font-mono truncate">{{ $selectedUser->email }}</div>
                                    @if($selectedUser->phone)
                                        <div class="text-[11px] text-muted flex items-center gap-1 mt-0.5">
                                            <svg class="w-3 h-3 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            <span>{{ $selectedUser->phone }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="shrink-0 text-right">
                                <span
                                    class="px-2.5 py-1 text-[11px] font-bold rounded-pill bg-primary/10 text-primary border border-primary/20 uppercase tracking-wide">
                                    {{ $selectedUser->primary_role_name }}
                                </span>
                            </div>
                        </div>

                        <!-- User Metadata Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted">User ID</span>
                                <div class="text-sm font-mono font-bold text-ink">
                                    #USR-{{ str_pad($selectedUser->id, 4, '0', STR_PAD_LEFT) }}</div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Parent
                                    Client</span>
                                <div class="text-sm font-bold text-ink truncate">
                                    {{ $selectedUser->organization?->name ?: 'Platform HQ (No Client)' }}
                                </div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Account Status</span>
                                <div>
                                    @if(($selectedUser->status ?? 'active') === 'active')
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Active Account
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                            Suspended Account
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Email
                                    Verification</span>
                                <div class="text-xs font-medium text-ink">
                                    @if($selectedUser->email_verified_at)
                                        <span class="text-emerald-700 font-semibold flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>Verified on {{ $selectedUser->email_verified_at->format('M d, Y') }}</span>
                                        </span>
                                    @else
                                        <span class="text-amber-600 font-semibold">Pending Verification</span>
                                    @endif
                                </div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Registered At</span>
                                <div class="text-xs font-mono text-ink">
                                    {{ $selectedUser->created_at ? $selectedUser->created_at->format('M d, Y h:i A') : '—' }}
                                </div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Last Updated</span>
                                <div class="text-xs font-mono text-ink">
                                    {{ $selectedUser->updated_at ? $selectedUser->updated_at->format('M d, Y h:i A') : '—' }}
                                </div>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="flex items-center justify-between pt-4 border-t border-border">
                            <div>
                                @if(auth()->id() !== $selectedUser->id)
                                    <a href="{{ route('admin.users.impersonate', $selectedUser->id) }}"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-purple-200 bg-purple-50 hover:bg-purple-100 text-purple-700 text-xs font-semibold transition cursor-pointer shadow-2xs"
                                        title="Impersonate User">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span>Impersonate User</span>
                                    </a>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="closeUserDetailsModal"
                                    class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer">
                                    Close
                                </button>
                                <button type="button" wire:click="switchUserDetailsMode('edit')"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition cursor-pointer shadow-xs">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                    <span>Edit User</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- EDIT MODE -->
                    <form wire:submit.prevent="updateUser" class="space-y-4">
                        <div class="space-y-3.5 text-xs">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="font-bold text-ink">Full Name <span class="text-red-500">*</span></label>
                                    <input type="text" wire:model="editName" placeholder="e.g. Rahul Sharma"
                                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                                    @error('editName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div>
                                    <label class="font-bold text-ink">Email Address <span class="text-red-500">*</span></label>
                                    <input type="email" wire:model="editEmail" placeholder="e.g. rahul@company.com"
                                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                                    @error('editEmail') <span
                                    class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="font-bold text-ink">Contact Phone <span
                                            class="text-muted font-normal">(Optional)</span></label>
                                    <input type="text" wire:model="editPhone" placeholder="e.g. +91 9876543210"
                                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                                    @error('editPhone') <span
                                    class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="font-bold text-ink">Account Status <span class="text-red-500">*</span></label>
                                    <x-ui.themed-select wire:model="editStatus" :options="['active' => 'Active', 'suspended' => 'Suspended']" placeholder="Select Status" class="w-full mt-1" />
                                    @error('editStatus') <span
                                    class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="font-bold text-ink">Client <span class="text-muted font-normal">(Optional for
                                            Platform HQ)</span></label>
                                    <x-ui.themed-select wire:model="editOrganizationId" :options="$orgOptions"
                                        placeholder="Select Client" searchable="true" class="w-full mt-1" />
                                    @error('editOrganizationId') <span
                                    class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="font-bold text-ink">CRM Access Role <span
                                            class="text-red-500">*</span></label>
                                    <x-ui.themed-select wire:model="editRole" :options="$dbRoles" placeholder="Select Role"
                                        searchable="true" class="w-full mt-1" />
                                    @error('editRole') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <label class="font-bold text-ink">Update Password <span class="text-muted font-normal">(Leave
                                        blank to keep unchanged)</span></label>
                                <input type="text" wire:model="editPassword"
                                    placeholder="Enter new password (min. 6 characters)"
                                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                                @error('editPassword') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                            <button type="button" wire:click="switchUserDetailsMode('view')"
                                class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit" wire:loading.attr="disabled"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition cursor-pointer shadow-xs disabled:opacity-50">
                                <span wire:loading wire:target="updateUser">
                                    <svg class="animate-spin -ml-1 mr-1.5 h-3.5 w-3.5 text-white" fill="none"
                                        viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                </span>
                                <svg wire:loading.remove wire:target="updateUser" class="w-3.5 h-3.5" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Save Changes</span>
                            </button>
                        </div>
                    </form>
                @endif
            @endif
        </div>
    </x-modal>

    <!-- 2. CREATE NEW USER MODAL (REPLACING OFFCANVAS) -->
    <x-modal name="create-user-modal" :show="$showCreateModal" maxWidth="lg">
        <div class="p-5 sm:p-6 space-y-4 max-h-[88vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="flex items-start justify-between gap-3 border-b border-border pb-3.5">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-primary/10 text-primary border border-primary/20 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Create New User</h3>
                        <p class="text-xs text-muted">Register a new user account, select their parent organization, and
                            assign their CRM role.</p>
                    </div>
                </div>

                <button type="button" wire:click="closeCreateUserModal"
                    class="text-muted hover:text-ink transition p-1.5 rounded-lg hover:bg-canvas cursor-pointer"
                    title="Close Modal">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Form Body -->
            <form wire:submit.prevent="createUser" class="space-y-3.5 text-xs">
                <div>
                    <label class="font-bold text-ink">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="newUserName" placeholder="e.g. Anand Kumar"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                    @error('newUserName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-ink">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" wire:model="newUserEmail" placeholder="e.g. anand@company.com"
                            class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                        @error('newUserEmail') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="font-bold text-ink">Contact Phone <span
                                class="text-muted font-normal">(Optional)</span></label>
                        <input type="text" wire:model="newUserPhone" placeholder="e.g. +91 9876543210"
                            class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                        @error('newUserPhone') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="font-bold text-ink">Client <span class="text-muted font-normal">(Optional
                            for Platform HQ)</span></label>
                    <x-ui.themed-select wire:model="newUserOrganizationId" :options="$orgOptions"
                        placeholder="Select Client" searchable="true" class="w-full mt-1" />
                    @error('newUserOrganizationId') <span
                    class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="font-bold text-ink">CRM Access Role <span class="text-red-500">*</span></label>
                    <x-ui.themed-select wire:model="newUserRole" :options="$dbRoles" placeholder="Select Role"
                        searchable="true" class="w-full mt-1" />
                    @error('newUserRole') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="font-bold text-ink">Password <span class="text-muted font-normal">(Auto-generated if
                            left blank)</span></label>
                    <input type="text" wire:model="newUserPassword"
                        placeholder="Auto-generated secure password if left blank"
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1">
                </div>

                @if($generatedInviteLink)
                    <div class="p-3.5 bg-surface border border-border rounded-xl space-y-2 mt-2 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-ink flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                <span>Shareable Activation Link</span>
                            </span>
                            <span class="text-[10px] text-muted">Single-use reset token</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly value="{{ $generatedInviteLink }}"
                                class="w-full h-8 px-3 text-[11px] font-mono bg-canvas border border-border rounded-lg text-ink select-all">
                            <button type="button" x-data="{ copied: false }"
                                @click="navigator.clipboard.writeText('{{ $generatedInviteLink }}'); copied = true; $wire.dispatch('toast', { type: 'success', title: 'Copied', message: 'Activation link copied to clipboard.' }); setTimeout(function() { copied = false; }, 2000)"
                                class="px-3 h-8 bg-ink text-white rounded-lg hover:bg-neutral-800 text-xs font-bold transition shrink-0 flex items-center gap-1.5 shadow-xs cursor-pointer">
                                <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <svg x-show="copied" class="w-3.5 h-3.5 text-green-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                <span x-text="copied ? 'Copied' : 'Copy'"></span>
                            </button>
                        </div>
                    </div>
                @endif

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                    <button type="button" wire:click="closeCreateUserModal"
                        class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition cursor-pointer shadow-xs disabled:opacity-50">
                        <span wire:loading wire:target="createUser">
                            <svg class="animate-spin -ml-1 mr-1.5 h-3.5 w-3.5 text-white" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                        </span>
                        <svg wire:loading.remove wire:target="createUser" class="w-3.5 h-3.5" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span>Create User</span>
                    </button>
                </div>
            </form>
        </div>
    </x-modal>
</div>