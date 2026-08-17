<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\WithPagination;

class OrganizationManager extends Component
{
    use WithPagination;

    public string $name = '';
    public string $type = 'builder';
    public string $status = 'active';

    public ?int $editingOrganizationId = null;

    // Organization Users Management Offcanvas
    public bool $showUserOffcanvas = false;
    public ?int $selectedOrgId = null;
    public ?Organization $selectedOrg = null;

    public string $newUserName = '';
    public string $newUserEmail = '';
    public string $newUserRole = 'builder';
    public string $newUserPassword = '';
    public ?string $generatedInviteLink = null;

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

        // Set default recommended role based on organization type
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

        // Generate temporary password activation link
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

    public function render()
    {
        $organizations = Organization::withCount(['clients', 'users'])->latest('id')->paginate(10);
        $roles = Role::all();

        return view('livewire.admin.organization-manager', [
            'organizations' => $organizations,
            'roles' => $roles,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $data = Organization::withCount(['clients', 'users'])->latest('id')->get();
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
