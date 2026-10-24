<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Organization;
use App\Models\User;
use App\Models\Client;
use App\Models\CreditWallet;
use App\Models\AuditLog;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrganizationDetail extends Component
{
    public Organization $organization;

    // Active Navigation Tab
    public string $activeTab = 'overview'; // 'overview' | 'team' | 'clients' | 'activity'

    // Organization Edit State
    public bool $isEditing = false;
    public string $editName = '';
    public string $editStatus = 'active';

    // Team Management & Filters
    public string $userSearch = '';
    public string $userRoleFilter = 'all';
    public bool $showAddMemberModal = false;
    public string $newUserName = '';
    public string $newUserEmail = '';
    public string $newUserRole = 'Builder';
    public string $newUserPassword = '';
    public ?string $generatedInviteLink = null;

    // Client Management
    public bool $showCreateClientModal = false;
    public string $newClientName = '';
    public string $newClientBillingEmail = '';
    public string $newClientStatus = 'active';

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
        $this->editName = $organization->name;
        $this->editStatus = $organization->status;

        $roles = $this->availableRoles;
        $this->newUserRole = isset($roles['Builder']) ? 'Builder' : (string)(array_key_first($roles) ?? 'Builder');
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['overview', 'team', 'clients', 'activity'])) {
            $this->activeTab = $tab;
        }
    }

    public function startEditing(): void
    {
        $this->editName = $this->organization->name;
        $this->editStatus = $this->organization->status;
        $this->isEditing = true;
    }

    public function cancelEditing(): void
    {
        $this->editName = $this->organization->name;
        $this->editStatus = $this->organization->status;
        $this->isEditing = false;
    }

    public function updateOrganization(): void
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editStatus' => 'required|in:active,suspended',
        ]);

        $this->organization->update([
            'name' => $this->editName,
            'status' => $this->editStatus,
        ]);

        $this->organization->refresh();
        $this->isEditing = false;
        $this->dispatch('toast', type: 'success', message: "Organization '{$this->organization->name}' updated successfully.");
    }

    public function toggleOrganizationStatus(): void
    {
        $newStatus = ($this->organization->status === 'active') ? 'suspended' : 'active';
        $this->organization->update(['status' => $newStatus]);
        $this->organization->refresh();
        $this->editStatus = $newStatus;

        $this->dispatch('toast', type: 'success', message: "Organization status changed to {$newStatus}.");
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
                'Builder' => 'Builder',
                'Channel Partner' => 'Channel Partner',
                'Sales Executive' => 'Sales Executive',
                'Account Manager' => 'Account Manager',
                'Client' => 'Client',
                'Super Admin' => 'Super Admin',
            ];
        }

        return $roles;
    }

    public function openAddMemberModal(): void
    {
        $this->reset(['newUserName', 'newUserEmail', 'newUserPassword', 'generatedInviteLink']);
        $roles = $this->availableRoles;
        $this->newUserRole = isset($roles['Builder']) ? 'Builder' : (string)(array_key_first($roles) ?? 'Builder');
        $this->showAddMemberModal = true;
        $this->dispatch('open-modal', 'add-org-member-modal');
    }

    public function closeAddMemberModal(): void
    {
        $this->showAddMemberModal = false;
        $this->reset(['newUserName', 'newUserEmail', 'newUserPassword', 'generatedInviteLink']);
        $this->dispatch('close-modal', 'add-org-member-modal');
    }

    public function createUserForOrganization(): void
    {
        $this->validate([
            'newUserName' => 'required|string|max:255',
            'newUserEmail' => 'required|email|unique:users,email',
            'newUserRole' => 'required|string',
        ]);

        $plainPassword = !empty($this->newUserPassword) ? $this->newUserPassword : Str::random(14);

        $user = User::create([
            'name' => $this->newUserName,
            'email' => $this->newUserEmail,
            'password' => Hash::make($plainPassword),
            'organization_id' => $this->organization->id,
            'status' => 'active',
        ]);

        // Spatie role assignment
        try {
            $matchedRole = Role::where('name', $this->newUserRole)
                ->orWhere('name', strtolower(str_replace(' ', '-', $this->newUserRole)))
                ->first();

            if ($matchedRole) {
                $user->assignRole($matchedRole->name);
            } else {
                $user->assignRole($this->newUserRole);
            }
        } catch (Throwable $e) {
            // Silently handled if role doesn't exist
        }

        // Generate password reset / activation link
        try {
            $token = Password::broker()->createToken($user);
            $this->generatedInviteLink = route('password.reset', ['token' => $token, 'email' => $user->email]);
        } catch (Throwable $e) {
            $this->generatedInviteLink = null;
        }

        $this->reset(['newUserName', 'newUserEmail', 'newUserPassword']);
        $this->organization->load('users.roles');

        $this->dispatch('toast', type: 'success', message: "User {$user->name} created and assigned to {$this->organization->name}.");
    }

    public function removeUserFromOrganization(int $userId): void
    {
        $user = User::where('organization_id', $this->organization->id)->find($userId);

        if (!$user) {
            $this->dispatch('toast', type: 'error', message: 'User not found in this organization.');
            return;
        }

        $userName = $user->name;
        $user->update(['organization_id' => null]);
        $this->organization->load('users.roles');

        $this->dispatch('toast', type: 'success', message: "User {$userName} unassigned from {$this->organization->name}.");
    }

    public function toggleUserStatus(int $userId): void
    {
        $user = User::where('organization_id', $this->organization->id)->find($userId);

        if (!$user) {
            $this->dispatch('toast', type: 'error', message: 'User not found.');
            return;
        }

        $currentStatus = $user->status ?? 'active';
        $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';
        $user->update(['status' => $newStatus]);
        $this->organization->load('users.roles');

        $this->dispatch('toast', type: 'success', message: "User {$user->name} status set to {$newStatus}.");
    }

    public function generateUserActivationLink(int $userId): void
    {
        $user = User::where('organization_id', $this->organization->id)->find($userId);

        if (!$user) {
            $this->dispatch('toast', type: 'error', message: 'User not found.');
            return;
        }

        try {
            $token = Password::broker()->createToken($user);
            $this->generatedInviteLink = route('password.reset', ['token' => $token, 'email' => $user->email]);
            $this->dispatch('toast', type: 'success', message: "Activation token generated for {$user->name}.");
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Failed to generate link: ' . $e->getMessage());
        }
    }

    public function openCreateClientModal(): void
    {
        $this->reset(['newClientName', 'newClientBillingEmail', 'newClientStatus']);
        $this->newClientStatus = 'active';
        $this->showCreateClientModal = true;
        $this->dispatch('open-modal', 'create-org-client-modal');
    }

    public function closeCreateClientModal(): void
    {
        $this->showCreateClientModal = false;
        $this->reset(['newClientName', 'newClientBillingEmail', 'newClientStatus']);
        $this->dispatch('close-modal', 'create-org-client-modal');
    }

    public function createClientForOrganization(): void
    {
        $this->validate([
            'newClientName' => 'required|string|max:255',
            'newClientBillingEmail' => 'nullable|email|max:255',
            'newClientStatus' => 'required|in:active,suspended',
        ]);

        $client = Client::create([
            'organization_id' => $this->organization->id,
            'name' => $this->newClientName,
            'billing_email' => $this->newClientBillingEmail,
            'status' => $this->newClientStatus,
        ]);

        // Create default wallet with 0 balance if not present
        CreditWallet::firstOrCreate(
            ['client_id' => $client->id],
            ['balance' => 0]
        );

        $this->closeCreateClientModal();
        $this->organization->load('clients.projects', 'clients.wallet');

        $this->dispatch('toast', type: 'success', message: "Client '{$client->name}' registered successfully.");
    }

    public function render()
    {
        // 1. Team Members Query
        $usersQuery = User::where('organization_id', $this->organization->id)->with('roles');

        if (!empty($this->userSearch)) {
            $search = '%' . trim($this->userSearch) . '%';
            $usersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('phone', 'like', $search);
            });
        }

        if ($this->userRoleFilter !== 'all') {
            $roleFilter = $this->userRoleFilter;
            $usersQuery->whereHas('roles', function ($q) use ($roleFilter) {
                $q->where('name', $roleFilter)
                  ->orWhere('name', strtolower(str_replace(' ', '-', $roleFilter)));
            });
        }

        $teamMembers = $usersQuery->orderBy('name')->get();

        // 2. Clients Query
        $clients = Client::where('organization_id', $this->organization->id)
            ->with(['projects', 'wallet'])
            ->withCount(['projects', 'leads'])
            ->orderBy('name')
            ->get();

        // 3. Projects Query
        $projects = DB::table('projects')
            ->join('clients', 'projects.client_id', '=', 'clients.id')
            ->where('clients.organization_id', $this->organization->id)
            ->select('projects.*', 'clients.name as client_name')
            ->orderBy('projects.name')
            ->get();

        // 4. Metric Aggregates
        $totalUsersCount = User::where('organization_id', $this->organization->id)->count();
        $totalClientsCount = $clients->count();
        $totalProjectsCount = $projects->count();
        $totalLeadsCount = DB::table('leads')
            ->join('clients', 'leads.client_id', '=', 'clients.id')
            ->where('clients.organization_id', $this->organization->id)
            ->count();

        $totalWalletBalance = DB::table('client_wallets')
            ->join('clients', 'client_wallets.client_id', '=', 'clients.id')
            ->where('clients.organization_id', $this->organization->id)
            ->sum('client_wallets.balance');

        // 5. Activity Logs (safe lookup)
        $auditLogs = collect();
        try {
            $userOrgIds = User::where('organization_id', $this->organization->id)->pluck('id')->toArray();
            $auditLogs = AuditLog::with('user')
                ->where(function ($q) use ($userOrgIds) {
                    $q->where(function ($sq) {
                        $sq->where('subject_type', Organization::class)
                           ->where('subject_id', $this->organization->id);
                    });
                    if (!empty($userOrgIds)) {
                        $q->orWhereIn('user_id', $userOrgIds);
                    }
                })
                ->latest('created_at')
                ->take(15)
                ->get();
        } catch (Throwable $e) {
            // Fail gracefully if audit_logs table isn't populated
        }

        return view('livewire.admin.organization-detail', [
            'teamMembers' => $teamMembers,
            'clients' => $clients,
            'projects' => $projects,
            'totalUsersCount' => $totalUsersCount,
            'totalClientsCount' => $totalClientsCount,
            'totalProjectsCount' => $totalProjectsCount,
            'totalLeadsCount' => $totalLeadsCount,
            'totalWalletBalance' => (float)$totalWalletBalance,
            'auditLogs' => $auditLogs,
        ])->layout('layouts.app');
    }
}
