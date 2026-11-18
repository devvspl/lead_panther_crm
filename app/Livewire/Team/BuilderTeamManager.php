<?php

namespace App\Livewire\Team;

use Livewire\Component;
use App\Models\User;
use App\Models\SalesTeamMember;
use App\Models\SalesTeam;
use App\Models\Organization;
use App\Models\ChannelPartner;
use App\Models\Project;
use App\Models\Client;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Livewire\Concerns\HasAdvancedTable;

class BuilderTeamManager extends Component
{
    use HasAdvancedTable;

    public string $memberName = '';
    public string $memberEmail = '';
    public string $roleName = 'Executive';
    public string $password = '';
    public ?string $generatedInviteLink = null;
    public ?int $teamId = null;
    public string $teamName = '';
    public ?int $teamLeaderId = null;
    public ?int $existingMemberId = null;
    public string $existingMemberRole = 'Executive';
    #[\Livewire\Attributes\Locked] public ?int $editingMemberId = null;
    public bool $showMemberEditor = false;
    public string $editMemberName = '';
    public string $editMemberEmail = '';
    public string $editMemberPhone = '';
    public ?int $moveToTeamId = null;

    private function managedMember(int $id): User
    {
        $this->guard();
        $team = $this->teams()->find($this->teamId);
        abort_unless($team && SalesTeamMember::where('sales_team_id',$team->id)->where('user_id',$id)->exists(),403);
        $member = User::where('organization_id',auth()->user()->organization_id)->findOrFail($id);
        abort_unless($member->hasAnyRole(['Executive','Sales Executive','sales-executive']) && !$member->hasAnyRole(['Super Admin','super-admin','Builder/Developer','Builder','builder','builder-admin','Team Leader','team-leader']),403);
        return $member;
    }

    public function editTeamMember(int $id): void
    {
        $member = $this->managedMember($id);
        $this->editingMemberId = $id;
        $this->editMemberName = $member->name;
        $this->editMemberEmail = $member->email;
        $this->editMemberPhone = $member->phone ?? '';
        $this->moveToTeamId = $this->teamId;
        $this->resetValidation();
        $this->showMemberEditor = true;
        $this->dispatch('open-modal','team-member-editor');
    }

    public function saveTeamMember(): void
    {
        $member = $this->managedMember($this->editingMemberId ?? 0);
        $this->validate(['editMemberName'=>'required|string|max:255','editMemberEmail'=>['required','email',\Illuminate\Validation\Rule::unique('users','email')->ignore($member->id)],
            'editMemberPhone'=>['nullable','string','max:30',\Illuminate\Validation\Rule::unique('users','phone')->ignore($member->id)],'moveToTeamId'=>'required|integer']);
        abort_unless($this->teams()->whereKey($this->moveToTeamId)->exists(),403);
        \Illuminate\Support\Facades\DB::transaction(function () use ($member) {
            $before = $member->only(['name','email','phone']);
            $data = ['name'=>$this->editMemberName,'email'=>$this->editMemberEmail,'phone'=>$this->editMemberPhone ?: null];
            if ($member->email !== $data['email']) $data['email_verified_at'] = null;
            $member->update($data);
            if ($this->moveToTeamId !== $this->teamId) {
                SalesTeamMember::where('sales_team_id',$this->teamId)->where('user_id',$member->id)->delete();
                SalesTeamMember::updateOrCreate(['sales_team_id'=>$this->moveToTeamId,'user_id'=>$member->id],['role'=>'Executive','is_active'=>true]);
            }
            $this->memberAudit($member,'team.member_updated',$before,['name'=>$member->name,'email'=>$member->email,'phone'=>$member->phone,'team_id'=>$this->moveToTeamId]);
        });
        $this->showMemberEditor = false;
        $this->dispatch('close-modal','team-member-editor');
        $this->dispatch('toast',type:'success',message:'Team member updated.');
    }

    public function removeTeamMember(int $id): void
    {
        $member = $this->managedMember($id);
        if (\App\Models\Lead::withoutGlobalScopes()->where('assigned_to',$id)->exists()) {
            $this->addError('teamMember','Reassign this member’s leads before removing their membership.');
            return;
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($member) {
            SalesTeamMember::where('sales_team_id',$this->teamId)->where('user_id',$member->id)->delete();
            $this->memberAudit($member,'team.member_removed',['team_id'=>$this->teamId],[]);
        });
        $this->dispatch('toast',type:'success',message:'Membership removed. The user account was preserved.');
    }

