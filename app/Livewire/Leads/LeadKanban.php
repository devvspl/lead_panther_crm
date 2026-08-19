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
use App\Livewire\Concerns\HasAdvancedTable;
use Livewire\Attributes\On;

class LeadKanban extends Component
{
    use HasAdvancedTable;

    #[Url] public string $search = '';
    #[Url] public string $statusFilter = 'all';
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
    public ?string $analyticsCustomFrom = null;
    public ?string $analyticsCustomTo = null;
    public string $breakdownTab = 'builder';

    public bool $otherColumnExpanded = false;

    // Terminal stage confirmation modal state
    public bool $showConfirmModal = false;
    public ?int $pendingLeadId = null;
    public string $pendingToStage = '';
    public string $pendingFromStage = '';

    // Per-column limits
    public array $stageLimits = [];

    // Manual Lead Ingestion & Meta Pull Modal State
    public bool $showPullLeadsModal = false;
    public string $pullMode = 'meta'; // 'meta' | 'manual'
    public ?int $selectedPortalAccountId = null;
    public string $customFormId = '';
    public string $customPageAccessToken = '';
    public int $pullLimit = 25;
    public ?int $syncProjectId = null;
    public ?int $syncCampaignId = null;
    public bool $isPulling = false;
    public ?array $pullSummary = null;

    // Single Manual Lead Ingestion State
    public string $manualName = '';
    public string $manualMobile = '';
    public string $manualEmail = '';
    public string $manualCity = 'Mumbai';
    public string $manualBudget = '₹75.0L';
    public string $manualPropertyType = '2 BHK';
    public string $manualRequirement = '';
    public ?int $manualProjectId = null;
    public ?int $manualCampaignId = null;
    public ?int $manualLeadSourceId = null;

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

        $this->loadColumnPreferences();

