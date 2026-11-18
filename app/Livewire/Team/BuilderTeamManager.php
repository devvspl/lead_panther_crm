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
        if (\App\Support\TeamAccess::isLeader(auth()->user())) abort_unless($member->hasRole('Executive') && ! $member->hasRole('Team Leader'),403);
        $membership=SalesTeamMember::where('sales_team_id',$team->id)->where('user_id',$userId)->firstOrFail();
        $membership->update(['is_active'=>! $membership->is_active]);
        $this->dispatch('toast',type:'success',message:'Team membership status updated.');
    }

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'Member ID', 'prefix' => '#', 'class' => 'font-mono font-bold text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'name', 'label' => 'Name', 'class' => 'font-bold text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'email', 'label' => 'Email', 'class' => 'font-mono text-muted text-xs', 'sortable' => true, 'priority' => 1],
            ['key' => 'role', 'label' => 'Role', 'type' => 'badge', 'sortable' => false, 'priority' => 1, 'badgeMap' => [
                'Super Admin' => 'bg-purple-50 text-purple-700 border border-purple-200',
                'Builder/Developer' => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
                'Channel Partner' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'Broker' => 'bg-cyan-50 text-cyan-700 border border-cyan-200',
                'Executive' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'Sales Executive' => 'bg-blue-50 text-blue-700 border border-blue-200',
            ], 'default' => 'Executive'],
            ['key' => 'assigned_leads_count', 'label' => 'Assigned', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-ink text-center'],
            ['key' => 'contacted_leads_count', 'label' => 'Contacted', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-blue-600 text-center'],
            ['key' => 'followups_count', 'label' => 'Follow-ups', 'type' => 'text', 'sortable' => true, 'priority' => 2, 'class' => 'font-mono text-ink text-center'],
            ['key' => 'site_visits_count', 'label' => 'Site Visits', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-purple-600 text-center'],
            ['key' => 'bookings_count', 'label' => 'Bookings Won', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-emerald-600 text-center'],
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

        return view('livewire.team.builder-team-manager', [
            'members' => $members,
            'teams'=>$accessibleTeams->pluck('name','id')->all(),
            'leaders'=>\App\Support\TeamAccess::isLeader(auth()->user()) ? [] : User::where('organization_id',$orgId)->where('status','active')->role('Team Leader')->pluck('name','id')->all(),
            'organizationMembers'=>\App\Support\TeamAccess::isLeader(auth()->user()) ? [] : User::where('organization_id',$orgId)->whereDoesntHave('roles',fn($q)=>$q->whereIn('name',['Super Admin','Builder/Developer','Builder','builder']))->pluck('name','id')->all(),
            'roleOptions'=>\App\Support\TeamAccess::isLeader(auth()->user()) ? ['Executive'=>'Sales Executive'] : ['Executive'=>'Sales Executive','Team Leader'=>'Team Leader','Channel Partner'=>'Channel Partner'],
        ])->layout('layouts.app');
    }
}
