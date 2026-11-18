<?php
namespace App\Livewire\Admin;

use App\Models\{AuditLog, User, Organization, SalesTeam, SalesTeamMember};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, Rules\Password};
use Livewire\Attributes\{Locked, On};
use Livewire\Component;

class OrganizationMemberDetails extends Component
{
    #[Locked] public int $organizationId;
    #[Locked] public ?int $memberId = null;
    public bool $show = false;
    public bool $editing = false;
    public bool $changingPassword = false;
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $status = 'active';
    public string $password = '';
    public string $password_confirmation = '';
    public ?int $leadershipTeamId = null;

    public function assignMemberToTeam(): void
    {
        $member = $this->member();
        abort_unless($member->hasAnyRole(['Executive', 'Sales Executive', 'sales-executive']) && !$member->hasAnyRole(['Team Leader', 'Super Admin', 'super-admin', 'Builder/Developer', 'Builder']), 403);
        $this->validate(['leadershipTeamId' => 'required|integer']);
        DB::transaction(function () use ($member) {
            $team = $this->teams()->with('leader')->lockForUpdate()->find($this->leadershipTeamId);
            abort_unless($team && $team->leader && $team->leader->organization_id === $this->organizationId && $team->leader->status === 'active' && $team->leader->hasRole('Team Leader'), 404);
            $previous = SalesTeamMember::whereIn('sales_team_id', $this->teams()->select('id'))->where('user_id', $member->id)->where('is_active', true)->pluck('sales_team_id')->all();
            SalesTeamMember::whereIn('sales_team_id', $this->teams()->select('id'))->where('user_id', $member->id)->where('sales_team_id', '!=', $team->id)->update(['is_active' => false]);
            SalesTeamMember::updateOrCreate(['sales_team_id' => $team->id, 'user_id' => $member->id], ['role' => 'Executive', 'is_active' => true]);
            $this->audit($member, 'organization.member_team_assigned', ['team_ids' => $previous], ['team_id' => $team->id, 'leader_id' => $team->leader_id]);
        });
        $this->dispatch('org-member-updated');
        $this->dispatch('toast', type: 'success', message: 'Member assigned to the selected Team Leader.');
    }

    private function teams()
    {
        return SalesTeam::where(fn ($q) => $q->where(fn ($q) => $q->where('ownable_type', Organization::class)->where('ownable_id', $this->organizationId))
            ->orWhere(fn ($q) => $q->where('ownable_type', User::class)->whereIn('ownable_id', User::where('organization_id', $this->organizationId)->select('id'))));
    }

    public function assignTeamLeader(): void
    {
        $member = $this->member();
        abort_unless($member->status === 'active' && $member->hasAnyRole(['Executive', 'Sales Executive', 'sales-executive', 'Team Leader', 'team-leader'])
            && !$member->hasAnyRole(['Super Admin', 'super-admin', 'Builder/Developer', 'Builder']), 403);
        $this->validate(['leadershipTeamId' => 'required|integer']);
        DB::transaction(function () use ($member) {
            $team = $this->teams()->lockForUpdate()->find($this->leadershipTeamId);
            abort_unless($team, 404);
            $before = ['leader_id' => $team->leader_id];
            if ($team->leader_id && $team->leader_id !== $member->id) {
                SalesTeamMember::where('sales_team_id', $team->id)->where('user_id', $team->leader_id)->update(['role' => 'Executive']);
            }
            $member->syncRoles('Team Leader');
            SalesTeamMember::updateOrCreate(['sales_team_id' => $team->id, 'user_id' => $member->id], ['role' => 'Team Leader', 'is_active' => true]);
            $team->update(['leader_id' => $member->id]);
            $this->audit($member, 'organization.team_leader_assigned', $before, ['team_id' => $team->id, 'leader_id' => $member->id]);
        });
        $this->dispatch('org-member-updated');
        $this->dispatch('toast', type: 'success', message: 'Team Leader assigned.');
    }

    public function boot(): void
    {
        abort_unless(auth()->user()?->status === 'active' && auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
    }

    private function member(): User
    {
        $member = User::where('organization_id', $this->organizationId)->find($this->memberId);
        abort_unless($member, 404);
        return $member;
    }

    #[On('open-org-member')]
    public function open(int $memberId): void
    {
        $this->memberId = $memberId;
        $member = $this->member();
        $this->resetValidation();
        $this->reset('password', 'password_confirmation', 'editing', 'changingPassword');
        foreach (['name', 'email', 'phone', 'status'] as $field) $this->$field = (string) $member->$field;
        $this->leadershipTeamId = $this->teams()->whereHas('members', fn ($q) => $q->where('user_id', $member->id)->where('is_active', true))->value('id');
        $this->show = true;
        $this->dispatch('open-modal', 'org-member-details');
    }

    public function close(): void
    {
        $this->reset('show', 'editing', 'changingPassword', 'password', 'password_confirmation');
        $this->dispatch('close-modal', 'org-member-details');
    }

    public function saveDetails(): void
    {
        $member = $this->member();
        $data = $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($member->id)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($member->id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $data['phone'] = $data['phone'] ?: null;
        DB::transaction(function () use ($member, $data) {
            $before = $member->only(array_keys($data));
            if ($member->email !== $data['email']) $data['email_verified_at'] = null;
            $member->update($data);
            $this->audit($member, 'organization.member_updated', $before, $member->only(['name', 'email', 'phone', 'status']));
        });
        $this->editing = false;
        $this->dispatch('org-member-updated');
        $this->dispatch('toast', type: 'success', message: 'Member details saved.');
    }

    public function changePassword(): void
    {
        $member = $this->member();
        $this->validate(['password' => ['required', 'string', Password::defaults(), 'confirmed']]);
        DB::transaction(function () use ($member) {
            $member->update(['password' => Hash::make($this->password), 'remember_token' => Str::random(60)]);
            $this->audit($member, 'organization.member_password_changed', [], ['password_changed' => true]);
        });
        $this->reset('password', 'password_confirmation', 'changingPassword');
        $this->dispatch('password-updated');
        $this->dispatch('toast', type: 'success', message: 'Member password changed.');
    }

    private function audit(User $member, string $action, array $before, array $after): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'organization_id' => $this->organizationId,
            'action' => $action, 'subject_type' => User::class, 'subject_id' => $member->id,
            'from_value' => json_encode($before), 'to_value' => json_encode($after), 'created_at' => now()]);
    }

    public function render()
    {
        return view('livewire.admin.organization-member-details', ['member' => $this->show ? $this->member() : null,
            'teams' => $this->show ? $this->teams()->with('leader')->whereHas('leader', fn ($q) => $q->where('organization_id', $this->organizationId)->where('status', 'active')->role('Team Leader'))->get() : collect()]);
    }
}
