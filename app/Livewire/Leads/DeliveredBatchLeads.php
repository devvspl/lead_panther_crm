<?php

namespace App\Livewire\Leads;

use App\Exports\BaseStyledExport;
use App\Models\{Campaign, LeadDeliveryAttempt, LeadDeliveryItem, LeadDeliveryRun, Organization, Project};
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\{Locked, On};
use Livewire\{Component, WithPagination};
use Maatwebsite\Excel\Facades\Excel;

class DeliveredBatchLeads extends Component
{
    use WithPagination;

    #[Locked] public int $organizationId;
    #[Locked] public ?int $batchId = null;
    public bool $show = false;
    public string $batchNumber = '';
    public string $deliveredSearch = '';
    public string $deliveredFrom = '';
    public string $deliveredTo = '';
    public string $deliveredProject = '';
    public string $deliveredCampaign = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->status === 'active' && auth()->user()->hasAnyRole(['Super Admin', 'super-admin']), 403);
    }

    public function mount(int $organizationId): void
    {
        $this->organizationId = Organization::findOrFail($organizationId)->id;
    }

    private function batches(): Builder
    {
        return LeadDeliveryRun::whereHas('plan', fn($q) => $q->where('organization_id', $this->organizationId));
    }

    private function selectedBatch(): LeadDeliveryRun
    {
        abort_unless($this->batchId, 404);
        return $this->batches()->findOrFail($this->batchId);
    }

    #[On('open-delivered-batch')]
    public function open(int $organizationId, int $batchId): void
    {
        if ($organizationId !== $this->organizationId) return;
        $batch = $this->batches()->findOrFail($batchId);
        if ($this->batchId !== $batch->id) $this->resetTableFilters();
        $this->batchId = $batch->id;
        $this->batchNumber = (string)$batch->id;
        $this->show = true;
        $this->dispatch('open-modal', 'delivered-batch-'.$this->organizationId);
    }

    public function close(): void
    {
        $this->show = false;
        $this->dispatch('close-modal', 'delivered-batch-'.$this->organizationId);
    }

    public function updatedBatchNumber(): void
    {
        abort_unless(ctype_digit($this->batchNumber), 422);
        $batch = $this->batches()->findOrFail((int)$this->batchNumber);
        $this->batchId = $batch->id;
        $this->resetTableFilters();
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'delivered')) $this->resetPage('deliveredPage');
    }

    public function resetTableFilters(): void
    {
        $this->reset(['deliveredSearch', 'deliveredFrom', 'deliveredTo', 'deliveredProject', 'deliveredCampaign']);
        $this->resetValidation();
        $this->resetPage('deliveredPage');
    }

    private function query(bool $filtered = true): Builder
    {
        $batch = $this->selectedBatch();
        $this->validate(['deliveredFrom' => 'nullable|date_format:Y-m-d', 'deliveredTo' => 'nullable|date_format:Y-m-d']);
        $attempt = function ($q) use ($batch, $filtered) {
            $q->where('run_id', $batch->id)->where('status', 'sent');
            if ($filtered) {
                $q->when($this->deliveredFrom, fn($q) => $q->whereDate('created_at', '>=', $this->deliveredFrom))
                    ->when($this->deliveredTo, fn($q) => $q->whereDate('created_at', '<=', $this->deliveredTo));
            }
        };
        // Attempt history remains attached to the original batch when an item is retried.
        $query = LeadDeliveryItem::where('plan_id', $batch->plan_id)->whereHas('lead')->whereHas('attemptHistory', $attempt)
            ->with(['lead.assignedTo', 'project', 'campaign'])
            ->withMax(['attemptHistory as batch_delivered_at' => $attempt], 'created_at');
        if (!$filtered) return $query;
        return $query->when(trim($this->deliveredSearch) !== '', function ($q) {
            $search = '%'.trim($this->deliveredSearch).'%';
            $q->whereHas('lead', fn($lead) => $lead->where(fn($lead) => $lead->where('lead_code', 'like', $search)->orWhere('name', 'like', $search)->orWhere('mobile', 'like', $search)));
        })->when($this->deliveredProject, fn($q) => $q->where('project_id', $this->deliveredProject))
            ->when($this->deliveredCampaign, fn($q) => $q->where('campaign_id', $this->deliveredCampaign));
    }

    public function columns(): array
    {
        return [
            ['key'=>'lead.lead_code', 'label'=>'Lead ID'], ['key'=>'lead.name', 'label'=>'Customer Name'],
            ['key'=>'lead.mobile', 'label'=>'Mobile Number', 'class'=>'font-mono text-xs text-ink'],
            ['key'=>'project.name', 'label'=>'Project'], ['key'=>'campaign.name', 'label'=>'Campaign'],
            ['key'=>'lead.assignedTo.name', 'label'=>'Assigned Executive', 'default'=>'Unassigned'],
            ['key'=>'batch_delivered_at', 'label'=>'Delivery Date', 'class'=>'text-xs text-muted'],
            ['key'=>'status', 'label'=>'Delivery Status', 'formatter'=>fn($status)=>$status === 'accepted' ? 'Accepted' : 'Delivered'],
            ['key'=>'id', 'label'=>'Actions', 'type'=>'html', 'render'=>fn($item)=>view('components.ui.delivered-lead-action',['item'=>$item])->render()],
        ];
    }

    public function viewDeliveredLead(int $itemId): void
    {
        $item = $this->query(false)->findOrFail($itemId);
        $this->close();
        $this->dispatch('open-lead-detail', id: $item->lead_id);
    }

    public function exportDelivered()
    {
        $batch = $this->selectedBatch();
        return Excel::download(new BaseStyledExport(
            data: $this->query()->orderByDesc('batch_delivered_at')->orderByDesc('id')->get(),
            title: 'Delivered Leads - Batch '.$batch->id, subtitle: 'Delivery batch #'.$batch->id,
            headings: ['Lead ID','Customer Name','Mobile Number','Project','Campaign','Assigned Executive','Delivery Date','Delivery Status','Batch #'],
            columns: ['lead.lead_code','lead.name','lead.mobile','project.name','campaign.name',fn($item)=>$item->lead?->assignedTo?->name ?? 'Unassigned','batch_delivered_at',fn($item)=>$item->status === 'accepted' ? 'Accepted' : 'Delivered',fn()=>$batch->id]
        ), 'delivered-leads-batch-'.$batch->id.'.xlsx');
    }

    public function render()
    {
        $batch = $this->show ? $this->selectedBatch() : null;
        $base = $batch ? $this->query(false) : null;
        $attempts = $batch ? LeadDeliveryAttempt::where('run_id', $batch->id) : null;
        return view('livewire.leads.delivered-batch-leads', [
            'batch'=>$batch,
            'summary'=>$batch ? ['Total'=>$batch->total, 'Processed'=>(clone $attempts)->count(), 'Successful'=>(clone $attempts)->where('status','sent')->count(), 'Failed'=>(clone $attempts)->where('status','failed')->count()] : [],
            'leads'=>$batch ? $this->query()->orderByDesc('batch_delivered_at')->orderByDesc('id')->paginate(15,['*'],'deliveredPage') : null,
            'batchOptions'=>$batch ? $this->batches()->latest('id')->get()->mapWithKeys(fn($run)=>[$run->id=>'#'.$run->id.' · '.$run->created_at->format('d M Y H:i')])->all() : [],
            'projects'=>$batch ? Project::withoutGlobalScopes()->whereIn('id',(clone $base)->select('project_id'))->orderBy('name')->pluck('name','id')->all() : [],
            'campaigns'=>$batch ? Campaign::withoutGlobalScopes()->whereIn('id',(clone $base)->select('campaign_id'))->orderBy('name')->pluck('name','id')->all() : [],
        ]);
    }
}
