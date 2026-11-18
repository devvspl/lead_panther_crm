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
        // Read-only team viewers must not receive other teams' member settings in Livewire's snapshot.
        if (!auth()->user()->hasRole('Super Admin')) return;
        $this->settings = app(LeadDistributionService::class)->assignmentDefaults($org);
        foreach (app(LeadDistributionService::class)->assignmentExecutives($organizationId)->get() as $member) {
            $this->settings['members'][$member->id] ??= ['available'=>true,'priority'=>1];
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
        $ownsPendingPool = !$leader || (!empty($effective['team_id']) && TeamAccess::teams(auth()->user())->whereKey($effective['team_id'])->exists());
        return view('livewire.settings.lead-assignment-configuration',[
            'canConfigure'=>auth()->user()->hasRole('Super Admin'),
            'teams'=>$service->assignmentTeams($org->id)->pluck('name','id')->all(),
            'statistics'=>$service->assignmentStatistics($org,$ids),
            'effective'=>$effective,
            'unassigned'=>$ownsPendingPool ? $service->assignmentPendingLeads($org->id)->count() : null,
            'projects'=>$projects,
            'campaigns'=>Campaign::withoutGlobalScopes()->whereIn('project_id',array_keys($projects))->pluck('name','id')->all(),
        ]);
    }
}
