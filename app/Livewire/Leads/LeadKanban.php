<?php

namespace App\Livewire\Leads;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Organization;
use App\Models\User;
use App\Models\LeadSource;
use App\Models\LeadStatusHistory;
use App\Events\LeadStageChanged;
use Livewire\Attributes\On;

class LeadKanban extends Component
{
    #[Url] public string $search = '';
    #[Url] public string $project = '';
    #[Url] public string $partner = '';
    #[Url] public string $executive = '';
    #[Url] public string $source = '';
    #[Url] public string $date_range = '';
    #[Url] public bool $sla_breached = false;
    #[Url] public string $sort_by = 'created_desc';

    public bool $otherColumnExpanded = false;

    // Terminal stage confirmation modal state
    public bool $showConfirmModal = false;
    public ?int $pendingLeadId = null;
    public string $pendingToStage = '';
    public string $pendingFromStage = '';

    // Per-column limits
    public array $stageLimits = [];

    public array $mainStages = [
        'new' => 'New',
        'assigned' => 'Assigned',
        'contacted' => 'Contacted',
        'connected' => 'Connected',
        'qualified' => 'Qualified',
        'interested' => 'Interested',
        'follow-up' => 'Follow-up',
        'meeting' => 'Meeting',
        'site_visit' => 'Site Visit',
        'negotiation' => 'Negotiation',
        'proposal' => 'Proposal',
        'booking' => 'Booking',
        'payment' => 'Payment',
        'closed_won' => 'Closed Won',
    ];

    public array $otherStages = [
        'closed_lost' => 'Closed Lost',
        'wrong_number' => 'Wrong Number',
        'duplicate' => 'Duplicate',
        'rental' => 'Rental',
        'replaced' => 'Replaced',
    ];

    public function mount(): void
    {
        foreach (array_merge(array_keys($this->mainStages), array_keys($this->otherStages)) as $stage) {
            $this->stageLimits[$stage] = 20;
        }
    }

    public function loadMore(string $stage): void
    {
        $this->stageLimits[$stage] = ($this->stageLimits[$stage] ?? 20) + 20;
    }

    #[On('lead-updated')]
    public function refreshBoard(): void
    {
        // Refresh component state
    }

    public function updateLeadStage(int $leadId, string $toStage): void
    {
        $lead = Lead::find($leadId);
        if (!$lead) return;

        // Prevent client users from dragging (read-only)
        if (auth()->user()?->hasRole('client')) {
            return;
        }

        $fromStage = $lead->current_stage;
        if ($fromStage === $toStage) return;

        // If moving to terminal/exception stage (Closed Lost, Replaced, Wrong Number, Duplicate, Rental)
        if (array_key_exists($toStage, $this->otherStages) || $toStage === 'closed_lost') {
            $this->pendingLeadId = $leadId;
            $this->pendingFromStage = $fromStage;
            $this->pendingToStage = $toStage;
            $this->showConfirmModal = true;
            return;
        }

        $this->executeStageTransition($lead, $fromStage, $toStage);
    }

    public function confirmTerminalTransition(): void
    {
        if ($this->pendingLeadId && $this->pendingToStage) {
            $lead = Lead::find($this->pendingLeadId);
            if ($lead) {
                $this->executeStageTransition($lead, $this->pendingFromStage, $this->pendingToStage);
            }
        }
        $this->cancelTerminalTransition();
    }

    public function cancelTerminalTransition(): void
    {
        $this->showConfirmModal = false;
        $this->pendingLeadId = null;
        $this->pendingFromStage = '';
        $this->pendingToStage = '';
    }

