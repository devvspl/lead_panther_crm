<?php

namespace App\Livewire\Leads;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Organization;
use App\Models\User;
use App\Models\LeadSource;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Models\LeadStatusHistory;
use App\Models\GeneralSetting;
use App\Events\LeadStageChanged;
use Livewire\Attributes\On;

class LeadKanban extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $project = '';
    #[Url] public string $partner = '';
    #[Url] public string $executive = '';
    #[Url] public string $team = '';
    #[Url] public string $source = '';
    #[Url] public string $date_range = '';
    #[Url] public bool $sla_breached = false;
    #[Url] public string $sort_by = 'created_desc';

    public string $viewMode = 'kanban';
    public string $analyticsRange = 'month';
    public string $breakdownTab = 'builder';

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

        if (auth()->check()) {
            $this->viewMode = GeneralSetting::getValue(auth()->id(), 'leads_view_mode', 'kanban') ?: 'kanban';
            $this->analyticsRange = GeneralSetting::getValue(auth()->id(), 'leads_analytics_range', 'month') ?: 'month';
        }
    }

    public function switchViewMode(string $mode): void
    {
        if (in_array($mode, ['kanban', 'table'])) {
            $this->viewMode = $mode;
            if (auth()->check()) {
                GeneralSetting::setValue(auth()->id(), 'leads_view_mode', $mode);
            }
        }
    }

    public function updatedAnalyticsRange(string $range): void
    {
        if (auth()->check()) {
            GeneralSetting::setValue(auth()->id(), 'leads_analytics_range', $range);
        }
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedProject(): void { $this->resetPage(); }
    public function updatedExecutive(): void { $this->resetPage(); }
    public function updatedTeam(): void { $this->resetPage(); }
    public function updatedSource(): void { $this->resetPage(); }
    public function updatedSlaBreached(): void { $this->resetPage(); }
    public function updatedSortBy(): void { $this->resetPage(); }

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

    public function getAvailableTeams()
    {
        $user = auth()->user();
        if (!$user) return collect();

        // Hide for Sales Executive
        if ($user->hasRole('sales-executive') || $user->hasRole('Sales Executive')) {
            return collect();
        }

        $query = SalesTeam::query();

        if ($user->hasRole('channel-partner') || $user->hasRole('Channel Partner')) {
            $query->where(function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('ownable_type', Organization::class)
                        ->where('ownable_id', $user->organization_id);
                })->orWhereHas('members', function ($sub) use ($user) {
                    $sub->where('user_id', $user->id);
                });
            });
        } elseif ($user->hasRole('builder') || $user->hasRole('Builder') || $user->hasRole('builder-admin')) {
            if ($user->organization_id) {
                $query->where('ownable_type', Organization::class)
                      ->where('ownable_id', $user->organization_id);
            }
        }

        return $query->get();
    }

    protected function getScopedQuery()
    {
        $user = auth()->user();
        $query = Lead::with(['client', 'project', 'campaign', 'leadSource', 'assignedTo.salesTeamMembers.salesTeam']);

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

        if ($this->team) {
            $query->whereIn('assigned_to', function ($sub) {
                $sub->select('user_id')
                    ->from('sales_team_members')
                    ->where('sales_team_id', $this->team);
            });
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

    protected function getAnalyticsData(): array
    {
        $scopedBase = $this->getScopedQuery();

        $analyticsQuery = (clone $scopedBase);
        if ($this->analyticsRange === 'today') {
            $analyticsQuery->where('created_at', '>=', now()->startOfDay());
        } elseif ($this->analyticsRange === 'week') {
            $analyticsQuery->where('created_at', '>=', now()->startOfWeek());
        } elseif ($this->analyticsRange === 'month') {
            $analyticsQuery->where('created_at', '>=', now()->startOfMonth());
        } elseif ($this->analyticsRange === 'quarter') {
            $analyticsQuery->where('created_at', '>=', now()->startOfQuarter());
        }

        $totalLeads = (clone $analyticsQuery)->count();
        $newLeadsToday = (clone $scopedBase)->whereDate('created_at', now()->today())->count();
        $assignedCount = (clone $analyticsQuery)->where(fn($q) => $q->where('current_stage', 'assigned')->orWhere('current_stage', 'Assigned'))->count();
        $pendingResponse = (clone $analyticsQuery)->whereNotNull('assigned_to')->whereNull('first_response_at')->count();
        $slaBreachedCount = (clone $analyticsQuery)->whereNull('first_response_at')->where('created_at', '<', now()->subMinutes(30))->count();
        $siteVisitsThisWeek = (clone $scopedBase)->where(fn($q) => $q->where('current_stage', 'site_visit')->orWhere('current_stage', 'Site Visit'))->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $bookingsThisMonth = (clone $scopedBase)->where(fn($q) => $q->whereIn('current_stage', ['booking', 'Booking', 'closed_won', 'Closed Won']))->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
        
        $wonCount = (clone $analyticsQuery)->where(fn($q) => $q->where('current_stage', 'closed_won')->orWhere('current_stage', 'Closed Won'))->count();
        $conversionRate = $totalLeads > 0 ? round(($wonCount / $totalLeads) * 100, 1) : 0;

        // Breakdown data logic
        $allLeads = (clone $analyticsQuery)->get();
        $grouped = [];

        foreach ($allLeads as $l) {
            $groupName = 'Unassigned';
            if ($this->breakdownTab === 'builder') {
                $groupName = $l->project?->client?->name ?: ($l->client?->name ?: 'Direct Builder');
            } elseif ($this->breakdownTab === 'project') {
                $groupName = $l->project?->name ?: 'No Project';
            } elseif ($this->breakdownTab === 'source') {
                $groupName = $l->leadSource?->name ?: 'Direct Ingestion';
            } elseif ($this->breakdownTab === 'executive') {
                $groupName = $l->assignedTo?->name ?: 'Unassigned';
            } elseif ($this->breakdownTab === 'team') {
                $teamMember = $l->assignedTo?->salesTeamMembers?->first();
                $groupName = $teamMember?->salesTeam?->name ?: 'Direct Team';
            }

            if (!isset($grouped[$groupName])) {
                $grouped[$groupName] = [
                    'name' => $groupName,
                    'total_leads' => 0,
                    'closed_won' => 0,
                    'sla_times' => [],
                ];
            }

            $grouped[$groupName]['total_leads']++;
            if (in_array(strtolower($l->current_stage), ['closed_won', 'closed won'])) {
                $grouped[$groupName]['closed_won']++;
            }

            if ($l->first_response_at && $l->created_at) {
                $diffMinutes = $l->created_at->diffInMinutes($l->first_response_at);
                $grouped[$groupName]['sla_times'][] = $diffMinutes;
            }
        }

        // Format breakdown rows
        $breakdownRows = [];
        foreach ($grouped as $g) {
            $tot = $g['total_leads'];
            $conv = $tot > 0 ? round(($g['closed_won'] / $tot) * 100, 1) : 0;
            
            $avgSlaStr = 'N/A';
            if (!empty($g['sla_times'])) {
                $avgMins = (int) round(array_sum($g['sla_times']) / count($g['sla_times']));
                if ($avgMins < 60) {
                    $avgSlaStr = "{$avgMins}m";
                } else {
                    $hrs = floor($avgMins / 60);
                    $remMins = $avgMins % 60;
                    $avgSlaStr = "{$hrs}h {$remMins}m";
                }
            }

            $breakdownRows[] = [
                'name' => $g['name'],
                'total_leads' => $tot,
                'conversion_rate' => $conv,
                'avg_sla_time' => $avgSlaStr,
            ];
        }

        // Sort descending by total leads
        usort($breakdownRows, fn($a, $b) => $b['total_leads'] <=> $a['total_leads']);

        $chartLabels = array_map(fn($r) => $r['name'], array_slice($breakdownRows, 0, 10));
        $chartCounts = array_map(fn($r) => $r['total_leads'], array_slice($breakdownRows, 0, 10));

        return [
            'totalLeads' => $totalLeads,
            'newLeadsToday' => $newLeadsToday,
            'assignedCount' => $assignedCount,
            'pendingResponse' => $pendingResponse,
            'slaBreachedCount' => $slaBreachedCount,
            'siteVisitsThisWeek' => $siteVisitsThisWeek,
            'bookingsThisMonth' => $bookingsThisMonth,
            'conversionRate' => $conversionRate,
            'breakdownRows' => $breakdownRows,
            'chartLabels' => $chartLabels,
            'chartCounts' => $chartCounts,
        ];
    }

    public function render()
    {
        $baseQuery = $this->getScopedQuery();
        $analytics = $this->getAnalyticsData();
        $availableTeams = $this->getAvailableTeams();

        $stageLeads = [];
        $stageCounts = [];
        $tableLeads = null;

        if ($this->viewMode === 'table') {
            $tableQuery = (clone $baseQuery);
            if ($this->sort_by === 'score_desc') {
                $tableQuery->orderByDesc('lead_score')->latest('id');
            } elseif ($this->sort_by === 'score_asc') {
                $tableQuery->orderBy('lead_score')->latest('id');
            } else {
                $tableQuery->latest();
            }

            $tableLeads = $tableQuery->paginate(15);
        } else {
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
        }

        return view('livewire.leads.lead-kanban', [
            'stageLeads' => $stageLeads,
            'stageCounts' => $stageCounts,
            'tableLeads' => $tableLeads,
            'analytics' => $analytics,
            'availableTeams' => $availableTeams,
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
