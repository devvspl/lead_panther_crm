<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Livewire\Concerns\HasAdvancedTable;
use App\Models\User;
use App\Models\Organization;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserManager extends Component
{
    use HasAdvancedTable;

    public string $roleFilter = '';
    public ?int $filterOrganizationId = null;

    // User Details Modal State (View & Edit Modes)
    public bool $showUserDetailsModal = false;
    public ?int $selectedUserId = null;
    public ?User $selectedUser = null;
    public string $userDetailsMode = 'view'; // 'view' or 'edit'

    // User Edit Form Fields
    public string $editName = '';
    public string $editEmail = '';
    public ?string $editPhone = '';
    public ?int $editOrganizationId = null;
    public string $editRole = 'Executive';
    public string $editStatus = 'active';
    public string $editPassword = '';

    // Create User Modal State
    public bool $showCreateModal = false;
    public bool $showCreateUserOffcanvas = false; // Backward-compatibility
    public string $newUserName = '';
    public string $newUserEmail = '';
    public ?string $newUserPhone = '';
    public ?int $newUserOrganizationId = null;
    public string $newUserRole = 'Executive';
    public string $newUserPassword = '';
    public ?string $generatedInviteLink = null;

    public function mount(): void
    {
        $this->loadColumnPreferences();
    }

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'ID', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono text-muted text-[11px]'],
            ['key' => 'name', 'label' => 'Full Name', 'type' => 'user_name', 'sortable' => true, 'priority' => 1, 'class' => 'font-bold text-ink'],
            ['key' => 'email', 'label' => 'Email Address', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono text-muted'],
            ['key' => 'organization_name', 'label' => 'Organization', 'type' => 'text', 'priority' => 2],
            ['key' => 'primary_role_name', 'label' => 'Role', 'type' => 'badge', 'priority' => 1, 'badgeMap' => [
                'Super Admin' => 'bg-purple-50 text-purple-700 border border-purple-200',
                'super-admin' => 'bg-purple-50 text-purple-700 border border-purple-200',
                'Builder/Developer' => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
                'Builder' => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
                'builder' => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
                'Channel Partner' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'channel-partner' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'Executive' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'Sales Executive' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'sales-executive' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'Account Manager' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'account-manager' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'Client' => 'bg-slate-50 text-slate-700 border border-slate-200',
                'client' => 'bg-slate-50 text-slate-700 border border-slate-200',
            ]],
            ['key' => 'created_at', 'label' => 'Registered', 'type' => 'date', 'sortable' => true, 'priority' => 2],
            ['key' => 'actions', 'label' => 'Actions', 'type' => 'user_actions', 'priority' => 1],
        ];
    }

    public function quickFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All Users'],
            ['key' => 'super_admin', 'label' => 'Super Admins'],
            ['key' => 'builder_developer', 'label' => 'Builders / Developers'],
            ['key' => 'channel_partner', 'label' => 'Channel Partners'],
            ['key' => 'executive', 'label' => 'Executives'],
        ];
    }

    public function openCreateUserModal(): void
    {
        $this->reset(['newUserName', 'newUserEmail', 'newUserPhone', 'newUserOrganizationId', 'newUserPassword', 'generatedInviteLink']);
        $this->newUserRole = 'Executive';
        $this->showCreateModal = true;
        $this->showCreateUserOffcanvas = true;
        $this->dispatch('open-modal', 'create-user-modal');
    }

    public function closeCreateUserModal(): void
    {
        $this->showCreateModal = false;
        $this->showCreateUserOffcanvas = false;
        $this->reset(['newUserName', 'newUserEmail', 'newUserPhone', 'newUserOrganizationId', 'newUserPassword', 'generatedInviteLink']);
        $this->resetValidation();
        $this->dispatch('close-modal', 'create-user-modal');
    }

    public function openCreateUserOffcanvas(): void
    {
        $this->openCreateUserModal();
    }

    public function closeCreateUserOffcanvas(): void
    {
        $this->closeCreateUserModal();
    }

    public function createUser(): void
    {
        $this->validate([
            'newUserName' => 'required|string|max:255',
            'newUserEmail' => 'required|email|unique:users,email',
            'newUserPhone' => 'nullable|string|max:50',
            'newUserRole' => 'required|string',
            'newUserOrganizationId' => 'nullable|exists:organizations,id',
        ]);

        $user = User::create([
            'name' => $this->newUserName,
            'email' => $this->newUserEmail,
            'phone' => $this->newUserPhone ?: null,
            'password' => bcrypt($this->newUserPassword ?: Str::random(16)),
            'organization_id' => $this->newUserOrganizationId,
            'status' => 'active',
        ]);

        $role = Role::firstOrCreate(['name' => $this->newUserRole, 'guard_name' => 'web']);
        $user->assignRole($role->name);

        $token = Password::createToken($user);
        $this->generatedInviteLink = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        $this->reset(['newUserName', 'newUserEmail', 'newUserPhone', 'newUserPassword', 'newUserOrganizationId']);
        $this->dispatch('toast', type: 'success', message: "User '{$user->name}' created successfully.");
    }

    public function openUserDetailsModal(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->selectedUser = User::with(['roles', 'organization'])->find($userId);

        if (!$this->selectedUser) {
            $this->dispatch('toast', type: 'error', message: 'User not found.');
            return;
        }

        $this->editName = $this->selectedUser->name;
        $this->editEmail = $this->selectedUser->email;
        $this->editPhone = $this->selectedUser->phone ?? '';
        $this->editOrganizationId = $this->selectedUser->organization_id;
        $primaryRole = $this->selectedUser->roles->first()?->name;
        $this->editRole = $primaryRole ? User::normalizeRoleName($primaryRole) : 'Sales Executive';
        $this->editStatus = $this->selectedUser->status ?? 'active';
        $this->editPassword = '';

        $this->userDetailsMode = 'view';
        $this->showUserDetailsModal = true;
        $this->dispatch('open-modal', 'user-details-modal');
    }

    public function switchUserDetailsMode(string $mode): void
    {
        if (!in_array($mode, ['view', 'edit'])) {
            return;
        }

        if ($mode === 'edit' && $this->selectedUser) {
            $this->editName = $this->selectedUser->name;
            $this->editEmail = $this->selectedUser->email;
            $this->editPhone = $this->selectedUser->phone ?? '';
            $this->editOrganizationId = $this->selectedUser->organization_id;
            $primaryRole = $this->selectedUser->roles->first()?->name;
            $this->editRole = $primaryRole ? User::normalizeRoleName($primaryRole) : 'Sales Executive';
            $this->editStatus = $this->selectedUser->status ?? 'active';
            $this->editPassword = '';
            $this->resetValidation();
        }

        $this->userDetailsMode = $mode;
    }

    public function updateUser(): void
    {
        if (!$this->selectedUserId) {
            return;
        }

        $this->validate([
            'editName' => 'required|string|max:255',
            'editEmail' => 'required|email|unique:users,email,' . $this->selectedUserId,
            'editPhone' => 'nullable|string|max:50',
            'editOrganizationId' => 'nullable|exists:organizations,id',
            'editRole' => 'required|string',
            'editStatus' => 'required|in:active,suspended',
            'editPassword' => 'nullable|string|min:6',
        ]);

        $user = User::find($this->selectedUserId);
        if (!$user) {
            $this->dispatch('toast', type: 'error', message: 'User not found.');
            return;
        }

        $updateData = [
            'name' => $this->editName,
            'email' => $this->editEmail,
            'phone' => $this->editPhone ?: null,
            'organization_id' => $this->editOrganizationId,
            'status' => $this->editStatus,
        ];

        if (!empty($this->editPassword)) {
            $updateData['password'] = bcrypt($this->editPassword);
        }

        $user->update($updateData);

        $role = Role::firstOrCreate(['name' => $this->editRole, 'guard_name' => 'web']);
        $user->syncRoles([$role->name]);

        $this->selectedUser = User::with(['roles', 'organization'])->find($this->selectedUserId);
        $this->userDetailsMode = 'view';
        $this->editPassword = '';

        $this->dispatch('toast', type: 'success', message: "User '{$user->name}' updated successfully.");
    }

    public function closeUserDetailsModal(): void
    {
        $this->showUserDetailsModal = false;
        $this->selectedUserId = null;
        $this->selectedUser = null;
        $this->userDetailsMode = 'view';
        $this->reset(['editName', 'editEmail', 'editPhone', 'editOrganizationId', 'editRole', 'editStatus', 'editPassword']);
        $this->resetValidation();
        $this->dispatch('close-modal', 'user-details-modal');
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

    public function getAvailableRolesProperty(): array
    {
        $dbRoles = Role::orderBy('name')->pluck('name');
        $roles = [];

        foreach ($dbRoles as $roleName) {
            $label = ucwords(str_replace(['-', '_'], ' ', $roleName));
            if (!isset($roles[$label])) {
                $roles[$label] = $label;
            }
        }

        if (empty($roles)) {
            $roles = [
                'Super Admin' => 'Super Admin',
                'Builder/Developer' => 'Builder/Developer',
                'Channel Partner' => 'Channel Partner',
                'Executive' => 'Executive',
            ];
        }

        return $roles;
    }

    public function getOrganizationOptionsProperty(): array
    {
        $orgs = Organization::where('status', 'active')->orderBy('name')->get();
        $options = ['' => 'Platform HQ (No Organization)'];
        foreach ($orgs as $org) {
            $options[(string)$org->id] = $org->name;
        }
        return $options;
    }

    protected function getFilteredQuery()
    {
        $query = User::with(['roles', 'organization']);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filterOrganizationId) {
            $query->where('organization_id', $this->filterOrganizationId);
        }

        // Role Filter (from legacy or direct select)
        if (!empty($this->roleFilter)) {
            $rf = $this->roleFilter;
            $rfSpaced = str_replace(['-', '_'], ' ', $rf);
            $rfKebab = str_replace(' ', '-', strtolower($rf));
            $query->whereHas('roles', fn($q) => $q->where('name', $rf)->orWhere('name', $rfSpaced)->orWhere('name', $rfKebab));
        }

        // Quick filter pills
        if ($this->statusFilter === 'super_admin') {
            $query->whereHas('roles', fn($q) => $q->whereIn('name', ['Super Admin', 'super-admin', 'super_admin']));
        } elseif ($this->statusFilter === 'builder_developer') {
            $query->whereHas('roles', fn($q) => $q->whereIn('name', ['Builder/Developer', 'Builder', 'builder']));
        } elseif ($this->statusFilter === 'channel_partner') {
            $query->whereHas('roles', fn($q) => $q->whereIn('name', ['Channel Partner', 'channel-partner', 'channel_partner']));
        } elseif ($this->statusFilter === 'executive') {
            $query->whereHas('roles', fn($q) => $q->whereIn('name', ['Executive', 'Sales Executive', 'sales-executive']));
        }

        if (!empty($this->sortField)) {
            $query->orderBy($this->sortField, $this->sortDirection);
        } else {
            $query->latest('id');
        }

        return $query;
    }

    public function render()
    {
        $users = $this->getFilteredQuery()->paginate($this->perPage);
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
        $data = $this->getFilteredQuery()->get();
        $filename = "users-and-roles-directory_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['User ID', 'Full Name', 'Email Address', 'Organization', 'Current Role', 'Registered At'];

        $rows = [];
        foreach ($data as $u) {
            $rows[] = [
                $u->id,
                $u->name,
                $u->email,
                $u->organization ? $u->organization->name : 'Platform HQ',
                $u->getRoleNames()->first() ?? 'No Role Assigned',
                $u->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return app(\App\Services\ExportService::class)->downloadExcel($filename, $headings, $rows);
    }
}
