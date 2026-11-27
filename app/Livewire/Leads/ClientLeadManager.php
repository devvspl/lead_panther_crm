<?php

namespace App\Livewire\Leads;

use App\Models\{AuditLog, Campaign, CreditTransaction, Lead, Organization, Project, SalesTeam, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

/** The existing lead manager, pinned to the Client Details organization. */
class ClientLeadManager extends LeadKanban
{
    #[Locked]
    public int $clientOrganizationId;
    public bool $showClientImport = false;
    public string $bulkStage = '';
    #[\Livewire\Attributes\Url]
    public string $deliveryFilter = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
    }

    public function mount(?int $organizationId = null): void
    {
        $this->clientOrganizationId = Organization::findOrFail($organizationId)->id;
        parent::mount();
        $this->otherStages += ['pending_credit' => 'Pending Credit', 'lost' => 'Lost', 'invalid' => 'Invalid', 'duplicate' => 'Duplicate', 'unreachable' => 'Unreachable'];
        $this->queue = 'client';
        $this->viewMode = 'table';
        if (!request()->has('date_range') && !request()->has('analyticsRange')) {
            $this->analyticsRange = 'all';
        }
    }

    public function isClientContext(): bool { return true; }

    public function openClientImport(): void
    {
        $this->showClientImport = true;
    }

    #[On('close-client-import')]
    public function closeClientImport(): void
    {
        $this->showClientImport = false;
        $this->refreshBoard();
        $this->resetPage();
    }

    #[On('bulk-import-completed')]
    public function handleBulkImportCompleted(): void
    {
        $this->refreshBoard();
        $this->resetPage();
    }

    public function pushSelectedToClient(): void
    {
        $this->validate(['selectedRows' => 'required|array|min:1', 'selectedRows.*' => 'integer|distinct']);
        $this->assertSelectedLeads($this->selectedRows);
        $this->dispatch('client-push-selected', organizationId: $this->clientOrganizationId, leadIds: $this->selectedRows)->to(ClientDeliveryCenter::class);
    }

    public function pushLeadToClient(int $id): void
    {
        $this->assertSelectedLeads([$id]);
        $this->dispatch('client-push-selected', organizationId: $this->clientOrganizationId, leadIds: [$id], sendNow: true)->to(ClientDeliveryCenter::class);
    }

    protected function ownerIds()
    {
        return User::where('organization_id', $this->clientOrganizationId)->select('id');
    }

    protected function contextLeads()
    {
        return Lead::withoutGlobalScopes()->whereIn('client_id', $this->ownerIds());
    }

    protected function contextProjects()
    {
        return Project::withoutGlobalScopes()->whereIn('client_id', $this->ownerIds());
    }

    protected function getScopedQuery(?Carbon $start = null, ?Carbon $end = null)
    {
        // URL filters cannot change the organization selected by the parent page.
        $this->filterOrganizationId = (string) $this->clientOrganizationId;
        $this->partner = '';
        if (! $start && ! $end && $this->analyticsRange !== 'all') [$start, $end] = $this->getDateBounds();
        $status = $this->statusFilter;
        if (in_array($status, ['in_progress', 'followups', 'lost'])) $this->statusFilter = 'all';
        try { $query = parent::getScopedQuery($start, $end)->whereIn('client_id', $this->ownerIds()); }
        finally { $this->statusFilter = $status; }
        if ($this->statusFilter === 'in_progress') $query->whereIn('current_stage', ['contacted', 'connected', 'interested', 'qualified', 'follow_up', 'meeting_scheduled', 'site_visit', 'negotiation', 'booking', 'payment_received']);
        if ($this->statusFilter === 'followups') $query->whereHas('followups', fn ($q) => $q->where('status', 'pending')->where('due_at', '<=', now()->endOfDay()));
        if ($this->statusFilter === 'lost') $query->whereIn('current_stage', Lead::stageValues('lost'));
        if ($this->deliveryFilter === 'delivered') $query->whereNotNull('delivered_at');
        if ($this->deliveryFilter === 'pending') $query->where('delivery_pending', true);
        if ($this->deliveryFilter === 'failed') $query->whereNotNull('delivery_failed_at');
        if ($this->deliveryFilter === 'internal') $query->where('internal_processing_pending', true);
        if ($this->deliveryFilter === 'handed_over') $query->whereHas('internalProcessing', fn ($q) => $q->whereNotNull('forwarded_at'));
        return $query;
    }

    public function updatedDeliveryFilter(): void
    {
        $this->resetPage();
    }

    public function getBuilderLeadKpisProperty(): array
    {
        $status = $this->statusFilter;
        $stage = $this->stageFilter;
        $this->statusFilter = 'all';
        $this->stageFilter = '';
        try {
            $query = $this->getScopedQuery();
        } finally {
            $this->statusFilter = $status;
            $this->stageFilter = $stage;
        }

        $metrics = [
            'all' => ['Total Leads', (clone $query)->count()],
            'new' => ['New', (clone $query)->whereIn('current_stage', Lead::stageValues('new'))->count()],
            'assigned' => ['Assigned', (clone $query)->whereNotNull('assigned_to')->count()],
            'in_progress' => ['In Progress', (clone $query)->whereIn('current_stage', ['contacted', 'connected', 'interested', 'qualified', 'follow_up', 'meeting_scheduled', 'site_visit', 'negotiation', 'booking', 'payment_received'])->count()],
            'followups' => ['Follow-ups Due', (clone $query)->whereHas('followups', fn ($q) => $q->where('status', 'pending')->where('due_at', '<=', now()->endOfDay()))->count()],
            'closed_won' => ['Closed Won', (clone $query)->whereIn('current_stage', Lead::stageValues('closed_won'))->count()],
            'lost' => ['Lost', (clone $query)->whereIn('current_stage', Lead::stageValues('lost'))->count()],
            'unassigned' => ['Unassigned', (clone $query)->whereNull('assigned_to')->count()],
        ];
        return collect($metrics)->map(fn ($metric, $filter) => ['label' => $metric[0], 'value' => $metric[1], 'filter' => $filter])->all();
    }

    public function render()
    {
        $this->queue = 'client';
        $this->pullMode = 'manual';
        $sourceIds = $this->contextLeads()->pluck('lead_source_id')->merge(
            Campaign::withoutGlobalScopes()->whereIn('project_id', $this->contextProjects()->select('id'))->pluck('lead_source_id')
        )->filter()->unique();
        return parent::render()->with([
            'canInternalQueue' => false,
            'allOrganizations' => Organization::whereKey($this->clientOrganizationId)->get(),
            'allProjects' => $this->contextProjects()->orderBy('name')->get(),
            'allCampaigns' => Campaign::withoutGlobalScopes()->whereIn('project_id', $this->contextProjects()->select('id'))->orderBy('name')->get(),
            'allLeadSources' => \App\Models\LeadSource::whereIn('id', $sourceIds)->orderBy('name')->get(),
            'eligibleAssignees' => User::whereIn('id', $this->ownerIds())->where('status', 'active')->when($this->assignTeamId, fn ($q) => $q->whereHas('salesTeamMembers', fn ($m) => $m->where('sales_team_id', $this->assignTeamId)->where('is_active', true)))->orderBy('name')->get(),
            'assignTeams' => $this->getAvailableTeams(),
            'clientStatusOptions' => $this->contextLeads()->distinct()->pluck('status')->mapWithKeys(fn ($status) => [$status => ucwords(str_replace('_', ' ', $status))])->all(),
        ]);
    }

    public function getAvailableTeams()
    {
        return SalesTeam::where('ownable_type', Organization::class)->where('ownable_id', $this->clientOrganizationId)->get();
    }

    public function resetFilters(): void
    {
        parent::resetFilters();
        $this->analyticsRange = 'all';
        $this->deliveryFilter = '';
        $this->sortField = '';
        $this->sort_by = 'created_desc';
        $this->selectedRows = [];
        $this->selectAll = false;
    }

    public function tableColumns(): array
    {
        return array_merge(parent::tableColumns(), [
            ['key' => 'client_push', 'label' => 'Push to Client', 'type' => 'client_push'],
            ['key' => 'delivered_at', 'label' => 'Delivery', 'formatter' => fn ($value, $lead) => $lead->internal_processing_pending ? 'Internal processing' : ($lead->delivery_failed_at ? 'Failed' : ($lead->delivery_pending ? 'Pending' : ($value ? 'Delivered '.Carbon::parse($value)->format('d M Y H:i') : 'No delivery recorded')))],
            ['key' => 'internalProcessing.forwarded_at', 'label' => 'Client Handover', 'formatter' => fn ($value) => $value ? Carbon::parse($value)->format('d M Y H:i') : 'Not recorded'],
            ['key' => 'replacements', 'label' => 'Replacement', 'formatter' => fn ($value) => $value?->last()?->status ?? 'None'],
        ]);
    }

    protected function assertSelectedLeads(array $ids): void
    {
        abort_unless(count($ids) > 0 && $this->contextLeads()->whereIn('id', $ids)->count() === count(array_unique($ids)), 403);
    }

    public function updateLeadStage(int $leadId, string $toStage): void
    {
        $this->assertSelectedLeads([$leadId]);
        abort_unless(in_array($toStage, array_merge(array_keys($this->mainStages), array_keys($this->otherStages)), true), 422);
        parent::updateLeadStage($leadId, $toStage);
    }

    protected function executeStageTransition(Lead $lead, string $fromStage, string $toStage): void
    {
        $this->assertSelectedLeads([$lead->id]);
        abort_unless(in_array($toStage, array_merge(array_keys($this->mainStages), array_keys($this->otherStages)), true), 422);
        parent::executeStageTransition($lead, $fromStage, $toStage);
    }

    public function executeAssignment(): void
    {
        $this->validate(['selectedLeadIds' => 'required|array|min:1', 'selectedLeadIds.*' => 'required|integer|distinct', 'assignUserId' => 'required|integer']);
        $this->assertSelectedLeads($this->selectedLeadIds);
        abort_unless(User::whereIn('id', $this->ownerIds())->where('status', 'active')->whereKey($this->assignUserId)->exists(), 403);
        if ($this->assignTeamId) {
            abort_unless($this->getAvailableTeams()->contains('id', $this->assignTeamId), 403);
            abort_unless(\App\Models\SalesTeamMember::where('sales_team_id', $this->assignTeamId)->where('user_id', $this->assignUserId)->where('is_active', true)->exists(), 403);
        }
        DB::transaction(fn () => parent::executeAssignment());
    }

    public function submitReturnLead(): void
    {
        $this->assertSelectedLeads([(int) $this->returnLeadId]);
        parent::submitReturnLead();
    }

    public function openAssignModalForSingleLead(int $id): void
    {
        $this->assertSelectedLeads([$id]);
        parent::openAssignModalForSingleLead($id);
    }

    public function openReturnLeadModal(int $leadId): void
    {
        $this->assertSelectedLeads([$leadId]);
        parent::openReturnLeadModal($leadId);
    }

    public function openPullLeadsModal(): void
    {
        $this->pullMode = 'manual';
        $this->showPullLeadsModal = true;
    }

    public function createManualLead(): void
    {
        $this->validate(['manualProjectId' => 'required|integer']);
        abort_unless($this->contextProjects()->whereKey($this->manualProjectId)->exists(), 403);
        parent::createManualLead();
    }

    public function updatedManualProjectId(): void { $this->manualCampaignId = null; }

    public function pullMetaLeads(): void { abort(403); }
    public function autoDistributeUnassignedLeads(): void { abort(403); }

    public function changeSelectedStatus(): void
    {
        $this->validate(['bulkStage' => ['required', Rule::in(array_merge(array_keys($this->mainStages), array_keys($this->otherStages)))]]);
        $this->assertSelectedLeads($this->selectedRows);
        DB::transaction(function () {
            foreach ($this->contextLeads()->whereIn('id', $this->selectedRows)->orderBy('id')->lockForUpdate()->get() as $lead) {
                if ($lead->current_stage !== $this->bulkStage) $this->executeStageTransition($lead, $lead->current_stage, $this->bulkStage);
            }
        });
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->dispatch('lead-updated');
    }

    public function deleteSelectedLeads(): void
    {
        \App\Services\LeadArchiveService::authorize();
        $this->assertSelectedLeads($this->selectedRows);
        DB::transaction(function () {
            foreach ($this->selectedRows as $id) app(\App\Services\LeadArchiveService::class)->archive((int)$id, 'Archived through the selected-leads action.');
        });
        $this->selectedRows = [];
        $this->selectAll = false;
        $this->dispatch('lead-updated');
        $this->dispatch('toast', type: 'success', message: 'Selected leads archived.');
    }

    public function exportSelectedLeads(): void
    {
        $this->assertSelectedLeads($this->selectedRows);
        $this->exportScope = 'selected';
        $this->openExportModal();
    }

    public function exportExcel(): void
    {
        $this->openExportModal();
    }
}
