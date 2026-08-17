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
    public string $newUserRole = 'Sales Executive';
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
        $this->newUserRole = 'Sales Executive';
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

        // Find or create role to prevent Spatie RoleDoesNotExist errors
        $role = Role::firstOrCreate(['name' => $this->newUserRole, 'guard_name' => 'web']);
        $user->assignRole($role->name);

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
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $user->syncRoles([$role->name]);
            $this->dispatch('toast', type: 'success', message: "Role for {$user->name} updated to '{$role->name}'.");
        }
    }

    protected function applyFilters($query)
    {
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if (!empty($this->roleFilter)) {
            $filter = $this->roleFilter;
            $spaced = str_replace('-', ' ', $filter);
            $kebab = str_replace(' ', '-', strtolower($filter));

            $query->whereHas('roles', function ($q) use ($filter, $spaced, $kebab) {
                $q->where('name', $filter)
                  ->orWhere('name', $spaced)
                  ->orWhere('name', $kebab)
                  ->orWhereRaw('LOWER(name) = ?', [strtolower($filter)])
                  ->orWhereRaw('LOWER(REPLACE(name, "-", " ")) = ?', [strtolower($spaced)]);
            });
        }

        return $query;
    }

    public function render()
    {
        $query = User::with(['roles', 'organization']);
        $this->applyFilters($query);

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
        $this->applyFilters($query);

        $data = $query->latest('id')->get();
        $filename = "users-and-roles-directory_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['User ID', 'Full Name', 'Email Address', 'Organization', 'Current Role', 'Registered At'];

        $rows = [];
        foreach ($data as $u) {
            $rows[] = [
                $u->id,
                $u->name,
                $u->email,
                $u->organization ? $u->organization->name . ' (' . strtoupper($u->organization->type) . ')' : 'Platform HQ',
                $u->getRoleNames()->first() ?? 'No Role Assigned',
                $u->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return app(\App\Services\ExportService::class)->downloadExcel($filename, $headings, $rows);
    }
}
