<?php
namespace App\Livewire\Leads;

use App\Models\{AuditLog, Campaign, LeadDeliveryItem, LeadDeliveryPlan, LeadDeliveryAttempt, Organization, Project, User};
use App\Services\LeadDistributionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\{Locked, On};
use Livewire\{Component, WithPagination};

class ClientDeliveryCenter extends Component
{
    use WithPagination;
    #[Locked] public int $organizationId;
    public array $settings = [];
    public array $selectedLeadIds = [];
    public bool $showConfiguration = false;
    public bool $consentConfirmed = false;
    public bool $showAttempts = false;
    public ?int $inspectedItemId = null;
    public string $historyStatus = '';
    public string $historyClient = '';
    public string $historyProject = '';
    public string $historyCampaign = '';
    public string $historyFrom = '';
    public string $historyTo = '';

    // Revoke Delivered Leads state
    public string $revokeStatus = 'delivered';
    public string $revokeClient = '';
    public string $revokeProject = '';
    public string $revokeFrom = '';
    public string $revokeTo = '';
    public string $revokeSearch = '';
    public array $selectedRevokeIds = [];
    public bool $selectAll = false;
    public bool $revokeSelectAllMatching = false;
    public bool $showRevokeConfirmModal = false;
    public array $revokeTargetItemIds = [];
    public string $revokeBatchReason = '';

    public function boot(): void { abort_unless(auth()->user()?->status === 'active' && auth()->user()->hasAnyRole(['Super Admin', 'super-admin']), 403); }
    public function mount(int $organizationId): void
    {
        $organization = Organization::findOrFail($organizationId);
        $this->organizationId = $organization->id;
        $plan = $this->plan();
        $this->settings = $plan ? array_merge($plan->settings, $plan->only('client_id', 'project_id', 'campaign_id')) : [
            'client_id' => null, 'project_id' => null, 'campaign_id' => null, 'frequency' => 'immediate', 'interval_minutes' => 20, 'quantity_mode' => 'fixed', 'quantity' => 1,
            'max_cycle' => null, 'daily_enabled' => false, 'daily_limit' => null, 'automatic' => true, 'timezone' => $organization->delivery_timezone ?: config('app.timezone'),
            'start_date' => null, 'end_date' => null, 'window_start' => '00:00', 'window_end' => '23:59', 'daily_time' => '09:00', 'weekly_day' => 1, 'working_days' => [0, 1, 2, 3, 4, 5, 6],
        ];
        if (!isset($this->settings['interval_minutes'])) {
            $this->settings['interval_minutes'] = $plan?->settings['interval_minutes'] ?? 20;
        }
        if (!$plan) {
            $this->settings['client_id'] = app(LeadDistributionService::class)->clientDeliveryRecipients()->where('organization_id', $this->organizationId)->orderBy('id')->value('id');
            $this->chooseSingleDestination();
        }
    }
    protected function plan(): ?LeadDeliveryPlan { return LeadDeliveryPlan::where('organization_id', $this->organizationId)->first(); }
    protected function chooseSingleDestination(): void
    {
        $projects = Project::withoutGlobalScopes()->where('client_id', $this->settings['client_id'])->where('status', 'active')->pluck('id');
        if (!$this->settings['project_id'] && $projects->count() === 1) $this->settings['project_id'] = $projects->first();
        $campaigns = Campaign::withoutGlobalScopes()->where('project_id', $this->settings['project_id'])->where('status', 'active')->pluck('id');
        if (!$this->settings['campaign_id'] && $campaigns->count() === 1) $this->settings['campaign_id'] = $campaigns->first();
    }
    public function updatedSettingsClientId(): void { $this->settings['project_id'] = $this->settings['campaign_id'] = null; $this->chooseSingleDestination(); }
    public function updatedSettingsProjectId(): void { $this->settings['campaign_id'] = null; $this->chooseSingleDestination(); }
    public function updatedSettingsFrequency(): void
    {
        $this->settings['automatic'] = $this->settings['frequency'] !== 'manual';
        if ($this->settings['frequency'] === 'custom_minutes' && empty($this->settings['interval_minutes'])) {
            $this->settings['interval_minutes'] = 20;
        }
    }
    public function updated($property): void
    {
        if (str_starts_with($property, 'history')) $this->resetPage('deliveryPage');
        if (str_starts_with($property, 'revoke')) {
            $this->resetPage('revokePage');
            $this->selectedRevokeIds = [];
            $this->selectAll = false;
            $this->revokeSelectAllMatching = false;
        }
    }

