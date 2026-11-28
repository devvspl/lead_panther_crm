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
use App\Models\Campaign;
use App\Models\MetaAdRecord;
use App\Models\MetaAdAccount;
use App\Models\PortalAccount;
use App\Models\LeadFormMapping;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Throwable;

class OrganizationDetail extends Component
{
    use WithPagination;
    use \Livewire\WithFileUploads;

    public Organization $organization;

    public array $projectRates = [];

    #[\Livewire\Attributes\On('lead-credits-updated')]
    public function refreshCreditData(): void {}
    public string $mappingProjectName = '';

    #[\Livewire\Attributes\On('org-member-updated')]
    public function refreshMemberDetails(): void {}

    public function setBuilderCreditBalanceVisibility(bool $visible): void
    {
        abort_unless(auth()->user()?->status === 'active' && auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
        DB::transaction(function () use ($visible) {
            $organization = Organization::whereKey($this->organization->id)->lockForUpdate()->firstOrFail();
            $before = $organization->show_builder_credit_balance;
            $organization->update(['show_builder_credit_balance' => $visible]);
            AuditLog::create([
                'user_id' => auth()->id(), 'organization_id' => $organization->id,
                'action' => 'organization.credit_balance_visibility_updated',
                'subject_type' => Organization::class, 'subject_id' => $organization->id,
                'from_value' => json_encode(['show_builder_credit_balance' => $before]),
                'to_value' => json_encode(['show_builder_credit_balance' => $visible]), 'created_at' => now(),
            ]);
        });
        $this->organization->refresh();
        $this->dispatch('toast', type: 'success', message: $visible ? 'Credit balance is visible on the builder dashboard.' : 'Credit balance is hidden on the builder dashboard.');
    }

    public function setLeadAssignmentMode(string $mode): void
    {
        abort_unless(auth()->user()?->status === 'active' && auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
        abort_unless(in_array($mode, [Organization::ASSIGNMENT_MODE_AUTOMATIC, Organization::ASSIGNMENT_MODE_MANUAL]), 422);

        $service = app(\App\Services\LeadDistributionService::class);
        $defaults = $service->assignmentDefaults($this->organization);
        $defaults['assignment_mode'] = $mode;
        $defaults['enabled'] = ($mode === Organization::ASSIGNMENT_MODE_AUTOMATIC);

        $service->saveAssignmentConfiguration($this->organization, $defaults, auth()->user());
        $this->organization->refresh();
        $this->dispatch('toast', type: 'success', message: 'Lead Assignment Mode set to ' . ($mode === Organization::ASSIGNMENT_MODE_AUTOMATIC ? 'Automatic Assignment' : 'Manual Assignment (Builder / Developer)') . '.');
    }

    public function auditTableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'Audit ID', 'sortable' => false],
            ['key' => 'action', 'label' => 'Action', 'sortable' => false, 'class' => 'font-mono text-xs'],
            ['key' => 'subject_summary', 'label' => 'Related Record', 'sortable' => false],
            ['key' => 'user_name', 'label' => 'Performed By', 'sortable' => false],
            ['key' => 'formatted_ip', 'label' => 'IP Address', 'sortable' => false],
            ['key' => 'created_at', 'label' => 'Timestamp', 'sortable' => false,
                'formatter' => fn ($value) => $value ? Carbon::parse($value)->format('M d, Y h:i A') : '—'],
        ];
    }

    private function billingClients()
    {
        return Client::where('organization_id', $this->organization->id)
            ->whereIn('id', User::where('organization_id', $this->organization->id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Builder/Developer', 'Builder', 'builder', 'builder-admin', 'Client', 'client', 'Broker', 'broker', 'Channel Partner', 'channel-partner']))->select('id'));
    }

    public function ensureBillingOwner(): Client
    {
        $owners = User::where('organization_id', $this->organization->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Builder/Developer', 'Builder', 'builder', 'builder-admin']))->select('id');

        $client = $this->billingClients()->whereIn('id', $owners)->orderBy('id')->first()
            ?? $this->billingClients()->orderBy('id')->first();

        if ($client) {
            return $client;
        }

        // 1. Check if any user in this organization already has a builder/developer/client role
        $existingBuilder = User::where('organization_id', $this->organization->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Builder/Developer', 'Builder', 'builder', 'builder-admin', 'Client', 'client']))
            ->orderBy('id')
            ->first();

        if ($existingBuilder) {
            $client = Client::find($existingBuilder->id);
            if ($client) {
                CreditWallet::firstOrCreate(['client_id' => $client->id], ['balance' => 0]);
                $this->organization->load('clients.projects', 'clients.wallet');
                return $client;
            }
        }

        // 2. Otherwise create a default primary billing owner client for this organization
        $slug = Str::slug($this->organization->name ?: 'client');
        $email = $slug . '_' . $this->organization->id . '@client.local';
        if (User::where('email', $email)->exists()) {
            $email = $slug . '_' . $this->organization->id . '_' . uniqid() . '@client.local';
        }

        $client = Client::create([
            'organization_id' => $this->organization->id,
            'name' => $this->organization->name,
            'email' => $email,
            'status' => 'active',
        ]);

        CreditWallet::firstOrCreate(
            ['client_id' => $client->id],
            ['balance' => 0]
        );

        $this->organization->load('clients.projects', 'clients.wallet');

        return $client;
    }

    public function autoCreateMainUser(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
        $client = $this->ensureBillingOwner();
        $this->dispatch('toast', type: 'success', message: "Primary billing owner '{$client->name}' is ready.");
    }

    public function saveProjectMapping(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
        $this->mappingProjectName = trim($this->mappingProjectName);
        $this->validate(['mappingProjectName' => 'required|string|max:255']);

        $client = $this->ensureBillingOwner();

        Project::withoutGlobalScopes()->firstOrCreate(['client_id' => $client->id, 'name' => trim($this->mappingProjectName)], ['status' => 'active']);
        $this->projectRates = Project::withoutGlobalScopes()->whereIn('client_id', $this->billingClients()->select('id'))->pluck('lead_cost', 'id')->all();
        $this->reset('mappingProjectName');
        $this->dispatch('toast', type: 'success', message: 'Project mapping saved.');
    }

    public function saveProjectRate(int $projectId): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
        $project = Project::withoutGlobalScopes()->whereIn('client_id', $this->billingClients()->select('id'))->findOrFail($projectId);
        $this->validate(["projectRates.$projectId" => 'nullable|integer|min:1|max:999999']);
        $project->update(['lead_cost' => ($this->projectRates[$projectId] ?? null) === '' ? null : ($this->projectRates[$projectId] ?? null)]);
        $this->dispatch('toast', type: 'success', message: 'Project pricing saved.');
    }

    public function openEditProjectModal(int $projectId): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
        $clientIds = $this->billingClients()->pluck('id')->toArray();
        if (empty($clientIds)) {
            $clientIds = User::where('organization_id', $this->organization->id)->pluck('id')->toArray();
        }

        $project = Project::withoutGlobalScopes()->whereIn('client_id', $clientIds)->findOrFail($projectId);

        $this->editingProjectId = $project->id;
        $this->editingProjectName = $project->name;
        $this->editingProjectStatus = $project->status ?: 'active';
        $this->editingProjectClientId = $project->client_id;
        $this->editingProjectLeadCost = $project->lead_cost;
        $this->resetErrorBag();
        $this->showEditProjectModal = true;
        $this->dispatch('open-modal', 'edit-org-project-modal');
    }

    public function closeEditProjectModal(): void
    {
        $this->showEditProjectModal = false;
        $this->editingProjectId = null;
        $this->editingProjectName = '';
        $this->editingProjectStatus = 'active';
        $this->editingProjectClientId = null;
        $this->editingProjectLeadCost = null;
        $this->resetErrorBag();
        $this->dispatch('close-modal', 'edit-org-project-modal');
    }

    public function updateProject(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);

        if (!$this->editingProjectId) {
            return;
        }

        $clientIds = $this->billingClients()->pluck('id')->toArray();
        if (empty($clientIds)) {
            $clientIds = User::where('organization_id', $this->organization->id)->pluck('id')->toArray();
        }

        $project = Project::withoutGlobalScopes()->whereIn('client_id', $clientIds)->findOrFail($this->editingProjectId);

        $this->editingProjectName = trim($this->editingProjectName);
        $this->validate([
            'editingProjectName' => 'required|string|max:255',
            'editingProjectStatus' => 'required|in:active,inactive,suspended',
            'editingProjectClientId' => ['required', 'integer', Rule::in($clientIds)],
            'editingProjectLeadCost' => 'nullable|integer|min:1|max:999999',
        ], [
            'editingProjectName.required' => 'Project name is required.',
            'editingProjectClientId.required' => 'Please select a mapped client.',
        ]);

        $oldName = $project->name;
        $newName = $this->editingProjectName;

        DB::transaction(function () use ($project, $oldName, $newName) {
            $leadCost = ($this->editingProjectLeadCost === '' || $this->editingProjectLeadCost === null) ? null : (int) $this->editingProjectLeadCost;

            $project->update([
                'name' => $newName,
                'status' => $this->editingProjectStatus,
                'client_id' => $this->editingProjectClientId,
                'lead_cost' => $leadCost,
            ]);

            if ($oldName !== $newName) {
                Campaign::withoutGlobalScopes()
                    ->where('project_id', $project->id)
                    ->where('name', $oldName)
                    ->update(['name' => $newName]);
            }

            AuditLog::create([
                'organization_id' => $this->organization->id,
                'user_id' => auth()->id(),
                'action' => 'project.updated',
                'subject_type' => Project::class,
                'subject_id' => $project->id,
                'from_value' => json_encode([
                    'name' => $oldName,
                    'status' => $project->getOriginal('status'),
                    'client_id' => $project->getOriginal('client_id'),
                    'lead_cost' => $project->getOriginal('lead_cost'),
                ]),
                'to_value' => json_encode([
                    'name' => $newName,
                    'status' => $this->editingProjectStatus,
                    'client_id' => $this->editingProjectClientId,
                    'lead_cost' => $leadCost,
                ]),
                'created_at' => now(),
            ]);
        });

        $this->organization->load('clients.projects', 'clients.wallet');
        $this->projectRates = Project::withoutGlobalScopes()->whereIn('client_id', $this->billingClients()->select('id'))->pluck('lead_cost', 'id')->all();

        $this->dispatch('toast', type: 'success', message: "Project '{$newName}' updated successfully.");
        $this->closeEditProjectModal();
    }

    public function getProjectLeadsCount(int $projectId): int
    {
        $hasProjectCol = Schema::hasColumn('leads', 'project_id');
        $campaignIds = Campaign::withoutGlobalScopes()->where('project_id', $projectId)->pluck('id')->toArray();

        return Lead::withoutGlobalScopes()->where(function ($q) use ($projectId, $campaignIds, $hasProjectCol) {
            if (!empty($campaignIds)) {
                $q->whereIn('campaign_id', $campaignIds);
            }
            if ($hasProjectCol) {
                $q->orWhere('project_id', $projectId);
            }
            if (empty($campaignIds) && !$hasProjectCol) {
                $q->whereRaw('0 = 1');
            }
        })->count();
    }

    public function openRemoveProjectModal(int $projectId): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
        $clientIds = $this->billingClients()->pluck('id')->toArray();
        if (empty($clientIds)) {
            $clientIds = User::where('organization_id', $this->organization->id)->pluck('id')->toArray();
        }

        $project = Project::withoutGlobalScopes()->whereIn('client_id', $clientIds)->findOrFail($projectId);

        $this->projectToRemoveId = $project->id;
        $this->projectToRemoveName = $project->name;
        $this->projectAssignedLeadsCount = $this->getProjectLeadsCount($project->id);
        $this->destinationProjectId = null;
        $this->resetErrorBag();
        $this->showRemoveProjectModal = true;
        $this->dispatch('open-modal', 'remove-org-project-modal');
    }

    public function closeRemoveProjectModal(): void
    {
        $this->showRemoveProjectModal = false;
        $this->projectToRemoveId = null;
        $this->projectToRemoveName = null;
        $this->projectAssignedLeadsCount = 0;
        $this->destinationProjectId = null;
        $this->resetErrorBag();
        $this->dispatch('close-modal', 'remove-org-project-modal');
    }

    public function getOtherProjectsProperty()
    {
        if (!$this->projectToRemoveId) {
            return collect();
        }
        $clientIds = $this->billingClients()->pluck('id')->toArray();
        if (empty($clientIds)) {
            $clientIds = User::where('organization_id', $this->organization->id)->pluck('id')->toArray();
        }
        return Project::withoutGlobalScopes()
            ->whereIn('client_id', $clientIds)
            ->where('id', '!=', $this->projectToRemoveId)
            ->orderBy('name')
            ->get();
    }

    public function confirmRemoveProject(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);

        if (!$this->projectToRemoveId) {
            return;
        }

        $clientIds = $this->billingClients()->pluck('id')->toArray();
        if (empty($clientIds)) {
            $clientIds = User::where('organization_id', $this->organization->id)->pluck('id')->toArray();
        }

        $project = Project::withoutGlobalScopes()->whereIn('client_id', $clientIds)->findOrFail($this->projectToRemoveId);
        $leadsCount = $this->getProjectLeadsCount($project->id);
        $destinationProject = null;

        if ($leadsCount > 0) {
            $otherProjectIds = $this->otherProjects->pluck('id')->toArray();

            if (empty($otherProjectIds)) {
                $this->addError('destinationProjectId', 'Cannot remove this project because it has assigned leads and no other project exists in this organization to receive them.');
                return;
            }

            $this->validate([
                'destinationProjectId' => ['required', 'integer', Rule::in($otherProjectIds)],
            ], [
                'destinationProjectId.required' => 'Please select a destination project to move the assigned leads to.',
                'destinationProjectId.in' => 'Selected destination project is invalid or does not belong to this organization.',
            ]);

            $destinationProject = Project::withoutGlobalScopes()->findOrFail($this->destinationProjectId);
        }

        $projectName = $project->name;
        $targetProjectId = $this->destinationProjectId;

        DB::transaction(function () use ($project, $leadsCount, $targetProjectId, $destinationProject) {
            $hasProjectCol = Schema::hasColumn('leads', 'project_id');
            $oldCampaignIds = Campaign::withoutGlobalScopes()->where('project_id', $project->id)->pluck('id')->toArray();

            // 1. Move assigned leads to destination project
            if ($leadsCount > 0 && $targetProjectId && $destinationProject) {
                $destCampaign = Campaign::withoutGlobalScopes()->where('project_id', $targetProjectId)->first();
                if (!$destCampaign) {
                    $sourceId = LeadSource::first()?->id ?? LeadSource::firstOrCreate(['name' => 'direct'])->id;
                    $destCampaign = Campaign::create([
                        'project_id' => $targetProjectId,
                        'name' => $destinationProject->name . ' Campaign',
                        'lead_source_id' => $sourceId,
                        'status' => 'active',
                    ]);
                }

                $leadsToUpdate = Lead::withoutGlobalScopes()->where(function ($q) use ($project, $oldCampaignIds, $hasProjectCol) {
                    if (!empty($oldCampaignIds)) {
                        $q->whereIn('campaign_id', $oldCampaignIds);
                    }
                    if ($hasProjectCol) {
                        $q->orWhere('project_id', $project->id);
                    }
                });

                $updatePayload = [
                    'campaign_id' => $destCampaign->id,
                    'client_id' => $destinationProject->client_id,
                ];
                if ($hasProjectCol) {
                    $updatePayload['project_id'] = $targetProjectId;
                }
                $leadsToUpdate->update($updatePayload);
            }

            // 2. Cascade or reassign references in related tables
            foreach (['campaigns', 'lead_form_mappings', 'meta_ad_records', 'channel_partners', 'upload_batches', 'lead_delivery_items', 'lead_delivery_plans', 'client_delivery_batches'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'project_id')) {
                    DB::table($table)->where('project_id', $project->id)->update(['project_id' => $targetProjectId]);
                }
            }

            if (Schema::hasTable('distribution_rules')) {
                DB::table('distribution_rules')->where('project_id', $project->id)->delete();
            }
            if (Schema::hasTable('project_units')) {
                DB::table('project_units')->where('project_id', $project->id)->delete();
            }

            // 3. Audit Log
            AuditLog::create([
                'organization_id' => $this->organization->id,
                'user_id' => auth()->id(),
                'action' => 'project.removed',
                'subject_type' => Project::class,
                'subject_id' => $project->id,
                'from_value' => json_encode([
                    'name' => $project->name,
                    'client_id' => $project->client_id,
                    'assigned_leads_count' => $leadsCount,
                ]),
                'to_value' => json_encode([
                    'moved_to_project_id' => $targetProjectId,
                    'moved_to_project_name' => $destinationProject?->name,
                ]),
                'created_at' => now(),
            ]);

            // 4. Delete the project
            $project->delete();
        });

        $this->organization->load('clients.projects', 'clients.wallet');
        $this->projectRates = Project::withoutGlobalScopes()->whereIn('client_id', $this->billingClients()->select('id'))->pluck('lead_cost', 'id')->all();

        if ($leadsCount > 0 && $destinationProject) {
            $this->dispatch('toast', type: 'success', message: "Project '{$projectName}' removed. {$leadsCount} lead(s) moved to '{$destinationProject->name}'.");
        } else {
            $this->dispatch('toast', type: 'success', message: "Project '{$projectName}' removed successfully.");
        }

        $this->closeRemoveProjectModal();
    }

    // Active Navigation Tab: 'overview' | 'team' | 'clients' | 'credits' | 'activity'
    #[Url(as: 'tab', history: true, keep: true)]
    public string $activeTab = 'overview';

    // Organization Edit State & Pricing
    public bool $isEditing = false;
    public string $editName = '';
    public string $editStatus = 'active';
    public $orgLeadCost = null;
    public array $deliverySettings = [];
    public string $deliveryTimezone = '';
    public $executiveResponseMinutes = 15;

    public function saveExecutiveResponseTime(): void
    {
        abort_unless(auth()->user()?->status === 'active' && auth()->user()->hasAnyRole(['Super Admin', 'super-admin']), 403);
        $this->validate(['executiveResponseMinutes' => 'required|integer|min:1|max:10080']);
        DB::transaction(function () {
            $organization = Organization::whereKey($this->organization->id)->lockForUpdate()->firstOrFail();
            $before = $organization->executive_response_minutes;
            $organization->update(['executive_response_minutes' => (int) $this->executiveResponseMinutes]);
            AuditLog::create(['organization_id' => $organization->id, 'user_id' => auth()->id(), 'action' => 'executive_response.configured',
                'subject_type' => Organization::class, 'subject_id' => $organization->id,
                'from_value' => json_encode(['minutes' => $before]), 'to_value' => json_encode(['minutes' => (int) $this->executiveResponseMinutes]), 'created_at' => now()]);
            $this->organization = $organization;
        });
        $this->dispatch('toast', type: 'success', message: 'Executive response time saved for new assignments.');
    }

    public function saveDeliveryTimezone(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
        $this->validate(['deliveryTimezone' => 'required|timezone']);
        try {
            $this->organization->update(['delivery_timezone' => $this->deliveryTimezone]);
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Delivery timezone could not be saved. Please try again.');
            return;
        }
        $this->dispatch('toast', type: 'success', message: 'Organization delivery timezone saved. Existing queued schedules are preserved.');
    }

    // Campaign & Integration Mapping Management
    public bool $showCampaignMappingModal = false;
    public ?int $editingMappingRecordId = null;
    public string $mappingSourceType = 'campaign'; // 'campaign' | 'form'
    public ?int $mappingAccountId = null;
    public string $mappingRemoteId = '';
    public ?int $mappingOrganizationId = null;
    public ?int $mappingClientId = null;
    public ?int $mappingProjectId = null;
    public ?int $mappingDailyLimit = null;
    public ?int $mappingDeliveryInterval = null;
    public bool $mappingInheritLimit = true;
    public bool $mappingInheritInterval = true;

    public string $campaignSearch = '';
    public string $campaignStatusFilter = 'all';
    public string $campaignSortField = 'campaign_name';
    public string $campaignSortDirection = 'asc';

    public function updatedCampaignSearch(): void
    {
        $this->resetPage('campaignsPage');
    }

    public function setStatusFilter(string $status): void
    {
        if ($this->activeTab === 'campaigns') {
            $this->campaignStatusFilter = $status;
            $this->resetPage('campaignsPage');
        }
    }

    public function setCampaignStatusFilter(string $status): void
    {
        $this->campaignStatusFilter = $status;
        $this->resetPage('campaignsPage');
    }

    public function sortCampaignsBy(string $field): void
    {
        if ($this->campaignSortField === $field) {
            $this->campaignSortDirection = $this->campaignSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->campaignSortField = $field;
            $this->campaignSortDirection = 'asc';
        }
        $this->resetPage('campaignsPage');
    }

    public function campaignTableColumns(): array
    {
        return [
            ['key' => 'campaign_name', 'label' => 'Campaign Name', 'type' => 'campaign_name', 'sortable' => true, 'priority' => 1],
            ['key' => 'campaign_id_formatted', 'label' => 'Campaign ID', 'sortable' => true, 'priority' => 2, 'class' => 'font-mono text-muted text-[11px]'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'sortable' => true, 'priority' => 1, 'badgeMap' => [
                'active' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'paused' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'inactive' => 'bg-slate-100 text-slate-600 border border-slate-200',
            ]],
            ['key' => 'facebook_page', 'label' => 'Facebook Page', 'sortable' => false, 'priority' => 2, 'class' => 'text-muted text-xs'],
            ['key' => 'organization_name', 'label' => 'Organization / Client', 'sortable' => false, 'priority' => 1, 'class' => 'font-semibold text-ink text-xs'],
            ['key' => 'project_name', 'label' => 'CRM Project', 'type' => 'campaign_project', 'sortable' => true, 'priority' => 1],
            ['key' => 'daily_limit_formatted', 'label' => 'Daily Limit', 'sortable' => true, 'priority' => 2, 'class' => 'font-mono text-ink text-xs'],
            ['key' => 'delivery_interval', 'label' => 'Delivery Interval', 'type' => 'delivery_interval_badge', 'sortable' => true, 'priority' => 2],
            ['key' => 'delivered_today', 'label' => 'Delivered Today', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-emerald-600 text-xs text-right', 'align' => 'right'],
            ['key' => 'pending_leads', 'label' => 'Pending Leads', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-ink text-xs text-right', 'align' => 'right'],
            ['key' => 'actions', 'label' => 'Actions', 'type' => 'campaign_actions', 'priority' => 1, 'align' => 'right'],
        ];
    }

    public function campaignQuickFilters(): array
    {
        $all = $this->allCampaignRows;
        return [
            ['key' => 'all', 'label' => 'All Campaigns', 'count' => $all->count()],
            ['key' => 'mapped', 'label' => 'Mapped', 'count' => $all->where('is_mapped', true)->count()],
            ['key' => 'unmapped', 'label' => 'Not Mapped', 'count' => $all->where('is_mapped', false)->count()],
            ['key' => 'active', 'label' => 'Active', 'count' => $all->where('status', 'active')->count()],
        ];
    }

    public function getCampaignSummaryStatsProperty(): array
    {
        $all = $this->allCampaignRows;
        return [
            'total' => $all->count(),
            'mapped' => $all->where('is_mapped', true)->count(),
            'unmapped' => $all->where('is_mapped', false)->count(),
            'delivered_today' => (int) $all->sum('delivered_today'),
            'pending' => (int) $all->sum('pending_leads'),
        ];
    }

    public function getAllCampaignRowsProperty(): \Illuminate\Support\Collection
    {
        $service = app(\App\Services\LeadDistributionService::class);
        $ownerIds = User::where('organization_id', $this->organization->id)->pluck('id')->all();

        $projects = $this->mappedDeliveryProjects()->orderBy('name')->get();
        $projectIds = $projects->pluck('id')->all();
        $projectsById = $projects->keyBy('id');

        // Ensure any MetaAdRecords mapped to these projects or organization have their CRM campaign linked
        $metaMappedRecords = MetaAdRecord::where('kind', 'campaign')
            ->where(function ($q) use ($ownerIds, $projectIds) {
                $q->whereIn('project_id', $projectIds)
                  ->orWhere(function ($sub) use ($ownerIds) {
                      $sub->whereIn('client_id', $ownerIds);
                  });
            })->get();

        foreach ($metaMappedRecords as $meta) {
            if ($meta->project_id && (!$meta->crm_campaign_id || !Campaign::withoutGlobalScopes()->where('id', $meta->crm_campaign_id)->where('project_id', $meta->project_id)->exists())) {
                $crmCamp = Campaign::withoutGlobalScopes()
                    ->where('project_id', $meta->project_id)
                    ->where('name', $meta->name)
                    ->first();
                if (!$crmCamp) {
                    $crmCamp = Campaign::create([
                        'project_id' => $meta->project_id,
                        'name' => $meta->name,
                        'utm_campaign' => Str::slug($meta->name),
                        'status' => 'active',
                    ]);
                }
                $meta->update(['crm_campaign_id' => $crmCamp->id]);
            }
        }

        $campaigns = Campaign::withoutGlobalScopes()
            ->whereIn('project_id', $projectIds)
            ->with('leadSource')
            ->orderBy('name')
            ->get();

        $metaByCampaignId = MetaAdRecord::where('kind', 'campaign')
            ->whereIn('crm_campaign_id', $campaigns->pluck('id'))
            ->with(['account', 'leadForm'])
            ->get()
            ->keyBy('crm_campaign_id');

        $metaByName = MetaAdRecord::where('kind', 'campaign')
            ->where(function ($q) use ($ownerIds, $projectIds) {
                $q->whereIn('client_id', $ownerIds)->orWhereIn('project_id', $projectIds);
            })
            ->with(['account', 'leadForm'])
            ->get()
            ->keyBy('name');

        $formsByCampaignId = LeadFormMapping::whereIn('campaign_id', $campaigns->pluck('id'))
            ->orWhereIn('project_id', $projectIds)
            ->with('portalAccount')
            ->get()
            ->keyBy('campaign_id');

        $rows = collect();
        $seenMetaIds = [];

        foreach ($campaigns as $campaign) {
            $project = $projectsById->get($campaign->project_id);
            $metaRecord = $metaByCampaignId->get($campaign->id) ?? $metaByName->get($campaign->name);
            if ($metaRecord) {
                $seenMetaIds[] = $metaRecord->id;
            }
            $formMapping = $formsByCampaignId->get($campaign->id);
            $isMapped = ($metaRecord !== null || $formMapping !== null);

            $fbPageName = $metaRecord?->account?->name;
            if (!$fbPageName && $formMapping?->portalAccount) {
                $fbPageName = $formMapping->portalAccount->account_name ?: "Page #{$formMapping->portalAccount->account_id}";
            }
            $leadFormName = $metaRecord?->leadForm?->form_name ?: $formMapping?->form_name;

            $effective = $project ? $service->effectiveDeliveryConfig($campaign, $project) : [
                'limit' => $campaign->delivery_daily_limit ?? 0,
                'interval' => $campaign->delivery_interval_minutes ?? 0,
                'limit_source' => $campaign->delivery_daily_limit !== null ? 'Campaign override' : 'Default',
                'interval_source' => $campaign->delivery_interval_minutes !== null ? 'Campaign override' : 'Default',
            ];

            $stats = $project ? $service->deliveryStatistics($project, $campaign) : ['today' => 0, 'pending' => 0, 'total' => 0];
            $remoteId = $metaRecord?->remote_id ?? ($formMapping?->form_id ?? null);

            $rows->push([
                'id' => $campaign->id,
                'campaign_id' => (string) ($remoteId ?? ''),
                'campaign_id_formatted' => $remoteId ? "Meta ID: {$remoteId}" : '—',
                'campaign_name' => $campaign->name,
                'status' => $campaign->status ?: 'active',
                'facebook_page' => $fbPageName ?: '—',
                'organization_name' => $this->organization->name,
                'project_name' => $project?->name ?? 'Not Mapped',
                'is_mapped' => $isMapped,
                'daily_limit' => $effective['limit'],
                'daily_limit_formatted' => $effective['limit'] === 0 ? 'Unlimited' : number_format($effective['limit']),
                'daily_limit_source' => $effective['limit_source'],
                'delivery_interval' => $effective['interval'],
                'delivery_interval_formatted' => $effective['interval'] === 0 ? 'Immediate' : $effective['interval'] . ' min',
                'delivery_interval_source' => $effective['interval_source'],
                'delivered_today' => $stats['today'] ?? 0,
                'pending_leads' => $stats['pending'] ?? 0,
                'total_delivered' => $stats['total'] ?? 0,
                'meta_record_id' => $metaRecord?->id,
                'lead_form' => $leadFormName,
                'lead_source' => $campaign->leadSource?->name,
                'source_type' => $metaRecord ? 'meta_campaign' : ($formMapping ? 'lead_form' : 'crm_campaign'),
                'mapping_id' => $metaRecord?->id ?: ($formMapping?->id ?: $campaign->id),
            ]);
        }

        $unlinkedMetas = MetaAdRecord::where('kind', 'campaign')
            ->whereIn('client_id', $ownerIds)
            ->whereNotIn('id', $seenMetaIds)
            ->with(['account', 'leadForm', 'project'])
            ->get();

        foreach ($unlinkedMetas as $meta) {
            $project = $meta->project_id ? $projectsById->get($meta->project_id) : null;
            $fbPageName = $meta->account?->name;
            $leadFormName = $meta->leadForm?->form_name;

            $rows->push([
                'id' => 'meta_' . $meta->id,
                'campaign_id' => (string) $meta->remote_id,
                'campaign_id_formatted' => $meta->remote_id ? "Meta ID: {$meta->remote_id}" : '—',
                'campaign_name' => $meta->name,
                'status' => strtolower($meta->status ?: 'active'),
                'facebook_page' => $fbPageName ?: '—',
                'organization_name' => $this->organization->name,
                'project_name' => $project?->name ?? 'Not Mapped',
                'is_mapped' => (bool) $project,
                'daily_limit' => $project?->delivery_daily_limit ?? 0,
                'daily_limit_formatted' => ($project?->delivery_daily_limit ? number_format($project->delivery_daily_limit) : 'Unlimited'),
                'daily_limit_source' => 'Project default',
                'delivery_interval' => $project?->delivery_interval_minutes ?? 0,
                'delivery_interval_formatted' => ($project?->delivery_interval_minutes ? $project->delivery_interval_minutes . ' min' : 'Immediate'),
                'delivery_interval_source' => 'Project default',
                'delivered_today' => 0,
                'pending_leads' => 0,
                'total_delivered' => 0,
                'meta_record_id' => $meta->id,
                'lead_form' => $leadFormName,
                'lead_source' => 'Meta Ads',
                'source_type' => 'meta_campaign',
                'mapping_id' => $meta->id,
            ]);
        }

        return $rows;
    }

    public function getPaginatedCampaignsProperty(): LengthAwarePaginator
    {
        $rows = $this->allCampaignRows;

        // Filter by Status Pill
        if ($this->campaignStatusFilter === 'mapped') {
            $rows = $rows->where('is_mapped', true);
        } elseif ($this->campaignStatusFilter === 'unmapped') {
            $rows = $rows->where('is_mapped', false);
        } elseif ($this->campaignStatusFilter === 'active') {
            $rows = $rows->where('status', 'active');
        } elseif ($this->campaignStatusFilter === 'inactive') {
            $rows = $rows->where('status', '!=', 'active');
        }

        // Search query
        if (!empty($this->campaignSearch)) {
            $q = strtolower(trim($this->campaignSearch));
            $rows = $rows->filter(function ($row) use ($q) {
                return str_contains(strtolower($row['campaign_name'] ?? ''), $q)
                    || str_contains(strtolower($row['campaign_id'] ?? ''), $q)
                    || str_contains(strtolower($row['project_name'] ?? ''), $q)
                    || str_contains(strtolower($row['facebook_page'] ?? ''), $q);
            });
        }

        // Sorting
        $field = $this->campaignSortField;
        $sortKey = match ($field) {
            'campaign_id_formatted' => 'campaign_id',
            'daily_limit_formatted' => 'daily_limit',
            default => $field,
        };
        $descending = $this->campaignSortDirection === 'desc';
        $rows = $rows->sortBy($sortKey, SORT_REGULAR, $descending)->values();

        $page = LengthAwarePaginator::resolveCurrentPage('campaignsPage');
        $perPage = 15;
        $currentItems = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($currentItems, $rows->count(), $perPage, $page, [
            'pageName' => 'campaignsPage',
        ]);
    }

    protected function mappedDeliveryProjects()
    {
        $owners = User::where('organization_id', $this->organization->id)->select('id');
        $mappedProjectIds = MetaAdRecord::where('kind', 'campaign')
            ->where(function ($q) use ($owners) {
                $q->whereIn('client_id', $owners);
                if ($this->organization->id) {
                    $q->orWhereHas('crmCampaign.leadFilterRules', fn($frq) => $frq->where('organization_id', $this->organization->id));
                }
            })
            ->whereNotNull('project_id')
            ->pluck('project_id')
            ->all();

        return Project::withoutGlobalScopes()
            ->where(function ($q) use ($owners, $mappedProjectIds) {
                $q->whereIn('client_id', $owners);
                if (!empty($mappedProjectIds)) {
                    $q->orWhereIn('id', $mappedProjectIds);
                }
                if (Schema::hasColumn('projects', 'organization_id')) {
                    $q->orWhere('organization_id', $this->organization->id);
                }
            });
    }

    protected function mappedDeliveryCampaigns()
    {
        $projectIds = $this->mappedDeliveryProjects()->select('id');
        $owners = User::where('organization_id', $this->organization->id)->select('id');

        return Campaign::withoutGlobalScopes()
            ->whereIn('project_id', $projectIds)
            ->where(function ($q) use ($owners) {
                $q->whereExists(function ($sub) use ($owners) {
                    $sub->selectRaw('1')->from('meta_ad_records as meta')
                        ->where('meta.kind', 'campaign')
                        ->where(function ($mq) use ($owners) {
                            $mq->whereColumn('meta.crm_campaign_id', 'campaigns.id')
                               ->orWhere(function ($mqq) use ($owners) {
                                   $mqq->whereColumn('meta.project_id', 'campaigns.project_id')
                                       ->where(function ($userQ) use ($owners) {
                                           $userQ->whereIn('meta.client_id', clone $owners)
                                                 ->orWhereNull('meta.client_id');
                                       });
                               });
                        });
                })
                ->orWhereExists(function ($sub) use ($owners) {
                    $sub->selectRaw('1')->from('lead_form_mappings as mapping')
                        ->where(function ($mq) use ($owners) {
                            $mq->whereColumn('mapping.campaign_id', 'campaigns.id')
                               ->orWhere(function ($mqq) use ($owners) {
                                   $mqq->whereColumn('mapping.project_id', 'campaigns.project_id')
                                       ->whereIn('mapping.client_id', clone $owners);
                               });
                        });
                })
                ->orWhere('campaigns.status', 'active');
            });
    }

    public function getDeliveryProjectsProperty()
    {
        $service = app(\App\Services\LeadDistributionService::class);
        $owners = User::where('organization_id', $this->organization->id)->select('id');

        return $this->mappedDeliveryProjects()
            ->orderBy('name')->get()->map(function ($project) use ($service, $owners) {
                // Ensure any MetaAdRecords mapped to this project or organization have their CRM campaign linked
                $metaMappedRecords = MetaAdRecord::where('kind', 'campaign')
                    ->where(function ($q) use ($owners, $project) {
                        $q->where('project_id', $project->id)
                          ->orWhere(function ($sub) use ($owners, $project) {
                              $sub->whereIn('client_id', clone $owners)->where('project_id', $project->id);
                          });
                    })->get();

                foreach ($metaMappedRecords as $meta) {
                    if (!$meta->crm_campaign_id || !Campaign::withoutGlobalScopes()->where('id', $meta->crm_campaign_id)->where('project_id', $project->id)->exists()) {
                        $crmCamp = Campaign::withoutGlobalScopes()
                            ->where('project_id', $project->id)
                            ->where('name', $meta->name)
                            ->first();
                        if (!$crmCamp) {
                            $crmCamp = Campaign::create([
                                'project_id' => $project->id,
                                'name' => $meta->name,
                                'utm_campaign' => Str::slug($meta->name),
                                'status' => 'active',
                            ]);
                        }
                        $meta->update(['crm_campaign_id' => $crmCamp->id, 'project_id' => $project->id]);
                    }
                }

                $campaigns = $this->mappedDeliveryCampaigns()
                    ->where('project_id', $project->id)
                    ->with('leadSource')
                    ->orderBy('name')
                    ->get();

                $metaByCampaignId = MetaAdRecord::where('kind', 'campaign')
                    ->whereIn('crm_campaign_id', $campaigns->pluck('id'))
                    ->with(['account', 'leadForm'])
                    ->get()
                    ->keyBy('crm_campaign_id');

                $formsByCampaignId = LeadFormMapping::whereIn('campaign_id', $campaigns->pluck('id'))
                    ->orWhere('project_id', $project->id)
                    ->with('portalAccount')
                    ->get()
                    ->keyBy('campaign_id');

                return [
                    'project' => $project,
                    'stats' => $service->deliveryStatistics($project, null, $campaigns->modelKeys()),
                    'campaigns' => $campaigns->map(function ($campaign) use ($service, $project, $metaByCampaignId, $formsByCampaignId) {
                        $metaRecord = $metaByCampaignId->get($campaign->id);
                        $formMapping = $formsByCampaignId->get($campaign->id);
                        $isMapped = ($metaRecord !== null || $formMapping !== null);

                        $fbPageName = $metaRecord?->account?->name;
                        if (!$fbPageName && $formMapping?->portalAccount) {
                            $fbPageName = $formMapping->portalAccount->account_name ?: "Page #{$formMapping->portalAccount->account_id}";
                        }
                        $leadFormName = $metaRecord?->leadForm?->form_name ?: $formMapping?->form_name;

                        return [
                            'campaign' => $campaign,
                            'effective' => $service->effectiveDeliveryConfig($campaign, $project),
                            'stats' => $service->deliveryStatistics($project, $campaign),
                            'meta_record' => $metaRecord,
                            'form_mapping' => $formMapping,
                            'is_mapped' => $isMapped,
                            'remote_id' => $metaRecord?->remote_id,
                            'facebook_page' => $fbPageName,
                            'lead_form' => $leadFormName,
                            'source_type' => $metaRecord ? 'meta_campaign' : ($formMapping ? 'lead_form' : 'crm_campaign'),
                            'mapping_id' => $metaRecord?->id ?: ($formMapping?->id ?: $campaign->id),
                        ];
                    }),
                ];
            });
    }

    public function loadDeliverySettings(): void
    {
        foreach (['project' => $this->mappedDeliveryProjects()->get(),
            'campaign' => $this->mappedDeliveryCampaigns()->get()] as $type => $records) {
            foreach ($records as $record) {
                $this->deliverySettings[$type.'_'.$record->id] = [
                    'limit' => $record->delivery_daily_limit ?? ($type === 'project' ? 0 : null),
                    'interval' => $record->delivery_interval_minutes ?? ($type === 'project' ? 0 : null),
                    'enabled' => $type === 'project' ? (bool) $record->delivery_enabled : true,
                    'status' => $record->status ?? 'active',
                    'inherit_limit' => $type === 'campaign' && $record->delivery_daily_limit === null,
                    'inherit_interval' => $type === 'campaign' && $record->delivery_interval_minutes === null,
                ];
            }
        }
    }

    public function getMetaAdAccountsProperty()
    {
        return MetaAdAccount::with(['pages', 'integration'])->whereIn('status', ['connected', 'active'])->get();
    }

    public function getMetaAccountOptionsProperty(): array
    {
        $options = ['' => 'Select Facebook Page / Ad Account'];
        foreach ($this->metaAdAccounts as $acc) {
            $pageNames = $acc->pages->pluck('account_name')->filter()->implode(', ');
            $label = $acc->name . ($pageNames ? " ({$pageNames})" : " (ID: {$acc->ad_account_id})");
            $options[$acc->id] = $label;
        }
        return $options;
    }

    public function getAvailableCampaignOptionsProperty(): array
    {
        $orgOwnerIds = $this->billingClients()->pluck('id')->all();
        if (empty($orgOwnerIds)) {
            $orgOwnerIds = User::where('organization_id', $this->organization->id)->pluck('id')->all();
        }

        $camps = MetaAdRecord::where('kind', 'campaign')
            ->when($this->mappingAccountId, fn($q) => $q->where('meta_ad_account_id', $this->mappingAccountId))
            ->where(function ($query) use ($orgOwnerIds) {
                $query->whereNull('client_id')
                    ->orWhereIn('client_id', $orgOwnerIds)
                    ->orWhereNotNull('meta_ad_account_id');
            })
            ->orderBy('name')
            ->get();

        $options = ['' => 'Select Facebook Campaign'];
        foreach ($camps as $c) {
            $statusLabel = $c->status ? ' [' . strtoupper($c->status) . ']' : '';
            $options[$c->remote_id] = "{$c->name}{$statusLabel} (ID: {$c->remote_id})";
        }
        return $options;
    }

    public function getAvailableFormOptionsProperty(): array
    {
        $orgOwnerIds = $this->billingClients()->pluck('id')->all();
        if (empty($orgOwnerIds)) {
            $orgOwnerIds = User::where('organization_id', $this->organization->id)->pluck('id')->all();
        }

        $forms = LeadFormMapping::query()
            ->where(function ($query) use ($orgOwnerIds) {
                $query->whereNull('client_id')
                    ->orWhereIn('client_id', $orgOwnerIds);
            })
            ->orderBy('form_name')
            ->get();

        $options = ['' => 'Select Lead Form'];
        foreach ($forms as $f) {
            $options[$f->form_id] = "{$f->form_name} (ID: {$f->form_id})";
        }
        return $options;
    }

    public function getOrganizationProjectOptionsProperty(): array
    {
        $owners = User::where('organization_id', $this->organization->id)->select('id');
        $projects = Project::withoutGlobalScopes()
            ->where(function ($q) use ($owners) {
                $q->whereIn('client_id', $owners);
                if (Schema::hasColumn('projects', 'organization_id')) {
                    $q->orWhere('organization_id', $this->organization->id);
                }
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        // Include partner / target organization projects (e.g. Truegrowth Realty) for cross-organization routing
        $otherProjects = Project::withoutGlobalScopes()
            ->with(['client.organization'])
            ->whereNotIn('id', array_keys($projects))
            ->whereHas('client.organization')
            ->orderBy('name')
            ->get();

        foreach ($otherProjects as $op) {
            $orgName = $op->client?->organization?->name;
            if ($orgName && $orgName !== $this->organization->name) {
                $projects[$op->id] = "{$op->name} ({$orgName})";
            }
        }

        return $projects;
    }

    public function openAddCampaignMapping(): void
    {
        $this->reset([
            'editingMappingRecordId',
            'mappingRemoteId',
            'mappingDailyLimit',
            'mappingDeliveryInterval',
        ]);
        $this->mappingSourceType = 'campaign';
        $this->mappingInheritLimit = true;
        $this->mappingInheritInterval = true;
        $this->mappingOrganizationId = $this->organization->id;
        $this->mappingClientId = $this->ensureBillingOwner()?->id;

        $firstAcc = MetaAdAccount::whereIn('status', ['connected', 'active'])->first();
        $this->mappingAccountId = $firstAcc?->id;

        $projects = $this->organizationProjectOptions;
        $this->mappingProjectId = count($projects) === 1 ? array_key_first($projects) : null;

        $this->resetValidation();
        $this->dispatch('open-modal', 'organization-campaign-mapping');
    }

    public function editCampaignMapping(int $recordId, string $sourceType = 'campaign'): void
    {
        $this->resetValidation();
        $this->editingMappingRecordId = $recordId;
        $this->mappingSourceType = $sourceType;
        $this->mappingOrganizationId = $this->organization->id;
        $this->mappingClientId = $this->ensureBillingOwner()?->id;

        if ($sourceType === 'crm_campaign') {
            $campaign = Campaign::withoutGlobalScopes()->findOrFail($recordId);
            $this->mappingProjectId = $campaign->project_id;
            $this->mappingDailyLimit = $campaign->delivery_daily_limit;
            $this->mappingDeliveryInterval = $campaign->delivery_interval_minutes;
            $this->mappingInheritLimit = $campaign->delivery_daily_limit === null;
            $this->mappingInheritInterval = $campaign->delivery_interval_minutes === null;

            $matchedMeta = MetaAdRecord::where('kind', 'campaign')->where('name', $campaign->name)->first();
            if ($matchedMeta) {
                $this->mappingAccountId = $matchedMeta->meta_ad_account_id;
                $this->mappingRemoteId = (string) $matchedMeta->remote_id;
            } else {
                $firstAcc = MetaAdAccount::whereIn('status', ['connected', 'active'])->first();
                $this->mappingAccountId = $firstAcc?->id;
                $this->mappingRemoteId = '';
            }
            $this->mappingSourceType = 'campaign';
        } elseif ($sourceType === 'campaign' || $sourceType === 'meta_campaign') {
            $record = MetaAdRecord::findOrFail($recordId);
            $this->mappingAccountId = $record->meta_ad_account_id;
            $this->mappingRemoteId = (string) $record->remote_id;
            $this->mappingProjectId = $record->project_id;

            $crmCampaign = $record->crmCampaign;
            if ($crmCampaign) {
                $this->mappingDailyLimit = $crmCampaign->delivery_daily_limit;
                $this->mappingDeliveryInterval = $crmCampaign->delivery_interval_minutes;
                $this->mappingInheritLimit = $crmCampaign->delivery_daily_limit === null;
                $this->mappingInheritInterval = $crmCampaign->delivery_interval_minutes === null;
            } else {
                $this->mappingDailyLimit = null;
                $this->mappingDeliveryInterval = null;
                $this->mappingInheritLimit = true;
                $this->mappingInheritInterval = true;
            }
        } else {
            $mapping = LeadFormMapping::findOrFail($recordId);
            $this->mappingAccountId = $mapping->portal_account_id;
            $this->mappingRemoteId = (string) $mapping->form_id;
            $this->mappingProjectId = $mapping->project_id;

            $campaign = $mapping->campaign;
            if ($campaign) {
                $this->mappingDailyLimit = $campaign->delivery_daily_limit;
                $this->mappingDeliveryInterval = $campaign->delivery_interval_minutes;
                $this->mappingInheritLimit = $campaign->delivery_daily_limit === null;
                $this->mappingInheritInterval = $campaign->delivery_interval_minutes === null;
            } else {
                $this->mappingDailyLimit = null;
                $this->mappingDeliveryInterval = null;
                $this->mappingInheritLimit = true;
                $this->mappingInheritInterval = true;
            }
        }

        $this->dispatch('open-modal', 'organization-campaign-mapping');
    }

    public function saveCampaignMapping(): void
    {
        $this->validate([
            'mappingProjectId' => 'required|integer',
            'mappingRemoteId' => 'required|string',
            'mappingDailyLimit' => 'nullable|integer|min:0|max:100000',
            'mappingDeliveryInterval' => 'nullable|integer|min:0|max:10080',
        ], [
            'mappingProjectId.required' => 'Please select a CRM Project belonging to this organization.',
            'mappingRemoteId.required' => 'Please select a Campaign or Form to map.',
        ]);

        $validProjectIds = array_keys($this->organizationProjectOptions);
        abort_unless(in_array((int) $this->mappingProjectId, $validProjectIds, true), 422, 'The selected project does not belong to this organization.');

        $project = Project::withoutGlobalScopes()->findOrFail($this->mappingProjectId);
        $targetClientId = $project->client_id ?: $this->ensureBillingOwner()->id;

        if ($this->mappingSourceType === 'campaign' || $this->mappingSourceType === 'meta_campaign') {
            $record = MetaAdRecord::where('kind', 'campaign')
                ->where(function ($q) {
                    if ($this->editingMappingRecordId && MetaAdRecord::where('id', $this->editingMappingRecordId)->exists()) {
                        $q->where('id', $this->editingMappingRecordId);
                    } else {
                        $q->where('remote_id', $this->mappingRemoteId);
                    }
                })->firstOrFail();

            $crmCampaign = null;
            if ($this->editingMappingRecordId && Campaign::withoutGlobalScopes()->where('id', $this->editingMappingRecordId)->where('project_id', $project->id)->exists()) {
                $crmCampaign = Campaign::withoutGlobalScopes()->find($this->editingMappingRecordId);
            }
            if (!$crmCampaign) {
                $crmCampaign = Campaign::withoutGlobalScopes()
                    ->where('project_id', $project->id)
                    ->where('name', $record->name)
                    ->first();
            }
            if (!$crmCampaign) {
                $crmCampaign = Campaign::create([
                    'project_id' => $project->id,
                    'name' => $record->name,
                    'utm_campaign' => Str::slug($record->name),
                    'status' => 'active',
                ]);
            }

            $crmCampaign->update([
                'delivery_daily_limit' => $this->mappingInheritLimit ? null : (int) $this->mappingDailyLimit,
                'delivery_interval_minutes' => $this->mappingInheritInterval ? null : (int) $this->mappingDeliveryInterval,
                'status' => 'active',
            ]);

            app(\App\Services\MetaCampaignLeadService::class)->saveMapping(
                $record,
                (int) $targetClientId,
                (int) $project->id,
                $crmCampaign->id
            );

            $this->dispatch('toast', type: 'success', message: "Facebook Campaign '{$record->name}' mapped to {$project->name} successfully.");
        } else {
            $mapping = LeadFormMapping::where('form_id', $this->mappingRemoteId)->first();
            $formName = $mapping?->form_name ?: "Lead Form #{$this->mappingRemoteId}";

            $campaign = Campaign::withoutGlobalScopes()
                ->where('project_id', $project->id)
                ->where('name', $formName)
                ->first();
            if (!$campaign) {
                $campaign = Campaign::create([
                    'project_id' => $project->id,
                    'name' => $formName,
                    'utm_campaign' => Str::slug($formName),
                    'status' => 'active',
                ]);
            }

            $campaign->update([
                'delivery_daily_limit' => $this->mappingInheritLimit ? null : (int) $this->mappingDailyLimit,
                'delivery_interval_minutes' => $this->mappingInheritInterval ? null : (int) $this->mappingDeliveryInterval,
            ]);

            LeadFormMapping::updateOrCreate(
                ['form_id' => $this->mappingRemoteId],
                [
                    'portal_account_id' => $this->mappingAccountId ?: $mapping?->portal_account_id ?: 1,
                    'form_name' => $formName,
                    'client_id' => $targetClientId,
                    'project_id' => $project->id,
                    'campaign_id' => $campaign->id,
                    'status' => 'active',
                ]
            );

            $this->dispatch('toast', type: 'success', message: "Lead Form '{$formName}' mapped to {$project->name} successfully.");
        }

        $this->loadDeliverySettings();
        $this->dispatch('close-modal', 'organization-campaign-mapping');
    }

    public function fetchMetaCampaignLeads(int $recordId): void
    {
        $record = MetaAdRecord::where('kind', 'campaign')->findOrFail($recordId);
        try {
            app(\App\Services\MetaCampaignLeadService::class)->start($record);
            $this->dispatch('toast', type: 'success', message: "Lead fetch started for '{$record->name}'.");
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function saveDeliverySettings(string $type, int $id): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
        abort_unless(in_array($type, ['project', 'campaign']), 422);
        $record = ($type === 'project' ? $this->mappedDeliveryProjects() : $this->mappedDeliveryCampaigns())->findOrFail($id);
        $key = $type.'_'.$id;
        $this->validate([
            "deliverySettings.$key.limit" => 'nullable|integer|min:0|max:100000',
            "deliverySettings.$key.interval" => 'nullable|integer|min:0|max:10080',
            "deliverySettings.$key.enabled" => 'sometimes|boolean',
            "deliverySettings.$key.status" => 'sometimes|string|in:active,inactive,paused',
            "deliverySettings.$key.inherit_limit" => 'sometimes|boolean',
            "deliverySettings.$key.inherit_interval" => 'sometimes|boolean',
        ]);
        $settings = $this->deliverySettings[$key] ?? [];
        $values = [];
        foreach (['limit' => 'delivery_daily_limit', 'interval' => 'delivery_interval_minutes'] as $field => $column) {
            $value = $settings[$field] ?? null;
            $values[$column] = $type === 'campaign' && (($settings['inherit_'.$field] ?? false) || $value === '' || $value === null)
                ? null : (int) $value;
        }
        if ($type === 'project') $values['delivery_enabled'] = (bool) ($settings['enabled'] ?? $record->delivery_enabled);
        if ($type === 'campaign' && isset($settings['status'])) $values['status'] = $settings['status'];
        try {
            $record->update($values);
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Delivery settings could not be saved. Please try again.');
            return;
        }
        $this->dispatch('close-modal', ($type === 'project' ? 'project-delivery-settings-'.$id : 'campaign-delivery-settings-'.$id));
        $this->dispatch('toast', type: 'success', message: 'Delivery settings saved. New leads will follow these settings; queued leads keep their scheduled delivery time.');
    }

    // Team Management & Filters
    public array $selectedTeamUserIds = [];
    #[\Livewire\Attributes\Locked]
    public array $loginDetailsLogIds = [];
    #[\Livewire\Attributes\Locked]
    public string $loginDetailsBatch = '';

    public bool $showAccountEmailComposer = false;
    public string $accountEmailSubject = '';
    public string $accountEmailBody = '';
    public string $accountEmailCc = '';
    public string $accountEmailBcc = '';
    public bool $accountEmailCopyToMe = false;
    public array $accountEmailAttachments = [];
    public string $accountEmailAddRecipientId = '';
    public string $accountEmailPreviewUserId = '';

    public function openAccountEmailComposer(): void
    {
        $this->authorizeLoginDetails();
        $this->validate(['selectedTeamUserIds' => ['required', 'array', 'min:1', 'max:100'], 'selectedTeamUserIds.*' => ['integer']]);
        $ids = array_unique(array_map('intval', $this->selectedTeamUserIds));
        abort_unless(User::where('organization_id', $this->organization->id)->whereIn('id', $ids)->count() === count($ids), 403);
        $this->resetValidation();
        $this->reset(['accountEmailCc', 'accountEmailBcc', 'accountEmailCopyToMe', 'accountEmailAttachments', 'accountEmailAddRecipientId']);
        $content = app(\App\Services\NotificationDeliveryService::class)->accountLoginEmailDefaults();
        $this->accountEmailSubject = $content['subject'];
        $this->accountEmailBody = $content['body'];
        $this->accountEmailPreviewUserId = (string) reset($ids);
        $this->loginDetailsBatch = '';
        $this->showAccountEmailComposer = true;
    }

    public function getAccountEmailRecipientOptionsProperty()
    {
        if (! $this->showAccountEmailComposer) return collect();
        $this->authorizeLoginDetails();
        return User::where('organization_id', $this->organization->id)->with('roles')->orderBy('name')->get(['id', 'name', 'email', 'status']);
    }

    public function updatedAccountEmailAddRecipientId(): void
    {
        $this->authorizeLoginDetails();
        if ($this->accountEmailAddRecipientId === '') return;
        $user = User::where('organization_id', $this->organization->id)->where('status', 'active')->find($this->accountEmailAddRecipientId);
        abort_unless($user, 403);
        $ids = array_values(array_unique(array_merge(array_map('intval', $this->selectedTeamUserIds), [$user->id])));
        if (count($ids) > 100) {
            $this->addError('selectedTeamUserIds', 'Select up to 100 recipients at a time.');
        } else {
            $this->selectedTeamUserIds = $ids;
            $this->updatedSelectedTeamUserIds();
        }
        $this->accountEmailAddRecipientId = '';
    }

    public function removeAccountEmailRecipient(int $userId): void
    {
        $this->authorizeLoginDetails();
        $this->selectedTeamUserIds = array_values(array_filter($this->selectedTeamUserIds, fn ($id) => (int) $id !== $userId));
        $this->updatedSelectedTeamUserIds();
    }

    public function getAccountEmailSelectedRecipientsProperty()
    {
        if (! $this->showAccountEmailComposer) return collect();
        $this->authorizeLoginDetails();
        return User::where('organization_id', $this->organization->id)->whereIn('id', $this->selectedTeamUserIds)->with('roles')->orderBy('name')->get(['id', 'name', 'email', 'phone', 'organization_id', 'user_code']);
    }

    public function getAccountEmailPreviewProperty(): string
    {
        if (! $this->showAccountEmailComposer) return '';
        $this->authorizeLoginDetails();
        $user = $this->accountEmailSelectedRecipients->firstWhere('id', (int) $this->accountEmailPreviewUserId) ?? $this->accountEmailSelectedRecipients->first();
        if (! $user) return '';
        $message = app(\App\Services\NotificationDeliveryService::class)->renderAccountLoginEmail(
            ['subject' => $this->accountEmailSubject, 'body' => $this->accountEmailBody], $user,
            '[Secure password setup link generated separately when sending]'
        );
        return (new \App\Mail\BrandedNotificationMail($message['subject'], $message['body']))->render();
    }

    public function removeAccountEmailAttachment(int $index): void
    {
        $this->authorizeLoginDetails();
        unset($this->accountEmailAttachments[$index]);
        $this->accountEmailAttachments = array_values($this->accountEmailAttachments);
        $this->resetValidation('accountEmailAttachments');
    }

    protected function accountEmailCopyAddresses(string $value, string $field): array
    {
        $addresses = array_values(array_unique(array_filter(array_map(fn ($email) => strtolower(trim($email)), preg_split('/[,;\s]+/', $value)))));
        $validator = \Illuminate\Support\Facades\Validator::make([$field => $addresses], [
            $field => ['array', 'max:20'], $field.'.*' => ['email:rfc', 'max:254'],
        ]);
        if ($validator->fails()) throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Enter up to 20 valid email addresses, separated by commas.']);
        return $addresses;
    }

    protected function authorizeLoginDetails(): void
    {
        abort_unless(auth()->user()?->status === 'active' && auth()->user()->hasAnyRole(['Super Admin', 'super-admin']), 403);
    }

    protected function filteredTeamMembers()
    {
        $query = User::where('organization_id', $this->organization->id);
        if (trim($this->userSearch) !== '') {
            $search = '%'.trim($this->userSearch).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('email', 'like', $search)->orWhere('phone', 'like', $search));
        }
        if ($this->userRoleFilter !== 'all') {
            $role = $this->userRoleFilter;
            $query->whereHas('roles', fn ($q) => $q->where('name', $role)->orWhere('name', strtolower(str_replace(' ', '-', $role))));
        }
        return $query;
    }

    public function updatedSelectedTeamUserIds(): void
    {
        $this->loginDetailsBatch = '';
        if (! in_array((int) $this->accountEmailPreviewUserId, array_map('intval', $this->selectedTeamUserIds), true)) {
            $this->accountEmailPreviewUserId = (string) (array_values($this->selectedTeamUserIds)[0] ?? '');
        }
    }

    public function toggleAllTeamUsers(): void
    {
        $this->authorizeLoginDetails();
        $ids = $this->filteredTeamMembers()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->selectedTeamUserIds = count(array_diff($ids, $this->selectedTeamUserIds)) === 0 ? [] : $ids;
        $this->loginDetailsBatch = '';
    }

    public function sendSelectedLoginDetails(): void
    {
        $this->authorizeLoginDetails();
        $this->validate([
            'selectedTeamUserIds' => ['required', 'array', 'min:1', 'max:100'], 'selectedTeamUserIds.*' => ['required', 'integer'],
            'accountEmailSubject' => ['required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'accountEmailBody' => ['required', 'string', 'max:20000', function ($attribute, $value, $fail) {
                if (! str_contains($value, '{{password_setup_link}}')) $fail('Keep the {{password_setup_link}} placeholder so each user receives their own secure setup link.');
            }],
            'accountEmailCc' => ['string', 'max:6000'], 'accountEmailBcc' => ['string', 'max:6000'],
            'accountEmailAttachments' => ['array', 'max:5'], 'accountEmailAttachments.*' => ['file', 'max:10240'],
        ]);
        if (collect($this->accountEmailAttachments)->sum(fn ($file) => $file->getSize()) > 15 * 1024 * 1024) {
            throw \Illuminate\Validation\ValidationException::withMessages(['accountEmailAttachments' => 'Attachments must total 15 MB or less.']);
        }
        $ids = array_values(array_unique(array_map('intval', $this->selectedTeamUserIds)));
        $members = User::where('organization_id', $this->organization->id)->whereIn('id', $ids)->get();
        abort_unless($members->count() === count($ids), 403);
        $cc = $this->accountEmailCopyAddresses($this->accountEmailCc, 'accountEmailCc');
        $bcc = $this->accountEmailCopyAddresses($this->accountEmailBcc, 'accountEmailBcc');
        if ($this->accountEmailCopyToMe) $bcc = array_unique(array_merge($bcc, $this->accountEmailCopyAddresses(auth()->user()->email, 'accountEmailBcc')));
        $to = $members->map(fn ($user) => strtolower($user->email))->all();
        if (array_intersect($to, array_merge($cc, $bcc))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['accountEmailCc' => 'Selected team members already receive separate emails. Remove their addresses from CC/BCC.']);
        }
        $bcc = array_values(array_diff($bcc, $cc));
        $this->loginDetailsBatch = $this->loginDetailsBatch ?: (string) Str::uuid();
        $key = 'account-login-batch:'.auth()->id().':'.$this->organization->id.':'.$this->loginDetailsBatch;
        $this->loginDetailsLogIds = \Illuminate\Support\Facades\Cache::lock($key.':lock', 120)->block(5, function () use ($members, $key, $cc, $bcc) {
            if ($existing = \Illuminate\Support\Facades\Cache::get($key)) return $existing;
            $attachments = [];
            try {
                foreach ($this->accountEmailAttachments as $file) {
                    $path = $file->store('account-email-attachments/'.now()->format('Y-m-d').'/'.$this->loginDetailsBatch, 'local');
                    if (! $path) throw new \RuntimeException('Attachment storage failed');
                    $attachments[] = ['path' => $path, 'name' => substr(preg_replace('/[\x00-\x1f\x7f]/', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 200), 'mime' => $file->getMimeType() ?: 'application/octet-stream'];
                }
                $composer = \Illuminate\Support\Facades\Crypt::encryptString(json_encode([
                    'subject' => $this->accountEmailSubject, 'body' => $this->accountEmailBody,
                    'cc' => $cc, 'bcc' => $bcc, 'attachments' => $attachments,
                ], JSON_THROW_ON_ERROR));
                $logs = DB::transaction(function () use ($members, $composer) {
                    $logs = [];
                    foreach ($members as $member) {
                        $log = \App\Models\NotificationLog::create([
                            'notifiable_type' => User::class, 'notifiable_id' => $member->id,
                            'channel' => 'email', 'status' => 'pending', 'retries' => 0,
                            'payload' => json_encode(['template_key' => 'account_login_details', 'organization_id' => $this->organization->id, 'sender_id' => auth()->id(), 'batch_id' => $this->loginDetailsBatch, 'composer' => $composer]),
                        ]);
                        $logs[] = $log->id;
                    }
                    return $logs;
                });
            } catch (Throwable $exception) {
                foreach ($attachments as $attachment) \Illuminate\Support\Facades\Storage::disk('local')->delete($attachment['path']);
                throw \Illuminate\Validation\ValidationException::withMessages(['accountEmailAttachments' => 'Unable to prepare the email. Check attachment storage and try again.']);
            }
            \Illuminate\Support\Facades\Cache::put($key, $logs, now()->addDay());
            foreach ($logs as $id) $this->dispatchAccountLoginDetails($id);
            return $logs;
        });
        $this->showAccountEmailComposer = false;
        $this->accountEmailAttachments = [];
        $this->dispatch('toast', type: 'success', message: 'Account emails submitted. See recipient statuses below.');
    }

    public function retryLoginDetails(int $userId): void
    {
        $this->authorizeLoginDetails();
        abort_unless(User::where('organization_id', $this->organization->id)->whereKey($userId)->exists(), 403);
        $log = \App\Models\NotificationLog::whereIn('id', $this->loginDetailsLogIds)->where('notifiable_type', User::class)->where('notifiable_id', $userId)->where('status', 'failed')->first();
        if ($log && \App\Models\NotificationLog::whereKey($log->id)->where('status', 'failed')->update(['status' => 'pending', 'last_error' => null])) {
            $this->dispatchAccountLoginDetails($log->id);
        }
    }

    protected function dispatchAccountLoginDetails(int $id): void
    {
        try {
            \App\Jobs\SendAccountLoginDetails::dispatch($id);
        } catch (Throwable $exception) {
            \App\Models\NotificationLog::whereKey($id)->where('status', 'pending')->update([
                'status' => 'failed', 'last_error' => 'Email could not be queued. Please retry.',
            ]);
        }
    }

    public function getLoginDetailsResultsProperty(): array
    {
        if (! $this->loginDetailsLogIds) return [];
        $this->authorizeLoginDetails();
        return \App\Models\NotificationLog::whereIn('id', $this->loginDetailsLogIds)
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', User::where('organization_id', $this->organization->id)->select('id'))
            ->get()->mapWithKeys(fn ($log) => [$log->notifiable_id => ['status' => $log->status, 'error' => $log->last_error]])->all();
    }

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

    // Project Removal & Lead Reassignment
    public bool $showRemoveProjectModal = false;
    public ?int $projectToRemoveId = null;
    public ?string $projectToRemoveName = null;
    public int $projectAssignedLeadsCount = 0;
    public ?int $destinationProjectId = null;

    // Project Edit
    public bool $showEditProjectModal = false;
    public ?int $editingProjectId = null;
    public string $editingProjectName = '';
    public string $editingProjectStatus = 'active';
    public ?int $editingProjectClientId = null;
    public $editingProjectLeadCost = null;

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

    // Lead Credits Management
    public string $creditFilterType = 'all'; // 'all' | 'recharge' | 'deduct' | 'refund' | 'reserve'
    public string $creditSearch = '';
    public bool $showCreditModal = false;
    public ?int $selectedCreditClientId = null;
    public string $creditAdjustmentType = 'recharge'; // 'recharge' | 'refund' | 'deduct'
    public $creditAmount = 100;
    public string $creditReason = 'Manual credit allocation';

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
        $this->projectRates = Project::withoutGlobalScopes()->whereIn('client_id', $this->billingClients()->select('id'))->pluck('lead_cost', 'id')->all();
        $this->deliveryTimezone = $organization->delivery_timezone ?: config('app.timezone');
        $this->executiveResponseMinutes = $organization->executive_response_minutes ?? 15;
        $this->editName = (string) ($organization->name ?? '');
        $this->editStatus = (string) ($organization->status ?? 'active');
        $this->orgLeadCost = $organization->lead_cost !== null ? (int) $organization->lead_cost : null;
        $this->loadDeliverySettings();

        $requestedTab = request()->query('tab');
        if ($requestedTab && in_array($requestedTab, ['overview', 'team', 'clients', 'leads', 'credits', 'activity', 'campaigns'])) {
            $this->activeTab = $requestedTab;
        }

        $roles = $this->availableRoles;
        $this->newUserRole = isset($roles['Builder']) ? 'Builder' : (string) (array_key_first($roles) ?? 'Builder');
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['overview', 'team', 'clients', 'leads', 'credits', 'activity', 'campaigns'])) {
            $this->activeTab = $tab;
            $this->resetPage();
        }
    }

    public function exception($e, $stopPropagation): void
    {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            $this->dispatch('toast', type: 'error', title: 'Please check the form', message: $e->validator->errors()->first());
        }
    }

    public function updateOrgLeadCost(): void
    {
        $this->validate([
            'orgLeadCost' => 'nullable|integer|min:1|max:100000',
        ]);

        $this->organization->update([
            'lead_cost' => $this->orgLeadCost !== null && $this->orgLeadCost > 0 ? (int) $this->orgLeadCost : null,
        ]);
        $this->organization->refresh();
        $this->orgLeadCost = $this->organization->lead_cost !== null ? (int) $this->organization->lead_cost : null;

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

    public function updatedCreditSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUserSearch(): void
    {
        $this->selectedTeamUserIds = [];
        $this->loginDetailsBatch = '';
        $this->resetPage();
    }

    public function updatedUserRoleFilter(): void
    {
        $this->selectedTeamUserIds = [];
        $this->loginDetailsBatch = '';
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
            if (in_array(strtolower(trim($roleName)), ['account manager', 'account-manager'])) {
                continue;
            }
            $label = ucwords(str_replace(['-', '_'], ' ', $roleName));
            if (!isset($roles[$label])) {
                $roles[$label] = $label;
            }
        }

        if (empty($roles)) {
            $roles = [
                'Builder/Developer' => 'Builder/Developer',
                'Broker' => 'Broker',
                'Channel Partner' => 'Channel Partner',
                'Executive' => 'Executive',
                'Super Admin' => 'Super Admin',
            ];
        }

        return $roles;
    }

    public function openAddMemberModal(): void
    {
        $this->reset(['newUserName', 'newUserEmail', 'newUserPassword', 'generatedInviteLink']);
        $roles = $this->availableRoles;
        $this->newUserRole = isset($roles['Builder']) ? 'Builder' : (string) (array_key_first($roles) ?? 'Builder');
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

            if (in_array(strtolower(str_replace([' ', '_'], '-', $this->newUserRole)), ['team-leader'])) {
                \App\Support\TeamAccess::ensureLeaderTeams($this->organization->id);
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
        $this->newClientName = $this->organization->name;
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
        $firstClient = $this->billingClients()->first();
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
            'newLeadClientId' => 'required|exists:users,id',
            'newLeadAssignedTo' => 'nullable|exists:users,id',
            'newLeadStatus' => 'required|string',
        ]);

        // Verify client belongs to this organization
        $client = $this->billingClients()->findOrFail($this->newLeadClientId);

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

        // Ensure a campaign exists for this project
        $campaign = Campaign::firstOrCreate(
            ['project_id' => $projectId, 'name' => "{$client->name} General Campaign"],
            ['lead_source_id' => $leadSource->id, 'status' => 'active']
        );

        $leadCode = Lead::generateUniqueLeadCode('LP');

        $lead = Lead::create([
            'lead_code' => $leadCode,
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
            'lead_source_id' => $leadSource->id,
            'name' => $this->newLeadName,
            'mobile' => $this->newLeadMobile,
            'email' => $this->newLeadEmail,
            'requirement' => $this->newLeadRequirement,
            'status' => $this->newLeadStatus,
            'current_stage' => $this->newLeadStatus,
            'assigned_to' => $this->newLeadAssignedTo, // Direct assignment!
        ]);

        // Use the same scheduled delivery path as webhook and Meta ingestion.
        event(new \App\Events\LeadCreated($lead));

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

    public function openCreditModal(?int $clientId = null, string $type = 'recharge'): void
    {
        $firstClient = $this->billingClients()->first();
        $this->selectedCreditClientId = $clientId ?: $firstClient?->id;
        $this->creditAmount = 100;
        $this->creditAdjustmentType = in_array($type, ['recharge', 'opening_credit', 'refund', 'manual_adjustment', 'deduct']) ? $type : 'recharge';
        $this->creditReason = match ($this->creditAdjustmentType) {
            'opening_credit' => 'Initial opening balance allocation',
            'refund' => 'Lead replacement / SLA refund',
            'manual_adjustment' => 'Administrative wallet adjustment',
            'deduct' => 'Manual deduction / fee',
            default => 'Manual admin recharge',
        };
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
            'selectedCreditClientId' => 'required|exists:users,id',
            'creditAmount' => 'required|integer|min:1',
            'creditAdjustmentType' => 'required|in:recharge,opening_credit,refund,manual_adjustment,deduct',
            'creditReason' => 'required|string|min:3',
        ]);

        $client = $this->billingClients()->findOrFail($this->selectedCreditClientId);

        app(\App\Services\LeadCreditService::class)->adjust($client, $this->creditAmount,
            $this->creditAdjustmentType, $this->creditReason, auth()->user());

        $this->closeCreditModal();
        $this->organization->load('clients.wallet');
        $this->dispatch('toast', type: 'success', message: "Credits updated successfully for {$client->name}.");
    }

    public function rebalanceOrgCredits(?int $clientId = null): void
    {
        $clients = $clientId ? [$this->billingClients()->findOrFail($clientId)] : $this->billingClients()->get();
        $totalRepaired = 0;
        foreach ($clients as $client) {
            $res = app(\App\Services\LeadCreditService::class)->auditAndRebalanceLedger($client, auth()->user());
            $totalRepaired += $res['repaired_count'];
        }
        $this->organization->load('clients.wallet');
        $this->dispatch('toast', type: 'success', message: "Credit ledger audited and balanced. {$totalRepaired} transaction(s) re-aligned.");
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
        if (in_array($tab, ['team', 'clients', 'leads', 'credits', 'activity'], true)) {
            $this->dispatch('toast', type: 'success', message: 'Preparing the selected tab for Excel download...');
        }
        $org = $this->organization;
        $slug = Str::slug($org->name);
        $date = now()->format('Y-m-d');
        $exportedAt = now()->format('d M Y, H:i T');

        $clientIds = $this->billingClients()->pluck('id')->toArray();
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
            $data = Project::withoutGlobalScopes()->whereIn('client_id', $clientIds)->with('client')->orderBy('name')->get();
            return Excel::download(new BaseStyledExport(
                data: $data,
                title: 'Project Mappings',
                subtitle: "Projects | {$org->name} | {$exportedAt}",
                headings: ['Project ID', 'Project', 'Mapped Client', 'Status', 'Project Rate Override'],
                columns: ['id', 'name', fn($p) => $p->client?->name ?? '', 'status', fn($p) => $p->lead_cost ?? 'Inherit'],
                statusColumns: ['status']
            ), "organization_{$slug}_project_mappings_{$date}.xlsx");
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
            $exportQuery = CreditTransaction::withoutGlobalScopes()
                ->whereIn('client_id', $clientIds)
                ->with(['client', 'lead']);

            if ($this->creditFilterType !== 'all') {
                if ($this->creditFilterType === 'consumption') {
                    $exportQuery->whereIn('transaction_type', ['lead_consumption', 'deduct', 'reserve']);
                } else {
                    $exportQuery->where('transaction_type', $this->creditFilterType);
                }
            }

            if (!empty($this->creditSearch)) {
                $term = trim($this->creditSearch);
                $cleanTerm = ltrim($term, '#');
                $exportQuery->where(function ($q) use ($term, $cleanTerm) {
                    if (is_numeric($cleanTerm)) {
                        $q->orWhere('credit_transactions.id', (int) $cleanTerm);
                    }
                    $q->orWhere('credit_transactions.description', 'like', "%{$term}%")
                      ->orWhere('credit_transactions.transaction_type', 'like', "%{$term}%")
                      ->orWhereHas('lead', function ($lq) use ($term) {
                          $lq->where('lead_code', 'like', "%{$term}%")
                             ->orWhere('name', 'like', "%{$term}%")
                             ->orWhere('mobile', 'like', "%{$term}%");
                      })
                      ->orWhereHas('client', function ($cq) use ($term) {
                          $cq->where('name', 'like', "%{$term}%")
                             ->orWhere('email', 'like', "%{$term}%");
                      });
                });
            }

            $data = $exportQuery->latest('created_at')->orderBy('id', 'desc')->get();

            $filename = "organization_{$slug}_credits_transactions_{$date}.xlsx";
            return Excel::download(new BaseStyledExport(
                data: $data,
                title: 'Lead Credits',
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
                currencyColumns: [],
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
            } catch (Throwable $e) {
            }

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
        $clientIds = $this->billingClients()->pluck('id')->toArray();
        $userIds = User::where('organization_id', $orgId)->pluck('id')->toArray();

        // 1. Team Members Query
        $usersQuery = $this->filteredTeamMembers()->with('roles');
        $teamMembers = $usersQuery->orderBy('name')->get();
        $teamMembershipDetails = [];
        if ($this->activeTab === 'team') {
            \App\Support\TeamAccess::ensureLeaderTeams($orgId);
            $teams = \App\Models\SalesTeam::where(fn ($q) => $q
                ->where(fn ($q) => $q->where('ownable_type', Organization::class)->where('ownable_id', $orgId))
                ->orWhere(fn ($q) => $q->where('ownable_type', User::class)->whereIn('ownable_id', User::where('organization_id', $orgId)->select('id'))))
                ->with(['leader', 'members' => fn ($q) => $q->where('is_active', true)])->get();
            foreach ($teamMembers as $member) {
                $assignedTeams = $teams->filter(fn ($team) => $team->leader_id === $member->id || $team->members->contains('user_id', $member->id));
                $teamMembershipDetails[$member->id] = [
                    'teams' => $assignedTeams->pluck('name')->unique()->join(', '),
                    'leaders' => $assignedTeams->filter(fn ($team) => $team->leader?->organization_id === $orgId)->pluck('leader.name')->filter()->unique()->join(', '),
                ];
            }
        }

        // 2. Clients Query
        $clients = $this->billingClients()
            ->with(['projects', 'wallet'])
            ->withCount(['projects', 'leads'])
            ->orderBy('name')
            ->get();

        // 3. Projects Query
        $projects = DB::table('projects')
            ->join('users', 'projects.client_id', '=', 'users.id')
            ->where('users.organization_id', $orgId)
            ->whereIn('projects.client_id', $clientIds)
            ->select('projects.*', 'users.name as client_name')
            ->selectSub(function ($query) {
                $hasProjectCol = Schema::hasColumn('leads', 'project_id');
                $query->from('leads')
                    ->join('campaigns', 'leads.campaign_id', '=', 'campaigns.id')
                    ->where(function ($w) use ($hasProjectCol) {
                        $w->whereColumn('campaigns.project_id', 'projects.id');
                        if ($hasProjectCol) {
                            $w->orWhereColumn('leads.project_id', 'projects.id');
                        }
                    })
                    ->selectRaw('count(*)');
            }, 'leads_count')
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
            ->with(['client', 'lead', 'createdBy']);

        if ($this->creditFilterType !== 'all') {
            if ($this->creditFilterType === 'consumption') {
                $creditTxQuery->whereIn('transaction_type', ['lead_consumption', 'deduct', 'reserve']);
            } else {
                $creditTxQuery->where('transaction_type', $this->creditFilterType);
            }
        }

        if (!empty($this->creditSearch)) {
            $term = trim($this->creditSearch);
            $cleanTerm = ltrim($term, '#');
            $creditTxQuery->where(function ($q) use ($term, $cleanTerm) {
                if (is_numeric($cleanTerm)) {
                    $q->orWhere('credit_transactions.id', (int) $cleanTerm);
                }
                $q->orWhere('credit_transactions.description', 'like', "%{$term}%")
                  ->orWhere('credit_transactions.transaction_type', 'like', "%{$term}%")
                  ->orWhereHas('lead', function ($lq) use ($term) {
                      $lq->where('lead_code', 'like', "%{$term}%")
                         ->orWhere('name', 'like', "%{$term}%")
                         ->orWhere('mobile', 'like', "%{$term}%");
                  })
                  ->orWhereHas('client', function ($cq) use ($term) {
                      $cq->where('name', 'like', "%{$term}%")
                         ->orWhere('email', 'like', "%{$term}%");
                  });
            });
        }

        $creditTransactions = $creditTxQuery->latest('created_at')->orderBy('id', 'desc')->paginate(15);

        // Recharge requests for clients of this org
        $rechargeRequests = RechargeRequest::whereIn('client_id', $clientIds)->latest('created_at')->take(10)->get();

        // Metric Aggregates
        $totalUsersCount = count($userIds);
        $totalClientsCount = count($clientIds);
        $totalProjectsCount = $projects->count();
        $activeMemberIds = User::where('organization_id', $orgId)->where('status', 'active')
            ->whereHas('roles')->pluck('id');
        $activeTeamMembersCount = $activeMemberIds->count();
        $activeProjectsCount = $projects->where('status', 'active')->count();
        // Owner IDs are users in this organization. An unrelated lead's assignee
        // must never make that lead part of this client's received-lead total.
        $organizationLeads = Lead::withoutGlobalScopes()->whereIn('client_id', $userIds)
            ->whereNotIn('status', ['inactive', 'deleted']);
        $totalLeadsCount = (clone $organizationLeads)->count();
        $directlyAssignedLeadsCount = (clone $organizationLeads)->whereIn('assigned_to', $activeMemberIds)->count();

        $totalWalletBalance = DB::table('client_wallets')
            ->whereIn('client_id', $clientIds)
            ->sum('balance') ?? 0;

        $totalOpeningBalance = DB::table('client_wallets')
            ->whereIn('client_id', $clientIds)
            ->sum('opening_balance') ?? 0;

        $globalDefaultLeadCost = app(\App\Services\LeadPricingService::class)->getGlobalDefaultCost();

        $totalCreditsRecharged = DB::table('credit_transactions')
            ->whereIn('client_id', $clientIds)
            ->where('transaction_type', 'recharge')
            ->sum('credit_used') ?? 0;

        $totalCreditsRefunded = DB::table('credit_transactions')
            ->whereIn('client_id', $clientIds)
            ->where('transaction_type', 'refund')
            ->sum('credit_used') ?? 0;

        $totalCreditsDeducted = DB::table('credit_transactions')
            ->whereIn('client_id', $clientIds)
            ->whereNotNull('lead_id')->where('is_legacy', false)
            ->whereIn('transaction_type', ['lead_consumption', 'deduct', 'reserve'])
            ->sum('credit_used') ?? 0;

        $leadRefunds = (int) CreditTransaction::withoutGlobalScopes()->whereIn('client_id', $clientIds)->whereNotNull('lead_id')->where('is_legacy', false)->where('transaction_type', 'refund')->sum('credit_used');
        $usedLeadCredits = max(0, (int) $totalCreditsDeducted - $leadRefunds);

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
                ->paginate(20, ['*'], 'auditPage');
        } catch (Throwable $e) {
        }

        // Projects for the selected client in create lead modal
        $clientProjects = $this->newLeadClientId
            ? Project::where('client_id', $this->newLeadClientId)->orderBy('name')->get()
            : collect();

        return view('livewire.admin.organization-detail', [
            'teamMembers' => $teamMembers,
            'teamMembershipDetails' => $teamMembershipDetails,
            'clients' => $clients,
            'projects' => $projects,
            'leads' => $leads,
            'creditTransactions' => $creditTransactions,
            'rechargeRequests' => $rechargeRequests,
            'totalUsersCount' => $totalUsersCount,
            'activeTeamMembersCount' => $activeTeamMembersCount,
            'activeProjectsCount' => $activeProjectsCount,
            'totalClientsCount' => $totalClientsCount,
            'totalProjectsCount' => $totalProjectsCount,
            'totalLeadsCount' => $totalLeadsCount,
            'directlyAssignedLeadsCount' => $directlyAssignedLeadsCount,
            'totalOpeningBalance' => (int) $totalOpeningBalance,
            'totalWalletBalance' => (int) $totalWalletBalance,
            'totalLeadCredits' => (int) $totalWalletBalance + $usedLeadCredits,
            'usedLeadCredits' => $usedLeadCredits,
            'globalDefaultLeadCost' => (int) $globalDefaultLeadCost,
            'totalCreditsRecharged' => (int) $totalCreditsRecharged,
            'totalCreditsRefunded' => (int) $totalCreditsRefunded,
            'totalCreditsDeducted' => (int) $totalCreditsDeducted,
            'auditLogs' => $auditLogs,
            'clientProjects' => $clientProjects,
        ])->layout('layouts.app');
    }
}
