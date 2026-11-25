<div class="space-y-4" x-data="{
    activeTab: 'assignment',
    tabs: ['assignment', 'management'],
    moveTab(step) {
        this.activeTab = this.tabs[(this.tabs.indexOf(this.activeTab) + step + this.tabs.length) % this.tabs.length];
        this.$nextTick(() => this.$root.querySelector('#team-tab-' + this.activeTab).focus());
    }
}">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">{{ \App\Support\TeamAccess::isLeader(auth()->user()) ? 'My Team Management' : 'Builder Direct Team Management' }}</h1>
            <p class="text-xs text-muted">Assignments and team members.</p>
        </div>
        <button type="button" wire:click="openAddMemberModal" title="Add Team Member" aria-label="Add Team Member" class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-lg bg-primary text-white hover:bg-primary-hover text-xs font-semibold whitespace-nowrap cursor-pointer shadow-xs">
            <x-ui.team-icon name="add" /><span>Add Team Member</span>
        </button>
    </div>

    <nav role="tablist" aria-label="Team Management sections" class="flex overflow-x-auto gap-1 border-b border-border text-xs font-semibold">
        @foreach(['assignment'=>['Lead Assignment','settings'], 'management'=>['Team Management','team']] as $key=>$tab)
            <button type="button" role="tab" id="team-tab-{{ $key }}" aria-controls="team-panel-{{ $key }}"
                x-bind:aria-selected="activeTab === '{{ $key }}'" x-bind:tabindex="activeTab === '{{ $key }}' ? 0 : -1"
                x-on:click="activeTab = '{{ $key }}'" x-on:keydown.arrow-right.prevent="moveTab(1)" x-on:keydown.arrow-left.prevent="moveTab(-1)"
                x-bind:class="activeTab === '{{ $key }}' ? 'border-primary text-primary bg-primary/5' : 'border-transparent text-muted hover:text-ink hover:bg-canvas'"
                class="inline-flex items-center justify-center gap-2 border-b-2 px-3 py-3 whitespace-nowrap rounded-t-lg focus-visible:outline-2 focus-visible:outline-primary">
                <x-ui.team-icon :name="$tab[1]" />{{ $tab[0] }}
            </button>
        @endforeach
    </nav>

    {{-- Keep panels mounted: switching tabs must retain Livewire and Alpine form state. --}}
    <section role="tabpanel" id="team-panel-assignment" aria-labelledby="team-tab-assignment" x-show="activeTab === 'assignment'">
        <livewire:settings.lead-assignment-configuration :organization-id="auth()->user()->organization_id" />
    </section>

    <section role="tabpanel" id="team-panel-management" aria-labelledby="team-tab-management" x-cloak x-show="activeTab === 'management'" class="space-y-4">
    <div @if(count($teams) <= 1 && \App\Support\TeamAccess::isLeader(auth()->user()) && !$errors->any()) class="hidden" @else class="bg-surface rounded-card border border-border p-5 space-y-4 text-xs" @endif>
        @if(\App\Support\TeamAccess::isLeader(auth()->user()) && empty($teams))
            <p role="status" class="rounded-lg border border-border bg-canvas p-3 text-sm text-ink">Team access is inactive. Ask Super Admin to confirm your membership.</p>
        @endif
        <div class="flex items-end gap-3">
            @if(count($teams) > 1)
                <div class="flex-1">
                    <label class="block font-semibold mb-1">Sales team</label>
                    <livewire:shared.searchable-select wire:model.live="teamId" :value="$teamId" :options="$teams" placeholder="Select team" class="block w-full" :key="'managed-teams-'.($teamId ?? 'none').'-'.md5(json_encode($teams))" />
                </div>
            @endif
            @unless(\App\Support\TeamAccess::isLeader(auth()->user()))
                <button type="button" title="New Team" aria-label="New Team" wire:click="newTeam" class="border border-border rounded-lg px-3 py-2 hover:bg-canvas transition cursor-pointer">
                    <x-ui.team-icon name="add" />
                </button>
            @endunless
        </div>

        @unless(\App\Support\TeamAccess::isLeader(auth()->user()))
            <div class="grid sm:grid-cols-3 gap-3 items-end">
                <label>
                    <span class="block font-semibold mb-1">Team name</span>
                    <input wire:model="teamName" class="block w-full border border-border bg-canvas rounded-lg px-3 py-2 mt-1">
                </label>
                <div>
                    <label class="block mb-1 font-semibold">Team Leader</label>
                    <livewire:shared.searchable-select wire:model.live="teamLeaderId" :options="$leaders" placeholder="Select Team Leader" class="block w-full" :key="'team-leader-'.$teamId" />
                </div>
                <div>
                    <button type="button" title="Save Team and Leader" aria-label="Save Team and Leader" wire:click="saveTeam" class="rounded-lg bg-primary hover:bg-primary-hover text-white px-4 py-2 font-semibold transition cursor-pointer flex items-center justify-center gap-1.5 w-full sm:w-auto">
                        <x-ui.team-icon name="check" />
                        <span>Save Team</span>
                    </button>
                </div>
            </div>
        @endunless

        @if($errors->any())<p class="text-danger">{{ $errors->first() }}</p>@endif
    </div>

    <!-- Add Team Member Modal -->
    <x-modal name="add-team-member" :show="$errors->hasAny(['memberName','memberEmail','roleName','password','existingMemberId','existingMemberRole']) || (bool)$generatedInviteLink" maxWidth="2xl">
        <div class="max-h-[calc(100dvh-4rem)] overflow-y-auto p-5 space-y-4" x-data="{ tab: @js(!\App\Support\TeamAccess::isLeader(auth()->user()) && count($organizationMembers) > 0 ? 'existing' : 'new') }">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
                <div>
                    <h2 class="text-sm font-bold text-ink">Add Team Member</h2>
                    <p class="text-xs text-muted">Select an existing organization member to assign a role, or invite a new member.</p>
                </div>
                <button type="button" x-on:click="$dispatch('close-modal', 'add-team-member')" title="Close" aria-label="Close" class="p-1.5 text-muted hover:text-ink rounded-lg hover:bg-canvas transition cursor-pointer">
                    <x-ui.team-icon name="close" />
                </button>
            </div>

            @unless(\App\Support\TeamAccess::isLeader(auth()->user()))
                <div class="flex p-1 bg-canvas rounded-lg border border-border text-xs font-semibold">
                    <button type="button" 
                        @click="tab = 'existing'" 
                        :class="tab === 'existing' ? 'bg-surface text-ink shadow-2xs font-bold' : 'text-muted hover:text-ink'" 
                        class="flex-1 py-1.5 px-3 rounded-md transition text-center cursor-pointer">
                        Select Existing Member
                    </button>
                    <button type="button" 
                        @click="tab = 'new'" 
                        :class="tab === 'new' ? 'bg-surface text-ink shadow-2xs font-bold' : 'text-muted hover:text-ink'" 
                        class="flex-1 py-1.5 px-3 rounded-md transition text-center cursor-pointer">
                        Invite New Member
                    </button>
                </div>
            @endunless

            <!-- Tab 1: Assign Existing Member & Role -->
            @unless(\App\Support\TeamAccess::isLeader(auth()->user()))
                <div x-show="tab === 'existing'" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="min-w-0 sm:col-span-2">
                            <label class="block font-semibold text-ink mb-1">Select Organization Member <span class="text-danger">*</span></label>
                            <x-ui.themed-select wire:model.live="existingMemberId" placeholder="-- Choose Organization Member --" :searchable="true" class="w-full">
                                <option value="">-- Choose Organization Member --</option>
                                @foreach($organizationMembers as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </x-ui.themed-select>
                            <x-input-error :messages="$errors->get('existingMemberId')" class="mt-1" />
                        </div>

                        <div class="min-w-0 sm:col-span-2">
                            <label class="block font-semibold text-ink mb-1">Assign Role <span class="text-danger">*</span></label>
                            <x-ui.themed-select wire:model="existingMemberRole" :options="$roleOptions" placeholder="Select Role" class="w-full [&>button]:h-9" />
                            <x-input-error :messages="$errors->get('existingMemberRole')" class="mt-1" />
                        </div>

                        @if($teamName)
                            <div class="sm:col-span-2 p-2.5 rounded-lg border border-border bg-canvas/60 text-xs text-muted flex items-center justify-between">
                                <span>Assigning to team: <strong class="text-ink">{{ $teamName }}</strong></span>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <button type="button" x-on:click="$dispatch('close-modal', 'add-team-member')" class="px-3 py-1.5 rounded-lg border border-border text-xs font-semibold text-ink hover:bg-canvas transition cursor-pointer">
                            Cancel
                        </button>
                        <x-ui.button wire:click="assignExistingMember" variant="primary" class="inline-flex items-center justify-center gap-2" wire:loading.attr="disabled">
                            <x-ui.team-icon name="check" /><span>Assign Role &amp; Add to Team</span>
                        </x-ui.button>
                    </div>
                </div>
            @endunless

            <!-- Tab 2: Invite / Create New Member -->
            <div x-show="tab === 'new'" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="min-w-0">
                        <label for="add-member-name" class="block font-semibold text-ink mb-1">Full Name <span class="text-danger">*</span></label>
                        <x-text-input id="add-member-name" variant="compact" wire:model="memberName" placeholder="Full name" class="h-9" />
                        <x-input-error :messages="$errors->get('memberName')" class="mt-1" />
                    </div>
                    <div class="min-w-0">
                        <label for="add-member-email" class="block font-semibold text-ink mb-1">Email Address <span class="text-danger">*</span></label>
                        <x-text-input id="add-member-email" variant="compact" type="email" wire:model="memberEmail" placeholder="name@example.com" class="h-9" />
                        <x-input-error :messages="$errors->get('memberEmail')" class="mt-1" />
                    </div>
                    <div class="min-w-0">
                        <label class="block font-semibold text-ink mb-1">Role <span class="text-danger">*</span></label>
                        <x-ui.themed-select wire:model="roleName" :options="$roleOptions" placeholder="Select role" class="w-full [&>button]:h-9" />
                        <x-input-error :messages="$errors->get('roleName')" class="mt-1" />
                    </div>
                    <div class="min-w-0">
                        <label for="add-member-password" class="block font-semibold text-ink mb-1">Password</label>
                        <x-ui.password-input id="add-member-password" wire:model="password" autocomplete="new-password" placeholder="Enter password" />
                        <p class="mt-1 text-[10px] text-muted">Leave blank to generate automatically.</p>
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                    <button type="button" x-on:click="$dispatch('close-modal', 'add-team-member')" class="px-3 py-1.5 rounded-lg border border-border text-xs font-semibold text-ink hover:bg-canvas transition cursor-pointer">
                        Cancel
                    </button>
                    <x-ui.button wire:click="addMember" variant="primary" title="Add Team Member" aria-label="Add Team Member" class="inline-flex items-center justify-center gap-2" wire:loading.attr="disabled">
                        <x-ui.team-icon name="add" /><span>Create &amp; Add Member</span>
                    </x-ui.button>
                </div>

                @if($generatedInviteLink)
                    <div class="p-3.5 bg-surface border border-border rounded-xl space-y-2 mt-4 shadow-xs">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span class="text-[11px] font-bold text-ink flex items-center gap-1.5">
                                <x-ui.team-icon name="link" class="text-primary" />
                                <span>Activation link</span>
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
                                title="Copy activation link" aria-label="Copy activation link" x-data="{ copied: false }"
                                @click="navigator.clipboard.writeText('{{ $generatedInviteLink }}'); copied = true; $wire.dispatch('toast', { type: 'success', title: 'Copied', message: 'Activation link copied to clipboard.' }); setTimeout(function() { copied = false; }, 2000)"
                                class="px-3 h-8 bg-primary hover:bg-primary-hover text-white rounded-lg hover:bg-neutral-800 text-xs font-bold transition shrink-0 flex items-center gap-1.5 shadow-xs cursor-pointer"
                            >
                                <x-ui.team-icon name="copy" x-show="!copied" />
                                <x-ui.team-icon name="check" x-show="copied" class="text-success" />
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </x-modal>

    <!-- Team Members Table Component -->
    <div class="space-y-2">
        <x-ui.advanced-table
            :columns="$this->tableColumns()"
            :rows="$members"
            :visibleColumns="$visibleColumns"
            :sortField="$sortField"
            :sortDirection="$sortDirection"
            :showFilterDropdown="false"
            :iconOnlyActions="true"
            searchPlaceholder="Search team members by name, email, ID..."
            emptyTitle="No Sales Team Members Found"
            emptyMessage="No sales team members registered yet."
        />
    </div>
    </section>
    <x-modal name="team-member-editor" :show="$showMemberEditor" maxWidth="xl">
        <form wire:submit="saveTeamMember" class="p-6 space-y-4">
            <div class="flex justify-between items-center"><h2 class="font-bold text-ink">Edit Team Member</h2><button type="button" wire:click="$set('showMemberEditor',false)" x-on:click="$dispatch('close-modal','team-member-editor')" aria-label="Close member editor"><x-ui.team-icon name="close" /></button></div>
            <label class="block text-xs text-ink">Name<x-text-input variant="compact" wire:model="editMemberName" /></label>
            <label class="block text-xs text-ink">Email<x-text-input variant="compact" type="email" wire:model="editMemberEmail" /></label>
            <label class="block text-xs text-ink">Phone<x-text-input variant="compact" wire:model="editMemberPhone" /></label>
            <div class="text-xs"><label class="block mb-1">Assigned Team</label><x-ui.themed-select wire:model="moveToTeamId" :options="$teams" :searchable="true" class="w-full" /></div>
            <p class="text-xs text-muted">Only teams you manage are available. Use Team Leads to reassign individual leads.</p>
            @if($errors->any())<p class="text-xs text-danger">{{ $errors->first() }}</p>@endif
            <button type="submit" wire:loading.attr="disabled" title="Save Member" aria-label="Save Member" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg text-xs font-semibold"><x-ui.team-icon name="check" /><span>Save Member</span></button>
        </form>
    </x-modal>
</div>
