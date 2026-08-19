<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Livewire\Concerns\HasAdvancedTable;
use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class OrganizationManager extends Component
{
    use HasAdvancedTable;

    public string $name = '';
    public string $type = 'builder';
    public string $status = 'active';

    public ?int $editingOrganizationId = null;

    // Create Organization Modal
    public bool $showCreateOrgModal = false;

    // Organization Users Management Offcanvas
    public bool $showUserOffcanvas = false;
    public ?int $selectedOrgId = null;
    public ?Organization $selectedOrg = null;

    public string $newUserName = '';
    public string $newUserEmail = '';
    public string $newUserRole = 'builder';
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
            ['key' => 'name', 'label' => 'Organization Name', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-bold text-ink'],
            ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'sortable' => true, 'priority' => 1, 'badgeMap' => [
                'builder' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'channel_partner' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'platform' => 'bg-purple-50 text-purple-700 border border-purple-200',
            ]],
            ['key' => 'users_count', 'label' => 'Users Count', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-bold text-ink font-mono'],
            ['key' => 'clients_count', 'label' => 'Clients Count', 'type' => 'text', 'sortable' => true, 'priority' => 2, 'class' => 'font-mono text-muted'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'sortable' => true, 'priority' => 1, 'badgeMap' => [
                'active' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'suspended' => 'bg-red-50 text-red-700 border border-red-200',
            ]],
            ['key' => 'created_at', 'label' => 'Created', 'type' => 'date', 'sortable' => true, 'priority' => 2],
            ['key' => 'actions', 'label' => 'Actions', 'type' => 'org_actions', 'priority' => 1],
        ];
    }

    public function quickFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All Organizations'],
            ['key' => 'active', 'label' => 'Active'],
            ['key' => 'builder', 'label' => 'Builders'],
            ['key' => 'channel_partner', 'label' => 'Channel Partners'],
            ['key' => 'suspended', 'label' => 'Suspended'],
        ];
    }

    public function createOrganization(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:builder,channel_partner,platform',
        ]);

        Organization::create([
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
        ]);

        $this->reset(['name', 'type', 'status']);
        $this->showCreateOrgModal = false;
        $this->dispatch('toast', type: 'success', message: 'Organization created successfully.');
    }

    public function toggleStatus(int $id): void
    {
        $org = Organization::find($id);
        if ($org) {
            $newStatus = ($org->status === 'active') ? 'suspended' : 'active';
            $org->update(['status' => $newStatus]);
            $this->dispatch('toast', type: 'success', message: "Organization {$org->name} status changed to {$newStatus}.");
        }
    }

    public function openUserOffcanvas(int $orgId): void
    {
        $this->selectedOrgId = $orgId;
        $this->selectedOrg = Organization::with(['users.roles'])->find($orgId);

        if (!$this->selectedOrg) {
            $this->dispatch('toast', type: 'error', message: 'Organization not found.');
            return;
        }

        if ($this->selectedOrg->type === 'channel_partner') {
            $this->newUserRole = 'channel-partner';
        } elseif ($this->selectedOrg->type === 'platform') {
            $this->newUserRole = 'super-admin';
        } else {
            $this->newUserRole = 'builder';
        }

        $this->reset(['newUserName', 'newUserEmail', 'newUserPassword', 'generatedInviteLink']);
        $this->showUserOffcanvas = true;
        $this->dispatch('open-offcanvas', 'org-users-drawer');
    }

    public function closeUserOffcanvas(): void
    {
        $this->showUserOffcanvas = false;
        $this->selectedOrgId = null;
        $this->selectedOrg = null;
        $this->reset(['newUserName', 'newUserEmail', 'newUserPassword', 'generatedInviteLink']);
        $this->dispatch('close-offcanvas', 'org-users-drawer');
    }

    public function createUserForOrganization(): void
    {
        $this->validate([
            'selectedOrgId' => 'required|exists:organizations,id',
            'newUserName' => 'required|string|max:255',
            'newUserEmail' => 'required|email|unique:users,email',
            'newUserRole' => 'required|string',
        ]);

        $user = User::create([
            'name' => $this->newUserName,
            'email' => $this->newUserEmail,
            'password' => bcrypt($this->newUserPassword ?: Str::random(16)),
            'organization_id' => $this->selectedOrgId,
        ]);

        $user->assignRole($this->newUserRole);

        $token = Password::createToken($user);
        $this->generatedInviteLink = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        $this->reset(['newUserName', 'newUserEmail', 'newUserPassword']);
        $this->selectedOrg = Organization::with(['users.roles'])->find($this->selectedOrgId);

        $this->dispatch('toast', type: 'success', message: "User '{$user->name}' created and assigned to '{$this->selectedOrg->name}'.");
    }

    public function updateUserRoleInOrg(int $userId, string $newRole): void
    {
        $user = User::where('organization_id', $this->selectedOrgId)->find($userId);
        if ($user) {
            $user->syncRoles([$newRole]);
            $this->selectedOrg = Organization::with(['users.roles'])->find($this->selectedOrgId);
            $this->dispatch('toast', type: 'success', message: "Role for {$user->name} updated to '{$newRole}'.");
        }
    }

    public function removeUserFromOrganization(int $userId): void
    {
        $user = User::where('organization_id', $this->selectedOrgId)->find($userId);
        if ($user) {
            $user->update(['organization_id' => null]);
            $this->selectedOrg = Organization::with(['users.roles'])->find($this->selectedOrgId);
            $this->dispatch('toast', type: 'success', message: "User {$user->name} unassigned from this organization.");
        }
    }

    protected function getFilteredQuery()
    {
        $query = Organization::withCount(['clients', 'users']);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('type', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter === 'active') {
            $query->where('status', 'active');
        } elseif ($this->statusFilter === 'suspended') {
            $query->where('status', 'suspended');
        } elseif ($this->statusFilter === 'builder') {
            $query->where('type', 'builder');
        } elseif ($this->statusFilter === 'channel_partner') {
            $query->where('type', 'channel_partner');
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
        $organizations = $this->getFilteredQuery()->paginate($this->perPage);
        $roles = Role::all();

        return view('livewire.admin.organization-manager', [
            'organizations' => $organizations,
            'roles' => $roles,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $data = $this->getFilteredQuery()->get();
        $filename = "organizations-directory_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Org ID', 'Organization Name', 'Type', 'Users Count', 'Clients Count', 'Status', 'Created At'];
        $columns = [
            'id',
            'name',
            fn($o) => str_replace('_', ' ', $o->type),
            'users_count',
            'clients_count',
            'status',
            fn($o) => $o->created_at ? $o->created_at->format('M d, Y H:i') : '',
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T');

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: $data,
                title: 'Multi-Tenant Organizations Directory',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                statusColumns: ['status']
            ),
            $filename
        );
    }
}