    protected function executeStageTransition(Lead $lead, string $fromStage, string $toStage): void
    {
        $lead->update([
            'current_stage' => $toStage,
            'status' => $toStage,
            'first_response_at' => ($fromStage === 'new' && !$lead->first_response_at) ? now() : $lead->first_response_at,
        ]);

        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'from_status' => $fromStage,
            'to_status' => $toStage,
            'changed_by' => auth()->id() ?? 1,
            'changed_at' => now(),
        ]);

        event(new LeadStageChanged($lead, $fromStage, $toStage, auth()->id() ?? 1));
        $this->dispatch('toast', type: 'success', message: "Lead {$lead->lead_code} moved to " . ucfirst(str_replace('_', ' ', $toStage)));
    }

    protected function getScopedQuery()
    {
        $user = auth()->user();
        $query = Lead::with(['client', 'project', 'campaign', 'leadSource', 'assignedTo']);

        if (!$user) {
            return $query;
        }

        // Scoping by Role
        if ($user->hasRole('sales-executive') || $user->hasRole('Sales Executive')) {
            $query->where('assigned_to', $user->id);
        } elseif ($user->hasRole('channel-partner') || $user->hasRole('Channel Partner')) {
            $query->whereHas('assignedTo', function ($q) use ($user) {
                $q->where('organization_id', $user->organization_id);
            });
        } elseif ($user->hasRole('client')) {
            $query->whereHas('client', function ($q) use ($user) {
                $q->where('organization_id', $user->organization_id);
            });
        }

        // Filters
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('lead_code', 'like', '%' . $this->search . '%')
                  ->orWhere('mobile', 'like', '%' . $this->search . '%')
                  ->orWhere('city', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->project) {
            $query->where('project_id', $this->project);
        }

        if ($this->executive) {
            $query->where('assigned_to', $this->executive);
        }

        if ($this->source) {
            $query->where('lead_source_id', $this->source);
        }

        if ($this->sla_breached) {
            $query->whereNull('first_response_at')
                  ->where('created_at', '<', now()->subMinutes(15));
        }

        return $query;
    }

    public function render()
    {
        $baseQuery = $this->getScopedQuery();

        $stageLeads = [];
        $stageCounts = [];

        $allStages = array_merge($this->mainStages, $this->otherStages);

        foreach ($allStages as $stageKey => $stageLabel) {
            $stageQuery = (clone $baseQuery)->where('current_stage', $stageKey);
            $stageCounts[$stageKey] = $stageQuery->count();
            $limit = $this->stageLimits[$stageKey] ?? 20;

            if ($this->sort_by === 'score_desc') {
                $stageQuery->orderByDesc('lead_score')->latest('id');
            } elseif ($this->sort_by === 'score_asc') {
                $stageQuery->orderBy('lead_score')->latest('id');
            } else {
                $stageQuery->latest();
            }

            $stageLeads[$stageKey] = $stageQuery->take($limit)->get();
        }

        return view('livewire.leads.lead-kanban', [
            'stageLeads' => $stageLeads,
            'stageCounts' => $stageCounts,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $baseQuery = $this->getScopedQuery();
        $data = $baseQuery->latest('id')->get();

        $user = auth()->user();
        $orgName = $user && $user->organization ? \Illuminate\Support\Str::slug($user->organization->name) : 'all';
        $filename = "leads-directory_{$orgName}_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Lead Code', 'Name', 'Phone', 'Email', 'Project', 'Source', 'Assigned Executive', 'Stage', 'Score', 'Created At'];
        $columns = [
            'lead_code',
            'name',
            fn($l) => ($user && ($user->hasRole('Account Manager') || $user->hasRole('account-manager'))) ? substr_replace($l->mobile, '******', 2, 6) : $l->mobile,
            fn($l) => ($user && ($user->hasRole('Account Manager') || $user->hasRole('account-manager'))) ? substr_replace($l->email, '****', 2, 4) : $l->email,
            fn($l) => $l->project?->name ?: 'N/A',
            fn($l) => $l->leadSource?->name ?: 'Direct Ingestion',
            fn($l) => $l->assignedTo?->name ?: 'Unassigned',
            fn($l) => $l->current_stage,
            'lead_score',
            fn($l) => $l->created_at ? $l->created_at->format('M d, Y H:i') : '',
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T') . ($this->search ? " | Search: {$this->search}" : '') . ($this->project ? " | ProjectID: {$this->project}" : '');

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: $data,
                title: 'Leads Directory & Pipeline Export',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                statusColumns: ['current_stage']
            ),
            $filename
        );
    }
}
