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
    public string $historyDateType = 'delivery_date';
    public string $historyStatus = '';
    public string $historyClient = '';
    public string $historyProject = '';
    public string $historyCampaign = '';
    public string $historyFrom = '';
    public string $historyTo = '';
    public string $historySearch = '';
    public array $selectedHistoryIds = [];
    public bool $selectAllHistory = false;
    public bool $historySelectAllMatching = false;

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

    public function historyQuery(bool $filtered = true)
    {
        $orgUserIds = User::where('organization_id', $this->organizationId)->select('id');
        $query = LeadDeliveryItem::whereHas('lead')
            ->where(function ($q) use ($orgUserIds) {
                $q->whereHas('plan', fn ($p) => $p->where('organization_id', $this->organizationId))
                  ->orWhereIn('source_client_id', $orgUserIds);
            })
            ->with(['lead.assignedTo', 'client.organization', 'project', 'campaign']);

        if (!$filtered) return $query;

        $this->validate([
            'historyFrom' => 'nullable|date_format:Y-m-d',
            'historyTo' => 'nullable|date_format:Y-m-d',
            'revokeFrom' => 'nullable|date_format:Y-m-d',
            'revokeTo' => 'nullable|date_format:Y-m-d',
        ]);

        $status = $this->revokeStatus !== '' ? $this->revokeStatus : $this->historyStatus;
        if ($status === 'delivered') {
            $query->whereIn('status', ['sent', 'accepted']);
        } elseif ($status === 'pending') {
            $query->whereIn('status', ['pending', 'queued']);
        } elseif ($status) {
            $query->where('status', $status);
        }

        $from = $this->revokeFrom ?: $this->historyFrom;
        $to = $this->revokeTo ?: $this->historyTo;

        if ($this->historyDateType === 'created_date') {
            $query->when($from, fn ($q) => $q->whereHas('lead', fn ($l) => $l->whereDate('created_at', '>=', $from)))
                  ->when($to, fn ($q) => $q->whereHas('lead', fn ($l) => $l->whereDate('created_at', '<=', $to)));
        } else {
            $query->when($from, fn ($q) => $q->whereDate('sent_at', '>=', $from))
                  ->when($to, fn ($q) => $q->whereDate('sent_at', '<=', $to));
        }

        $client = $this->revokeClient ?: $this->historyClient;
        $project = $this->revokeProject ?: $this->historyProject;
        $search = trim($this->revokeSearch ?: $this->historySearch);

        return $query
            ->when($client, fn ($q) => $q->where('client_id', $client))
            ->when($project, fn ($q) => $q->where('project_id', $project))
            ->when($this->historyCampaign, fn ($q) => $q->where('campaign_id', $this->historyCampaign))
            ->when($search !== '', function ($q) use ($search) {
                $term = '%' . $search . '%';
                $q->whereHas('lead', fn ($l) => $l->where('lead_code', 'like', $term)->orWhere('name', 'like', $term)->orWhere('mobile', 'like', $term));
            });
    }

    public function revokeQuery(bool $filtered = true)
    {
        return $this->historyQuery($filtered);
    }

    public function historyColumns(): array
    {
        return [
            ['key' => 'lead.lead_code', 'label' => 'Lead ID & Name', 'type' => 'html', 'render' => function($item) {
                $code = e($item->lead?->lead_code ?? '#'.$item->lead_id);
                $name = e($item->lead?->name ?? 'Customer');
                return '<div class="space-y-0.5"><div class="font-mono font-bold text-xs text-ink">'.$code.'</div><div class="text-[11px] text-muted truncate max-w-[140px]">'.$name.'</div></div>';
            }],
            ['key' => 'client.name', 'label' => 'Destination Client', 'type' => 'html', 'render' => function($item) {
                $clientName = e($item->client?->name ?? 'N/A');
                $orgName = e($item->client?->organization?->name ?? '');
                return '<div class="space-y-0.5"><div class="font-semibold text-xs text-ink">'.$clientName.'</div>'.($orgName ? '<div class="text-[10px] text-muted truncate max-w-[130px]">'.$orgName.'</div>' : '').'</div>';
            }],
            ['key' => 'project.name', 'label' => 'Project', 'formatter' => fn($val) => $val ?: 'N/A'],
            ['key' => 'campaign.name', 'label' => 'Campaign', 'formatter' => fn($val) => $val ?: 'Project Default'],
            ['key' => 'lead.created_at', 'label' => 'Lead Created Date', 'formatter' => fn($val) => $val ? \Carbon\Carbon::parse($val)->format('d M Y, h:i A') : 'N/A'],
            ['key' => 'sent_at', 'label' => 'Delivery Date & Time', 'formatter' => fn($val) => $val ? \Carbon\Carbon::parse($val)->format('d M Y, h:i A') : 'Pending'],
            ['key' => 'status', 'label' => 'Delivery Status', 'type' => 'html', 'render' => function($item) {
                $status = (string)$item->status;
                return match($status) {
                    'sent', 'accepted' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Delivered</span>',
                    'pending', 'queued' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Pending</span>',
                    'revoked' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Revoked</span>',
                    'do_not_send' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">Do Not Send</span>',
                    'failed' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-50 text-red-700 border border-red-200">Failed</span>',
                    default => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-canvas text-muted border border-border">'.e(ucfirst($status)).'</span>',
                };
            }],
            ['key' => 'rate_per_lead', 'label' => 'Rate / Lead', 'formatter' => fn($val) => $val !== null ? '₹'.number_format((float)$val, 2) : '-'],
            ['key' => 'billing_amount', 'label' => 'Billing Amount', 'formatter' => fn($val) => $val !== null ? '₹'.number_format((float)$val, 2) : '-'],
            ['key' => 'revoked_at', 'label' => 'Revoked Date', 'formatter' => fn($val) => $val ? \Carbon\Carbon::parse($val)->format('d M Y, h:i A') : '-'],
            ['key' => 'id', 'label' => 'Actions', 'type' => 'html', 'render' => function($item) {
                $status = (string)$item->status;
                $buttons = [];
                if (in_array($status, ['pending', 'queued', 'failed'])) {
                    $buttons[] = '<button type="button" wire:click="deliverLeadItem('.$item->id.')" class="px-2 py-0.5 text-[11px] font-semibold rounded bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 cursor-pointer">Deliver</button>';
                }
                if (in_array($status, ['sent', 'accepted'])) {
                    $buttons[] = '<button type="button" wire:click="openRevokeSingle('.$item->id.')" class="px-2 py-0.5 text-[11px] font-semibold rounded bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 cursor-pointer">Revoke</button>';
                }
                if ($status === 'revoked' || $status === 'failed') {
                    $buttons[] = '<button type="button" wire:click="redeliverLeadItem('.$item->id.')" class="px-2 py-0.5 text-[11px] font-semibold rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 cursor-pointer">Re-deliver</button>';
                }
                if ($status !== 'do_not_send') {
                    $buttons[] = '<button type="button" wire:click="excludeLeadItem('.$item->id.')" class="px-2 py-0.5 text-[11px] font-semibold rounded bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200 cursor-pointer" title="Exclude from delivery to this client">Do Not Send</button>';
                } else {
                    $buttons[] = '<button type="button" wire:click="includeLeadItem('.$item->id.')" class="px-2 py-0.5 text-[11px] font-semibold rounded bg-teal-50 text-teal-700 hover:bg-teal-100 border border-teal-200 cursor-pointer" title="Allow delivery to this client">Allow</button>';
                }
                return '<div class="flex items-center gap-1.5 flex-wrap">'.implode('', $buttons).'</div>';
            }],
        ];
    }

    public function exportHistory()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\BaseStyledExport(
            data: $this->historyQuery()->latest('id')->get(),
            title: 'Lead Distribution & Delivery History',
            subtitle: Organization::findOrFail($this->organizationId)->name . ' - Delivery & Billing Records',
            headings: ['Lead ID', 'Customer Name', 'Destination Client', 'Project', 'Campaign', 'Lead Created Date', 'Delivery Date & Time', 'Delivery Status', 'Rate Per Lead', 'Billing Amount', 'Revoked Date', 'Failure/Return Reason'],
            columns: [
                fn ($i) => $i->lead?->lead_code ?? '#'.$i->lead_id,
                fn ($i) => $i->lead?->name,
                fn ($i) => $i->client?->name,
                fn ($i) => $i->project?->name,
                fn ($i) => $i->campaign?->name,
                fn ($i) => $i->lead?->created_at ? $i->lead->created_at->format('d M Y H:i') : 'N/A',
                fn ($i) => $i->sent_at ? $i->sent_at->format('d M Y H:i') : 'Pending',
                fn ($i) => ucfirst((string)$i->status),
                fn ($i) => $i->rate_per_lead !== null ? '₹'.number_format((float)$i->rate_per_lead, 2) : '-',
                fn ($i) => $i->billing_amount !== null ? '₹'.number_format((float)$i->billing_amount, 2) : '-',
                fn ($i) => $i->revoked_at ? $i->revoked_at->format('d M Y H:i') : '-',
                fn ($i) => $i->failure_reason ?? $i->revocation_reason ?? '-'
            ]
        ), 'client-delivery-history-'.$this->organizationId.'.xlsx');
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
        $this->selectedHistoryIds = [];
        $this->selectAll = false;
        $this->selectAllHistory = false;
        $this->revokeSelectAllMatching = false;
        $this->historySelectAllMatching = false;
    }

    public function toggleSelectHistoryRow($id): void
    {
        $idStr = (string) $id;
        if (in_array($idStr, $this->selectedHistoryIds)) {
            $this->selectedHistoryIds = array_values(array_diff($this->selectedHistoryIds, [$idStr]));
        } else {
            $this->selectedHistoryIds[] = $idStr;
        }
        $this->historySelectAllMatching = false;
    }

    public function updatedSelectAllHistory($value): void
    {
        if ($value) {
            $this->selectedHistoryIds = $this->historyQuery()
                ->paginate(10, ['*'], 'deliveryPage')
                ->pluck('id')
                ->map(fn($id) => (string)$id)
                ->all();
        } else {
            $this->selectedHistoryIds = [];
            $this->historySelectAllMatching = false;
        }
    }

    public function selectAllMatchingHistory(): void
    {
        $this->historySelectAllMatching = true;
        $this->selectedHistoryIds = $this->historyQuery()
            ->pluck('id')
            ->map(fn($id) => (string)$id)
            ->all();
    }

    public function clearHistorySelection(): void
    {
        $this->selectedHistoryIds = [];
        $this->selectAllHistory = false;
        $this->historySelectAllMatching = false;
    }

    public function deliverLeadItem(int $itemId): void
    {
        $item = LeadDeliveryItem::findOrFail($itemId);
        $ok = app(LeadDistributionService::class)->deliverItemToClient($item, auth()->user());
        if ($ok) {
            $this->dispatch('toast', type: 'success', message: 'Lead delivered and billed successfully.');
        } else {
            $this->dispatch('toast', type: 'error', message: 'Delivery failed: insufficient client wallet credits.');
        }
        $this->dispatch('lead-updated');
    }

    public function redeliverLeadItem(int $itemId): void
    {
        $item = LeadDeliveryItem::findOrFail($itemId);
        $ok = app(LeadDistributionService::class)->redeliverToClient($item, auth()->user());
        if ($ok) {
            $this->dispatch('toast', type: 'success', message: 'Lead re-delivered and billed successfully.');
        } else {
            $this->dispatch('toast', type: 'error', message: 'Re-delivery failed: insufficient client wallet credits.');
        }
        $this->dispatch('lead-updated');
    }

    public function excludeLeadItem(int $itemId): void
    {
        $item = LeadDeliveryItem::findOrFail($itemId);
        app(LeadDistributionService::class)->excludeClientDelivery($item, auth()->user());
        $this->dispatch('toast', type: 'info', message: 'Lead excluded from delivery to this client (Do Not Send).');
        $this->dispatch('lead-updated');
    }

    public function includeLeadItem(int $itemId): void
    {
        $item = LeadDeliveryItem::findOrFail($itemId);
        app(LeadDistributionService::class)->includeClientDelivery($item, auth()->user());
        $this->dispatch('toast', type: 'success', message: 'Delivery exclusion removed for this client.');
        $this->dispatch('lead-updated');
    }

    public function bulkDeliverSelected(): void
    {
        $service = app(LeadDistributionService::class);
        $count = 0;
        $ids = !empty($this->selectedHistoryIds) ? $this->selectedHistoryIds : $this->selectedRevokeIds;
        foreach ($ids as $itemId) {
            $item = LeadDeliveryItem::find($itemId);
            if ($item && in_array($item->status, ['pending', 'queued', 'failed'])) {
                if ($service->deliverItemToClient($item, auth()->user())) {
                    $count++;
                }
            }
        }
        $this->clearSelection();
        $this->dispatch('toast', type: 'success', message: "{$count} lead(s) delivered to client(s).");
        $this->dispatch('lead-updated');
    }

    public function bulkExcludeSelected(): void
    {
        $service = app(LeadDistributionService::class);
        $count = 0;
        $ids = !empty($this->selectedHistoryIds) ? $this->selectedHistoryIds : $this->selectedRevokeIds;
        foreach ($ids as $itemId) {
            $item = LeadDeliveryItem::find($itemId);
            if ($item && $item->status !== 'do_not_send') {
                $service->excludeClientDelivery($item, auth()->user());
                $count++;
            }
        }
        $this->clearSelection();
        $this->dispatch('toast', type: 'info', message: "{$count} lead(s) marked as Do Not Send for selected client(s).");
        $this->dispatch('lead-updated');
    }

    public function bulkRedeliverSelected(): void
    {
        $service = app(LeadDistributionService::class);
        $count = 0;
        $ids = !empty($this->selectedHistoryIds) ? $this->selectedHistoryIds : $this->selectedRevokeIds;
        foreach ($ids as $itemId) {
            $item = LeadDeliveryItem::find($itemId);
            if ($item && in_array($item->status, ['revoked', 'failed'])) {
                if ($service->redeliverToClient($item, auth()->user())) {
                    $count++;
                }
            }
        }
        $this->clearSelection();
        $this->dispatch('toast', type: 'success', message: "{$count} lead(s) re-delivered to client(s).");
        $this->dispatch('lead-updated');
    }

    public function openRevokeSingle(int $itemId): void
    {
        $item = LeadDeliveryItem::whereKey($itemId)->firstOrFail();
        abort_unless(in_array($item->status, ['sent', 'accepted']), 422);
        $this->revokeTargetItemIds = [$item->id];
        $this->revokeBatchReason = '';
        $this->revokeSelectAllMatching = false;
        $this->historySelectAllMatching = false;
        $this->showRevokeConfirmModal = true;
    }

    public function openRevokeBatch(): void
    {
        $ids = !empty($this->selectedHistoryIds) ? $this->selectedHistoryIds : $this->selectedRevokeIds;
        if ($this->historySelectAllMatching || $this->revokeSelectAllMatching) {
            $this->revokeTargetItemIds = $this->historyQuery()
                ->whereIn('status', ['sent', 'accepted'])
                ->pluck('id')
                ->all();
        } else {
            $this->revokeTargetItemIds = LeadDeliveryItem::whereIn('id', array_map('intval', $ids))
                ->whereIn('status', ['sent', 'accepted'])
                ->pluck('id')
                ->all();
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

        $this->clearSelection();
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
