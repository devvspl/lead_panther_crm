<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use App\Models\Organization;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\CreditWallet;
use App\Models\CreditTransaction;
use App\Models\RechargeRequest;
use App\Models\AuditLog;
use App\Exports\BaseStyledExport;
use App\Exports\OrganizationDetailMultiTabExport;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Throwable;

class OrganizationDetail extends Component
{
    use WithPagination;

    public Organization $organization;

    // Active Navigation Tab: 'overview' | 'team' | 'clients' | 'credits' | 'activity'
    #[Url(as: 'tab', history: true, keep: true)]
    public string $activeTab = 'overview';

    // Organization Edit State & Pricing
    public bool $isEditing = false;
    public string $editName = '';
    public string $editStatus = 'active';
    public ?float $orgLeadCost = null;

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

    // Directly Assigned Leads Management (Retained for background compatibility)
    public string $leadSearch = '';
    public string $leadAssignmentFilter = 'all'; // 'all' | 'assigned' | 'unassigned'
    public string $leadStatusFilter = 'all';
    public ?int $filterLeadClientId = null;
    public bool $showCreateLeadModal = false;
    public string $newLeadName = '';
    public string $newLeadMobile = '';
    public string $newLeadEmail = '';
    public ?int $newLeadClientId = null;
    public ?int $newLeadProjectId = null;
    public ?int $newLeadAssignedTo = null; // Direct user assignment
    public string $newLeadStatus = 'new';
    public string $newLeadRequirement = '';

    // Credits & Wallet Management
    public string $creditFilterType = 'all'; // 'all' | 'recharge' | 'deduct' | 'refund' | 'reserve'
    public bool $showCreditModal = false;
    public ?int $selectedCreditClientId = null;
    public string $creditAdjustmentType = 'recharge'; // 'recharge' | 'refund' | 'deduct'
    public float $creditAmount = 100.00;
    public string $creditReason = 'Manual credit allocation';

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
        $this->editName = $organization->name;
        $this->editStatus = $organization->status;
        $this->orgLeadCost = $organization->lead_cost !== null ? (float) $organization->lead_cost : null;

        $requestedTab = request()->query('tab');
        if ($requestedTab && in_array($requestedTab, ['overview', 'team', 'clients', 'credits', 'activity'])) {
            $this->activeTab = $requestedTab;
        }

