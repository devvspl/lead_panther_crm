<?php

namespace App\Livewire\Team;

use Livewire\Component;
use App\Models\User;
use App\Models\SalesTeamMember;
use App\Models\SalesTeam;
use App\Models\Organization;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Livewire\Concerns\HasAdvancedTable;

class BuilderTeamManager extends Component
{
    use HasAdvancedTable;

    public string $memberName = '';
    public string $memberEmail = '';
    public string $roleName = 'Sales Executive';
    public string $password = '';
    public ?string $generatedInviteLink = null;

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'Member ID', 'prefix' => '#', 'class' => 'font-mono font-bold text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'name', 'label' => 'Name', 'class' => 'font-bold text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'email', 'label' => 'Email', 'class' => 'font-mono text-muted', 'sortable' => true, 'priority' => 1],
            ['key' => 'role', 'label' => 'Role', 'type' => 'badge', 'sortable' => false, 'priority' => 1, 'badgeMap' => [
                'Sales Executive' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'Account Manager' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'Channel Partner' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'Super Admin' => 'bg-purple-50 text-purple-700 border border-purple-200',
            ], 'default' => 'Sales Executive'],
        ];
    }

    public function addMember(): void
    {
        $this->validate([
            'memberName' => 'required|string|max:255',
            'memberEmail' => 'required|email|unique:users,email',
            'roleName' => 'required|string',
        ]);

        $user = User::create([
            'name' => $this->memberName,
            'email' => $this->memberEmail,
            'password' => bcrypt($this->password ?: Str::random(16)),
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

        // Generate temporary password activation link
        $token = Password::createToken($user);
        $this->generatedInviteLink = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        $this->reset(['memberName', 'memberEmail', 'password']);
        $this->dispatch('toast', type: 'success', message: "Team member '{$user->name}' added successfully.");
    }

    public function render()
    {
        $query = User::where('organization_id', auth()->user()?->organization_id);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
                  ->orWhere('id', 'like', "%{$this->search}%");
            });
        }

        $sortField = in_array($this->sortField, ['id', 'name', 'email']) ? $this->sortField : 'id';
        $sortDir = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $members = $query->orderBy($sortField, $sortDir)->paginate($this->perPage);

        return view('livewire.team.builder-team-manager', [
            'members' => $members,
        ])->layout('layouts.app');
    }
}
