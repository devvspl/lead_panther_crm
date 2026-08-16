<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $roleFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
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

        return view('livewire.admin.user-manager', [
            'users' => $users,
            'roles' => $roles,
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
