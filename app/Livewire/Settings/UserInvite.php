<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Livewire\Concerns\HasAdvancedTable;

class UserInvite extends Component
{
    use HasAdvancedTable;

    public string $name = '';
    public string $email = '';
    public string $roleName = 'Sales Executive';

    public ?string $generatedInviteLink = null;

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'User ID', 'prefix' => '#', 'class' => 'font-mono font-bold text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'name', 'label' => 'Name', 'class' => 'font-bold text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'email', 'label' => 'Email', 'class' => 'font-mono text-muted', 'sortable' => true, 'priority' => 1],
            ['key' => 'role', 'label' => 'Role', 'type' => 'badge', 'sortable' => false, 'priority' => 1, 'badgeMap' => [
                'Sales Executive' => 'bg-purple-50 text-purple-700 border border-purple-200',
                'Account Manager' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'Channel Partner' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'Super Admin' => 'bg-purple-50 text-purple-700 border border-purple-200',
            ], 'default' => 'Sales Executive'],
        ];
    }

    public function inviteUser(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'roleName' => 'required',
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => bcrypt(Str::random(16)),
            'organization_id' => auth()->user()?->organization_id,
        ]);

        $user->assignRole($this->roleName);

        // Generate temporary password reset link for the invite
        $token = Password::createToken($user);
        $this->generatedInviteLink = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        $this->reset(['name', 'email']);
        $this->dispatch('toast', type: 'success', message: "Invitation sent to {$user->email}. Shareable activation link generated below.");
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

        $invitedUsers = $query->orderBy($sortField, $sortDir)->paginate($this->perPage);
        $roles = Role::all();

        return view('livewire.settings.user-invite', [
            'invitedUsers' => $invitedUsers,
            'roles' => $roles,
        ])->layout('layouts.app');
    }
}
