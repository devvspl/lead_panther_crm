<?php

namespace App\Livewire\AccountManager;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Lead;
use App\Support\LeadPresenter;

class AccountManagerLeads extends Component
{
    use WithPagination;

    public string $search = '';
    public string $stageFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
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

        $rawLeads = $query->latest('id')->paginate(10);

        // Map leads through LeadPresenter to guarantee PII masking
        $presentedLeads = $rawLeads->getCollection()->map(function ($lead) {
            return LeadPresenter::present($lead, auth()->user());
        });

        return view('livewire.account-manager.account-manager-leads', [
            'leads' => $presentedLeads,
            'paginator' => $rawLeads,
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
