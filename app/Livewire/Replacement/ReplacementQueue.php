<?php

namespace App\Livewire\Replacement;

use Livewire\Component;
use App\Livewire\Concerns\HasAdvancedTable;
use App\Models\LeadReplacement;
use App\Models\Client;
use App\Models\Project;

class ReplacementQueue extends Component
{
    use HasAdvancedTable;

    public bool $showRejectModal = false;
    public ?int $rejectingReplacementId = null;
    public string $rejectionNote = '';

    public $filterClient = '';
    public $filterProject = '';

    public function mount(): void
    {
        $this->loadColumnPreferences();
    }

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'Claim ID', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-ink'],
            ['key' => 'requested_at', 'label' => 'Requested At', 'type' => 'date', 'sortable' => true, 'priority' => 1, 'format' => 'M d, Y H:i'],
            ['key' => 'lead_summary', 'label' => 'Original Lead', 'type' => 'text', 'priority' => 1, 'class' => 'font-medium text-ink'],
            ['key' => 'reason_name', 'label' => 'Reason', 'type' => 'text', 'priority' => 1],
            ['key' => 'requested_by_name', 'label' => 'Requested By', 'type' => 'text', 'priority' => 2, 'class' => 'text-muted'],
            ['key' => 'sla_badge', 'label' => 'SLA Status', 'type' => 'badge', 'priority' => 2, 'badgeMap' => [
                'SLA Met' => 'bg-green-50 text-green-700 border border-green-200',
                'SLA Missed' => 'bg-amber-50 text-amber-700 border border-amber-200',
            ]],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'sortable' => true, 'priority' => 1, 'badgeMap' => [
                'pending' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'approved' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'rejected' => 'bg-red-50 text-red-700 border border-red-200',
            ]],
            ['key' => 'actions', 'label' => 'Actions', 'type' => 'replacement_actions', 'priority' => 1],
        ];
    }

    public function quickFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All Claims'],
            ['key' => 'pending', 'label' => 'Pending Review'],
            ['key' => 'approved', 'label' => 'Approved'],
            ['key' => 'rejected', 'label' => 'Rejected'],
        ];
    }

    public function approveReplacement(int $id): void
    {
        $replacement = LeadReplacement::find($id);
        if ($replacement) {
            $replacement->approve(auth()->id() ?? 1);
            $this->dispatch('toast', type: 'success', message: 'Replacement APPROVED. Credit refunded and replacement lead generated.');
        }
    }

    public function openRejectModal(int $id): void
    {
        $this->rejectingReplacementId = $id;
        $this->showRejectModal = true;
    }

    public function closeRejectModal(): void
    {
        $this->showRejectModal = false;
        $this->rejectionNote = '';
        $this->rejectingReplacementId = null;
    }

    public function confirmReject(): void
    {
        $this->validate([
            'rejectionNote' => 'required|string|min:5',
        ]);

        if ($this->rejectingReplacementId) {
            $replacement = LeadReplacement::find($this->rejectingReplacementId);
            if ($replacement) {
                $replacement->reject($this->rejectionNote, auth()->id() ?? 1);
                $this->dispatch('toast', type: 'success', message: 'Replacement claim REJECTED with logged audit reason.');
            }
        }

        $this->closeRejectModal();
    }

    protected function getFilteredQuery()
    {
        $query = LeadReplacement::with(['lead', 'reason', 'requestedBy', 'replacementLead']);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->whereHas('lead', function ($lq) {
                    $lq->where('name', 'like', '%' . $this->search . '%')
                       ->orWhere('lead_code', 'like', '%' . $this->search . '%')
                       ->orWhere('mobile', 'like', '%' . $this->search . '%');
                })->orWhereHas('reason', function ($rq) {
                    $rq->where('reason_name', 'like', '%' . $this->search . '%');
                });
            });
        }

        if ($this->filterClient) {
            $query->whereHas('lead', function ($q) {
                $q->where('client_id', $this->filterClient);
            });
        }

        if ($this->filterProject) {
            $query->whereHas('lead', function ($q) {
                $q->where('project_id', $this->filterProject);
            });
        }

        if ($this->statusFilter === 'pending') {
            $query->where('status', 'pending');
        } elseif ($this->statusFilter === 'approved') {
            $query->where('status', 'approved');
        } elseif ($this->statusFilter === 'rejected') {
            $query->where('status', 'rejected');
        }

        if (!empty($this->sortField)) {
            $query->orderBy($this->sortField, $this->sortDirection);
        } else {
            $query->latest('requested_at');
        }

        return $query;
    }

    public function render()
    {
        $replacements = $this->getFilteredQuery()->paginate($this->perPage);
        $clients = Client::all();
        $projects = Project::all();

        return view('livewire.replacement.replacement-queue', [
            'replacements' => $replacements,
            'clients' => $clients,
            'projects' => $projects,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $data = $this->getFilteredQuery()->get();
        $filename = "replacement-requests-queue_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Claim ID', 'Requested At', 'Original Lead', 'Reason', 'SLA Status', 'Eligible', 'Status', 'Resolution Note'];
        $columns = [
            'id',
            fn($r) => $r->requested_at ? ($r->requested_at instanceof \Carbon\CarbonInterface ? $r->requested_at->format('M d, Y H:i') : \Carbon\Carbon::parse($r->requested_at)->format('M d, Y H:i')) : '',
            fn($r) => $r->lead?->lead_code ?: 'N/A',
            fn($r) => $r->reason?->reason_name ?: 'N/A',
            fn($r) => $r->sla_met ? 'SLA Met' : 'Missed',
            fn($r) => $r->reason?->is_eligible ? 'Yes' : 'No',
            fn($r) => $r->status,
            fn($r) => $r->resolution_note ?: ($r->replacementLead ? "Replaced by {$r->replacementLead->lead_code}" : ''),
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T') . ($this->statusFilter ? " | Status: {$this->statusFilter}" : '');

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: $data,
                title: 'Lead Replacement Claims Queue',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                statusColumns: ['status', 'sla_status', 'eligible']
            ),
            $filename
        );
    }
}
