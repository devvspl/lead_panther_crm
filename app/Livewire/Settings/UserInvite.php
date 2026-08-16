<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

use Livewire\WithPagination;

class UserInvite extends Component
{
    use WithPagination;

    public string $name = '';
    public string $email = '';
    public string $roleName = 'Sales Executive';

    public ?string $generatedInviteLink = null;

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
        $invitedUsers = User::where('organization_id', auth()->user()?->organization_id)
            ->latest('id')
            ->paginate(15);

        $roles = Role::all();

        return view('livewire.settings.user-invite', [
            'invitedUsers' => $invitedUsers,
            'roles' => $roles,
        ])->layout('layouts.app');
    }
}
