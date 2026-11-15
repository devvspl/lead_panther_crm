<?php
namespace App\Livewire\Leads;

use Livewire\{Component, WithPagination};
use Livewire\Attributes\Locked;
use App\Models\{InternalLeadProcessing, InternalProcessingStatus};
use App\Services\LeadDistributionService;

class InternalProcessingQueue extends Component
{
    use WithPagination;
    #[Locked] public ?int $selectedId = null;
    public string $search = '';
    public ?int $organizationId = null;
    public string $statusFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $action = 'view';
    public string $remarks = '';
    public string $callingStatus = '';
    public string $statusCode = '';
    public string $followupAt = '';
    public ?int $assignUserId = null;

    private function service(): LeadDistributionService { return app(LeadDistributionService::class); }
    private function guard(): void { abort_unless($this->service()->hasInternalQueueAccess(auth()->user()), 403); }
    public function mount(): void { $this->guard(); if ($id = request()->integer('processing')) $this->openRecord($id); }
    public function updatedSearch(): void { $this->resetPage('internalPage'); }
    public function updatedStatusFilter(): void { $this->resetPage('internalPage'); }
    public function updatedOrganizationId(): void { $this->resetPage('internalPage'); }

    public function getSelectedProperty(): ?InternalLeadProcessing
    {
        return $this->selectedId ? $this->service()->internalQueueQuery(auth()->user())->with(['lead', 'organization', 'assignee', 'status'])->findOrFail($this->selectedId) : null;
    }
    public function openRecord(int $id, string $action = 'view'): void
    {
        $this->guard();
        $record = $this->service()->internalQueueQuery(auth()->user())->findOrFail($id);
        $allowed = $this->service()->availableInternalActions($record, auth()->user());
        abort_unless($action === 'view' || in_array($action, $allowed) || ($action === 'assign' && auth()->user()->hasAnyRole(['Super Admin', 'super-admin']) && ! $record->handover_requested_at), 403);
        $this->selectedId = $record->id; $this->action = $action;
        $this->remarks = $action === 'call' ? '' : ($record->calling_remarks ?? ''); $this->callingStatus = ''; $this->statusCode = ''; $this->followupAt = ''; $this->assignUserId = $record->assigned_user_id;
        $this->resetValidation(); $this->resetPage('historyPage'); $this->dispatch('open-modal', 'internal-processing-action');
    }
    public function closeRecord(): void { $this->selectedId = null; $this->dispatch('close-modal', 'internal-processing-action'); }
    public function saveAction(): void
    {
        $this->guard(); $record = $this->selected;
        abort_unless($record, 404);
        try {
            if ($this->action === 'assign') {
                $this->validate(['assignUserId' => 'required|integer']);
                $this->service()->assignInternalExecutive($record->id, $this->assignUserId, auth()->user());
            } else $this->service()->updateInternalLead($record->id, auth()->user(), ['action' => $this->action, 'remarks' => $this->remarks, 'calling_status' => $this->callingStatus, 'status_code' => $this->statusCode, 'followup_at' => $this->followupAt]);
            $this->action = 'view'; $this->dispatch('toast', type: 'success', message: 'Internal processing updated.');
        } catch (\Illuminate\Validation\ValidationException $e) { $this->dispatch('toast', type: 'error', message: $e->validator->errors()->first()); throw $e; }
    }
    public function forward(int $id): void
    {
        $this->guard();
        try {
            $record = $this->service()->requestInternalHandover($id, auth()->user());
            $this->dispatch('toast', type: $record->forwarding_error ? 'error' : ($record->forwarded_at ? 'success' : 'info'), message: $record->forwarding_error ?: ($record->forwarded_at ? 'Lead handed over to the client.' : 'Verified lead queued for client delivery under the current delivery limits.'));
        } catch (\Illuminate\Validation\ValidationException $e) { $this->dispatch('toast', type: 'error', message: $e->validator->errors()->first()); throw $e; }
    }
    public function render()
    {
        $this->guard(); $this->validate(['dateFrom' => 'nullable|date', 'dateTo' => 'nullable|date'.($this->dateFrom ? '|after_or_equal:dateFrom' : '')]);
        $query = $this->service()->internalQueueQuery(auth()->user())->with(['lead', 'organization', 'assignee', 'status']);
        if ($this->organizationId) $query->where('organization_id', $this->organizationId);
        if ($this->statusFilter) $query->where('status_code', $this->statusFilter);
        if ($this->dateFrom) $query->where('created_at', '>=', $this->dateFrom.' 00:00:00');
        if ($this->dateTo) $query->where('created_at', '<=', $this->dateTo.' 23:59:59');
        if ($this->search) $query->whereHas('lead', fn ($q) => $q->where('lead_code', 'like', '%'.$this->search.'%')->orWhere('name', 'like', '%'.$this->search.'%')->orWhere('mobile', 'like', '%'.$this->search.'%'));
        $selected = $this->selected;
        $team = $selected ? $this->service()->internalTeams()->find($selected->organization->internal_processing_team_id) : null;
        return view('livewire.leads.internal-processing-queue', ['records' => $query->latest('id')->paginate(15, ['*'], 'internalPage'), 'selected' => $selected,
            'statuses' => InternalProcessingStatus::orderBy('sort_order')->get(), 'service' => $this->service(),
            'organizations' => \App\Models\Organization::whereIn('id', $this->service()->internalQueueQuery(auth()->user())->select('organization_id'))->orderBy('name')->get(),
            'history' => $selected ? $selected->events()->with('user')->latest('id')->paginate(15, ['*'], 'historyPage') : null,
            'assignees' => $team ? $this->service()->internalTeamMembers($team)->whereIn('id', $selected->organization->internal_processing_config['member_ids'] ?? [])->orderBy('name')->get() : collect()]);
    }
}
