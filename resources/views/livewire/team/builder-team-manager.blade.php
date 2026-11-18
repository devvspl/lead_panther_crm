<div class="space-y-6">
    <livewire:settings.lead-assignment-configuration :organization-id="auth()->user()->organization_id" />
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">{{ \App\Support\TeamAccess::isLeader(auth()->user()) ? 'My Team Management' : 'Builder Direct Team Management' }}</h1>
            <p class="text-xs text-muted">Manage team leadership, executive membership and assignments within your organization.</p>
        </div>
    </div>

    <div class="bg-surface rounded-card border border-border p-5 space-y-4 text-xs">
        @if(\App\Support\TeamAccess::isLeader(auth()->user()) && empty($teams))
            <p role="status" class="rounded-lg border border-border bg-canvas p-3 text-sm text-ink">Your team access is not active. Ask Super Admin to open a team executive in Client Details, choose your name under Team Leader and click Save Team Leader to confirm the membership link.</p>
        @endif
        <div class="flex items-end gap-3"><div class="flex-1"><label class="block font-semibold mb-1">Sales team</label><livewire:shared.searchable-select wire:model.live="teamId" :value="$teamId" :options="$teams" placeholder="Select team" class="block w-full" :key="'managed-teams-'.($teamId ?? 'none').'-'.md5(json_encode($teams))" /></div>@unless(\App\Support\TeamAccess::isLeader(auth()->user()))<button wire:click="newTeam" class="border border-border rounded-lg px-3 py-2">New Team</button>@endunless</div>
        @unless(\App\Support\TeamAccess::isLeader(auth()->user()))
        <div class="grid sm:grid-cols-3 gap-3 items-end"><label>Team name<input wire:model="teamName" class="block w-full border border-border bg-canvas rounded-lg px-3 py-2 mt-1"></label><div><label class="block mb-1">Team Leader</label><livewire:shared.searchable-select wire:model.live="teamLeaderId" :options="$leaders" placeholder="Select Team Leader" class="block w-full" :key="'team-leader-'.$teamId" /></div><button wire:click="saveTeam" class="rounded-lg bg-ink text-white px-3 py-2">Save Team &amp; Leader</button></div>
        @if($teamId)<div class="grid sm:grid-cols-3 gap-3 items-end border-t border-border pt-3"><div><label class="block mb-1">Existing organization member</label><livewire:shared.searchable-select wire:model.live="existingMemberId" :options="$organizationMembers" placeholder="Select member" class="block w-full" :key="'existing-member-'.$teamId" /></div><div><label class="block mb-1">Assign role</label><x-ui.themed-select wire:model="existingMemberRole" :options="$roleOptions" class="w-full" /></div><button wire:click="assignExistingMember" class="border border-border rounded-lg px-3 py-2">Update Role &amp; Add to Team</button></div>@endif
        @endunless
        @if($teamId)<div class="flex flex-wrap gap-2 border-t border-border pt-3">@foreach($members as $member)@if(!\App\Support\TeamAccess::isLeader(auth()->user()) || $member->hasRole('Executive'))<button wire:click="toggleMember({{ $member->id }})" class="border border-border rounded-lg px-2 py-1">{{ $member->name }}: {{ \App\Models\SalesTeamMember::where('sales_team_id',$teamId)->where('user_id',$member->id)->value('is_active') ? 'Deactivate membership' : 'Activate membership' }}</button>@endif @endforeach</div>@endif
        @if($errors->any())<p class="text-red-600">{{ $errors->first() }}</p>@endif
    </div>
    <!-- Add Member Form -->
    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Add Team Member</h2>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
            <div>
                <label class="font-bold text-ink">Full Name <span class="text-red-500">*</span></label>
                <input 
                    type="text" 
                    wire:model="memberName" 
                    placeholder="e.g. Vikram Sethi"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1"
                >
                @error('memberName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="font-bold text-ink">Email Address <span class="text-red-500">*</span></label>
                <input 
                    type="email" 
                    wire:model="memberEmail" 
                    placeholder="vikram@builder.com"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1"
                >
                @error('memberEmail') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="font-bold text-ink">Role <span class="text-red-500">*</span></label>
                <x-ui.themed-select 
                    wire:model="roleName" 
                    :options="$roleOptions"
                    placeholder="Select Role" 
                    class="w-full mt-1" 
                />
                @error('roleName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="font-bold text-ink">Password <span class="text-muted font-normal">(Auto-set if blank)</span></label>
                <input 
                    type="text" 
                    wire:model="password" 
                    placeholder="Set custom password"
                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition mt-1"
                >
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <x-ui.button wire:click="addMember" variant="primary" class="text-xs">
                Add Team Member
            </x-ui.button>
        </div>

        @if($generatedInviteLink)
            <div class="p-3.5 bg-surface border border-border rounded-xl space-y-2 mt-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-ink flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                        <span>Team Member Created — Shareable Activation Link</span>
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

    <!-- Team Members Table Component -->
    <div class="space-y-2">
        <h2 class="text-xs font-bold text-ink uppercase tracking-wider">Direct Team Roster</h2>

        <x-ui.advanced-table 
            :columns="$this->tableColumns()"
            :rows="$members"
            :visibleColumns="$visibleColumns"
            :sortField="$sortField"
            :sortDirection="$sortDirection"
            :showFilterDropdown="false"
            searchPlaceholder="Search team members by name, email, ID..."
            emptyTitle="No Sales Team Members Found"
            emptyMessage="No sales team members registered yet."
        />
    </div>
</div>