        if (auth()->check()) {
            $this->viewMode = GeneralSetting::getValue(auth()->id(), 'leads_view_mode', 'kanban') ?: 'kanban';
            $savedRange = GeneralSetting::getValue(auth()->id(), 'leads_analytics_range', 'month') ?: 'month';
            if (str_starts_with($savedRange, '{')) {
                $decoded = json_decode($savedRange, true);
                if (is_array($decoded)) {
                    $this->analyticsRange = $decoded['range'] ?? 'custom';
                    $this->analyticsCustomFrom = $decoded['from'] ?? null;
                    $this->analyticsCustomTo = $decoded['to'] ?? null;
                }
            } else {
                $this->analyticsRange = $savedRange;
            }
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
            if ($range === 'custom') {
                $this->persistCustomAnalyticsRange();
            } else {
                GeneralSetting::setValue(auth()->id(), 'leads_analytics_range', $range);
            }
        }
    }

    public function updatedAnalyticsCustomFrom(): void
    {
        $this->persistCustomAnalyticsRange();
    }

    public function updatedAnalyticsCustomTo(): void
    {
        $this->persistCustomAnalyticsRange();
    }

    #[On('date-range-applied')]
    public function handleDateRangeApplied(string|array $range = 'custom', ?string $from = null, ?string $to = null): void
    {
        if (is_array($range)) {
            $this->analyticsRange = $range['range'] ?? 'custom';
            $this->analyticsCustomFrom = $range['from'] ?? null;
            $this->analyticsCustomTo = $range['to'] ?? null;
        } else {
            $this->analyticsRange = $range ?: 'custom';
            $this->analyticsCustomFrom = $from;
            $this->analyticsCustomTo = $to;
        }
        $this->persistCustomAnalyticsRange();
    }

    protected function persistCustomAnalyticsRange(): void
    {
        if (auth()->check() && $this->analyticsRange === 'custom') {
            $payload = json_encode([
                'range' => 'custom',
                'from' => $this->analyticsCustomFrom,
                'to' => $this->analyticsCustomTo,
            ]);
            GeneralSetting::setValue(auth()->id(), 'leads_analytics_range', $payload);
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

        // Advanced Table Quick-Filter Pills
        if ($this->statusFilter === 'new') {
            $query->where('current_stage', 'new');
        } elseif ($this->statusFilter === 'assigned') {
            $query->where('current_stage', 'assigned');
        } elseif ($this->statusFilter === 'sla_breached') {
            $query->whereNull('first_response_at')->where('created_at', '<', now()->subMinutes(15));
        } elseif ($this->statusFilter === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif ($this->statusFilter === 'closed_won') {
            $query->where('current_stage', 'closed_won');
        }

        return $query;
    }

    protected function getAnalyticsData(): array
    {
        $scopedBase = $this->getScopedQuery();

        $analyticsQuery = (clone $scopedBase);
        if ($this->analyticsRange === 'today') {
            $analyticsQuery->where('created_at', '>=', now()->startOfDay());
        } elseif ($this->analyticsRange === 'yesterday') {
            $analyticsQuery->whereDate('created_at', now()->subDay()->toDateString());
        } elseif ($this->analyticsRange === 'week') {
            $analyticsQuery->where('created_at', '>=', now()->startOfWeek());
        } elseif ($this->analyticsRange === 'last7') {
            $analyticsQuery->where('created_at', '>=', now()->subDays(7));
        } elseif ($this->analyticsRange === 'month') {
            $analyticsQuery->where('created_at', '>=', now()->startOfMonth());
        } elseif ($this->analyticsRange === 'lastMonth') {
            $analyticsQuery->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]);
        } elseif ($this->analyticsRange === 'quarter') {
            $analyticsQuery->where('created_at', '>=', now()->startOfQuarter());
        } elseif ($this->analyticsRange === 'year') {
            $analyticsQuery->where('created_at', '>=', now()->startOfYear());
        } elseif ($this->analyticsRange === 'custom' && $this->analyticsCustomFrom && $this->analyticsCustomTo) {
            $analyticsQuery->whereBetween('created_at', [
                $this->analyticsCustomFrom . ' 00:00:00',
                $this->analyticsCustomTo . ' 23:59:59',
            ]);
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
            if (!empty($this->sortField)) {
                $tableQuery->orderBy($this->sortField, $this->sortDirection);
            } elseif ($this->sort_by === 'score_desc') {
                $tableQuery->orderByDesc('lead_score')->latest('id');
            } elseif ($this->sort_by === 'score_asc') {
                $tableQuery->orderBy('lead_score')->latest('id');
            } else {
                $tableQuery->latest();
            }

            $tableLeads = $tableQuery->paginate($this->perPage);
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

        $portalAccounts = \App\Models\PortalAccount::where('status', 'active')->get();
        $allProjects = Project::orderBy('name')->get();
        $allCampaigns = \App\Models\Campaign::orderBy('name')->get();
        $allLeadSources = LeadSource::orderBy('name')->get();

        return view('livewire.leads.lead-kanban', [
            'stageLeads' => $stageLeads,
            'stageCounts' => $stageCounts,
            'tableLeads' => $tableLeads,
            'analytics' => $analytics,
            'availableTeams' => $availableTeams,
            'portalAccounts' => $portalAccounts,
            'allProjects' => $allProjects,
            'allCampaigns' => $allCampaigns,
            'allLeadSources' => $allLeadSources,
        ])->layout('layouts.app');
    }

    public function tableColumns(): array
    {
        return [
            ['key' => 'lead_code', 'label' => 'Lead ID', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-ink'],
            ['key' => 'name', 'label' => 'Contact Name', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-bold text-ink'],
            ['key' => 'project_name', 'label' => 'Project', 'type' => 'text', 'priority' => 2],
            ['key' => 'current_stage', 'label' => 'Stage', 'type' => 'badge', 'sortable' => true, 'priority' => 1, 'badgeMap' => [
                'new' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'assigned' => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
                'contacted' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'connected' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'qualified' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'closed_won' => 'bg-green-100 text-green-800 border border-green-300',
                'closed_lost' => 'bg-red-50 text-red-700 border border-red-200',
            ]],
            ['key' => 'lead_source_name', 'label' => 'Source', 'type' => 'text', 'priority' => 2],
            ['key' => 'budget_formatted', 'label' => 'Budget', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-ink'],
            ['key' => 'assigned_to_user', 'label' => 'Assigned To', 'type' => 'avatar-stack', 'priority' => 1],
            ['key' => 'sla_badge', 'label' => 'SLA Status', 'type' => 'badge', 'priority' => 2, 'badgeMap' => [
                'SLA Met' => 'bg-green-50 text-green-700 border border-green-200',
                'SLA Breached' => 'bg-red-50 text-red-700 border border-red-200',
                'SLA Pending' => 'bg-amber-50 text-amber-700 border border-amber-200',
            ]],
            ['key' => 'lead_score', 'label' => 'Score', 'type' => 'progress', 'sortable' => true, 'priority' => 1],
            ['key' => 'created_at', 'label' => 'Created', 'type' => 'date', 'sortable' => true, 'priority' => 2],
            ['key' => 'actions', 'label' => 'Actions', 'type' => 'lead_actions', 'priority' => 1],
        ];
    }

    public function quickFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All Leads'],
            ['key' => 'new', 'label' => 'New'],
            ['key' => 'assigned', 'label' => 'Assigned'],
            ['key' => 'sla_breached', 'label' => 'SLA Breached'],
            ['key' => 'unassigned', 'label' => 'Unassigned'],
            ['key' => 'closed_won', 'label' => 'Closed Won'],
        ];
    }

    public function openPullLeadsModal(): void
    {
        $this->showPullLeadsModal = true;
        $this->pullSummary = null;
        $firstAccount = \App\Models\PortalAccount::where('type', 'meta')->first();
        if ($firstAccount && !$this->selectedPortalAccountId) {
            $this->selectedPortalAccountId = $firstAccount->id;
        }
    }

    public function closePullLeadsModal(): void
    {
        $this->showPullLeadsModal = false;
        $this->pullSummary = null;
    }

    public function pullMetaLeads(): void
    {
        $this->isPulling = true;
        $this->pullSummary = null;

        try {
            $account = null;
            if ($this->selectedPortalAccountId) {
                $account = \App\Models\PortalAccount::with(['credentials', 'formMappings'])->find($this->selectedPortalAccountId);
            }

            $pageId = $account?->getCredential('page_id') ?? '';
            $accessToken = $this->customPageAccessToken ?: ($account?->getCredential('access_token') ?? '');
            $formId = $this->customFormId;

            if (empty($accessToken)) {
                $this->dispatch('toast', type: 'error', message: 'No Page Access Token found. Please enter or configure credentials in Settings > Integrations.');
                $this->isPulling = false;
                return;
            }

            // Determine Target Form IDs
            $targetFormIds = [];
            if (!empty($formId)) {
                $targetFormIds[] = $formId;
            } elseif ($account && $account->formMappings->isNotEmpty()) {
                $targetFormIds = $account->formMappings->pluck('form_id')->toArray();
            } elseif (!empty($pageId)) {
                // Discover active forms for page from Graph API
                $formsResponse = \Illuminate\Support\Facades\Http::timeout(10)->get("https://graph.facebook.com/v19.0/{$pageId}/leadgen_forms", [
                    'access_token' => $accessToken,
                    'fields' => 'id,name,status',
                ]);

                if ($formsResponse->successful() && is_array($formsResponse->json('data'))) {
                    foreach ($formsResponse->json('data') as $f) {
                        $targetFormIds[] = $f['id'];
                    }
                }
            }

            if (empty($targetFormIds)) {
                $this->dispatch('toast', type: 'error', message: 'No Leadgen Form ID found. Please specify a Form ID or configure Form Mappings.');
                $this->isPulling = false;
                return;
            }

            $totalFetched = 0;
            $totalCreated = 0;
            $totalDuplicates = 0;
            $totalErrors = 0;

            $metaMapper = new \App\Support\LeadMappers\MetaLeadMapper();
            $client = \App\Models\Client::first();
            $clientId = $client?->id ?: 1;
            $leadSource = LeadSource::firstOrCreate(['name' => 'meta']);

            foreach ($targetFormIds as $currentFormId) {
                $leadsResponse = \Illuminate\Support\Facades\Http::timeout(15)->get("https://graph.facebook.com/v19.0/{$currentFormId}/leads", [
                    'access_token' => $accessToken,
                    'limit' => $this->pullLimit ?: 25,
                    'fields' => 'id,created_time,field_data,ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name,form_id,is_organic',
                ]);

                if (!$leadsResponse->successful()) {
                    $totalErrors++;
                    continue;
                }

                $leadsData = $leadsResponse->json('data') ?? [];
                $totalFetched += count($leadsData);

                // Match form mapping for project & campaign assignment
                $mapping = $account ? \App\Models\LeadFormMapping::where('portal_account_id', $account->id)->where('form_id', $currentFormId)->first() : null;
                $projectId = $this->syncProjectId ?: ($mapping?->project_id ?: (Project::where('client_id', $clientId)->first()?->id ?: 1));
                $campaignId = $this->syncCampaignId ?: ($mapping?->campaign_id ?: null);

                foreach ($leadsData as $rawLead) {
                    try {
                        $rawLead['form_id'] = $currentFormId;
                        $mapped = $metaMapper->map($rawLead);

                        $mobile = $mapped['mobile'] ?: '9876543210';
                        $email = $mapped['email'] ?: 'meta_user@example.com';

                        // 90-Day Duplicate Check
                        $existingLead = Lead::where('client_id', $clientId)
                            ->where('created_at', '>=', now()->subDays(90))
                            ->where(function ($q) use ($mobile, $email) {
                                $q->where('mobile', $mobile);
                                if ($email && $email !== 'meta_user@example.com') {
                                    $q->orWhere('email', $email);
                                }
                            })
                            ->first();

                        if ($existingLead) {
                            $totalDuplicates++;
                            continue;
                        }

                        // Create Lead
                        $lead = Lead::create([
                            'lead_code' => Lead::generateUniqueLeadCode(),
                            'client_id' => $clientId,
                            'project_id' => $projectId,
                            'campaign_id' => $campaignId,
                            'lead_source_id' => $leadSource->id,
                            'name' => $mapped['name'] ?? 'Meta Lead',
                            'mobile' => $mobile,
                            'email' => $email,
                            'city' => $mapped['city'] ?? 'Mumbai',
                            'budget' => $mapped['budget'] ?? '₹75.0L',
                            'property_type' => $mapped['property_type'] ?? '2 BHK',
                            'requirement' => $mapped['requirement'] ?? 'Manual sync from Meta Ads Form #' . $currentFormId,
                            'status' => 'new',
                            'current_stage' => 'new',
                            'assigned_to' => null,
                        ]);

                        // Save Metadata
                        LeadMetadata::create([
                            'lead_id' => $lead->id,
                            'key' => 'meta_leadgen_id',
                            'value' => $rawLead['id'] ?? '',
                        ]);

                        LeadMetadata::create([
                            'lead_id' => $lead->id,
                            'key' => 'raw_json',
                            'value' => json_encode($rawLead),
                        ]);

                        // Dispatch Event for Credit Reservation / Auto Distribution
                        event(new \App\Events\LeadCreated($lead));

                        $totalCreated++;
                    } catch (\Throwable $e) {
                        $totalErrors++;
                    }
                }
            }

            $this->pullSummary = [
                'fetched' => $totalFetched,
                'created' => $totalCreated,
                'duplicates' => $totalDuplicates,
                'errors' => $totalErrors,
            ];

            $this->dispatch('toast', type: 'success', title: 'Sync Completed', message: "Pulled {$totalCreated} new leads from Meta ({$totalDuplicates} duplicates skipped).");
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Sync failed: ' . $e->getMessage());
        } finally {
            $this->isPulling = false;
        }
    }

    public function createManualLead(): void
    {
        $this->validate([
            'manualName' => 'required|string|max:255',
            'manualMobile' => 'required|string|min:10',
            'manualEmail' => 'nullable|email',
            'manualProjectId' => 'required|exists:projects,id',
        ], [
            'manualName.required' => 'Lead Name is required.',
            'manualMobile.required' => 'Mobile number is required.',
            'manualProjectId.required' => 'Please select an associated project.',
        ]);

        $client = \App\Models\Client::first();
        $clientId = $client?->id ?: 1;
        $leadSourceId = $this->manualLeadSourceId ?: (LeadSource::firstOrCreate(['name' => 'direct'])->id);

        $mobile = preg_replace('/[^0-9]/', '', $this->manualMobile);
        $email = $this->manualEmail ?: null;

        // 90-Day Duplicate Check
        $existingLead = Lead::where('client_id', $clientId)
            ->where('created_at', '>=', now()->subDays(90))
            ->where(function ($q) use ($mobile, $email) {
                $q->where('mobile', $mobile);
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->first();

        if ($existingLead) {
            $this->dispatch('toast', type: 'warning', title: 'Duplicate Detected', message: "Lead with mobile '{$mobile}' already exists (#{$existingLead->lead_code}).");
            return;
        }

        $lead = Lead::create([
            'lead_code' => Lead::generateUniqueLeadCode(),
            'client_id' => $clientId,
            'project_id' => $this->manualProjectId,
            'campaign_id' => $this->manualCampaignId ?: null,
            'lead_source_id' => $leadSourceId,
            'name' => $this->manualName,
            'mobile' => $mobile,
            'email' => $email ?: 'direct_entry@leadpanther.com',
            'city' => $this->manualCity ?: 'Mumbai',
            'budget' => $this->manualBudget ?: '₹75.0L',
            'property_type' => $this->manualPropertyType ?: '2 BHK',
            'requirement' => $this->manualRequirement ?: 'Manual lead entry via Lead Manager',
            'status' => 'new',
            'current_stage' => 'new',
            'assigned_to' => null,
        ]);

        event(new \App\Events\LeadCreated($lead));

        $this->reset([
            'manualName',
            'manualMobile',
            'manualEmail',
            'manualRequirement',
            'manualProjectId',
            'manualCampaignId',
        ]);

        $this->showPullLeadsModal = false;
        $this->dispatch('toast', type: 'success', message: "Lead '{$lead->name}' (#{$lead->lead_code}) added to pipeline.");
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
