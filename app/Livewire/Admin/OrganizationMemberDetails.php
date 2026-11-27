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
    public bool $editing = true;
    public bool $changingPassword = false;

    // Member Information Fields
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $userCode = '';
    public string $role = '';
    public ?int $teamId = null;
    public string $teamName = '';
    public ?int $teamLeaderId = null;
    public ?int $leadershipTeamId = null;
    public string $status = 'active';
    public string $joinedDate = '';

    // Password Update Fields
    public string $password = '';
    public string $password_confirmation = '';

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

    private function teams()
    {
        return SalesTeam::where(fn ($q) => $q->where(fn ($q) => $q->where('ownable_type', Organization::class)->where('ownable_id', $this->organizationId))
            ->orWhere(fn ($q) => $q->where('ownable_type', User::class)->whereIn('ownable_id', User::where('organization_id', $this->organizationId)->select('id'))));
    }

    #[On('open-org-member')]
    public function open(int $memberId): void
    {
        $this->memberId = $memberId;
        $member = $this->member();
        \App\Support\TeamAccess::ensureLeaderTeams($this->organizationId);
        $this->resetValidation();
        $this->reset('password', 'password_confirmation', 'changingPassword');

        $this->name = (string) $member->name;
        $this->email = (string) $member->email;
        $this->phone = (string) ($member->phone ?? '');
        $this->status = (string) ($member->status ?? 'active');
        $this->userCode = (string) ($member->user_code ?: ('LP' . str_pad($member->id, 5, '0', STR_PAD_LEFT)));
        $this->role = (string) ($member->roles->first()?->name ?? 'Executive');
        $this->joinedDate = $member->created_at ? $member->created_at->format('M d, Y') : '—';

        // Find active team membership or leadership
        $activeMembership = SalesTeamMember::whereIn('sales_team_id', $this->teams()->select('id'))
            ->where('user_id', $member->id)
            ->where('is_active', true)
            ->first();

        $team = null;
        if ($activeMembership) {
            $team = $this->teams()->with('leader')->find($activeMembership->sales_team_id);
        }
        if (!$team) {
            $team = $this->teams()->with('leader')->where('leader_id', $member->id)->first();
        }

        $this->teamId = $team?->id;
        $this->leadershipTeamId = $team?->id;
        $this->teamName = $team?->name ?? '';
        $this->teamLeaderId = $team?->leader_id;

        $this->show = true;
        $this->editing = true;
        $this->dispatch('open-modal', 'org-member-details');
    }

    public function updatedTeamLeaderId($value): void
    {
        $leaderId = $value ? (int)$value : null;
        if ($leaderId) {
            $team = $this->teams()->where('leader_id', $leaderId)->first();
            if ($team) {
                $this->teamId = $team->id;
                $this->leadershipTeamId = $team->id;
                $this->teamName = $team->name;
            }
        } else {
            $this->teamId = null;
            $this->leadershipTeamId = null;
            $this->teamName = '';
        }
    }

    public function updatedLeadershipTeamId($value): void
    {
        $teamId = $value ? (int)$value : null;
        $this->teamId = $teamId;
        if ($teamId) {
            $team = $this->teams()->with('leader')->find($teamId);
            $this->teamLeaderId = $team?->leader_id;
            $this->teamName = $team?->name ?? '';
        } else {
            $this->teamLeaderId = null;
            $this->teamName = '';
        }
    }

    public function updatedTeamId($value): void
    {
        $this->updatedLeadershipTeamId($value);
    }

    public function close(): void
    {
        $this->reset('show', 'changingPassword', 'password', 'password_confirmation');
        $this->editing = true;
        $this->dispatch('close-modal', 'org-member-details');
    }

    public function saveChanges(): void
    {
        $this->saveDetails();
    }

    public function saveDetails(): void
    {
        $member = $this->member();

        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($member->id)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($member->id)],
            'role' => 'required|string|max:100',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'teamLeaderId' => 'nullable|integer',
            'teamName' => 'nullable|string|max:255',
        ];

        if ($this->changingPassword && !empty($this->password)) {
            $rules['password'] = ['required', 'string', Password::defaults(), 'confirmed'];
        }

        $data = $this->validate($rules);
        $data['phone'] = $data['phone'] ?: null;

        DB::transaction(function () use ($member, $data) {
            $before = $member->only(['name', 'email', 'phone', 'status', 'user_code']);

            $updateFields = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'status' => $data['status'],
            ];

            if (empty($member->user_code)) {
                $member->allowUserCodeChange = true;
                $updateFields['user_code'] = User::generateUniqueUserCode();
                $this->userCode = $updateFields['user_code'];
            }

            if ($member->email !== $data['email']) {
                $updateFields['email_verified_at'] = null;
            }

            if ($this->changingPassword && !empty($this->password)) {
                $updateFields['password'] = Hash::make($this->password);
                $updateFields['remember_token'] = Str::random(60);
            }

            $member->update($updateFields);

            // Sync Role
            $roleName = $this->role;
            $role = \Spatie\Permission\Models\Role::where('name', $roleName)
                ->orWhere('name', strtolower(str_replace(' ', '-', $roleName)))
                ->first();
            if ($role) {
                $member->syncRoles([$role->name]);
            }

            // Handle Team Leader role
            if ($member->hasAnyRole(['Team Leader', 'team-leader', 'team_leader', 'Team-Leader'])) {
                \App\Support\TeamAccess::ensureLeaderTeams($this->organizationId);
                if (!empty($this->teamName)) {
                    $team = $this->teams()->where('leader_id', $member->id)->first();
                    if ($team) {
                        $team->update(['name' => $this->teamName]);
                    }
                }
            }

            // Handle Team Assignment for members
            if ($this->isExecutive) {
                $targetLeaderId = $this->teamLeaderId;
                if ($targetLeaderId) {
                    $team = $this->teams()->where('leader_id', $targetLeaderId)->first();
                    if (!$team && $this->teamId) {
                        $team = $this->teams()->find($this->teamId);
                    }
                    if ($team) {
                        SalesTeamMember::whereIn('sales_team_id', $this->teams()->select('id'))
                            ->where('user_id', $member->id)
                            ->where('sales_team_id', '!=', $team->id)
                            ->update(['is_active' => false]);

                        SalesTeamMember::updateOrCreate(
                            ['sales_team_id' => $team->id, 'user_id' => $member->id],
                            ['role' => $this->role ?: 'Executive', 'is_active' => true]
                        );
                    }
                } else {
                    SalesTeamMember::whereIn('sales_team_id', $this->teams()->select('id'))
                        ->where('user_id', $member->id)
                        ->update(['is_active' => false]);
                }
            } elseif ($this->isTeamLeader) {
                \App\Support\TeamAccess::ensureLeaderTeams($this->organizationId);
                if (!empty($this->teamName)) {
                    $team = $this->teams()->where('leader_id', $member->id)->first();
                    if ($team) {
                        $team->update(['name' => $this->teamName]);
                    }
                }
            } else {
                SalesTeamMember::whereIn('sales_team_id', $this->teams()->select('id'))
                    ->where('user_id', $member->id)
                    ->update(['is_active' => false]);
            }

            $after = $member->fresh()->only(['name', 'email', 'phone', 'status', 'user_code']);
            $this->audit($member, 'organization.member_updated', $before, $after);
        });

        $this->reset('password', 'password_confirmation', 'changingPassword');
        $this->dispatch('org-member-updated');
        $this->dispatch('toast', type: 'success', message: 'Member details saved.');
    }

    public function suspendActivate(): void
    {
        $this->toggleStatus();
    }

    public function toggleStatus(): void
    {
        $member = $this->member();
        $newStatus = ($this->status === 'active') ? 'inactive' : 'active';

        $member->update(['status' => $newStatus]);
        $this->status = $newStatus;

        $this->audit($member, 'organization.member_status_toggled', ['status' => ($newStatus === 'active' ? 'inactive' : 'active')], ['status' => $newStatus]);
        $this->dispatch('org-member-updated');
        $this->dispatch('toast', type: 'success', message: "User status updated to {$newStatus}.");
    }

    public function sendLoginDetails(): void
    {
        $member = $this->member();
        if ($member->status !== 'active') {
            $this->dispatch('toast', type: 'error', message: 'User must be active to send login details.');
            return;
        }

        try {
            $log = \App\Models\NotificationLog::create([
                'notifiable_type' => User::class,
                'notifiable_id' => $member->id,
                'channel' => 'email',
                'status' => 'pending',
                'retries' => 0,
                'payload' => json_encode([
                    'template_key' => 'account_login_details',
                    'organization_id' => $this->organizationId,
                    'sender_id' => auth()->id(),
                    'batch_id' => (string) Str::uuid(),
                ]),
            ]);

            \App\Jobs\SendAccountLoginDetails::dispatch($log->id);

            $this->audit($member, 'organization.member_login_details_sent', [], ['log_id' => $log->id]);
            $this->dispatch('toast', type: 'success', message: "Login details email sent to {$member->name}.");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Failed to queue login details: ' . $e->getMessage());
        }
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

    public function assignMemberToTeam(): void
    {
        $member = $this->member();
        abort_unless($member->hasAnyRole(['Executive', 'Sales Executive', 'sales-executive', 'sales_executive']) && !$member->hasAnyRole(['Team Leader', 'team-leader', 'team_leader', 'Team-Leader', 'Super Admin', 'super-admin', 'Builder/Developer', 'Builder', 'builder']), 403);
        $this->validate(['leadershipTeamId' => 'required|integer']);
        DB::transaction(function () use ($member) {
            $team = $this->teams()->with('leader')->lockForUpdate()->find($this->leadershipTeamId);
            abort_unless($team && $team->leader && $team->leader->organization_id === $this->organizationId && $team->leader->status === 'active' && $team->leader->hasAnyRole(['Team Leader', 'team-leader', 'team_leader', 'Team-Leader']), 404);

            $leaderMembership = SalesTeamMember::updateOrCreate(['sales_team_id' => $team->id, 'user_id' => $team->leader_id], ['role' => 'Team Leader', 'is_active' => true]);
            if ($leaderMembership->wasRecentlyCreated || $leaderMembership->wasChanged()) {
                $this->audit($team->leader, 'organization.leader_membership_confirmed', [], ['team_id' => $team->id, 'is_active' => true]);
            }
            $previous = SalesTeamMember::whereIn('sales_team_id', $this->teams()->select('id'))->where('user_id', $member->id)->where('is_active', true)->pluck('sales_team_id')->all();
            SalesTeamMember::whereIn('sales_team_id', $this->teams()->select('id'))->where('user_id', $member->id)->where('sales_team_id', '!=', $team->id)->update(['is_active' => false]);
            SalesTeamMember::updateOrCreate(['sales_team_id' => $team->id, 'user_id' => $member->id], ['role' => 'Executive', 'is_active' => true]);
            $this->audit($member, 'organization.member_team_assigned', ['team_ids' => $previous], ['team_id' => $team->id, 'leader_id' => $team->leader_id]);

            $this->teamId = $team->id;
            $this->teamName = $team->name;
            $this->teamLeaderId = $team->leader_id;
        });
        $this->dispatch('org-member-updated');
        $this->dispatch('toast', type: 'success', message: 'Member assigned to the selected Team Leader.');
    }

    public function assignTeamLeader(): void
    {
        $member = $this->member();
        abort_unless($member->status === 'active' && $member->hasAnyRole(['Executive', 'Sales Executive', 'sales-executive', 'sales_executive', 'Team Leader', 'team-leader', 'team_leader', 'Team-Leader'])
            && !$member->hasAnyRole(['Super Admin', 'super-admin', 'Builder/Developer', 'Builder', 'builder']), 403);
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

            $this->role = 'Team Leader';
            $this->teamId = $team->id;
            $this->teamName = $team->name;
            $this->teamLeaderId = $member->id;
        });
        $this->dispatch('org-member-updated');
        $this->dispatch('toast', type: 'success', message: 'Team Leader assigned.');
    }

    public function getIsExecutiveProperty(): bool
    {
        $role = strtolower(trim(str_replace(['_', '-'], ' ', $this->role)));
        return in_array($role, ['executive', 'sales executive']);
    }

    public function getIsTeamLeaderProperty(): bool
    {
        $role = strtolower(trim(str_replace(['_', '-'], ' ', $this->role)));
        return in_array($role, ['team leader']);
    }

    public function getAvailableRolesProperty(): array
    {
        $dbRoles = \Spatie\Permission\Models\Role::orderBy('name')->pluck('name');
        $roles = [];
        foreach ($dbRoles as $roleName) {
            if (in_array(strtolower(trim($roleName)), ['account manager', 'account-manager'])) {
                continue;
            }
            $label = ucwords(str_replace(['-', '_'], ' ', $roleName));
            $roles[$roleName] = $label;
        }
        if (empty($roles)) {
            $roles = [
                'Executive' => 'Executive',
                'Team Leader' => 'Team Leader',
                'Builder' => 'Builder',
                'Super Admin' => 'Super Admin',
            ];
        }
        if ($this->role && !isset($roles[$this->role])) {
            $roles[$this->role] = ucwords(str_replace(['-', '_'], ' ', $this->role));
        }
        return $roles;
    }

    public function getAvailableLeadersProperty(): array
    {
        $leaderRoles = ['Team Leader', 'team-leader', 'team_leader', 'Team-Leader'];
        $leaders = User::where('organization_id', $this->organizationId)
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $leaderRoles))
            ->orderBy('name')
            ->get();

        $options = ['' => 'Not Assigned'];
        foreach ($leaders as $leader) {
            $options[$leader->id] = $leader->name;
        }
        return $options;
    }

    public function getAvailableTeamsProperty(): array
    {
        $teams = $this->teams()->orderBy('name')->get();
        $options = ['' => 'Not Assigned'];
        foreach ($teams as $team) {
            $options[$team->id] = $team->name;
        }
        return $options;
    }

    private function audit(User $member, string $action, array $before, array $after): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'organization_id' => $this->organizationId,
            'action' => $action,
            'subject_type' => User::class,
            'subject_id' => $member->id,
            'from_value' => json_encode($before),
            'to_value' => json_encode($after),
            'created_at' => now(),
        ]);
    }

    public function render()
    {
        if ($this->show) {
            \App\Support\TeamAccess::ensureLeaderTeams($this->organizationId);
        }

        $teams = $this->show ? $this->teams()->with('leader')
            ->whereHas('leader', fn ($q) => $q->where('organization_id', $this->organizationId)
                ->where('status', 'active')
                ->whereHas('roles', fn ($r) => $r->whereIn('name', ['Team Leader', 'team-leader', 'team_leader', 'Team-Leader'])))
            ->get() : collect();

        return view('livewire.admin.organization-member-details', [
            'member' => $this->show ? $this->member() : null,
            'teams' => $teams,
        ]);
    }
}
