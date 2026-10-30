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

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'Member ID', 'prefix' => '#', 'class' => 'font-mono font-bold text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'name', 'label' => 'Name', 'class' => 'font-bold text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'email', 'label' => 'Email', 'class' => 'font-mono text-muted', 'sortable' => true, 'priority' => 1],
            ['key' => 'role', 'label' => 'Role', 'type' => 'badge', 'sortable' => false, 'priority' => 1, 'badgeMap' => [
                'Super Admin' => 'bg-purple-50 text-purple-700 border border-purple-200',
                'Builder/Developer' => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
                'Channel Partner' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'Broker' => 'bg-cyan-50 text-cyan-700 border border-cyan-200',
                'Executive' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'Sales Executive' => 'bg-blue-50 text-blue-700 border border-blue-200',
            ], 'default' => 'Executive'],
        ];
    }

    public function addMember(): void
    {
        $this->validate([
            'memberName' => 'required|string|max:255',
            'memberEmail' => 'required|email|unique:users,email',
            'roleName' => 'required|in:Executive,Channel Partner',
        ], [
            'memberName.required' => 'The member name field is required.',
            'memberEmail.required' => 'The member email field is required.',
            'memberEmail.unique' => 'This email is already registered.',
            'roleName.required' => 'Please select a role.',
            'roleName.in' => 'The role must be Executive or Channel Partner.',
        ]);

        $orgId = auth()->user()?->organization_id ?? 1;

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

        $team = SalesTeam::firstOrCreate([
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
