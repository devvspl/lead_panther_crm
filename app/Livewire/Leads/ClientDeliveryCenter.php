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
    public bool $confirmSendAll = false;
    public bool $confirmUnlimited = false;
    public bool $showAttempts = false;
    public ?int $inspectedItemId = null;
    public string $historyStatus = '';
    public string $historyClient = '';
    public string $historyProject = '';
    public string $historyCampaign = '';
    public string $historyFrom = '';
    public string $historyTo = '';

    public function boot(): void { abort_unless(auth()->user()?->status === 'active' && auth()->user()->hasAnyRole(['Super Admin', 'super-admin']), 403); }
    public function mount(int $organizationId): void
    {
        $organization = Organization::findOrFail($organizationId);
        $this->organizationId = $organization->id;
        $plan = $this->plan();
        $this->settings = $plan ? array_merge($plan->settings, $plan->only('client_id', 'project_id', 'campaign_id')) : [
            'client_id' => null, 'project_id' => null, 'campaign_id' => null, 'frequency' => 'manual', 'quantity_mode' => 'fixed', 'quantity' => null,
            'max_cycle' => null, 'daily_enabled' => true, 'daily_limit' => null, 'automatic' => false, 'timezone' => $organization->delivery_timezone ?: config('app.timezone'),
            'start_date' => null, 'end_date' => null, 'window_start' => '00:00', 'window_end' => '23:59', 'daily_time' => '09:00', 'weekly_day' => 1, 'working_days' => [1, 2, 3, 4, 5],
        ];
    }
    protected function plan(): ?LeadDeliveryPlan { return LeadDeliveryPlan::where('organization_id', $this->organizationId)->first(); }
    public function updatedSettingsClientId(): void { $this->settings['project_id'] = $this->settings['campaign_id'] = null; }
    public function updatedSettingsProjectId(): void { $this->settings['campaign_id'] = null; }
    public function updated($property): void { if (str_starts_with($property, 'history')) $this->resetPage('deliveryPage'); }

    #[On('client-push-selected')]
    public function openPush(int $organizationId, array $leadIds): void
    {
        if ($organizationId !== $this->organizationId) return;
        $this->selectedLeadIds = array_values(array_unique(array_map('intval', $leadIds)));
        $this->consentConfirmed = false;
        $this->showConfiguration = true;
    }

    protected function persistSettings(): LeadDeliveryPlan
    {
        try {
            return app(LeadDistributionService::class)->saveClientDeliveryPlan(Organization::findOrFail($this->organizationId), array_merge($this->settings, [
                'confirmed_send_all' => $this->confirmSendAll, 'confirmed_unlimited' => $this->confirmUnlimited,
            ]), auth()->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($messages, $key) => ['settings.'.$key => $messages])->all());
        }
    }

    public function saveSettings(): void
    {
        $this->persistSettings();
        $this->dispatch('toast', type: 'success', message: 'Delivery settings saved. Existing queued leads are preserved.');
    }

    public function queueSelected(bool $allEligible = false): void
    {
        $this->validate(['consentConfirmed' => 'accepted']);
        $plan = $this->persistSettings();
        $ids = $allEligible ? app(LeadDistributionService::class)->eligibleClientDeliveryLeads($this->organizationId)->pluck('id')->all() : $this->selectedLeadIds;
        if (!$ids) throw ValidationException::withMessages(['selectedLeadIds' => 'Select at least one eligible undelivered lead.']);
        $result = app(LeadDistributionService::class)->enqueueClientLeads($plan, $ids, $this->consentConfirmed, auth()->user());
        if ($plan->settings['automatic'] && $plan->settings['frequency'] === 'immediate') $this->launchCycle($plan, false);
        $this->showConfiguration = false;
        $this->selectedLeadIds = [];
        $this->dispatch('lead-updated');
        $this->dispatch('toast', type: 'success', message: $result['queued'].' lead(s) queued; '.$result['skipped'].' ineligible or already queued lead(s) skipped.');
    }

    protected function launchCycle(LeadDeliveryPlan $plan, bool $manual): void
    {
        if ($run = app(LeadDistributionService::class)->startClientDeliveryCycle($plan->id, $manual)) {
            \App\Jobs\ProcessClientDeliveryRun::dispatch($run->id);
            $this->dispatch('toast', type: 'success', message: 'Delivery cycle queued. Progress updates automatically.');
        } else $this->dispatch('toast', type: 'info', message: 'No delivery can start now. Check pending leads, state, working dates/hours and remaining daily capacity.');
    }
    public function runNow(): void { if ($plan = $this->plan()) $this->launchCycle($plan, true); }
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
            AuditLog::create(['user_id' => auth()->id(), 'action' => 'delivery.accepted', 'subject_type' => LeadDeliveryItem::class, 'subject_id' => $item->id, 'from_value' => json_encode(['status' => 'sent']), 'to_value' => json_encode(['status' => 'accepted', 'accepted_at' => $item->accepted_at]), 'created_at' => now()]);
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
        $query = LeadDeliveryItem::whereHas('plan', fn ($q) => $q->where('organization_id', $this->organizationId))->with(['lead', 'client', 'project', 'campaign']);
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

    public function render()
    {
        $plan = $this->plan();
        $run = $plan?->runs()->latest('id')->first();
        $items = $this->historyQuery(false);
        $attempts = $run ? LeadDeliveryAttempt::where('run_id', $run->id) : null;
        $stats = [
            'Eligible' => app(LeadDistributionService::class)->eligibleClientDeliveryLeads($this->organizationId)->count(),
            'Delivered' => (clone $items)->whereNotNull('sent_at')->count(), 'Pending' => (clone $items)->whereIn('status', ['pending', 'queued'])->count(),
            'Failed' => (clone $items)->where('status', 'failed')->count(),
            'Awaiting consent' => (clone $items)->where('status', 'pending')->whereHas('lead', fn ($q) => $q->whereNull('delivery_consent_at'))->count(),
            'Sent this cycle' => $attempts ? (clone $attempts)->where('status', 'sent')->count() : 0,
            'Daily capacity' => $plan ? (app(LeadDistributionService::class)->remainingClientDeliveryCapacity($plan) ?? 'Unlimited') : 'Not configured',
            'Next delivery' => $plan?->next_run_at?->format('d M Y H:i T') ?? 'Not scheduled',
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
        ]);
    }
}