    private function memberAudit(User $member, string $action, array $before, array $after): void
    {
        \App\Models\AuditLog::create(['user_id'=>auth()->id(),'organization_id'=>$member->organization_id,'action'=>$action,'subject_type'=>User::class,'subject_id'=>$member->id,
            'from_value'=>json_encode($before),'to_value'=>json_encode($after),'created_at'=>now()]);
    }

    private function guard(): void {
        $user=auth()->user();
        abort_unless($user?->is_active && $user->organization_id && ($user->hasAnyRole(['Super Admin','Builder/Developer','Builder','builder']) || (\App\Support\TeamAccess::isLeader($user) && $user->can('manage-team-members'))),403);
    }
    private function teams() {
        $this->guard(); $user=auth()->user();
        return \App\Support\TeamAccess::isLeader($user) ? \App\Support\TeamAccess::teams($user) : SalesTeam::where('ownable_type',Organization::class)->where('ownable_id',$user->organization_id);
    }
    public function mount(): void { $this->guard(); $this->teamId=$this->teams()->orderBy('id')->value('id'); $this->updatedTeamId(); }
    public function updatedTeamId(): void {
        $team=$this->teamId ? $this->teams()->findOrFail($this->teamId) : null;
        $this->teamName=$team?->name ?? ''; $this->teamLeaderId=$team?->leader_id; $this->existingMemberId=null;
    }
    public function saveTeam(): void {
        $this->guard(); $actor=auth()->user();
        abort_if(\App\Support\TeamAccess::isLeader($actor),403);
        abort_unless($actor->hasRole('Super Admin') || $actor->can('assign-team-leaders'),403);
        $this->validate(['teamName'=>'required|string|max:120','teamLeaderId'=>'nullable|integer']);
        if ($this->teamLeaderId) abort_unless(User::where('organization_id',$actor->organization_id)->where('status','active')->role('Team Leader')->whereKey($this->teamLeaderId)->exists(),403);
        $team=$this->teamId ? $this->teams()->findOrFail($this->teamId) : new SalesTeam(['ownable_type'=>Organization::class,'ownable_id'=>$actor->organization_id]);
        \Illuminate\Support\Facades\DB::transaction(function () use($team) {
            $team->fill(['name'=>$this->teamName,'leader_id'=>$this->teamLeaderId])->save();
            if ($this->teamLeaderId) SalesTeamMember::updateOrCreate(['sales_team_id'=>$team->id,'user_id'=>$this->teamLeaderId],['role'=>'Team Leader','is_active'=>true]);
        });
        $this->teamId=$team->id; $this->dispatch('toast',type:'success',message:'Team and leader saved.');
    }
    public function newTeam(): void { $this->guard(); abort_if(\App\Support\TeamAccess::isLeader(auth()->user()),403); $this->teamId=null; $this->teamName=''; $this->teamLeaderId=null; }
    public function assignExistingMember(): void {
        $this->guard(); $actor=auth()->user(); abort_if(\App\Support\TeamAccess::isLeader($actor),403);
        $team=$this->teams()->findOrFail($this->teamId);
        $this->validate(['existingMemberId'=>'required|integer','existingMemberRole'=>'required|in:Executive,Team Leader,Channel Partner']);
        if ($this->existingMemberRole==='Team Leader') abort_unless($actor->hasRole('Super Admin') || $actor->can('assign-team-leaders'),403);
        $member=User::where('organization_id',$actor->organization_id)->findOrFail($this->existingMemberId);
        abort_if($member->hasAnyRole(['Super Admin','Builder/Developer','Builder']),403);
        \Illuminate\Support\Facades\DB::transaction(function () use($member,$team) {
            $member->syncRoles($this->existingMemberRole);
            SalesTeamMember::updateOrCreate(['sales_team_id'=>$team->id,'user_id'=>$member->id],['role'=>$this->existingMemberRole,'is_active'=>true]);
            if ($team->leader_id===$member->id && $this->existingMemberRole!=='Team Leader') $team->update(['leader_id'=>null]);
            if ($this->existingMemberRole==='Team Leader') $team->update(['leader_id'=>$member->id]);
        });
        $this->updatedTeamId(); $this->dispatch('toast',type:'success',message:'Member role and team assignment updated.');
    }
    public function toggleMember(int $userId): void {
        $this->guard(); $team=$this->teams()->findOrFail($this->teamId);
        $member=User::where('organization_id',auth()->user()->organization_id)->findOrFail($userId);
        if (\App\Support\TeamAccess::isLeader(auth()->user())) $this->managedMember($userId);
        $membership=SalesTeamMember::where('sales_team_id',$team->id)->where('user_id',$userId)->firstOrFail();
        if ($membership->is_active && \App\Support\TeamAccess::isLeader(auth()->user()) && \App\Models\Lead::withoutGlobalScopes()->where('assigned_to',$userId)->exists()) {
            $this->addError('teamMember','Reassign this member’s leads before deactivating their membership.');
            return;
        }
        $membership->update(['is_active'=>! $membership->is_active]);
        $this->memberAudit($member,'team.membership_updated',[],['team_id'=>$team->id,'is_active'=>(bool)$membership->is_active]);
        $this->dispatch('toast',type:'success',message:'Team membership status updated.');
    }

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'Member ID', 'prefix' => '#', 'class' => 'font-mono font-bold text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'name', 'label' => 'Name', 'class' => 'font-bold text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'email', 'label' => 'Email', 'class' => 'font-mono text-muted text-xs', 'sortable' => true, 'priority' => 1],
            ['key'=>'status','label'=>'Account Status','type'=>'badge','sortable'=>false,'priority'=>1,'badgeStyle'=>fn ($value) => strtolower($value)==='active' ? 'bg-success/10 text-success border border-success/20' : 'bg-warning/10 text-warning border border-warning/20'],
            ['key'=>'membership_status','label'=>'Membership','type'=>'badge','sortable'=>false,'priority'=>1,'badgeStyle'=>fn ($value) => strtolower($value)==='active' ? 'bg-success/10 text-success border border-success/20' : 'bg-warning/10 text-warning border border-warning/20'],
            ['key'=>'member_actions','label'=>'Actions','sortable'=>false,'priority'=>1,'render'=>function ($row) {
                return view('components.ui.team-member-actions',['member'=>$row])->render();
            }],
            ['key' => 'role', 'label' => 'Role', 'type' => 'badge', 'sortable' => false, 'priority' => 1, 'badgeStyle' => fn () => 'bg-primary/10 text-primary border border-primary/20', 'default' => 'Executive'],
            ['key' => 'assigned_leads_count', 'label' => 'Assigned', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-ink text-center'],
            ['key' => 'contacted_leads_count', 'label' => 'Contacted', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-primary text-center'],
            ['key' => 'followups_count', 'label' => 'Follow-ups', 'type' => 'text', 'sortable' => true, 'priority' => 2, 'class' => 'font-mono text-ink text-center'],
            ['key' => 'site_visits_count', 'label' => 'Site Visits', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-primary text-center'],
            ['key' => 'bookings_count', 'label' => 'Bookings Won', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-success text-center'],
        ];
    }

    public function addMember(): void
    {
        $this->guard();
        $this->validate([
            'memberName' => 'required|string|max:255',
            'memberEmail' => 'required|email|unique:users,email',
            'roleName' => 'required|in:Executive,Channel Partner,Team Leader',
        ], [
            'memberName.required' => 'The member name field is required.',
            'memberEmail.required' => 'The member email field is required.',
            'memberEmail.unique' => 'This email is already registered.',
            'roleName.required' => 'Please select a role.',
            'roleName.in' => 'Choose Sales Executive, Team Leader or Channel Partner.',
        ]);

        $orgId = auth()->user()->organization_id;
        if ($this->roleName==='Team Leader') abort_unless(auth()->user()->hasRole('Super Admin') || auth()->user()->can('assign-team-leaders'),403);
        if (\App\Support\TeamAccess::isLeader(auth()->user())) abort_unless($this->roleName==='Executive',403);
        $selectedTeam=$this->teamId ? $this->teams()->findOrFail($this->teamId) : null;
        if (! $selectedTeam && \App\Support\TeamAccess::isLeader(auth()->user())) abort(403);

        $user = User::create([
            'name' => $this->memberName,
            'email' => $this->memberEmail,
            'password' => bcrypt($this->password ?: Str::random(16)),
            'organization_id' => $orgId,
        ]);

        $user->assignRole($this->roleName);

        // If Channel Partner, also register in channel_partners table
        if ($this->roleName === 'Channel Partner') {
            $project = Project::where('client_id', auth()->id())
                ->orWhere('client_id', $orgId)
                ->first();

            if (!$project) {
                $project = Project::first();
            }

            if (!$project) {
                $client = Client::firstOrCreate(
                    ['id' => $orgId],
                    ['name' => auth()->user()?->organization?->name ?? 'Default Organization', 'status' => 'active']
                );
                $project = Project::create([
                    'client_id' => $client->id,
                    'name' => 'Main Development Project',
                    'status' => 'active',
                ]);
            }

            ChannelPartner::firstOrCreate([
                'organization_id' => $orgId,
                'name' => $this->memberName,
            ], [
                'project_id' => $project->id,
                'status' => 'active',
            ]);
        }

        $team = $selectedTeam ?? SalesTeam::firstOrCreate([
            'ownable_type' => Organization::class,
            'ownable_id' => $orgId,
            'name' => 'Direct Builder Sales Team',
        ]);

        SalesTeamMember::firstOrCreate([
            'sales_team_id' => $team->id,
            'user_id' => $user->id,
        ], [
            'role' => $this->roleName,
            'is_active' => true,
        ]);

        if ($this->roleName==='Team Leader') $team->update(['leader_id'=>$user->id]);
        $this->teamId=$team->id;
        // Generate temporary password activation link
        $token = Password::createToken($user);
        $this->generatedInviteLink = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        $assignedRole = $this->roleName;
        $this->reset(['memberName', 'memberEmail', 'password']);
        $this->roleName = 'Executive';
        $this->dispatch('toast', type: 'success', message: "Team member '{$user->name}' added successfully as {$assignedRole}.");
    }

    public function render()
    {
        $this->guard();
        $orgId = auth()->user()?->organization_id;
        $accessibleTeams = $this->teams()->orderBy('name')->get();
        if (!$this->teamId && $accessibleTeams->isNotEmpty()) {
            $this->teamId = $accessibleTeams->first()->id;
            $this->updatedTeamId();
        }
        $query = User::where('organization_id', $orgId)
            ->with('roles')
            ->withCount([
                'assignedLeads as assigned_leads_count',
                'assignedLeads as contacted_leads_count' => fn($q) => $q->whereIn('current_stage', ['contacted', 'connected', 'qualified', 'interested', 'site_visit', 'negotiation', 'booking', 'closed_won']),
                'assignedLeads as followups_count' => fn($q) => $q->whereHas('followups'),
                'assignedLeads as site_visits_count' => fn($q) => $q->where(fn($sq) => $sq->whereHas('siteVisits')->orWhere('current_stage', 'site_visit')),
                'assignedLeads as bookings_count' => fn($q) => $q->where(fn($sq) => $sq->whereHas('bookings')->orWhereIn('current_stage', ['booking', 'closed_won'])),
            ]);

        if ($this->teamId) {
            $team=$this->teams()->findOrFail($this->teamId);
            $query->whereIn('id',SalesTeamMember::where('sales_team_id',$team->id)->select('user_id'));
        } elseif (\App\Support\TeamAccess::isLeader(auth()->user())) $query->whereRaw('1=0');
        if (\App\Support\TeamAccess::isLeader(auth()->user())) $query->whereIn('id',\App\Support\TeamAccess::executives(auth()->user(),false)->select('id'));
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
                  ->orWhere('id', 'like', "%{$this->search}%");
            });
        }

        $sortField = in_array($this->sortField, ['id', 'name', 'email', 'assigned_leads_count', 'contacted_leads_count', 'followups_count', 'site_visits_count', 'bookings_count']) ? $this->sortField : 'id';
        $sortDir = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $members = $query->orderBy($sortField, $sortDir)->paginate($this->perPage);
        $membershipStates = SalesTeamMember::where('sales_team_id',$this->teamId)->pluck('is_active','user_id');
        $members->getCollection()->each(function ($member) use ($membershipStates) {
            $member->membership_status = ($membershipStates[$member->id] ?? false) ? 'Active' : 'Inactive';
            $member->role = $member->getRoleNames()->join(', ');
        });

        return view('livewire.team.builder-team-manager', [
            'members' => $members,
            'teams'=>$accessibleTeams->pluck('name','id')->all(),
            'leaders'=>\App\Support\TeamAccess::isLeader(auth()->user()) ? [] : User::where('organization_id',$orgId)->where('status','active')->role('Team Leader')->pluck('name','id')->all(),
            'organizationMembers'=>\App\Support\TeamAccess::isLeader(auth()->user()) ? [] : User::where('organization_id',$orgId)->whereDoesntHave('roles',fn($q)=>$q->whereIn('name',['Super Admin','Builder/Developer','Builder','builder']))->pluck('name','id')->all(),
            'roleOptions'=>\App\Support\TeamAccess::isLeader(auth()->user()) ? ['Executive'=>'Sales Executive'] : ['Executive'=>'Sales Executive','Team Leader'=>'Team Leader','Channel Partner'=>'Channel Partner'],
        ])->layout('layouts.app');
    }
}
