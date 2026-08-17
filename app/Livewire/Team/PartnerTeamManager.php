<?php

namespace App\Livewire\Team;

use Livewire\Component;
use App\Models\User;
use App\Models\SalesTeamMember;
use App\Models\SalesTeam;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\WithPagination;

class PartnerTeamManager extends Component
{
    use WithPagination;

    public string $memberName = '';
    public string $memberEmail = '';
    public string $mobile = '';
    public string $password = '';
    public ?string $generatedInviteLink = null;

    public function addTeamMember(): void
    {
        $this->validate([
            'memberName' => 'required|string|max:255',
            'memberEmail' => 'required|email|unique:users,email',
            'mobile' => 'required|string',
        ]);

        $user = User::create([
            'name' => $this->memberName,
            'email' => $this->memberEmail,
            'password' => bcrypt($this->password ?: Str::random(16)),
            'organization_id' => auth()->user()?->organization_id,
        ]);

        $user->assignRole('Sales Executive');

        $team = SalesTeam::firstOrCreate([
            'ownable_type' => Organization::class,
            'ownable_id' => auth()->user()?->organization_id ?? 1,
            'name' => 'Partner Sales Squad',
        ]);

        SalesTeamMember::create([
            'sales_team_id' => $team->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        // Generate temporary password activation link
        $token = Password::createToken($user);
        $this->generatedInviteLink = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        $this->reset(['memberName', 'memberEmail', 'mobile', 'password']);
        $this->dispatch('toast', type: 'success', message: 'Channel Partner team member added successfully.');
    }

    public function render()
    {
        $members = User::role('Sales Executive')
            ->where('organization_id', auth()->user()?->organization_id)
            ->latest('id')
            ->paginate(15);

        $projects = Project::all();

        return view('livewire.team.partner-team-manager', [
            'members' => $members,
            'projects' => $projects,
        ])->layout('layouts.app');
    }
}