    #[On('client-push-selected')]
    public function openPush(int $organizationId, array $leadIds, bool $sendNow = false): void
    {
        if ($organizationId !== $this->organizationId) return;
        $this->selectedLeadIds = array_values(array_unique(array_map('intval', $leadIds)));
        if ($sendNow && ($plan = $this->plan())) {
            $this->settings = array_merge($plan->settings, $plan->only('client_id','project_id','campaign_id'));
            if (!isset($this->settings['interval_minutes'])) $this->settings['interval_minutes'] = $plan->settings['interval_minutes'] ?? 20;
            $this->pushLeads();
            return;
        }
        if (!$this->plan()) {
            $this->settings['quantity'] = max(1, count($this->selectedLeadIds));
            if (!isset($this->settings['interval_minutes'])) $this->settings['interval_minutes'] = 20;
        }
        $this->consentConfirmed = false;
        $this->showConfiguration = true;
        $this->dispatch('open-offcanvas', name: 'client-delivery-configuration');
    }

    protected function persistSettings(): LeadDeliveryPlan
    {
        $this->settings['automatic'] = $this->settings['frequency'] !== 'manual';
        try {
            return app(LeadDistributionService::class)->saveClientDeliveryPlan(Organization::findOrFail($this->organizationId), array_merge($this->settings, [
                // The save/push button confirms these choices once, without additional checkbox validation.
                'confirmed_send_all' => true, 'confirmed_unlimited' => true,
            ]), auth()->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($messages, $key) => ['settings.'.$key => $messages])->all());
        }
    }

    public function saveSettings(): void
    {
        $this->persistSettings();
        $this->dispatch('toast', type: 'success', message: 'Settings saved. Internal verification and handover approval are required only when Internal Processing is enabled.');
    }

    public function queueSelected(bool $allEligible = false, bool $startCycle = true): int
    {
        $this->validate(['consentConfirmed' => 'accepted']);
        $ids = $allEligible ? app(LeadDistributionService::class)->eligibleClientDeliveryLeads($this->organizationId)->pluck('id')->all() : $this->selectedLeadIds;
        if (!$ids) throw ValidationException::withMessages(['selectedLeadIds' => 'Select leads from the table.']);
        $plan = $this->persistSettings();
        $result = app(LeadDistributionService::class)->enqueueClientLeads($plan, $ids, $this->consentConfirmed, auth()->user());
        if ($startCycle && $result['queued'] > 0 && $plan->settings['automatic'] && $plan->settings['frequency'] === 'immediate') $this->launchCycle($plan, false);
        $this->showConfiguration = false;
        $this->selectedLeadIds = [];
        $this->dispatch('lead-updated');
        $waiting = $plan->items()->whereIn('lead_id', $ids)->whereIn('status', ['pending', 'queued'])->count();
        $this->dispatch('toast', type: ($result['queued'] || $waiting) ? 'info' : 'warning', message: $result['queued']
            ? $result['queued'].' lead(s) added to the delivery queue. Delivery follows the saved frequency and quantity.'
            : ($waiting ? $waiting.' selected lead(s) are already waiting for delivery. Use Send Cycle Now to run the saved batch immediately.'
                : 'No leads added: selected leads are already delivered or not eligible. Check Delivery History for their status.'));
        return $result['queued'];
    }

    public function pushLeads(bool $allEligible = false): void
    {
        // The UI confirmation explicitly attests sharing permission; enqueue records the actor and time.
        $this->consentConfirmed = true;
        $selected = $this->selectedLeadIds;
        $queued = $this->queueSelected($allEligible, false);
        if (($plan = $this->plan()) && in_array($plan->settings['frequency'], ['manual','immediate'])) {
            $waiting = $plan->items()->whereIn('lead_id',$selected)->whereIn('status',['pending','queued'])->exists();
            if ($queued > 0 || $waiting) $this->launchCycle($plan, true);
        }
    }

    protected function launchCycle(LeadDeliveryPlan $plan, bool $manual): void
    {
        $service = app(LeadDistributionService::class);
        if ($run = $service->startClientDeliveryCycle($plan->id, $manual)) {
            // Give explicit user actions immediate progress without draining a large batch in HTTP.
            $remaining = $service->processClientDeliveryRun($run->id, 1);
            if ($remaining) \App\Jobs\ProcessClientDeliveryRun::dispatch($run->id);
            $failed = $run->items()->where('status', 'failed')->exists();
            $sent = $run->items()->where('status', 'sent')->count();
            $this->dispatch('lead-updated');
            $this->dispatch('toast', type: $failed ? 'error' : 'success', message: $failed
                ? 'A delivery failed. Open Delivery History for the reason and retry.'
                : ($sent.' lead(s) delivered.'.($remaining ? ' Remaining leads are queued for background delivery.' : '')));
        } else $this->dispatch('toast', type: 'info', message: 'No delivery can start now. Check pending leads, state, working dates/hours and remaining daily capacity.');
    }
    public function runNow(): void
    {
        $plan = $this->plan();
        if (!$plan) { $this->showConfiguration = true; return; }
        $this->launchCycle($plan, true);
    }
    public function control(string $action): void
    {
        if ($plan = $this->plan()) app(LeadDistributionService::class)->controlClientDelivery($plan, $action, auth()->user());
        $this->dispatch('toast', type: 'success', message: 'Delivery control saved. Unsent leads remain queued.');
    }
    public function retry(int $itemId): void
    {
        $item = $this->historyQuery(false)->find($itemId);
        abort_unless($item, 404);
        app(LeadDistributionService::class)->retryClientDelivery($item, auth()->user());
        $this->dispatch('toast', type: 'success', message: 'Failed lead returned to the pending queue.');
    }
    public function accept(int $itemId): void
    {
        DB::transaction(function () use ($itemId) {
            $item = $this->historyQuery(false)->whereKey($itemId)->lockForUpdate()->firstOrFail();
            abort_unless($item->status === 'sent', 422);
            $item->update(['status' => 'accepted', 'accepted_at' => now()]);
            AuditLog::create(['user_id' => auth()->id(), 'organization_id' => $this->organizationId, 'action' => 'delivery.accepted', 'subject_type' => LeadDeliveryItem::class, 'subject_id' => $item->id, 'from_value' => json_encode(['status' => 'sent']), 'to_value' => json_encode(['status' => 'accepted', 'accepted_at' => $item->accepted_at]), 'created_at' => now()]);
        });
        $this->dispatch('toast', type: 'success', message: 'Client acceptance recorded.');
    }

    public function inspectAttempts(int $itemId): void
    {
        abort_unless($this->historyQuery(false)->whereKey($itemId)->exists(), 404);
        $this->inspectedItemId = $itemId;
        $this->showAttempts = true;
    }

    protected function historyQuery(bool $filtered = true)
    {
        $query = LeadDeliveryItem::whereHas('lead')->whereHas('plan', fn ($q) => $q->where('organization_id', $this->organizationId))->with(['lead', 'client', 'project', 'campaign']);
        if (!$filtered) return $query;
        $this->validate(['historyFrom' => 'nullable|date_format:Y-m-d', 'historyTo' => 'nullable|date_format:Y-m-d']);
        return $query->when($this->historyStatus, fn ($q) => $q->where('status', $this->historyStatus))
            ->when($this->historyClient, fn ($q) => $q->where('client_id', $this->historyClient))
            ->when($this->historyProject, fn ($q) => $q->where('project_id', $this->historyProject))
            ->when($this->historyCampaign, fn ($q) => $q->where('campaign_id', $this->historyCampaign))
            ->when($this->historyFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->historyFrom))
            ->when($this->historyTo, fn ($q) => $q->whereDate('created_at', '<=', $this->historyTo));
    }

    public function historyColumns(): array
    {
        return [
            ['key' => 'lead.lead_code', 'label' => 'Lead ID'], ['key' => 'lead.name', 'label' => 'Customer'],
            ['key' => 'client.name', 'label' => 'Destination Client'], ['key' => 'project.name', 'label' => 'Project'], ['key' => 'campaign.name', 'label' => 'Campaign'],
            ['key' => 'run_id', 'label' => 'Batch #'], ['key' => 'status', 'label' => 'Status'],
            ['key' => 'sent_at', 'label' => 'Delivered At'], ['key' => 'failure_reason', 'label' => 'Failure Reason'],
            ['key' => 'id', 'label' => 'Actions', 'type' => 'delivery_item_actions'],
        ];
    }
    public function exportHistory()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\BaseStyledExport(
            data: $this->historyQuery()->latest('id')->get(), title: 'Client Delivery History', subtitle: Organization::findOrFail($this->organizationId)->name,
            headings: ['Lead ID', 'Customer', 'Destination Client', 'Project', 'Campaign', 'Batch #', 'Status', 'Delivered At', 'Accepted At', 'Failure Reason', 'Attempts'],
            columns: [fn ($i) => $i->lead?->lead_code, fn ($i) => $i->lead?->name, fn ($i) => $i->client?->name, fn ($i) => $i->project?->name, fn ($i) => $i->campaign?->name, 'run_id', 'status', 'sent_at', 'accepted_at', 'failure_reason', 'attempts']
        ), 'client-delivery-history-'.$this->organizationId.'.xlsx');
    }

    public function revokeQuery(bool $filtered = true)
    {
        $query = LeadDeliveryItem::whereHas('lead')
            ->whereHas('plan', fn ($q) => $q->where('organization_id', $this->organizationId))
            ->with(['lead.assignedTo', 'client.organization', 'project', 'campaign']);

        if (!$filtered) return $query;

        $this->validate([
            'revokeFrom' => 'nullable|date_format:Y-m-d',
            'revokeTo' => 'nullable|date_format:Y-m-d',
        ]);

        return $query
            ->when($this->revokeStatus === 'delivered', fn ($q) => $q->whereIn('status', ['sent', 'accepted']))
            ->when($this->revokeStatus && $this->revokeStatus !== 'delivered', fn ($q) => $q->where('status', $this->revokeStatus))
            ->when($this->revokeClient, fn ($q) => $q->where('client_id', $this->revokeClient))
            ->when($this->revokeProject, fn ($q) => $q->where('project_id', $this->revokeProject))
            ->when($this->revokeFrom, fn ($q) => $q->whereDate('sent_at', '>=', $this->revokeFrom))
            ->when($this->revokeTo, fn ($q) => $q->whereDate('sent_at', '<=', $this->revokeTo))
            ->when(trim($this->revokeSearch) !== '', function ($q) {
                $search = '%' . trim($this->revokeSearch) . '%';
                $q->whereHas('lead', fn ($l) => $l->where('lead_code', 'like', $search)->orWhere('name', 'like', $search)->orWhere('mobile', 'like', $search));
            });
    }

    public function revokeColumns(): array
    {
        return [
            ['key' => 'lead.lead_code', 'label' => 'Lead ID'],
            ['key' => 'lead.name', 'label' => 'Customer Name'],
            ['key' => 'client.name', 'label' => 'Destination Client'],
            ['key' => 'project.name', 'label' => 'Project'],
            ['key' => 'campaign.name', 'label' => 'Campaign'],
            ['key' => 'sent_at', 'label' => 'Delivered Date', 'formatter' => fn($val) => $val ? \Carbon\Carbon::parse($val)->format('d M Y, h:i A') : 'N/A'],
            ['key' => 'status', 'label' => 'Status', 'formatter' => fn($val) => ucfirst((string)$val)],
            ['key' => 'actions', 'label' => 'Actions', 'type' => 'html', 'render' => function($item) {
                if (in_array($item->status, ['sent', 'accepted'])) {
                    return '<button type="button" wire:click="openRevokeSingle(' . $item->id . ')" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 transition cursor-pointer">Revoke</button>';
                }
                return '<span class="text-xs text-muted font-medium capitalize">' . e($item->status) . '</span>';
            }],
        ];
    }

    public function toggleSelectRow($id): void
    {
        $idStr = (string) $id;
        if (in_array($idStr, $this->selectedRevokeIds)) {
            $this->selectedRevokeIds = array_values(array_diff($this->selectedRevokeIds, [$idStr]));
        } else {
            $this->selectedRevokeIds[] = $idStr;
        }
        $this->revokeSelectAllMatching = false;
    }

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedRevokeIds = $this->revokeQuery()
                ->whereIn('status', ['sent', 'accepted'])
                ->paginate(10, ['*'], 'revokePage')
                ->pluck('id')
                ->map(fn($id) => (string)$id)
                ->all();
        } else {
            $this->selectedRevokeIds = [];
            $this->revokeSelectAllMatching = false;
        }
    }

    public function selectAllMatching(): void
    {
        $this->revokeSelectAllMatching = true;
        $this->selectedRevokeIds = $this->revokeQuery()
            ->whereIn('status', ['sent', 'accepted'])
            ->pluck('id')
            ->map(fn($id) => (string)$id)
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selectedRevokeIds = [];
        $this->selectAll = false;
        $this->revokeSelectAllMatching = false;
    }

    public function openRevokeSingle(int $itemId): void
    {
        $item = $this->revokeQuery(false)->findOrFail($itemId);
        abort_unless(in_array($item->status, ['sent', 'accepted']), 422);
        $this->revokeTargetItemIds = [$item->id];
        $this->revokeBatchReason = '';
        $this->revokeSelectAllMatching = false;
        $this->showRevokeConfirmModal = true;
    }

    public function openRevokeBatch(): void
    {
        if ($this->revokeSelectAllMatching) {
            $this->revokeTargetItemIds = $this->revokeQuery()
                ->whereIn('status', ['sent', 'accepted'])
                ->pluck('id')
                ->all();
        } else {
            $this->revokeTargetItemIds = array_values(array_map('intval', $this->selectedRevokeIds));
        }

        if (empty($this->revokeTargetItemIds)) {
            $this->dispatch('toast', type: 'warning', message: 'Select at least one delivered lead to revoke.');
            return;
        }

        $this->revokeBatchReason = '';
        $this->showRevokeConfirmModal = true;
    }

    public function closeRevokeConfirmModal(): void
    {
        $this->showRevokeConfirmModal = false;
        $this->revokeTargetItemIds = [];
        $this->revokeBatchReason = '';
        $this->resetValidation('revokeBatchReason');
    }

    public function confirmRevokeDelivered(): void
    {
        $this->validate([
            'revokeBatchReason' => 'required|string|min:3|max:1000',
        ], [
            'revokeBatchReason.required' => 'Please provide a reason for revoking the delivered lead(s).',
            'revokeBatchReason.min' => 'The return reason must be at least 3 characters.',
        ]);

        $service = app(LeadDistributionService::class);
        $revokedCount = 0;

        foreach ($this->revokeTargetItemIds as $itemId) {
            $item = LeadDeliveryItem::find($itemId);
            if ($item && in_array($item->status, ['sent', 'accepted'])) {
                $service->revokeClientDelivery($item, $this->revokeBatchReason, auth()->user());
                $revokedCount++;
            }
        }

        $this->selectedRevokeIds = [];
        $this->selectAll = false;
        $this->revokeSelectAllMatching = false;
        $this->closeRevokeConfirmModal();

        $this->dispatch('toast', type: 'success', message: "{$revokedCount} delivered lead(s) successfully revoked and returned to CRM.");
        $this->dispatch('lead-updated');
    }

    public function exportRevokeList()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\BaseStyledExport(
            data: $this->revokeQuery()->latest('sent_at')->get(),
            title: 'Delivered & Revoked Leads',
            subtitle: Organization::findOrFail($this->organizationId)->name . ' - Delivery & Revocation Records',
            headings: ['Lead ID', 'Customer Name', 'Destination Client', 'Project', 'Campaign', 'Delivered Date', 'Delivery Status', 'Return Reason'],
            columns: [
                fn ($i) => $i->lead?->lead_code,
                fn ($i) => $i->lead?->name,
                fn ($i) => $i->client?->name,
                fn ($i) => $i->project?->name,
                fn ($i) => $i->campaign?->name,
                fn ($i) => $i->sent_at ? $i->sent_at->format('d M Y H:i') : 'N/A',
                fn ($i) => ucfirst((string)$i->status),
                fn ($i) => $i->failure_reason ?? 'N/A'
            ]
        ), 'delivered-leads-revoke-' . $this->organizationId . '.xlsx');
    }

    public function render()
    {
        $plan = $this->plan();
        $run = $plan?->runs()->latest('id')->first();
        $items = $this->historyQuery(false);
        $attempts = $run ? LeadDeliveryAttempt::where('run_id', $run->id) : null;
        $deliveryHealth = $plan ? app(LeadDistributionService::class)->clientDeliveryHealth($plan) : null;
        $stats = [
            'Ready' => app(LeadDistributionService::class)->readyClientDeliveryLeads($this->organizationId)->count(),
            'Delivered' => (clone $items)->whereNotNull('sent_at')->count(), 'Pending' => (clone $items)->whereIn('status', ['pending', 'queued'])->count(),
            'Failed' => (clone $items)->where('status', 'failed')->count(),
            'Awaiting internal approval' => $deliveryHealth['awaiting'] ?? 0,
            'Sent this cycle' => $attempts ? (clone $attempts)->where('status', 'sent')->count() : 0,
            'Daily capacity' => $plan ? (app(LeadDistributionService::class)->remainingClientDeliveryCapacity($plan) ?? 'Unlimited') : 'Not configured',
            'Next delivery' => $plan?->next_run_at ? $plan->next_run_at->format('d M Y H:i T') . ($plan->next_run_at->isPast() ? ' (Due now)' : ' (in ' . $plan->next_run_at->diffForHumans(null, true) . ')') : 'Not scheduled',
            'Last successful' => (clone $items)->max('sent_at') ?? 'None recorded',
        ];
        $processed = $attempts ? (clone $attempts)->count() : 0;
        return view('livewire.leads.client-delivery-center', [
            'plan' => $plan, 'run' => $run, 'stats' => $stats, 'processed' => $processed, 'remaining' => $run ? max(0, $run->total - $processed) : 0,
            'successes' => $attempts ? (clone $attempts)->where('status', 'sent')->count() : 0, 'failures' => $attempts ? (clone $attempts)->where('status', 'failed')->count() : 0,
            'clients' => app(LeadDistributionService::class)->clientDeliveryRecipients()->orderBy('name')->get(),
            'projects' => Project::withoutGlobalScopes()->where('client_id', $this->settings['client_id'])->where('status', 'active')->orderBy('name')->get(),
            'campaigns' => Campaign::withoutGlobalScopes()->where('project_id', $this->settings['project_id'])->where('status', 'active')->orderBy('name')->get(),
            'historyClients' => User::whereIn('id', (clone $items)->select('client_id'))->get(),
            'historyProjects' => Project::withoutGlobalScopes()->whereIn('id', (clone $items)->select('project_id'))->get(),
            'historyCampaigns' => Campaign::withoutGlobalScopes()->whereIn('id', (clone $items)->select('campaign_id'))->get(),
            'history' => $this->historyQuery()->latest('id')->paginate(10, ['*'], 'deliveryPage'),
            'inspectedItem' => $this->inspectedItemId ? $this->historyQuery(false)->with(['attemptHistory.run'])->find($this->inspectedItemId) : null,
            'deliveryHealth' => $deliveryHealth,
            'revokeLeads' => $this->revokeQuery()->latest('sent_at')->paginate(10, ['*'], 'revokePage'),
            'totalRevokeMatching' => $this->revokeQuery()->whereIn('status', ['sent', 'accepted'])->count(),
            'revokeClients' => User::whereIn('id', (clone $items)->select('client_id'))->get(),
            'revokeProjects' => Project::withoutGlobalScopes()->whereIn('id', (clone $items)->select('project_id'))->get(),
        ]);
    }
}
