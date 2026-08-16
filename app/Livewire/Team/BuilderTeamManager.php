<?php

namespace App\Livewire\Team;

use Livewire\Component;
use App\Models\User;
use App\Models\SalesTeamMember;
use App\Models\SalesTeam;
use App\Models\Organization;

use Livewire\WithPagination;

class BuilderTeamManager extends Component
{
    use WithPagination;

    public string $memberName = '';
    public string $memberEmail = '';
    public string $roleName = 'Sales Executive';

    public function addMember(): void
    {
        $this->validate([
            'memberName' => 'required|string|max:255',
            'memberEmail' => 'required|email|unique:users,email',
        ]);

        $user = User::create([
            'name' => $this->memberName,
            'email' => $this->memberEmail,
            'password' => bcrypt('Password123!'),
            'organization_id' => auth()->user()?->organization_id,
        ]);

        $user->assignRole($this->roleName);

        $team = SalesTeam::firstOrCreate([
            'ownable_type' => Organization::class,
            'ownable_id' => auth()->user()?->organization_id ?? 1,
            'name' => 'Direct Builder Sales Team',
        ]);

        SalesTeamMember::create([
            'sales_team_id' => $team->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->reset(['memberName', 'memberEmail']);
        $this->dispatch('toast', type: 'success', message: 'Builder team member added successfully.');
    }

    public function render()
    {
        $members = User::where('organization_id', auth()->user()?->organization_id)
            ->latest('id')
            ->paginate(15);

        return view('livewire.team.builder-team-manager', [
            'members' => $members,
        ])->layout('layouts.app');
    }
}
