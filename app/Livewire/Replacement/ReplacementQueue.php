<?php

namespace App\Livewire\Replacement;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\LeadReplacement;
use App\Models\Client;
use App\Models\Project;

class ReplacementQueue extends Component
{
    use WithPagination;

    public bool $showRejectModal = false;
    public ?int $rejectingReplacementId = null;
    public string $rejectionNote = '';

    public string $filterClient = '';
    public string $filterProject = '';
    public string $filterStatus = '';
    public string $filterDateRange = '';

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

    public function render()
    {
        $query = LeadReplacement::with(['lead', 'reason', 'requestedBy', 'replacementLead']);

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

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterDateRange) {
            if ($this->filterDateRange === 'today') {
                $query->whereDate('requested_at', now());
            } elseif ($this->filterDateRange === 'week') {
                $query->where('requested_at', '>=', now()->subDays(7));
            } elseif ($this->filterDateRange === 'month') {
                $query->where('requested_at', '>=', now()->subDays(30));
            }
        }

        $replacements = $query->latest('requested_at')->paginate(15);
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
        $query = LeadReplacement::with(['lead', 'reason', 'requestedBy', 'replacementLead']);

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

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterDateRange) {
            if ($this->filterDateRange === 'today') {
                $query->whereDate('requested_at', now());
            } elseif ($this->filterDateRange === 'week') {
                $query->where('requested_at', '>=', now()->subDays(7));
            } elseif ($this->filterDateRange === 'month') {
                $query->where('requested_at', '>=', now()->subDays(30));
            }
        }

        $data = $query->latest('requested_at')->get();
        $filename = "replacement-requests-queue_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Claim ID', 'Requested At', 'Original Lead', 'Reason', 'SLA Status', 'Eligible', 'Status', 'Resolution Note'];
        $columns = [
            'id',
            fn($r) => $r->requested_at ? $r->requested_at->format('M d, Y H:i') : '',
            fn($r) => $r->lead?->lead_code ?: 'N/A',
            fn($r) => $r->reason?->reason_name ?: 'N/A',
            fn($r) => $r->sla_met ? 'SLA Met' : 'Missed',
            fn($r) => $r->reason?->is_eligible ? 'Yes' : 'No',
            fn($r) => $r->status,
            fn($r) => $r->resolution_note ?: ($r->replacementLead ? "Replaced by {$r->replacementLead->lead_code}" : ''),
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T') . ($this->filterStatus ? " | Status: {$this->filterStatus}" : '');

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
