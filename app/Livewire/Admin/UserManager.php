<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Organization;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $roleFilter = '';

    // Create User Offcanvas State
    public bool $showCreateUserOffcanvas = false;
    public string $newUserName = '';
    public string $newUserEmail = '';
    public ?int $newUserOrganizationId = null;
    public string $newUserRole = 'sales-executive';
    public string $newUserPassword = '';
    public ?string $generatedInviteLink = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateUserOffcanvas(): void
    {
        $this->reset(['newUserName', 'newUserEmail', 'newUserOrganizationId', 'newUserPassword', 'generatedInviteLink']);
        $this->newUserRole = 'sales-executive';
        $this->showCreateUserOffcanvas = true;
        $this->dispatch('open-offcanvas', 'create-user-drawer');
    }

    public function closeCreateUserOffcanvas(): void
    {
        $this->showCreateUserOffcanvas = false;
        $this->reset(['newUserName', 'newUserEmail', 'newUserOrganizationId', 'newUserPassword', 'generatedInviteLink']);
        $this->dispatch('close-offcanvas', 'create-user-drawer');
    }

    public function createUser(): void
    {
        $this->validate([
            'newUserName' => 'required|string|max:255',
            'newUserEmail' => 'required|email|unique:users,email',
            'newUserRole' => 'required|string',
            'newUserOrganizationId' => 'nullable|exists:organizations,id',
        ]);

        $user = User::create([
            'name' => $this->newUserName,
            'email' => $this->newUserEmail,
            'password' => bcrypt($this->newUserPassword ?: Str::random(16)),
            'organization_id' => $this->newUserOrganizationId,
        ]);

        $user->assignRole($this->newUserRole);

        // Generate temporary password reset / invite activation link
        $token = Password::createToken($user);
        $this->generatedInviteLink = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        $this->reset(['newUserName', 'newUserEmail', 'newUserPassword', 'newUserOrganizationId']);
        $this->dispatch('toast', type: 'success', message: "User '{$user->name}' created successfully.");
    }

    public function updateUserRole(int $userId, string $roleName): void
    {
        $user = User::find($userId);
        if ($user) {
            $user->syncRoles([$roleName]);
            $this->dispatch('toast', type: 'success', message: "Role for {$user->name} updated to '{$roleName}'.");
        }
    }

    public function render()
    {
        $query = User::with(['roles', 'organization']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->roleFilter) {
            $query->whereHas('roles', function ($q) {
                $q->where('name', $this->roleFilter);
            });
        }

        $users = $query->latest('id')->paginate(10);
        $roles = Role::all();
        $organizations = Organization::where('status', 'active')->orderBy('name')->get();

        return view('livewire.admin.user-manager', [
            'users' => $users,
            'roles' => $roles,
            'organizations' => $organizations,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $query = User::with(['roles', 'organization']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->roleFilter) {
            $query->whereHas('roles', function ($q) {
                $q->where('name', $this->roleFilter);
            });
        }

        $data = $query->latest('id')->get();
        $filename = "users-and-roles-directory_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['User ID', 'Full Name', 'Email Address', 'Organization', 'Current Role', 'Registered At'];
        $columns = [
            'id',
            'name',
            'email',
            fn($u) => $u->organization?->name ?: 'Platform HQ',
            fn($u) => $u->roles->pluck('name')->implode(', ') ?: 'No Role',
            fn($u) => $u->created_at ? $u->created_at->format('M d, Y H:i') : '',
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T') . ($this->search ? " | Search: {$this->search}" : '') . ($this->roleFilter ? " | Role: {$this->roleFilter}" : '');

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: $data,
                title: 'Users & Roles Directory',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                statusColumns: ['current_role']
            ),
            $filename
        );
    }
}