        $roles = $this->availableRoles;
        $this->newUserRole = isset($roles['Builder']) ? 'Builder' : (string)(array_key_first($roles) ?? 'Builder');
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['overview', 'team', 'clients', 'credits', 'activity'])) {
            $this->activeTab = $tab;
            $this->resetPage();
        }
    }

    public function updateOrgLeadCost(): void
    {
        $this->validate([
            'orgLeadCost' => 'nullable|numeric|min:0.01|max:100000',
        ]);

        $this->organization->update([
            'lead_cost' => $this->orgLeadCost !== null && $this->orgLeadCost > 0 ? (float) $this->orgLeadCost : null,
        ]);
        $this->organization->refresh();
        $this->orgLeadCost = $this->organization->lead_cost !== null ? (float) $this->organization->lead_cost : null;

        $this->dispatch('toast', type: 'success', message: "Per-lead pricing override for '{$this->organization->name}' updated successfully.");
    }

    public function updatedLeadSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLeadAssignmentFilter(): void
    {
        $this->resetPage();
    }

    public function updatedLeadStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedFilterLeadClientId(): void
    {
        $this->resetPage();
    }

    public function updatedCreditFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedUserSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUserRoleFilter(): void
    {
        $this->resetPage();
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
            // Handled safely
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

        // Create default wallet with 0 balance
        CreditWallet::firstOrCreate(
            ['client_id' => $client->id],
            ['balance' => 0]
        );

        $this->closeCreateClientModal();
        $this->organization->load('clients.projects', 'clients.wallet');

        $this->dispatch('toast', type: 'success', message: "Client '{$client->name}' registered successfully.");
    }

    // ─────────────────────────────────────────────────────────────
    // DIRECTLY ASSIGNED LEADS METHODS
    // ─────────────────────────────────────────────────────────────

    public function openCreateLeadModal(): void
    {
        $firstClient = Client::where('organization_id', $this->organization->id)->first();
        $this->newLeadClientId = $firstClient?->id;
        $this->newLeadProjectId = $firstClient ? Project::where('client_id', $firstClient->id)->first()?->id : null;
        $this->newLeadAssignedTo = User::where('organization_id', $this->organization->id)->first()?->id;

        $this->reset(['newLeadName', 'newLeadMobile', 'newLeadEmail', 'newLeadRequirement']);
        $this->newLeadStatus = 'new';
        $this->showCreateLeadModal = true;
        $this->dispatch('open-modal', 'create-org-lead-modal');
    }

    public function closeCreateLeadModal(): void
    {
        $this->showCreateLeadModal = false;
        $this->reset(['newLeadName', 'newLeadMobile', 'newLeadEmail', 'newLeadRequirement']);
        $this->dispatch('close-modal', 'create-org-lead-modal');
    }

    public function updatedNewLeadClientId($clientId): void
    {
        if ($clientId) {
            $this->newLeadProjectId = Project::where('client_id', $clientId)->first()?->id;
        } else {
            $this->newLeadProjectId = null;
        }
    }

    public function createDirectlyAssignedLead(): void
    {
        $this->validate([
            'newLeadName' => 'required|string|max:255',
            'newLeadMobile' => 'required|string|max:25',
            'newLeadEmail' => 'nullable|email|max:255',
            'newLeadClientId' => 'required|exists:clients,id',
            'newLeadAssignedTo' => 'nullable|exists:users,id',
            'newLeadStatus' => 'required|string',
        ]);

        // Verify client belongs to this organization
        $client = Client::where('organization_id', $this->organization->id)->findOrFail($this->newLeadClientId);

        // Ensure a project exists for client (create default project if client has none)
        $projectId = $this->newLeadProjectId;
        if (!$projectId || !Project::where('client_id', $client->id)->where('id', $projectId)->exists()) {
            $project = Project::firstOrCreate(
                ['client_id' => $client->id, 'name' => "{$client->name} General Project"],
                ['status' => 'active']
            );
            $projectId = $project->id;
        }

        // Ensure a lead source exists
        $leadSource = LeadSource::firstOrCreate(
            ['name' => 'manual'],
            ['is_active' => true]
        );

        $leadCode = Lead::generateUniqueLeadCode('LP');

        $lead = Lead::create([
            'lead_code' => $leadCode,
            'client_id' => $client->id,
            'project_id' => $projectId,
            'lead_source_id' => $leadSource->id,
            'name' => $this->newLeadName,
            'mobile' => $this->newLeadMobile,
            'email' => $this->newLeadEmail,
            'requirement' => $this->newLeadRequirement,
            'status' => $this->newLeadStatus,
            'current_stage' => $this->newLeadStatus,
            'assigned_to' => $this->newLeadAssignedTo, // Direct assignment!
        ]);

        // Try reserving credit for lead
        try {
            CreditTransaction::reserveForLead($lead);
        } catch (Throwable $e) {}

        $assignedName = $lead->assignedTo?->name ?: 'Direct Pool';
        $this->closeCreateLeadModal();

        $this->dispatch('toast', type: 'success', message: "Lead {$lead->lead_code} created & directly assigned to {$assignedName}.");
    }

    public function reassignLead(int $leadId, ?int $newAssignedUserId): void
    {
        $lead = Lead::withoutGlobalScopes()->find($leadId);
        if (!$lead) {
            $this->dispatch('toast', type: 'error', message: 'Lead not found.');
            return;
        }

        $lead->update(['assigned_to' => $newAssignedUserId]);
        $userName = $newAssignedUserId ? User::find($newAssignedUserId)?->name : 'Unassigned';

        $this->dispatch('toast', type: 'success', message: "Lead {$lead->lead_code} directly reassigned to {$userName}.");
    }

    // ─────────────────────────────────────────────────────────────
    // CREDITS & WALLET ADJUSTMENT METHODS
    // ─────────────────────────────────────────────────────────────

    public function openCreditModal(?int $clientId = null): void
    {
        $firstClient = Client::where('organization_id', $this->organization->id)->first();
        $this->selectedCreditClientId = $clientId ?: $firstClient?->id;
        $this->creditAmount = 100.00;
        $this->creditAdjustmentType = 'recharge';
        $this->creditReason = 'Manual admin allocation';
        $this->showCreditModal = true;
        $this->dispatch('open-modal', 'adjust-org-credits-modal');
    }

    public function closeCreditModal(): void
    {
        $this->showCreditModal = false;
        $this->reset(['selectedCreditClientId', 'creditAmount', 'creditReason']);
        $this->dispatch('close-modal', 'adjust-org-credits-modal');
    }

    public function executeCreditAdjustment(): void
    {
        $this->validate([
            'selectedCreditClientId' => 'required|exists:clients,id',
            'creditAmount' => 'required|numeric|min:1',
            'creditAdjustmentType' => 'required|in:recharge,refund,deduct',
            'creditReason' => 'required|string|min:3',
        ]);

        $client = Client::where('organization_id', $this->organization->id)->findOrFail($this->selectedCreditClientId);

        DB::transaction(function () use ($client) {
            $wallet = CreditWallet::where('client_id', $client->id)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = CreditWallet::create([
                    'client_id' => $client->id,
                    'balance' => 0.00,
                ]);
            }

            $before = (float)$wallet->balance;
            if ($this->creditAdjustmentType === 'recharge' || $this->creditAdjustmentType === 'refund') {
                $after = $before + $this->creditAmount;
                $txType = $this->creditAdjustmentType;
            } else {
                $after = max(0.00, $before - $this->creditAmount);
                $txType = 'deduct';
            }

            $wallet->update(['balance' => $after]);

            CreditTransaction::create([
                'client_id' => $client->id,
                'credit_before' => $before,
                'credit_used' => $this->creditAmount,
                'credit_after' => $after,
                'transaction_type' => $txType,
                'created_at' => now(),
            ]);
        });

        $this->closeCreditModal();
        $this->dispatch('toast', type: 'success', message: "Credits adjusted successfully for {$client->name}.");
    }

    // ─────────────────────────────────────────────────────────────
    // EXCEL EXPORTS (MULTI-TAB WORKBOOK & INDIVIDUAL TABS)
    // ─────────────────────────────────────────────────────────────

    public function exportFullExcel()
    {
        $slug = Str::slug($this->organization->name);
        $filename = "organization_{$slug}_full_details_" . now()->format('Y-m-d') . ".xlsx";

        $this->dispatch('toast', type: 'success', message: 'Generating multi-tab Excel workbook...');

        return Excel::download(
            new OrganizationDetailMultiTabExport($this->organization),
            $filename
        );
    }

    public function exportTabExcel(string $tab)
    {
        $org = $this->organization;
        $slug = Str::slug($org->name);
        $date = now()->format('Y-m-d');
        $exportedAt = now()->format('d M Y, H:i T');

        $clientIds = Client::where('organization_id', $org->id)->pluck('id')->toArray();
        $userIds = User::where('organization_id', $org->id)->pluck('id')->toArray();

        if ($tab === 'team') {
            $data = User::where('organization_id', $org->id)->with('roles')->orderBy('name')->get();
            $filename = "organization_{$slug}_team_members_{$date}.xlsx";
            return Excel::download(new BaseStyledExport(
                data: $data,
                title: 'Team Members',
                subtitle: "Assigned Team Members | {$org->name} | {$exportedAt}",
                headings: ['User ID', 'Name', 'Email', 'Role', 'Status', 'Joined Date'],
                columns: [
                    'id',
                    'name',
                    'email',
                    fn($u) => $u->roles->first()?->name ? ucwords(str_replace(['-', '_'], ' ', $u->roles->first()->name)) : 'No Role',
                    'status',
                    fn($u) => $u->created_at ? $u->created_at->format('M d, Y H:i') : '—',
                ],
                statusColumns: ['status']
            ), $filename);
        }

        if ($tab === 'clients') {
            $data = Client::where('organization_id', $org->id)->with(['projects', 'wallet'])->withCount(['projects', 'leads'])->orderBy('name')->get();
            $filename = "organization_{$slug}_clients_projects_{$date}.xlsx";
            return Excel::download(new BaseStyledExport(
                data: $data,
                title: 'Clients & Projects',
                subtitle: "Clients & Projects | {$org->name} | {$exportedAt}",
                headings: ['Client ID', 'Client Name', 'Billing Email', 'Status', 'Projects Count', 'Wallet Balance', 'Created Date'],
                columns: [
                    'id',
                    'name',
                    fn($c) => $c->billing_email ?: '—',
                    'status',
                    'projects_count',
                    fn($c) => (float)($c->wallet?->balance ?? 0),
                    fn($c) => $c->created_at ? $c->created_at->format('M d, Y H:i') : '—',
                ],
                statusColumns: ['status']
            ), $filename);
        }

        if ($tab === 'leads') {
            $data = Lead::withoutGlobalScopes()
                ->where(function ($q) use ($clientIds, $userIds) {
                    if (!empty($clientIds)) {
                        $q->whereIn('client_id', $clientIds);
                    }
                    if (!empty($userIds)) {
                        $q->orWhereIn('assigned_to', $userIds);
                    }
                })
                ->with(['client', 'project', 'assignedTo'])
                ->latest('created_at')
                ->get();

            $filename = "organization_{$slug}_directly_assigned_leads_{$date}.xlsx";
            return Excel::download(new BaseStyledExport(
                data: $data,
                title: 'Direct Leads',
                subtitle: "Directly Assigned Leads | {$org->name} | {$exportedAt}",
                headings: ['Lead Code', 'Name', 'Mobile', 'Email', 'Project', 'Client', 'Directly Assigned To', 'Status', 'Created Date'],
                columns: [
                    'lead_code',
                    'name',
                    'mobile',
                    fn($l) => $l->email ?: '—',
                    fn($l) => $l->project?->name ?: '—',
                    fn($l) => $l->client?->name ?: '—',
                    fn($l) => $l->assignedTo?->name ?: 'Unassigned',
                    'status',
                    fn($l) => $l->created_at ? $l->created_at->format('M d, Y H:i') : '—',
                ],
                statusColumns: ['status']
            ), $filename);
        }

        if ($tab === 'credits') {
            $data = CreditTransaction::withoutGlobalScopes()
                ->whereIn('client_id', $clientIds)
                ->with(['client', 'lead'])
                ->latest('created_at')
                ->get();

            $filename = "organization_{$slug}_credits_transactions_{$date}.xlsx";
            return Excel::download(new BaseStyledExport(
                data: $data,
                title: 'Credits & Wallet',
                subtitle: "Credit Transactions | {$org->name} | {$exportedAt}",
                headings: ['Tx ID', 'Client Name', 'Type', 'Lead Code', 'Credit Before', 'Credit In/Out', 'Credit After', 'Date'],
                columns: [
                    'id',
                    fn($tx) => $tx->client?->name ?: '—',
                    'transaction_type',
                    fn($tx) => $tx->lead?->lead_code ?: 'Direct Adjustment',
                    'credit_before',
                    'credit_used',
                    'credit_after',
                    fn($tx) => $tx->created_at ? Carbon::parse($tx->created_at)->format('M d, Y H:i') : '—',
                ],
                statusColumns: ['transaction_type'],
                currencyColumns: ['credit_before', 'credit_used', 'credit_after'],
                hasTotals: true
            ), $filename);
        }

        if ($tab === 'activity') {
            $data = collect();
            try {
                $data = AuditLog::with('user')
                    ->where(function ($q) use ($org, $userIds) {
                        $q->where(function ($sq) use ($org) {
                            $sq->where('subject_type', Organization::class)
                               ->where('subject_id', $org->id);
                        });
                        if (!empty($userIds)) {
                            $q->orWhereIn('user_id', $userIds);
                        }
                    })
                    ->latest('created_at')
                    ->take(100)
                    ->get();
            } catch (Throwable $e) {}

            $filename = "organization_{$slug}_audit_trail_{$date}.xlsx";
            return Excel::download(new BaseStyledExport(
                data: $data,
                title: 'Audit Logs',
                subtitle: "Audit Trail | {$org->name} | {$exportedAt}",
                headings: ['Log ID', 'Action', 'Target / Subject', 'Performed By', 'IP Address', 'Timestamp'],
                columns: [
                    'id',
                    'action',
                    fn($a) => $a->subject_summary,
                    fn($a) => $a->user_name,
                    fn($a) => $a->formatted_ip,
                    fn($a) => $a->created_at ? $a->created_at->format('M d, Y H:i') : '—',
                ],
                statusColumns: ['action']
            ), $filename);
        }

        return $this->exportFullExcel();
    }

    public function render()
    {
        $orgId = $this->organization->id;
        $clientIds = Client::where('organization_id', $orgId)->pluck('id')->toArray();
        $userIds = User::where('organization_id', $orgId)->pluck('id')->toArray();

        // 1. Team Members Query
        $usersQuery = User::where('organization_id', $orgId)->with('roles');
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
        $clients = Client::where('organization_id', $orgId)
            ->with(['projects', 'wallet'])
            ->withCount(['projects', 'leads'])
            ->orderBy('name')
            ->get();

        // 3. Projects Query
        $projects = DB::table('projects')
            ->join('clients', 'projects.client_id', '=', 'clients.id')
            ->where('clients.organization_id', $orgId)
            ->select('projects.*', 'clients.name as client_name')
            ->orderBy('projects.name')
            ->get();

        // 4. Directly Assigned Leads Query
        $leadsQuery = Lead::withoutGlobalScopes()
            ->where(function ($q) use ($clientIds, $userIds) {
                if (!empty($clientIds)) {
                    $q->whereIn('client_id', $clientIds);
                }
                if (!empty($userIds)) {
                    $q->orWhereIn('assigned_to', $userIds);
                }
            })
            ->with(['client', 'project', 'assignedTo']);

        if (!empty($this->leadSearch)) {
            $search = '%' . trim($this->leadSearch) . '%';
            $leadsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('mobile', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('lead_code', 'like', $search);
            });
        }

        if ($this->leadAssignmentFilter === 'assigned') {
            $leadsQuery->whereNotNull('assigned_to');
        } elseif ($this->leadAssignmentFilter === 'unassigned') {
            $leadsQuery->whereNull('assigned_to');
        }

        if ($this->leadStatusFilter !== 'all') {
            $leadsQuery->where('status', $this->leadStatusFilter);
        }

        if ($this->filterLeadClientId) {
            $leadsQuery->where('client_id', $this->filterLeadClientId);
        }

        $leads = $leadsQuery->latest('created_at')->paginate(15);

        // 5. Credits Query
        $creditTxQuery = CreditTransaction::withoutGlobalScopes()
            ->whereIn('client_id', $clientIds)
            ->with(['client', 'lead']);

        if ($this->creditFilterType !== 'all') {
            $creditTxQuery->where('transaction_type', $this->creditFilterType);
        }

        $creditTransactions = $creditTxQuery->latest('created_at')->paginate(15);

        // Recharge requests for clients of this org
        $rechargeRequests = RechargeRequest::whereIn('client_id', $clientIds)->latest('created_at')->take(10)->get();

        // Metric Aggregates
        $totalUsersCount = count($userIds);
        $totalClientsCount = count($clientIds);
        $totalProjectsCount = $projects->count();
        $totalLeadsCount = Lead::withoutGlobalScopes()
            ->where(function ($q) use ($clientIds, $userIds) {
                if (!empty($clientIds)) {
                    $q->whereIn('client_id', $clientIds);
                }
                if (!empty($userIds)) {
                    $q->orWhereIn('assigned_to', $userIds);
                }
            })->count();

        $directlyAssignedLeadsCount = Lead::withoutGlobalScopes()
            ->whereIn('assigned_to', $userIds)
            ->count();

        $totalWalletBalance = DB::table('client_wallets')
            ->whereIn('client_id', $clientIds)
            ->sum('balance') ?? 0;

        $totalOpeningBalance = DB::table('client_wallets')
            ->whereIn('client_id', $clientIds)
            ->sum('opening_balance') ?? 0;

        $globalDefaultLeadCost = app(\App\Services\LeadPricingService::class)->getGlobalDefaultCost();

        $totalCreditsRecharged = DB::table('credit_transactions')
            ->whereIn('client_id', $clientIds)
            ->whereIn('transaction_type', ['recharge', 'refund'])
            ->sum('credit_used') ?? 0;

        $totalCreditsDeducted = DB::table('credit_transactions')
            ->whereIn('client_id', $clientIds)
            ->whereIn('transaction_type', ['deduct', 'reserve'])
            ->sum('credit_used') ?? 0;

        // 6. Audit Trail
        $auditLogs = collect();
        try {
            $auditLogs = AuditLog::with('user')
                ->where(function ($q) use ($orgId, $userIds) {
                    $q->where(function ($sq) use ($orgId) {
                        $sq->where('subject_type', Organization::class)
                           ->where('subject_id', $orgId);
                    });
                    if (!empty($userIds)) {
                        $q->orWhereIn('user_id', $userIds);
                    }
                })
                ->latest('created_at')
                ->take(20)
                ->get();
        } catch (Throwable $e) {}

        // Projects for the selected client in create lead modal
        $clientProjects = $this->newLeadClientId
            ? Project::where('client_id', $this->newLeadClientId)->orderBy('name')->get()
            : collect();

        return view('livewire.admin.organization-detail', [
            'teamMembers' => $teamMembers,
            'clients' => $clients,
            'projects' => $projects,
            'leads' => $leads,
            'creditTransactions' => $creditTransactions,
            'rechargeRequests' => $rechargeRequests,
            'totalUsersCount' => $totalUsersCount,
            'totalClientsCount' => $totalClientsCount,
            'totalProjectsCount' => $totalProjectsCount,
            'totalLeadsCount' => $totalLeadsCount,
            'directlyAssignedLeadsCount' => $directlyAssignedLeadsCount,
            'totalOpeningBalance' => (float)$totalOpeningBalance,
            'totalWalletBalance' => (float)$totalWalletBalance,
            'globalDefaultLeadCost' => (float)$globalDefaultLeadCost,
            'totalCreditsRecharged' => (float)$totalCreditsRecharged,
            'totalCreditsDeducted' => (float)$totalCreditsDeducted,
            'auditLogs' => $auditLogs,
            'clientProjects' => $clientProjects,
        ])->layout('layouts.app');
    }
}
