<?php
namespace App\Livewire\Settings;
use App\Models\{Organization, User, Project, Campaign};
use App\Services\LeadDistributionService;
use App\Support\TeamAccess;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LeadAssignmentConfiguration extends Component
{
    #[Locked] public int $organizationId;
    public array $settings = [];
    private function organization(): Organization
    {
        $user = auth()->user();
        abort_unless($user?->is_active && ($user->hasRole('Super Admin') || ($user->organization_id === $this->organizationId
            && ($user->hasAnyRole(['Builder/Developer','Builder','builder']) || (TeamAccess::isLeader($user) && $user->can('view-team-leads'))))),403);
        return Organization::findOrFail($this->organizationId);
    }
    public function mount(int $organizationId): void
    {
        $this->organizationId = $organizationId;
        $org = $this->organization();
        $this->settings = app(LeadDistributionService::class)->assignmentDefaults($org);
        $this->settings['assignment_mode'] = $org->getLeadAssignmentMode();
        $this->settings['enabled'] = ($this->settings['assignment_mode'] === Organization::ASSIGNMENT_MODE_AUTOMATIC);

        $user = auth()->user();
        $isSuperAdmin = $user && ($user->hasRole('Super Admin') || $user->hasRole('super-admin'));
        $isBuilder = $user && $user->is_active && $user->organization_id === $this->organizationId
            && $user->hasAnyRole(['Builder/Developer','Builder','builder','builder-admin']);

        // Read-only team viewers must not receive other teams' member settings in Livewire's snapshot.
        if (!$isSuperAdmin && !$isBuilder) {
            if (TeamAccess::isLeader($user)) {
                $all = $this->settings;
                $this->settings = array_intersect_key($all, array_flip(['enabled','assignment_mode','method','daily_limit','batch_size','working_days','start_time','end_time']));
                $this->settings['members'] = [];
                foreach (TeamAccess::executives($user, false)->whereIn('id', \App\Models\SalesTeamMember::where('sales_team_id', $all['team_id'])->select('user_id'))->get() as $member) {
                    $this->settings['members'][$member->id] = ['available'=>$all['members'][$member->id]['available'] ?? true];
                }
            } else {
                $this->settings = [];
            }
            return;
        }
        foreach (app(LeadDistributionService::class)->assignmentExecutives($organizationId)->get() as $member) {
            $this->settings['members'][$member->id] ??= ['available'=>true,'priority'=>1];
        }
    }

    public function updatedSettingsAssignmentMode(string $value): void
    {
        $this->settings['enabled'] = ($value === Organization::ASSIGNMENT_MODE_AUTOMATIC);
    }

    public function updatedSettingsEnabled(bool $value): void
    {
        $this->settings['assignment_mode'] = $value ? Organization::ASSIGNMENT_MODE_AUTOMATIC : Organization::ASSIGNMENT_MODE_MANUAL;
    }

    public function setAssignmentMode(string $mode): void
    {
        if (in_array($mode, [Organization::ASSIGNMENT_MODE_AUTOMATIC, Organization::ASSIGNMENT_MODE_MANUAL])) {
            $this->settings['assignment_mode'] = $mode;
            $this->settings['enabled'] = ($mode === Organization::ASSIGNMENT_MODE_AUTOMATIC);
        }
    }
    public function save(): void
    {
        app(LeadDistributionService::class)->saveAssignmentConfiguration($this->organization(),$this->settings,auth()->user());
        $this->dispatch('toast',type:'success',message:'Lead assignment configuration saved.');
    }
    public function assignPending(): void
    {
        $org = $this->organization();
        abort_unless(auth()->user()->hasRole('Super Admin'),403);
        $count = app(LeadDistributionService::class)->processOrganizationAssignments($org->id);
        $this->dispatch('toast',type:'success',message:$count.' lead(s) assigned in this batch.');
        $this->dispatch('lead-updated');
    }
    public function render()
    {
        $org = $this->organization();
        $service = app(LeadDistributionService::class);
        $leader = TeamAccess::isLeader(auth()->user());
        $ids = $leader ? TeamAccess::executives(auth()->user(),false)->pluck('id')->all() : null;
        $projects = $org->projects()->withoutGlobalScopes()->pluck('projects.name','projects.id')->all();
        $effective = $service->assignmentDefaults($org);
        $activeTeams = $service->assignmentTeams($org->id)->whereHas('members', fn ($members) => $members
            ->where('is_active', true)->whereHas('user', fn ($users) => $users->where('status', 'active')
                ->where('organization_id', $org->id)->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['Executive','Sales Executive','sales-executive','sales_executive']))))
            ->orderBy('name')->get();
        $rosterTeamIds = $activeTeams->pluck('id')->when($effective['team_id'], fn ($teamIds) => $teamIds->filter(fn ($id) => (int) $id === (int) $effective['team_id']));
        $activeMemberships = \App\Models\SalesTeamMember::with('salesTeam')->whereIn('sales_team_id', $rosterTeamIds)
            ->where('is_active', true)->whereHas('user', fn ($users) => $users->where('status', 'active')->where('organization_id', $org->id))->get();
        $activeIds = $activeMemberships->pluck('user_id')->unique()->all();
        $ids = $ids === null ? $activeIds : array_values(array_intersect($ids, $activeIds));
        $ownsPendingPool = !$leader || (!empty($effective['team_id']) && TeamAccess::teams(auth()->user())->whereKey($effective['team_id'])->exists());
        if ($leader && $ownsPendingPool) $ids = array_values(array_intersect($ids, \App\Models\SalesTeamMember::where('sales_team_id', $effective['team_id'])->pluck('user_id')->all()));
        $user = auth()->user();
        $isBuilder = $user && $user->organization_id === $this->organizationId
            && $user->hasAnyRole(['Builder/Developer','Builder','builder','builder-admin']);

        return view('livewire.settings.lead-assignment-configuration',[
            'canConfigure'=>auth()->user()->hasRole('Super Admin') || $isBuilder || ($leader && $ownsPendingPool && auth()->user()->can('assign-team-leads')),
            'isBuilder'=>$isBuilder,
            'isTeamLeader'=>$leader,
            'teams'=>$activeTeams->pluck('name','id')->all(),
            'statistics'=>collect($service->assignmentStatistics($org,$ids))->map(function ($row) use ($activeMemberships) {
                $row['team'] = $activeMemberships->where('user_id', $row['id'])->pluck('salesTeam.name')->unique()->implode(', ');
                return $row;
            })->all(),
            'effective'=>$effective,
            'unassigned'=>$ownsPendingPool ? $service->assignmentPendingLeads($org->id)->count() : null,
            'projects'=>$projects,
            'campaigns'=>Campaign::withoutGlobalScopes()->whereIn('project_id',array_keys($projects))->pluck('name','id')->all(),
        ]);
    }
}
