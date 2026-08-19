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

    // Create User Offcanvas State
    public bool $showCreateUserOffcanvas = false;
    public string $newUserName = '';
    public string $newUserEmail = '';
    public ?int $newUserOrganizationId = null;
    public string $newUserRole = 'Sales Executive';
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
            ['key' => 'name', 'label' => 'Full Name', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-bold text-ink'],
            ['key' => 'email', 'label' => 'Email Address', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono text-muted'],
            ['key' => 'organization_name', 'label' => 'Organization', 'type' => 'text', 'priority' => 2],
            ['key' => 'primary_role_name', 'label' => 'Role', 'type' => 'badge', 'priority' => 1, 'badgeMap' => [
                'Super Admin' => 'bg-purple-50 text-purple-700 border border-purple-200',
                'super-admin' => 'bg-purple-50 text-purple-700 border border-purple-200',
                'Sales Executive' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'sales-executive' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'Channel Partner' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'channel-partner' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
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
            ['key' => 'sales_executive', 'label' => 'Sales Executives'],
            ['key' => 'channel_partner', 'label' => 'Channel Partners'],
            ['key' => 'account_manager', 'label' => 'Account Managers'],
        ];
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
            $rfSpaced = str_replace('-', ' ', $rf);
            $rfKebab = str_replace(' ', '-', strtolower($rf));
            $query->whereHas('roles', fn($q) => $q->where('name', $rf)->orWhere('name', $rfSpaced)->orWhere('name', $rfKebab));
        }

        // Quick filter pills
        if ($this->statusFilter === 'super_admin') {
            $query->whereHas('roles', fn($q) => $q->whereIn('name', ['Super Admin', 'super-admin', 'super_admin']));
        } elseif ($this->statusFilter === 'sales_executive') {
            $query->whereHas('roles', fn($q) => $q->whereIn('name', ['Sales Executive', 'sales-executive', 'sales_executive']));
        } elseif ($this->statusFilter === 'channel_partner') {
            $query->whereHas('roles', fn($q) => $q->whereIn('name', ['Channel Partner', 'channel-partner', 'channel_partner']));
        } elseif ($this->statusFilter === 'account_manager') {
            $query->whereHas('roles', fn($q) => $q->whereIn('name', ['Account Manager', 'account-manager', 'account_manager']));
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
                $u->organization ? $u->organization->name . ' (' . strtoupper($u->organization->type) . ')' : 'Platform HQ',
                $u->getRoleNames()->first() ?? 'No Role Assigned',
                $u->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return app(\App\Services\ExportService::class)->downloadExcel($filename, $headings, $rows);
    }
}
