<?php

namespace App\Livewire\AccountManager;

use Livewire\Component;
use App\Models\Lead;
use App\Support\LeadPresenter;
use App\Livewire\Concerns\HasAdvancedTable;

class AccountManagerLeads extends Component
{
    use HasAdvancedTable;

    public string $stageFilter = '';

    public function tableColumns(): array
    {
        return [
            ['key' => 'lead_code', 'label' => 'Lead ID', 'class' => 'font-mono font-bold text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'name', 'label' => 'Lead Name', 'class' => 'font-bold text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'mobile', 'label' => 'Masked Mobile', 'render' => fn($row) => '<span class="bg-canvas px-2 py-0.5 rounded border border-border text-[11px] font-semibold text-ink font-mono">' . e(is_array($row) ? $row['mobile'] : $row->mobile) . '</span>', 'sortable' => false, 'priority' => 1],
            ['key' => 'email', 'label' => 'Masked Email', 'render' => fn($row) => '<span class="bg-canvas px-2 py-0.5 rounded border border-border text-[11px] font-semibold text-ink font-mono">' . e(is_array($row) ? $row['email'] : $row->email) . '</span>', 'sortable' => false, 'priority' => 2],
            ['key' => 'project_name', 'label' => 'Project', 'class' => 'text-muted', 'sortable' => false, 'priority' => 2],
            ['key' => 'source_name', 'label' => 'Campaign / Source', 'render' => fn($row) => '<div><div class="text-ink font-medium">' . e(is_array($row) ? $row['campaign_name'] : $row->campaign_name) . '</div><div class="text-[10px] text-muted">' . strtoupper(e(is_array($row) ? $row['source_name'] : $row->source_name)) . '</div></div>', 'sortable' => false, 'priority' => 2],
            ['key' => 'assigned_executive', 'label' => 'Assigned Executive', 'class' => 'font-semibold text-ink', 'sortable' => false, 'priority' => 2],
            ['key' => 'current_stage', 'label' => 'Stage', 'class' => 'px-2.5 py-0.5 text-[10px] font-bold rounded-pill uppercase bg-canvas text-ink border border-border inline-block', 'sortable' => false, 'priority' => 1],
        ];
    }

    public function updatingStageFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Lead::with(['project', 'client', 'campaign', 'leadSource', 'assignedTo']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('lead_code', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->stageFilter) {
            $query->where('current_stage', $this->stageFilter);
        }

        $sortField = in_array($this->sortField, ['id', 'name', 'lead_code']) ? $this->sortField : 'id';
        $sortDir = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $rawLeads = $query->orderBy($sortField, $sortDir)->paginate($this->perPage);

        // Map leads through LeadPresenter to guarantee PII masking
        $presentedLeads = $rawLeads->getCollection()->map(function ($lead) {
            return LeadPresenter::present($lead, auth()->user());
        });
        $rawLeads->setCollection($presentedLeads);

        return view('livewire.account-manager.account-manager-leads', [
            'leads' => $rawLeads,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $query = Lead::with(['project', 'client', 'campaign', 'leadSource', 'assignedTo']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('lead_code', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->stageFilter) {
            $query->where('current_stage', $this->stageFilter);
        }

        $user = auth()->user();
        $rawLeads = $query->latest('id')->get();
        $data = $rawLeads->map(fn($lead) => LeadPresenter::present($lead, $user));

        $filename = "account-manager-leads_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Lead Code', 'Name', 'Phone (Masked)', 'Email (Masked)', 'Project', 'Campaign', 'Assigned Executive', 'Stage'];
        $columns = [
            'lead_code',
            'name',
            'mobile',
            'email',
            'project_name',
            'campaign_name',
            'assigned_executive',
            'current_stage',
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T') . " | PII Masked Account Directory";

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: $data,
                title: 'Account Manager Leads Directory',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                statusColumns: ['current_stage']
            ),
            $filename
        );
    }
}
