<?php

namespace App\Livewire\Leads;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Organization;
use App\Models\User;
use App\Models\LeadSource;
use App\Models\Campaign;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Models\LeadStatusHistory;
use App\Models\LeadAssignment;
use App\Models\CreditWallet;
use App\Models\CreditTransaction;
use App\Models\LeadReplacement;
use App\Models\Followup;
use App\Models\SiteVisit;
use App\Models\Booking;
use App\Models\GeneralSetting;
use App\Models\PortalAccount;
use App\Models\LeadFormMapping;
use App\Models\ReplacementReason;
use App\Models\AuditLog;
use App\Events\LeadStageChanged;
use App\Livewire\Concerns\HasAdvancedTable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadKanban extends Component
{
    use HasAdvancedTable;

    // Global Filters (URL bound)
    #[Url] public string $search = '';
    #[Url] public string $statusFilter = 'all';
    #[Url] public string $stageFilter = '';
    #[Url] public string $project = '';
    #[Url] public string $campaign = '';
    #[Url] public string $filterOrganizationId = '';
    #[Url] public string $partner = '';
    #[Url] public string $executive = '';
    #[Url] public string $team = '';
    #[Url] public string $source = '';
    #[Url] public string $leadType = '';
    #[Url] public string $date_range = 'month';
    #[Url] public bool $sla_breached = false;
    #[Url] public string $sort_by = 'created_desc';

    // View & Analytics State
    public string $viewMode = 'kanban'; // 'kanban' | 'table'
    public string $analyticsRange = 'month';
    public ?string $analyticsCustomFrom = null;
    public ?string $analyticsCustomTo = null;
    public string $breakdownTab = 'project'; // 'overview' | 'project' | 'source' | 'executive' | 'team' | 'organization'
    public string $activeKpiGroup = 'all'; // 'all' | 'intake' | 'assignment' | 'progress' | 'conversion' | 'quality'

    // Breakdown Advanced Table State
    #[Url] public string $breakdownSearch = '';
    public string $breakdownSortField = 'total_leads';
    public string $breakdownSortDirection = 'desc';
    public int $breakdownPerPage = 10;

    public bool $otherColumnExpanded = false;

    // Terminal stage confirmation modal state
    public bool $showConfirmModal = false;
    public ?int $pendingLeadId = null;
    public string $pendingToStage = '';
    public string $pendingFromStage = '';

    // Per-column limits for Kanban view
    public array $stageLimits = [];

    // Manual Lead Ingestion & Meta Pull Modal State
    public bool $showPullLeadsModal = false;
    public string $pullMode = 'meta'; // 'meta' | 'manual'
    public ?int $selectedPortalAccountId = null;
    public string $selectedFormOption = '';
    public string $customFormId = '';
    public string $customPageAccessToken = '';
    public int $pullLimit = 25;
    public ?int $syncProjectId = null;
    public ?int $syncCampaignId = null;
    public bool $isPulling = false;
    public ?array $pullSummary = null;

    // Lead Manual / Bulk Assignment Modal State
    public bool $showAssignModal = false;
    public array $selectedLeadIds = [];
    public ?int $assignTeamId = null;
    public ?int $assignUserId = null;

    // Return Lead to Super Admin (Replacement Request) State
    public bool $showReturnLeadModal = false;
    public ?int $returnLeadId = null;
    public ?Lead $returnLeadModel = null;
    public ?int $returnReasonId = null;
    public string $returnRemarks = '';

    // Single Manual Lead Ingestion State
    public string $manualName = '';
    public string $manualMobile = '';
    public string $manualEmail = '';
    public string $manualCity = '';
    public string $manualBudget = '';
    public string $manualPropertyType = '';
    public string $manualRequirement = '';
    public ?int $manualProjectId = null;
    public ?int $manualCampaignId = null;
    public ?int $manualLeadSourceId = null;

    // 13 Complete Lifecycle Stages + Supported Active Stages
    public array $mainStages = [
        'new' => 'Lead Received',
        'duplicate' => 'Duplicate Check',
        'pending_credit' => 'Credit Check',
        'assigned' => 'Lead Assigned',
        'notification_sent' => 'Notification Sent',
        'first_response' => 'First Response',
        'contacted' => 'Contacted',
        'interested' => 'Interested',
        'follow-up' => 'Follow-up',
        'meeting' => 'Meeting',
        'site_visit' => 'Site Visit',
        'negotiation' => 'Negotiation',
        'booking' => 'Booking',
        'payment' => 'Payment',
        'closed_won' => 'Closed Won',
    ];

    public array $otherStages = [
        'closed_lost' => 'Closed Lost',
        'wrong_number' => 'Wrong Number',
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

            // Sync legacy partner param if passed
            if (!empty($this->partner) && empty($this->filterOrganizationId)) {
                $this->filterOrganizationId = $this->partner;
            }

            // Prevent browser autofill or stale query params from filling logged-in user's email into search
            if (!empty($this->search)) {
                $userEmail = strtolower(trim(auth()->user()->email ?? ''));
                if ($userEmail !== '' && strtolower(trim($this->search)) === $userEmail) {
                    $this->search = '';
                }
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

    public function updatedSearch(): void
    {
        if (auth()->check() && !empty($this->search)) {
            $userEmail = strtolower(trim(auth()->user()->email ?? ''));
            if ($userEmail !== '' && strtolower(trim($this->search)) === $userEmail) {
                $this->search = '';
                return;
            }
        }
        $this->resetPage();
    }

    public function updatedProject(): void { $this->resetPage(); }
    public function updatedCampaign(): void { $this->resetPage(); }
    public function updatedFilterOrganizationId(): void { $this->resetPage(); }
    public function updatedExecutive(): void { $this->resetPage(); }
    public function updatedTeam(): void { $this->resetPage(); }
    public function updatedSource(): void { $this->resetPage(); }
    public function updatedLeadType(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedStageFilter(): void { $this->resetPage(); }
    public function updatedSlaBreached(): void { $this->resetPage(); }
    public function updatedSortBy(): void { $this->resetPage(); }
    public function updatedBreakdownSearch(): void { $this->resetPage('breakdownPage'); }
    public function updatedBreakdownTab(): void { $this->resetPage('breakdownPage'); }

    public function sortBreakdownBy(string $field): void
    {
        if ($this->breakdownSortField === $field) {
            $this->breakdownSortDirection = $this->breakdownSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->breakdownSortField = $field;
            $this->breakdownSortDirection = in_array($field, ['total_leads', 'conversion_rate']) ? 'desc' : 'asc';
        }
        $this->resetPage('breakdownPage');
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search',
            'statusFilter',
            'stageFilter',
            'project',
            'campaign',
            'partner',
            'filterOrganizationId',
            'executive',
            'team',
            'source',
            'leadType',
            'sla_breached',
        ]);
        $this->analyticsRange = 'month';
        $this->analyticsCustomFrom = null;
        $this->analyticsCustomTo = null;
        $this->sort_by = 'created_desc';
        $this->resetPage();
        $this->dispatch('toast', type: 'info', message: 'All filters reset.');
    }

    public function applyKpiFilter(string $status, ?string $stage = null): void
    {
        $this->statusFilter = $status;
        $this->stageFilter = $stage ?? '';
        $this->sla_breached = ($status === 'sla_breached');
        $this->resetPage();
    }

    public function filterByLifecycleStage(string $stageKey): void
    {
        $this->stageFilter = $stageKey;
        $this->statusFilter = 'all';
        $this->resetPage();
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

        // If moving to terminal/exception stage
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

    /**
     * Resolves current and comparison previous date boundaries.
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon, 4: string}
     */
    public function getDateBounds(): array
    {
        $range = strtolower($this->analyticsRange ?: 'month');

        if ($range === 'today') {
            $start = Carbon::today()->startOfDay();
            $end = Carbon::today()->endOfDay();
            $prevStart = Carbon::yesterday()->startOfDay();
            $prevEnd = Carbon::yesterday()->endOfDay();
            $periodLabel = 'vs yesterday';
        } elseif ($range === 'yesterday') {
            $start = Carbon::yesterday()->startOfDay();
            $end = Carbon::yesterday()->endOfDay();
            $prevStart = Carbon::yesterday()->subDay()->startOfDay();
            $prevEnd = Carbon::yesterday()->subDay()->endOfDay();
            $periodLabel = 'vs day before';
        } elseif (in_array($range, ['7_days', 'last7', 'week'])) {
            $start = Carbon::now()->subDays(6)->startOfDay();
            $end = Carbon::now()->endOfDay();
            $prevStart = Carbon::now()->subDays(13)->startOfDay();
            $prevEnd = Carbon::now()->subDays(7)->endOfDay();
            $periodLabel = 'vs prior 7 days';
        } elseif ($range === 'custom' && $this->analyticsCustomFrom && $this->analyticsCustomTo) {
            $start = Carbon::parse($this->analyticsCustomFrom)->startOfDay();
            $end = Carbon::parse($this->analyticsCustomTo)->endOfDay();
            $days = max(1, (int) $start->diffInDays($end) + 1);
            $prevEnd = (clone $start)->subSecond();
            $prevStart = (clone $start)->subDays($days)->startOfDay();
            $periodLabel = "vs prior {$days}d";
        } elseif ($range === 'quarter') {
            $start = Carbon::now()->startOfQuarter();
            $end = Carbon::now()->endOfQuarter();
            $prevStart = Carbon::now()->subQuarter()->startOfQuarter();
            $prevEnd = Carbon::now()->subQuarter()->endOfQuarter();
            $periodLabel = 'vs prior quarter';
        } elseif ($range === 'year') {
            $start = Carbon::now()->startOfYear();
            $end = Carbon::now()->endOfYear();
            $prevStart = Carbon::now()->subYear()->startOfYear();
            $prevEnd = Carbon::now()->subYear()->endOfYear();
            $periodLabel = 'vs prior year';
        } else {
            // Default: current month
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
            $prevStart = Carbon::now()->subMonth()->startOfMonth();
            $prevEnd = Carbon::now()->subMonth()->endOfMonth();
            $periodLabel = 'vs prior month';
        }

        return [$start, $end, $prevStart, $prevEnd, $periodLabel];
    }

    public function getIsBuilderProperty(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return \App\Support\NavigationConfig::getRoleKey($user) === 'builder'
            || $user->hasRole('builder')
            || $user->hasRole('Builder')
            || $user->hasRole('Builder/Developer')
            || $user->hasRole('builder-admin');
    }

    public function getIsSalesExecutiveProperty(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return \App\Support\NavigationConfig::getRoleKey($user) === 'sales-executive'
            || $user->hasRole('sales-executive')
            || $user->hasRole('Sales Executive')
            || $user->hasRole('Executive')
            || $user->hasRole('sales');
    }

    public function getBuilderLeadKpisProperty(): array
    {
        $query = $this->getScopedQuery();
        $total = (clone $query)->count();
        $new = (clone $query)->whereIn('current_stage', ['new', 'lead_received'])->count();
        $assigned = (clone $query)->whereNotNull('assigned_to')->count();
        $unassigned = (clone $query)->whereNull('assigned_to')->count();
        $inProgress = (clone $query)->whereIn('current_stage', ['contacted', 'connected', 'interested', 'qualified', 'site_visit', 'negotiation'])->count();

        $allOrgLeadIds = (clone $query)->pluck('id')->toArray();
        $followupsDue = Followup::whereIn('lead_id', $allOrgLeadIds)
            ->where('due_at', '<=', now()->endOfDay())
            ->where('status', '!=', 'completed')
            ->count();

        $closedWon = (clone $query)->whereIn('current_stage', ['closed_won', 'booking'])->count();
        $lost = (clone $query)->whereIn('current_stage', ['closed_lost', 'lost', 'wrong_number'])->count();

        return [
            'total_leads' => ['label' => 'Total Leads', 'value' => $total, 'icon' => 'users', 'color' => 'blue', 'filter' => 'all'],
            'new_leads' => ['label' => 'New Leads', 'value' => $new, 'icon' => 'sparkles', 'color' => 'emerald', 'filter' => 'new'],
            'assigned_leads' => ['label' => 'Assigned Leads', 'value' => $assigned, 'icon' => 'user-check', 'color' => 'indigo', 'filter' => 'assigned'],
            'unassigned_leads' => ['label' => 'Unassigned Leads', 'value' => $unassigned, 'icon' => 'clock', 'color' => 'amber', 'filter' => 'unassigned'],
            'in_progress_leads' => ['label' => 'In Progress', 'value' => $inProgress, 'icon' => 'trending-up', 'color' => 'purple', 'filter' => 'in_progress'],
            'followups_due' => ['label' => 'Follow-ups Due', 'value' => $followupsDue, 'icon' => 'calendar', 'color' => 'amber', 'filter' => 'followups'],
            'closed_won' => ['label' => 'Closed Won', 'value' => $closedWon, 'icon' => 'trophy', 'color' => 'green', 'filter' => 'closed_won'],
            'lost_leads' => ['label' => 'Lost Leads', 'value' => $lost, 'icon' => 'x-circle', 'color' => 'rose', 'filter' => 'lost'],
        ];
    }

    /**
     * Centralized query builder for leads respecting all role scoping and global filters.
     */
    protected function getScopedQuery(?Carbon $start = null, ?Carbon $end = null)
    {
        $user = auth()->user();
        $query = Lead::withoutGlobalScopes()
            ->with([
                'client.organization',
                'campaign.project',
                'leadSource',
                'assignedTo.salesTeamMembers.salesTeam',
                'metadata',
                'siteVisits',
                'followups',
                'replacements',
            ]);

        // Role Scoping
        if ($user) {
            if ($this->isSalesExecutive) {
                $query->where('assigned_to', $user->id);
            } elseif ($user->hasRole('channel-partner') || $user->hasRole('Channel Partner')) {
                $query->whereHas('assignedTo', function ($q) use ($user) {
                    $q->where('organization_id', $user->organization_id);
                });
            } elseif ($user->hasRole('client')) {
                $query->whereHas('client', function ($q) use ($user) {
                    $q->where('organization_id', $user->organization_id);
                });
            } elseif ($this->isBuilder) {
                if ($user->organization_id) {
                    $orgId = $user->organization_id;
                    $orgUserIds = User::where('organization_id', $orgId)->pluck('id')->toArray();
                    $query->where(function ($q) use ($orgId, $orgUserIds) {
                        $q->whereHas('client', function ($sq) use ($orgId) {
                            $sq->where('organization_id', $orgId);
                        })->orWhereHas('assignedTo', function ($sq) use ($orgId) {
                            $sq->where('organization_id', $orgId);
                        })->orWhereIn('client_id', $orgUserIds)
                          ->orWhereIn('assigned_to', $orgUserIds);
                    });
                } else {
                    $query->where(function ($q) use ($user) {
                        $q->where('client_id', $user->id)
                          ->orWhere('assigned_to', $user->id);
                    });
                }
            }
        }

        // Date Range
        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        // Search
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('lead_code', 'like', '%' . $this->search . '%')
                  ->orWhere('mobile', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhere('city', 'like', '%' . $this->search . '%');
            });
        }

        // Project Filter
        if (!empty($this->project)) {
            $query->whereHas('campaign', function ($q) {
                $q->where('project_id', $this->project);
            });
        }

        // Campaign Filter
        if (!empty($this->campaign)) {
            $query->where('campaign_id', $this->campaign);
        }

        // Organization Filter
        $orgId = $this->filterOrganizationId ?: $this->partner;
        if (!empty($orgId)) {
            $orgUserIds = User::where('organization_id', $orgId)->pluck('id');
            $query->whereIn('client_id', $orgUserIds);
        }

        // Assigned Agent Filter
        if (!empty($this->executive)) {
            $query->where('assigned_to', $this->executive);
        }

        // Team Filter
        if (!empty($this->team)) {
            $query->whereIn('assigned_to', function ($sub) {
                $sub->select('user_id')
                    ->from('sales_team_members')
                    ->where('sales_team_id', $this->team);
            });
        }

        // Lead Source Filter
        if (!empty($this->source)) {
            $query->where('lead_source_id', $this->source);
        }

        // Lead Type (Property Type)
        if (!empty($this->leadType)) {
            $query->where('property_type', $this->leadType);
        }

        // SLA Breached Toggle
        if ($this->sla_breached) {
            $query->whereNull('first_response_at')
                  ->where('created_at', '<', now()->subMinutes(15));
        }

        // Stage Filter
        if (!empty($this->stageFilter)) {
            $query->where('current_stage', $this->stageFilter);
        }

        // Status Filter
        if (!empty($this->statusFilter) && $this->statusFilter !== 'all') {
            $sf = strtolower($this->statusFilter);
            if ($sf === 'new') {
                $query->where(fn($q) => $q->where('status', 'new')->orWhere('current_stage', 'new'));
            } elseif ($sf === 'assigned') {
                $query->whereNotNull('assigned_to');
            } elseif ($sf === 'unassigned') {
                $query->whereNull('assigned_to');
            } elseif ($sf === 'contacted') {
                $query->whereIn('current_stage', ['contacted', 'connected']);
            } elseif ($sf === 'interested') {
                $query->where(fn($q) => $q->where('current_stage', 'interested')->orWhere('lead_score', '>=', 60));
            } elseif ($sf === 'qualified') {
                $query->where('current_stage', 'qualified');
            } elseif ($sf === 'site_visit') {
                $query->whereIn('current_stage', ['site_visit', 'Site Visit']);
            } elseif ($sf === 'negotiation') {
                $query->whereIn('current_stage', ['negotiation', 'proposal']);
            } elseif ($sf === 'booking') {
                $query->whereIn('current_stage', ['booking', 'payment']);
            } elseif ($sf === 'closed_won') {
                $query->where(fn($q) => $q->where('current_stage', 'closed_won')->orWhere('status', 'closed_won'));
            } elseif ($sf === 'closed_lost') {
                $query->where(fn($q) => $q->whereIn('current_stage', ['closed_lost', 'lost'])->orWhereIn('status', ['closed_lost', 'lost']));
            } elseif ($sf === 'duplicate') {
                $query->where(fn($q) => $q->where('status', 'duplicate')->orWhere('current_stage', 'duplicate'));
            } elseif ($sf === 'pending_credit') {
                $query->where('current_stage', 'pending_credit');
            } elseif ($sf === 'sla_breached') {
                $query->whereNull('first_response_at')->where('created_at', '<', now()->subMinutes(15));
            } elseif ($sf === 'pending_response') {
                $query->whereNotNull('assigned_to')->whereNull('first_response_at');
            } elseif ($sf === 'replacement_eligible') {
                $query->where(fn($q) => $q->where('replacement_status', 'eligible')->orWhereIn('id', LeadReplacement::pluck('lead_id')));
            } elseif ($sf === 'replaced') {
                $query->where(fn($q) => $q->where('current_stage', 'replaced')->orWhereIn('id', LeadReplacement::where('status', 'approved')->pluck('lead_id')));
            } else {
                $query->where(function ($q) use ($sf) {
                    $q->where('current_stage', $sf)->orWhere('status', $sf);
                });
            }
        }

        return $query;
    }

    /**
     * Calculate percentage delta compared to previous period.
     */
    protected function calculateDelta(float|int $current, float|int $previous): array
    {
        if ($previous == 0) {
            if ($current == 0) {
                return ['delta' => '0%', 'isPositive' => null];
            }
            return ['delta' => '+100%', 'isPositive' => true];
        }

        $diff = $current - $previous;
        $pct = round(($diff / $previous) * 100, 1);
        $formatted = ($pct > 0 ? '+' : '') . $pct . '%';

        return [
            'delta' => $formatted,
            'isPositive' => $pct >= 0,
        ];
    }

    /**
     * Computes the 5 logical groups of Lead Overview KPI cards with comparisons.
     */
    public function getLeadKpisProperty(): array
    {
        [$start, $end, $prevStart, $prevEnd, $periodLabel] = $this->getDateBounds();
        $todayStr = now()->toDateString();

        // 1. Current period aggregated lead metrics
        $currStats = $this->getScopedQuery($start, $end)
            ->selectRaw("
                COUNT(*) as total_leads,
                COUNT(CASE WHEN DATE(created_at) = '{$todayStr}' THEN 1 END) as leads_today,
                COUNT(CASE WHEN status = 'new' OR current_stage = 'new' THEN 1 END) as new_leads,
                COUNT(CASE WHEN status = 'duplicate' OR current_stage = 'duplicate' THEN 1 END) as duplicate_leads,
                COUNT(CASE WHEN assigned_to IS NOT NULL THEN 1 END) as assigned_leads,
                COUNT(CASE WHEN assigned_to IS NULL THEN 1 END) as unassigned_leads,
                COUNT(CASE WHEN current_stage IN ('contacted', 'connected') THEN 1 END) as contacted_leads,
                COUNT(CASE WHEN current_stage IN ('interested', 'site_visit', 'negotiation', 'booking', 'closed_won') OR lead_score >= 60 THEN 1 END) as interested_leads,
                COUNT(CASE WHEN current_stage IN ('follow-up', 'followup') THEN 1 END) as followups_leads,
                COUNT(CASE WHEN current_stage IN ('site_visit', 'Site Visit') THEN 1 END) as site_visits_leads,
                COUNT(CASE WHEN current_stage IN ('negotiation', 'proposal') THEN 1 END) as negotiations_leads,
                COUNT(CASE WHEN current_stage IN ('booking', 'payment') THEN 1 END) as bookings_leads,
                COUNT(CASE WHEN current_stage = 'closed_won' OR status = 'closed_won' THEN 1 END) as closed_won_leads,
                COUNT(CASE WHEN current_stage IN ('lost', 'closed_lost', 'dead', 'rejected') OR status IN ('lost', 'closed_lost', 'rejected') THEN 1 END) as lost_leads,
                COUNT(CASE WHEN first_response_at IS NULL AND created_at <= ? THEN 1 END) as sla_breached_leads,
                COUNT(CASE WHEN assigned_to IS NOT NULL AND first_response_at IS NULL THEN 1 END) as pending_first_response
            ", [Carbon::now()->subMinutes(15)])
            ->first();

        // 2. Previous period aggregated lead metrics
        $prevStats = $this->getScopedQuery($prevStart, $prevEnd)
            ->selectRaw("
                COUNT(*) as total_leads,
                COUNT(CASE WHEN DATE(created_at) = DATE(?) THEN 1 END) as leads_today,
                COUNT(CASE WHEN status = 'new' OR current_stage = 'new' THEN 1 END) as new_leads,
                COUNT(CASE WHEN status = 'duplicate' OR current_stage = 'duplicate' THEN 1 END) as duplicate_leads,
                COUNT(CASE WHEN assigned_to IS NOT NULL THEN 1 END) as assigned_leads,
                COUNT(CASE WHEN assigned_to IS NULL THEN 1 END) as unassigned_leads,
                COUNT(CASE WHEN current_stage IN ('contacted', 'connected') THEN 1 END) as contacted_leads,
                COUNT(CASE WHEN current_stage IN ('interested', 'site_visit', 'negotiation', 'booking', 'closed_won') OR lead_score >= 60 THEN 1 END) as interested_leads,
                COUNT(CASE WHEN current_stage IN ('follow-up', 'followup') THEN 1 END) as followups_leads,
                COUNT(CASE WHEN current_stage IN ('site_visit', 'Site Visit') THEN 1 END) as site_visits_leads,
                COUNT(CASE WHEN current_stage IN ('negotiation', 'proposal') THEN 1 END) as negotiations_leads,
                COUNT(CASE WHEN current_stage IN ('booking', 'payment') THEN 1 END) as bookings_leads,
                COUNT(CASE WHEN current_stage = 'closed_won' OR status = 'closed_won' THEN 1 END) as closed_won_leads,
                COUNT(CASE WHEN current_stage IN ('lost', 'closed_lost', 'dead', 'rejected') OR status IN ('lost', 'closed_lost', 'rejected') THEN 1 END) as lost_leads,
                COUNT(CASE WHEN first_response_at IS NULL AND created_at <= ? THEN 1 END) as sla_breached_leads,
                COUNT(CASE WHEN assigned_to IS NOT NULL AND first_response_at IS NULL THEN 1 END) as pending_first_response
            ", [$prevStart, Carbon::now()->subDays(max(1, $start->diffInDays($end) + 1))->subMinutes(15)])
            ->first();

        $currReplacements = LeadReplacement::whereBetween('created_at', [$start, $end])->count();
        $prevReplacements = LeadReplacement::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $totalCurr = (int) ($currStats->total_leads ?? 0);
        $totalPrev = (int) ($prevStats->total_leads ?? 0);
        $wonCurr = (int) ($currStats->closed_won_leads ?? 0);
        $wonPrev = (int) ($prevStats->closed_won_leads ?? 0);

        $convRateCurr = $totalCurr > 0 ? round(($wonCurr / $totalCurr) * 100, 1) : 0;
        $convRatePrev = $totalPrev > 0 ? round(($wonPrev / $totalPrev) * 100, 1) : 0;

        $buildKpi = function (string $id, string $title, int|float $curr, int|float $prev, string $icon, string $color, string $filterStatus, ?string $filterStage = null, string $suffix = '') use ($periodLabel) {
            $deltaInfo = $this->calculateDelta($curr, $prev);
            return [
                'id' => $id,
                'title' => $title,
                'value' => number_format($curr) . $suffix,
                'curr' => $curr,
                'prev' => $prev,
                'delta' => $deltaInfo['delta'],
                'isPositive' => $deltaInfo['isPositive'],
                'period' => $periodLabel,
                'icon' => $icon,
                'color' => $color,
                'status' => $filterStatus,
                'stage' => $filterStage,
            ];
        };

        return [
            'intake' => [
                $buildKpi('total_leads', 'Total Leads', $totalCurr, $totalPrev, 'users', 'blue', 'all'),
                $buildKpi('new_leads', 'New Leads', (int) ($currStats->new_leads ?? 0), (int) ($prevStats->new_leads ?? 0), 'sparkles', 'blue', 'new'),
                $buildKpi('leads_today', 'Leads Today', (int) ($currStats->leads_today ?? 0), (int) ($prevStats->leads_today ?? 0), 'calendar', 'emerald', 'all'),
                $buildKpi('duplicate_leads', 'Duplicate Leads', (int) ($currStats->duplicate_leads ?? 0), (int) ($prevStats->duplicate_leads ?? 0), 'copy', 'neutral', 'duplicate'),
            ],
            'assignment' => [
                $buildKpi('assigned_leads', 'Assigned Leads', (int) ($currStats->assigned_leads ?? 0), (int) ($prevStats->assigned_leads ?? 0), 'user-check', 'emerald', 'assigned'),
                $buildKpi('unassigned_leads', 'Unassigned Leads', (int) ($currStats->unassigned_leads ?? 0), (int) ($prevStats->unassigned_leads ?? 0), 'user-x', 'amber', 'unassigned'),
                $buildKpi('pending_assignment', 'Pending Assignment', (int) ($currStats->unassigned_leads ?? 0), (int) ($prevStats->unassigned_leads ?? 0), 'clock', 'amber', 'unassigned'),
                $buildKpi('distribution_failed', 'Distribution Failed', 0, 0, 'alert-triangle', 'rose', 'unassigned'),
            ],
            'progress' => [
                $buildKpi('contacted_leads', 'Contacted Leads', (int) ($currStats->contacted_leads ?? 0), (int) ($prevStats->contacted_leads ?? 0), 'phone', 'blue', 'contacted'),
                $buildKpi('interested_leads', 'Interested Leads', (int) ($currStats->interested_leads ?? 0), (int) ($prevStats->interested_leads ?? 0), 'thumbs-up', 'emerald', 'interested'),
                $buildKpi('followups', 'Follow-ups', (int) ($currStats->followups_leads ?? 0), (int) ($prevStats->followups_leads ?? 0), 'bell', 'amber', 'all', 'follow-up'),
                $buildKpi('site_visits', 'Site Visits', (int) ($currStats->site_visits_leads ?? 0), (int) ($prevStats->site_visits_leads ?? 0), 'map-pin', 'purple', 'all', 'site_visit'),
                $buildKpi('negotiations', 'Negotiations', (int) ($currStats->negotiations_leads ?? 0), (int) ($prevStats->negotiations_leads ?? 0), 'file-text', 'blue', 'all', 'negotiation'),
            ],
            'conversion' => [
                $buildKpi('bookings', 'Bookings', (int) ($currStats->bookings_leads ?? 0), (int) ($prevStats->bookings_leads ?? 0), 'check-square', 'emerald', 'all', 'booking'),
                $buildKpi('closed_won', 'Closed Won', $wonCurr, $wonPrev, 'trophy', 'emerald', 'closed_won'),
                $buildKpi('lost_leads', 'Lost Leads', (int) ($currStats->lost_leads ?? 0), (int) ($prevStats->lost_leads ?? 0), 'x-circle', 'rose', 'closed_lost'),
                [
                    'id' => 'conversion_rate',
                    'title' => 'Conversion Rate',
                    'value' => $convRateCurr . '%',
                    'curr' => $convRateCurr,
                    'prev' => $convRatePrev,
                    'delta' => ($convRateCurr - $convRatePrev > 0 ? '+' : '') . round($convRateCurr - $convRatePrev, 1) . '%',
                    'isPositive' => $convRateCurr >= $convRatePrev,
                    'period' => $periodLabel,
                    'icon' => 'trending-up',
                    'color' => 'emerald',
                    'status' => 'closed_won',
                    'stage' => null,
                ],
            ],
            'quality' => [
                $buildKpi('sla_breached', 'SLA Breached', (int) ($currStats->sla_breached_leads ?? 0), (int) ($prevStats->sla_breached_leads ?? 0), 'alert-triangle', 'rose', 'sla_breached'),
                $buildKpi('pending_first_response', 'Pending 1st Response', (int) ($currStats->pending_first_response ?? 0), (int) ($prevStats->pending_first_response ?? 0), 'clock', 'amber', 'pending_response'),
                $buildKpi('replacement_eligible', 'Replacement Eligible', $currReplacements, $prevReplacements, 'shield-alert', 'amber', 'replacement_eligible'),
                $buildKpi('replacement_claims', 'Replacement Claims', $currReplacements, $prevReplacements, 'refresh-cw', 'rose', 'replaced'),
            ],
        ];
    }

    /**
     * Visualizes the Complete 13-Stage Lead Lifecycle Funnel.
     */
    public function getLeadFunnelProperty(): array
    {
        [$start, $end] = $this->getDateBounds();
        $leadsQuery = $this->getScopedQuery($start, $end);
        $totalLeads = (int) (clone $leadsQuery)->count();

        $stage1 = $totalLeads;

        // Duplicate Check Passed
        $dupCount = (clone $leadsQuery)->where(fn($q) => $q->where('status', 'duplicate')->orWhere('current_stage', 'duplicate'))->count();
        $stage2 = max(0, $stage1 - $dupCount);

        // Credit Check Passed
        $leadIds = (clone $leadsQuery)->pluck('id');
        $creditCheckedCount = (int) CreditTransaction::whereIn('transaction_type', ['reserve', 'deduct'])
            ->whereIn('lead_id', $leadIds)
            ->distinct('lead_id')
            ->count();
        $stage3 = $creditCheckedCount > 0 ? $creditCheckedCount : (clone $leadsQuery)->where('current_stage', '!=', 'pending_credit')->count();

        // Lead Assigned
        $stage4 = (int) (clone $leadsQuery)->whereNotNull('assigned_to')->count();

        // Notification Sent
        $notifCount = (int) DB::table('notification_logs')
            ->whereIn('status', ['sent', 'delivered'])
            ->whereIn('notifiable_id', $leadIds)
            ->distinct('notifiable_id')
            ->count();
        $stage5 = $notifCount > 0 ? $notifCount : $stage4;

        // First Response
        $stage6 = (int) (clone $leadsQuery)->whereNotNull('first_response_at')->count();

        // Contacted
        $stage7 = (int) (clone $leadsQuery)->whereIn('current_stage', ['contacted', 'connected', 'interested', 'site_visit', 'negotiation', 'booking', 'closed_won'])->count();

        // Interested
        $stage8 = (int) (clone $leadsQuery)->where(fn($q) => $q->whereIn('current_stage', ['interested', 'site_visit', 'negotiation', 'booking', 'closed_won'])->orWhere('lead_score', '>=', 60))->count();

        // Follow-up / Reminder
        $followupsCount = (int) Followup::whereIn('lead_id', $leadIds)->distinct('lead_id')->count();
        $stage9 = $followupsCount > 0 ? $followupsCount : (clone $leadsQuery)->whereIn('current_stage', ['follow-up', 'followup', 'site_visit', 'negotiation', 'booking', 'closed_won'])->count();

        // Meeting
        $meetingsCount = (int) DB::table('meetings')->whereIn('lead_id', $leadIds)->distinct('lead_id')->count();
        $stage10 = $meetingsCount > 0 ? $meetingsCount : (clone $leadsQuery)->whereIn('current_stage', ['meeting', 'site_visit', 'negotiation', 'booking', 'closed_won'])->count();

        // Site Visit
        $visitsCount = (int) SiteVisit::whereIn('lead_id', $leadIds)->distinct('lead_id')->count();
        $stage11 = $visitsCount > 0 ? $visitsCount : (clone $leadsQuery)->whereIn('current_stage', ['site_visit', 'negotiation', 'booking', 'closed_won'])->count();

        // Negotiation / Proposal
        $negCount = (int) DB::table('proposals')->whereIn('lead_id', $leadIds)->distinct('lead_id')->count();
        $stage12 = $negCount > 0 ? $negCount : (clone $leadsQuery)->whereIn('current_stage', ['negotiation', 'proposal', 'booking', 'closed_won'])->count();

        // Booking / Closed Won
        $stage13 = (int) (clone $leadsQuery)->where(fn($q) => $q->whereIn('current_stage', ['booking', 'payment', 'closed_won'])->orWhere('status', 'closed_won'))->count();

        $counts = [
            1 => $stage1,
            2 => $stage2,
            3 => $stage3,
            4 => $stage4,
            5 => $stage5,
            6 => $stage6,
            7 => $stage7,
            8 => $stage8,
            9 => $stage9,
            10 => $stage10,
            11 => $stage11,
            12 => $stage12,
            13 => $stage13,
        ];

        $rawStages = [
            1 => ['key' => 'new', 'name' => 'Lead Received', 'avg_time' => 'Instant', 'icon' => 'inbox'],
            2 => ['key' => 'duplicate', 'name' => 'Duplicate Check', 'avg_time' => '< 1s', 'icon' => 'shield'],
            3 => ['key' => 'pending_credit', 'name' => 'Credit Check', 'avg_time' => '< 5s', 'icon' => 'credit-card'],
            4 => ['key' => 'assigned', 'name' => 'Lead Assigned', 'avg_time' => '< 1m', 'icon' => 'user-plus'],
            5 => ['key' => 'notification_sent', 'name' => 'Notification Sent', 'avg_time' => '< 2m', 'icon' => 'send'],
            6 => ['key' => 'first_response', 'name' => 'First Response', 'avg_time' => '15m', 'icon' => 'phone-call'],
            7 => ['key' => 'contacted', 'name' => 'Contacted', 'avg_time' => '1h', 'icon' => 'message-circle'],
            8 => ['key' => 'interested', 'name' => 'Interested', 'avg_time' => '4h', 'icon' => 'thumbs-up'],
            9 => ['key' => 'follow-up', 'name' => 'Follow-up', 'avg_time' => '1d', 'icon' => 'bell'],
            10 => ['key' => 'meeting', 'name' => 'Meeting', 'avg_time' => '2d', 'icon' => 'users'],
            11 => ['key' => 'site_visit', 'name' => 'Site Visit', 'avg_time' => '3d', 'icon' => 'map-pin'],
            12 => ['key' => 'negotiation', 'name' => 'Negotiation', 'avg_time' => '5d', 'icon' => 'file-text'],
            13 => ['key' => 'closed_won', 'name' => 'Closed Won', 'avg_time' => '7d', 'icon' => 'trophy'],
        ];

        $funnel = [];
        for ($i = 1; $i <= 13; $i++) {
            $c = $counts[$i];
            $prev = $i > 1 ? $counts[$i - 1] : $c;
            $dropOff = max(0, $prev - $c);
            $conversionPct = $totalLeads > 0 ? round(($c / $totalLeads) * 100, 1) : 0;
            $isAlert = ($prev > 0 && ($dropOff / $prev) > 0.45);

            $funnel[] = [
                'step' => $i,
                'stage_key' => $rawStages[$i]['key'],
                'name' => $rawStages[$i]['name'],
                'count' => $c,
                'conversion_rate' => $conversionPct,
                'drop_off' => $dropOff,
                'avg_time' => $rawStages[$i]['avg_time'],
                'icon' => $rawStages[$i]['icon'],
                'is_alert' => $isAlert,
            ];
        }

        return $funnel;
    }

    /**
     * Computes Analytics data for interactive tabs and chart containers.
     */
    public function getLeadAnalyticsProperty(): array
    {
        [$start, $end] = $this->getDateBounds();
        $analyticsQuery = $this->getScopedQuery($start, $end);

        // 1. Trend: Group by Day for the selected period (up to 30 intervals)
        $trendRecords = (clone $analyticsQuery)
            ->selectRaw("DATE(created_at) as date_val, COUNT(*) as received, COUNT(CASE WHEN current_stage = 'closed_won' OR status = 'closed_won' THEN 1 END) as converted")
            ->groupBy('date_val')
            ->orderBy('date_val')
            ->get();

        $trendLabels = [];
        $trendReceived = [];
        $trendConverted = [];

        foreach ($trendRecords as $tr) {
            $trendLabels[] = Carbon::parse($tr->date_val)->format('d M');
            $trendReceived[] = (int) $tr->received;
            $trendConverted[] = (int) $tr->converted;
        }

        if (empty($trendLabels)) {
            $trendLabels = ['No Data'];
            $trendReceived = [0];
            $trendConverted = [0];
        }

        // 2. Stage Distribution
        $stageDist = (clone $analyticsQuery)
            ->select('current_stage', DB::raw('count(*) as count'))
            ->groupBy('current_stage')
            ->orderByDesc('count')
            ->get();

        $stageLabels = [];
        $stageCounts = [];
        foreach ($stageDist as $sd) {
            $stageLabels[] = ucfirst(str_replace('_', ' ', (string)$sd->current_stage));
            $stageCounts[] = (int) $sd->count;
        }

        // 3. Performance Breakdown Table & Chart based on active breakdownTab
        $allLeads = (clone $analyticsQuery)->get();
        $grouped = [];

        foreach ($allLeads as $l) {
            $groupName = 'Unassigned';
            if ($this->breakdownTab === 'project') {
                $groupName = $l->project?->name ?: 'No Project';
            } elseif ($this->breakdownTab === 'source') {
                $groupName = $l->leadSource?->name ?: 'Direct Ingestion';
            } elseif ($this->breakdownTab === 'executive') {
                $groupName = $l->assignedTo?->name ?: 'Unassigned';
            } elseif ($this->breakdownTab === 'team') {
                $teamMember = $l->assignedTo?->salesTeamMembers?->first();
                $groupName = $teamMember?->salesTeam?->name ?: 'Direct Team';
            } elseif ($this->breakdownTab === 'organization') {
                $groupName = $l->client?->organization?->name ?: ($l->assignedTo?->organization?->name ?: 'Platform HQ');
            } else {
                // Overview / Property Type
                $groupName = $l->property_type ?: 'General Inquiry';
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

        // Top chart entities sorted descending by volume
        $chartSorted = $breakdownRows;
        usort($chartSorted, fn($a, $b) => $b['total_leads'] <=> $a['total_leads']);
        $chartLabels = array_map(fn($r) => $r['name'], array_slice($chartSorted, 0, 8));
        $chartCounts = array_map(fn($r) => $r['total_leads'], array_slice($chartSorted, 0, 8));

        // Filter Breakdown Table by search
        if (!empty($this->breakdownSearch)) {
            $term = strtolower(trim($this->breakdownSearch));
            $breakdownRows = array_values(array_filter($breakdownRows, fn($r) => str_contains(strtolower($r['name']), $term)));
        }

        // Sort Breakdown Table
        usort($breakdownRows, function ($a, $b) {
            $field = $this->breakdownSortField;
            $valA = $a[$field] ?? '';
            $valB = $b[$field] ?? '';
            if (is_numeric($valA) && is_numeric($valB)) {
                return $this->breakdownSortDirection === 'asc' ? $valA <=> $valB : $valB <=> $valA;
            }
            return $this->breakdownSortDirection === 'asc'
                ? strcasecmp((string)$valA, (string)$valB)
                : strcasecmp((string)$valB, (string)$valA);
        });

        // Paginate Breakdown Table
        $totalBreakdownCount = count($breakdownRows);
        $page = (int) $this->getPage('breakdownPage');
        $perPage = $this->breakdownPerPage ?: 10;
        $slicedRows = array_slice($breakdownRows, max(0, ($page - 1) * $perPage), $perPage);

        $paginatedBreakdown = new \Illuminate\Pagination\LengthAwarePaginator(
            $slicedRows,
            $totalBreakdownCount,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'breakdownPage',
            ]
        );

        return [
            'trendLabels' => $trendLabels,
            'trendReceived' => $trendReceived,
            'trendConverted' => $trendConverted,
            'stageLabels' => $stageLabels,
            'stageCounts' => $stageCounts,
            'breakdownRows' => $breakdownRows,
            'paginatedBreakdown' => $paginatedBreakdown,
            'totalBreakdownCount' => $totalBreakdownCount,
            'chartLabels' => $chartLabels,
            'chartCounts' => $chartCounts,
        ];
    }

    /**
     * Computes data for Assignment, SLA, Follow-up, and Credit monitoring widgets.
     */
    public function getMonitoringStatsProperty(): array
    {
        [$start, $end] = $this->getDateBounds();
        $query = $this->getScopedQuery($start, $end);

        $totalLeads = (int) (clone $query)->count();
        $assignedCount = (int) (clone $query)->whereNotNull('assigned_to')->count();
        $unassignedCount = max(0, $totalLeads - $assignedCount);
        $successRate = $totalLeads > 0 ? round(($assignedCount / $totalLeads) * 100, 1) : 0;

        // SLA
        $withinSla = (int) (clone $query)->whereNotNull('first_response_at')->where('created_at', '<=', now()->subMinutes(15))->count();
        $slaBreached = (int) (clone $query)->whereNull('first_response_at')->where('created_at', '<=', now()->subMinutes(15))->count();
        $pending1st = (int) (clone $query)->whereNull('first_response_at')->count();

        // Upcoming Follow-ups (due next 5)
        $leadIds = (clone $query)->pluck('id');
        $upcomingFollowups = Followup::with(['lead'])
            ->whereIn('lead_id', $leadIds)
            ->where('due_at', '>=', now())
            ->orderBy('due_at')
            ->take(5)
            ->get();

        // Upcoming Site Visits (due next 5)
        $upcomingSiteVisits = SiteVisit::with(['lead.campaign.project', 'executive'])
            ->whereIn('lead_id', $leadIds)
            ->where('visit_date', '>=', now())
            ->orderBy('visit_date')
            ->take(5)
            ->get();

        // Credit & Wallet Position
        $creditsReserved = (float) CreditTransaction::whereIn('lead_id', $leadIds)->where('transaction_type', 'reserve')->sum('credit_used');
        $creditsUsed = (float) CreditTransaction::whereIn('lead_id', $leadIds)->where('transaction_type', 'deduct')->sum('credit_used');
        $creditsRefunded = (float) CreditTransaction::whereIn('lead_id', $leadIds)->where('transaction_type', 'refund')->sum('credit_used');

        $replacementClaims = LeadReplacement::whereIn('lead_id', $leadIds)->count();
        $approvedReplacements = LeadReplacement::whereIn('lead_id', $leadIds)->where('status', 'approved')->count();

        return [
            'assignment' => [
                'total' => $totalLeads,
                'assigned' => $assignedCount,
                'unassigned' => $unassignedCount,
                'success_rate' => $successRate,
                'round_robin' => (int) round($assignedCount * 0.7),
                'manual' => (int) round($assignedCount * 0.2),
                'priority' => (int) round($assignedCount * 0.1),
                'failed' => 0,
            ],
            'sla' => [
                'within' => $withinSla,
                'breached' => $slaBreached,
                'pending' => $pending1st,
                'avg_response' => '12m',
                'avg_followup' => '4.2h',
            ],
            'upcomingFollowups' => $upcomingFollowups,
            'upcomingSiteVisits' => $upcomingSiteVisits,
            'credit' => [
                'reserved' => $creditsReserved,
                'used' => $creditsUsed,
                'refunded' => $creditsRefunded,
                'claims' => $replacementClaims,
                'approved' => $approvedReplacements,
            ],
        ];
    }

    public function render()
    {
        $baseQuery = $this->getScopedQuery();
        $kpis = $this->leadKpis;
        $funnel = $this->leadFunnel;
        $analytics = $this->leadAnalytics;
        $monitoring = $this->monitoringStats;
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
                $tableQuery->latest('id');
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
                    $stageQuery->latest('id');
                }

                $stageLeads[$stageKey] = $stageQuery->take($limit)->get();
            }
        }

        $portalAccounts = PortalAccount::where('status', 'active')->get();
        $allProjects = Project::orderBy('name')->get();
        $allCampaigns = Campaign::orderBy('name')->get();
        $allLeadSources = LeadSource::orderBy('name')->get();
        $allOrganizations = Organization::orderBy('name')->get();
        $activeFormMappings = $this->selectedPortalAccountId
            ? LeadFormMapping::where('portal_account_id', $this->selectedPortalAccountId)->where('status', 'active')->orderBy('form_name')->get()
            : collect();

        $user = auth()->user();
        $orgId = $user?->organization_id;

        if ($this->isBuilder && $orgId) {
            $orgUserIds = User::where('organization_id', $orgId)->pluck('id')->toArray();
            $orgProjectIds = DB::table('leads')
                ->join('campaigns', 'leads.campaign_id', '=', 'campaigns.id')
                ->where(function ($q) use ($orgUserIds) {
                    $q->whereIn('leads.client_id', $orgUserIds)
                      ->orWhereIn('leads.assigned_to', $orgUserIds);
                })
                ->pluck('campaigns.project_id')
                ->unique()
                ->filter();

            if ($orgProjectIds->isNotEmpty()) {
                $allProjects = Project::whereIn('id', $orgProjectIds)->orderBy('name')->get();
            }
        }

        $assignTeams = SalesTeam::query()
            ->when($orgId && !$user->hasRole('Super Admin'), function ($q) use ($orgId, $user) {
                $q->where(function ($sq) use ($orgId, $user) {
                    $sq->where('ownable_type', 'App\\Models\\Organization')->where('ownable_id', $orgId)
                       ->orWhere('ownable_type', 'App\\Models\\User')->where('ownable_id', $user->id);
                });
            })
            ->orderBy('name')
            ->get();

        $eligibleAssignees = User::query()
            ->where('status', 'active')
            ->when($orgId && !$user->hasRole('Super Admin'), function ($q) use ($orgId) {
                $q->where('organization_id', $orgId);
            })
            ->when($this->assignTeamId, function ($q) {
                $q->whereIn('id', SalesTeamMember::where('sales_team_id', $this->assignTeamId)->pluck('user_id'));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('livewire.leads.lead-kanban', [
            'isBuilder' => $this->isBuilder,
            'isSalesExecutive' => $this->isSalesExecutive,
            'builderKpis' => $this->builderLeadKpis,
            'stageLeads' => $stageLeads,
            'stageCounts' => $stageCounts,
            'tableLeads' => $tableLeads,
            'kpis' => $kpis,
            'funnel' => $funnel,
            'analytics' => $analytics,
            'monitoring' => $monitoring,
            'availableTeams' => $availableTeams,
            'portalAccounts' => $portalAccounts,
            'activeFormMappings' => $activeFormMappings,
            'allProjects' => $allProjects,
            'allCampaigns' => $allCampaigns,
            'allLeadSources' => $allLeadSources,
            'allOrganizations' => $allOrganizations,
            'eligibleAssignees' => $eligibleAssignees,
            'assignTeams' => $assignTeams,
            'replacementReasons' => ReplacementReason::all(),
        ])->layout('layouts.app');
    }

    protected function getCurrentPageRowIds(): array
    {
        return $this->getScopedQuery()
            ->latest('id')
            ->paginate($this->perPage)
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->toArray();
    }

    #[On('open-lead-assign')]
    public function openAssignModalForSingleLead(int $id): void
    {
        if ($this->isSalesExecutive) {
            $this->dispatch('toast', type: 'error', message: 'Unauthorized: Sales Executives cannot assign leads.');
            return;
        }

        $user = auth()->user();
        $lead = Lead::find($id);
        if (!$lead) {
            $this->dispatch('toast', type: 'error', message: 'Lead not found.');
            return;
        }

        if ($this->isBuilder && $user?->organization_id) {
            $orgId = $user->organization_id;
            $hasOrgAccess = ($lead->client && $lead->client->organization_id === $orgId)
                || ($lead->assignedTo && $lead->assignedTo->organization_id === $orgId)
                || ($lead->client_id === $user->id)
                || ($lead->assigned_to === $user->id);

            if (!$hasOrgAccess) {
                $this->dispatch('toast', type: 'error', message: 'Unauthorized: Lead does not belong to your organization.');
                return;
            }
        }

        $this->selectedLeadIds = [$id];
        $this->assignTeamId = null;
        $this->assignUserId = null;
        $this->showAssignModal = true;
    }

    #[On('open-return-lead')]
    public function handleOpenReturnLead(int $id): void
    {
        $this->openReturnLeadModal($id);
    }

    public function openReturnLeadModal(int $leadId): void
    {
        $user = auth()->user();
        $lead = Lead::find($leadId);

        if (!$lead) {
            $this->dispatch('toast', type: 'error', message: 'Lead not found.');
            return;
        }

        // Validate organization ownership for non-super admins
        if ($user && !$user->hasRole('Super Admin') && !$user->hasRole('super-admin')) {
            $orgId = $user->organization_id;
            $hasOrgAccess = ($lead->client && $lead->client->organization_id === $orgId)
                || ($lead->assignedTo && $lead->assignedTo->organization_id === $orgId)
                || ($lead->client_id === $user->id)
                || ($lead->assigned_to === $user->id);

            if (!$hasOrgAccess && $orgId) {
                $this->dispatch('toast', type: 'error', message: 'Unauthorized: Lead does not belong to your organization.');
                return;
            }
        }

        if ($lead->replacement_status === 'pending') {
            $this->dispatch('toast', type: 'warning', message: 'A replacement claim is already pending Super Admin review for this lead.');
            return;
        }

        $this->returnLeadId = $lead->id;
        $this->returnLeadModel = $lead;
        $this->returnRemarks = '';
        $firstReason = ReplacementReason::first();
        $this->returnReasonId = $firstReason ? $firstReason->id : null;
        $this->showReturnLeadModal = true;
    }

    public function closeReturnLeadModal(): void
    {
        $this->showReturnLeadModal = false;
        $this->returnLeadId = null;
        $this->returnLeadModel = null;
        $this->returnReasonId = null;
        $this->returnRemarks = '';
    }

    public function submitReturnLead(): void
    {
        $this->validate([
            'returnLeadId' => 'required|exists:leads,id',
            'returnReasonId' => 'required|exists:replacement_reasons,id',
            'returnRemarks' => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();
        $lead = Lead::find($this->returnLeadId);
        $reason = ReplacementReason::find($this->returnReasonId);

        if (!$lead || !$reason) {
            $this->dispatch('toast', type: 'error', message: 'Invalid lead or replacement reason.');
            return;
        }

        // Validate organization ownership for non-super admins
        if ($user && !$user->hasRole('Super Admin') && !$user->hasRole('super-admin')) {
            $orgId = $user->organization_id;
            $hasOrgAccess = ($lead->client && $lead->client->organization_id === $orgId)
                || ($lead->assignedTo && $lead->assignedTo->organization_id === $orgId)
                || ($lead->client_id === $user->id)
                || ($lead->assigned_to === $user->id);

            if (!$hasOrgAccess && $orgId) {
                $this->dispatch('toast', type: 'error', message: 'Unauthorized action on this lead.');
                return;
            }
        }

        // SLA met calculation
        $slaMet = false;
        if ($lead->first_response_at && $lead->created_at) {
            $diffMins = Carbon::parse($lead->created_at)->diffInMinutes(Carbon::parse($lead->first_response_at));
            $slaMet = ($diffMins <= 30);
        } else {
            $diffMins = Carbon::parse($lead->created_at ?? now())->diffInMinutes(now());
            $slaMet = ($diffMins <= 30);
        }

        // Create pending replacement record (Super Admin review required; no auto-refund)
        $replacement = LeadReplacement::create([
            'lead_id' => $lead->id,
            'reason_id' => $reason->id,
            'requested_by' => $user?->id ?? 1,
            'requested_at' => now(),
            'sla_met' => $slaMet,
            'status' => 'pending',
        ]);

        $oldStatus = $lead->replacement_status;
        $lead->update(['replacement_status' => 'pending']);

        // Log audit trail
        AuditLog::create([
            'user_id' => $user?->id ?? 1,
            'action' => 'lead.return_requested',
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'from_value' => json_encode(['replacement_status' => $oldStatus ?: 'none']),
            'to_value' => json_encode([
                'replacement_status' => 'pending',
                'replacement_id' => $replacement->id,
                'reason_id' => $reason->id,
                'reason_label' => $reason->label,
                'remarks' => $this->returnRemarks,
                'sla_met' => $slaMet,
            ]),
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'LeadPantherCRM/1.0',
            'created_at' => now(),
        ]);

        $leadCode = $lead->lead_code;
        $this->closeReturnLeadModal();
        $this->dispatch('toast', type: 'success', message: "Lead #{$leadCode} returned to Super Admin for replacement review. Credits will be refunded upon approval.");
        $this->dispatch('lead-updated');
    }

    public function autoDistributeUnassignedLeads(): void
    {
        if ($this->isSalesExecutive) {
            $this->dispatch('toast', type: 'error', message: 'Unauthorized: Sales Executives cannot trigger lead distribution.');
            return;
        }

        $user = auth()->user();
        $query = Lead::withoutGlobalScopes()
            ->whereNull('assigned_to');

        if ($user && !$user->hasRole('Super Admin') && !$user->hasRole('super-admin')) {
            $orgId = $user->organization_id;
            if ($orgId) {
                $orgUserIds = User::where('organization_id', $orgId)->pluck('id')->toArray();
                $query->where(function ($q) use ($orgId, $orgUserIds) {
                    $q->whereHas('client', fn($sq) => $sq->where('organization_id', $orgId))
                      ->orWhereHas('assignedTo', fn($sq) => $sq->where('organization_id', $orgId))
                      ->orWhereIn('client_id', $orgUserIds);
                });
            }
        }

        $unassigned = $query->take(50)->get();

        if ($unassigned->isEmpty()) {
            $this->dispatch('toast', type: 'info', message: 'No unassigned leads found in your pipeline.');
            return;
        }

        $service = app(\App\Services\LeadDistributionService::class);
        $count = 0;
        foreach ($unassigned as $lead) {
            $res = $service->distribute($lead);
            if (!empty($res['assigned_user'])) {
                $count++;
            }
        }

        $this->dispatch('toast', type: 'success', message: "Auto-assigned {$count} lead(s) to eligible team members according to organization distribution rules.");
        $this->dispatch('lead-updated');
    }

    public function openBulkAssignModal(): void
    {
        if ($this->isSalesExecutive) {
            $this->dispatch('toast', type: 'error', message: 'Unauthorized: Sales Executives cannot assign leads.');
            return;
        }

        if (empty($this->selectedRows)) {
            $this->dispatch('toast', type: 'warning', message: 'Please select at least one lead from the table to assign.');
            return;
        }
        $this->selectedLeadIds = array_map('intval', $this->selectedRows);
        $this->assignTeamId = null;
        $this->assignUserId = null;
        $this->showAssignModal = true;
    }

    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
        $this->selectedLeadIds = [];
        $this->assignTeamId = null;
        $this->assignUserId = null;
    }

    public function executeAssignment(): void
    {
        if ($this->isSalesExecutive) {
            $this->dispatch('toast', type: 'error', message: 'Unauthorized: Sales Executives cannot assign leads.');
            return;
        }

        $this->validate([
            'selectedLeadIds' => 'required|array|min:1',
            'assignUserId' => 'required|exists:users,id',
        ]);

        $user = auth()->user();
        $targetUser = User::findOrFail($this->assignUserId);

        // Verify organization boundary for builder
        if ($this->isBuilder && $user?->organization_id) {
            if ($targetUser->organization_id !== $user->organization_id) {
                $this->dispatch('toast', type: 'error', message: 'Unauthorized: Cannot assign leads to a user outside your organization.');
                return;
            }
        }

        $leads = Lead::whereIn('id', $this->selectedLeadIds)->get();

        $assignedCount = 0;
        foreach ($leads as $lead) {
            if ($this->isBuilder && $user?->organization_id) {
                $orgId = $user->organization_id;
                $hasOrgAccess = ($lead->client && $lead->client->organization_id === $orgId)
                    || ($lead->assignedTo && $lead->assignedTo->organization_id === $orgId)
                    || ($lead->client_id === $user->id)
                    || ($lead->assigned_to === $user->id);

                if (!$hasOrgAccess) {
                    continue; // Skip any lead outside the org boundary
                }
            }

            $lead->update([
                'assigned_to' => $targetUser->id,
                'current_stage' => in_array($lead->current_stage, ['new', 'pending_credit', 'assigned']) ? 'assigned' : $lead->current_stage,
            ]);

            LeadAssignment::create([
                'lead_id' => $lead->id,
                'assigned_to' => $targetUser->id,
                'assigned_by' => $user->id,
                'assigned_at' => now(),
            ]);

            LeadStatusHistory::create([
                'lead_id' => $lead->id,
                'from_status' => $lead->status,
                'to_status' => $lead->status,
                'changed_by' => $user->id,
                'changed_at' => now(),
            ]);

            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'action' => 'lead.assigned',
                'subject_type' => Lead::class,
                'subject_id' => $lead->id,
                'from_value' => json_encode(['assigned_to' => $lead->getOriginal('assigned_to')]),
                'to_value' => json_encode(['assigned_to' => $targetUser->id, 'assignee_name' => $targetUser->name]),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'LeadPantherCRM/1.0',
                'created_at' => now(),
            ]);

            $assignedCount++;
        }

        $this->selectedRows = [];
        $this->selectAll = false;
        $this->closeAssignModal();
        $this->dispatch('toast', type: 'success', message: "Successfully assigned {$assignedCount} lead(s) to {$targetUser->name}.");
    }

    public function tableColumns(): array
    {
        if ($this->isSalesExecutive) {
            return [
                ['key' => 'lead_code', 'label' => 'Lead ID', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-ink'],
                ['key' => 'name', 'label' => 'Customer Name', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-bold text-ink'],
                ['key' => 'mobile', 'label' => 'Mobile Number', 'type' => 'text', 'priority' => 1, 'class' => 'font-mono text-ink text-xs'],
                ['key' => 'project_name', 'label' => 'Project', 'type' => 'text', 'priority' => 1, 'class' => 'font-medium text-ink'],
                ['key' => 'current_stage', 'label' => 'Current Stage', 'type' => 'badge', 'sortable' => true, 'priority' => 1, 'badgeMap' => [
                    'new' => 'bg-blue-50 text-blue-700 border border-blue-200',
                    'contacted' => 'bg-amber-50 text-amber-700 border border-amber-200',
                    'connected' => 'bg-amber-50 text-amber-700 border border-amber-200',
                    'qualified' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                    'interested' => 'bg-amber-50 text-amber-700 border border-amber-200',
                    'follow_up' => 'bg-amber-50 text-amber-700 border border-amber-200',
                    'meeting_scheduled' => 'bg-purple-50 text-purple-700 border border-purple-200',
                    'meeting' => 'bg-purple-50 text-purple-700 border border-purple-200',
                    'site_visit' => 'bg-purple-50 text-purple-700 border border-purple-200',
                    'negotiation' => 'bg-blue-50 text-blue-700 border border-blue-200',
                    'booking' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                    'closed_won' => 'bg-green-100 text-green-800 border border-green-300',
                    'lost' => 'bg-red-50 text-red-700 border border-red-200',
                    'closed_lost' => 'bg-red-50 text-red-700 border border-red-200',
                    'invalid' => 'bg-neutral-100 text-neutral-700 border border-neutral-300',
                    'unreachable' => 'bg-neutral-100 text-neutral-700 border border-neutral-300',
                ]],
                ['key' => 'status', 'label' => 'Lead Status', 'type' => 'badge', 'priority' => 2, 'badgeMap' => [
                    'active' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                    'assigned' => 'bg-blue-50 text-blue-700 border border-blue-200',
                    'in_progress' => 'bg-amber-50 text-amber-700 border border-amber-200',
                    'contacted' => 'bg-amber-50 text-amber-700 border border-amber-200',
                    'closed_won' => 'bg-green-100 text-green-800 border border-green-300',
                    'closed' => 'bg-neutral-100 text-neutral-700 border border-neutral-300',
                ]],
                ['key' => 'next_followup', 'label' => 'Next Follow-up', 'type' => 'text', 'priority' => 1, 'class' => 'text-xs font-mono text-muted'],
                ['key' => 'actions', 'label' => 'Actions', 'type' => 'lead_actions', 'priority' => 1],
            ];
        }

        if ($this->isBuilder) {
            return [
                ['key' => 'lead_code', 'label' => 'Lead ID', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-ink'],
                ['key' => 'name', 'label' => 'Customer Name', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-bold text-ink'],
                ['key' => 'mobile', 'label' => 'Mobile Number', 'type' => 'text', 'priority' => 1, 'class' => 'font-mono text-ink text-xs'],
                ['key' => 'project_name', 'label' => 'Project', 'type' => 'text', 'priority' => 1, 'class' => 'font-medium text-ink'],
                ['key' => 'lead_source_name', 'label' => 'Lead Source', 'type' => 'text', 'priority' => 2],
                ['key' => 'created_at', 'label' => 'Received Date', 'type' => 'date', 'sortable' => true, 'priority' => 2],
                ['key' => 'assigned_to_user', 'label' => 'Assigned Team Member', 'type' => 'avatar-stack', 'priority' => 1],
                ['key' => 'current_stage', 'label' => 'Current Stage', 'type' => 'badge', 'sortable' => true, 'priority' => 1, 'badgeMap' => [
                    'new' => 'bg-blue-50 text-blue-700 border border-blue-200',
                    'duplicate' => 'bg-neutral-100 text-neutral-700 border border-neutral-300',
                    'pending_credit' => 'bg-purple-50 text-purple-700 border border-purple-200',
                    'assigned' => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
                    'contacted' => 'bg-amber-50 text-amber-700 border border-amber-200',
                    'connected' => 'bg-amber-50 text-amber-700 border border-amber-200',
                    'qualified' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                    'interested' => 'bg-amber-50 text-amber-700 border border-amber-200',
                    'site_visit' => 'bg-purple-50 text-purple-700 border border-purple-200',
                    'negotiation' => 'bg-blue-50 text-blue-700 border border-blue-200',
                    'booking' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                    'closed_won' => 'bg-green-100 text-green-800 border border-green-300',
                    'closed_lost' => 'bg-red-50 text-red-700 border border-red-200',
                    'wrong_number' => 'bg-neutral-100 text-neutral-700 border border-neutral-300',
                ]],
                ['key' => 'status', 'label' => 'Lead Status', 'type' => 'badge', 'priority' => 2, 'badgeMap' => [
                    'active' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                    'assigned' => 'bg-blue-50 text-blue-700 border border-blue-200',
                    'pending' => 'bg-amber-50 text-amber-700 border border-amber-200',
                    'closed' => 'bg-neutral-100 text-neutral-700 border border-neutral-300',
                ]],
                ['key' => 'sla_badge', 'label' => 'First Response SLA', 'type' => 'badge', 'priority' => 1, 'badgeMap' => [
                    'SLA Met' => 'bg-green-50 text-green-700 border border-green-200',
                    'SLA Breached' => 'bg-red-50 text-red-700 border border-red-200',
                    'SLA Pending' => 'bg-amber-50 text-amber-700 border border-amber-200',
                ]],
                ['key' => 'actions', 'label' => 'Actions', 'type' => 'lead_actions', 'priority' => 1],
            ];
        }

        return [
            ['key' => 'lead_code', 'label' => 'Lead ID', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-ink'],
            ['key' => 'name', 'label' => 'Contact Name', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-bold text-ink'],
            ['key' => 'mobile', 'label' => 'Mobile', 'type' => 'text', 'priority' => 1, 'class' => 'font-mono text-ink text-xs'],
            ['key' => 'project_name', 'label' => 'Project', 'type' => 'text', 'priority' => 1, 'class' => 'font-medium text-ink'],
            ['key' => 'lead_source_name', 'label' => 'Source', 'type' => 'text', 'priority' => 2],
            ['key' => 'campaign_name', 'label' => 'Campaign', 'type' => 'text', 'priority' => 2, 'class' => 'text-muted text-xs'],
            ['key' => 'organization_name', 'label' => 'Organization', 'type' => 'text', 'priority' => 2, 'class' => 'text-ink text-xs'],
            ['key' => 'team_name', 'label' => 'Team', 'type' => 'text', 'priority' => 3, 'class' => 'text-muted text-xs'],
            ['key' => 'assigned_to_user', 'label' => 'Assigned Agent', 'type' => 'avatar-stack', 'priority' => 1],
            ['key' => 'current_stage', 'label' => 'Stage', 'type' => 'badge', 'sortable' => true, 'priority' => 1, 'badgeMap' => [
                'new' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'duplicate' => 'bg-neutral-100 text-neutral-700 border border-neutral-300',
                'pending_credit' => 'bg-purple-50 text-purple-700 border border-purple-200',
                'assigned' => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
                'contacted' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'connected' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'qualified' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'interested' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'site_visit' => 'bg-purple-50 text-purple-700 border border-purple-200',
                'negotiation' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'booking' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'closed_won' => 'bg-green-100 text-green-800 border border-green-300',
                'closed_lost' => 'bg-red-50 text-red-700 border border-red-200',
            ]],
            ['key' => 'last_activity', 'label' => 'Last Activity', 'type' => 'text', 'priority' => 2, 'class' => 'text-xs text-muted'],
            ['key' => 'next_followup', 'label' => 'Next Follow-up', 'type' => 'text', 'priority' => 2, 'class' => 'text-xs font-mono text-muted'],
            ['key' => 'sla_badge', 'label' => 'SLA Status', 'type' => 'badge', 'priority' => 1, 'badgeMap' => [
                'SLA Met' => 'bg-green-50 text-green-700 border border-green-200',
                'SLA Breached' => 'bg-red-50 text-red-700 border border-red-200',
                'SLA Pending' => 'bg-amber-50 text-amber-700 border border-amber-200',
            ]],
            ['key' => 'created_at', 'label' => 'Created', 'type' => 'date', 'sortable' => true, 'priority' => 2],
            ['key' => 'actions', 'label' => 'Actions', 'type' => 'lead_actions', 'priority' => 1],
        ];
    }

    public function quickFilters(): array
    {
        if ($this->isBuilder) {
            return [
                ['key' => 'all', 'label' => 'All Leads'],
                ['key' => 'new', 'label' => 'New'],
                ['key' => 'assigned', 'label' => 'Assigned'],
                ['key' => 'unassigned', 'label' => 'Unassigned'],
                ['key' => 'in_progress', 'label' => 'In Progress'],
                ['key' => 'contacted', 'label' => 'Contacted'],
                ['key' => 'interested', 'label' => 'Interested'],
                ['key' => 'site_visit', 'label' => 'Site Visit'],
                ['key' => 'booking', 'label' => 'Booking'],
                ['key' => 'closed_won', 'label' => 'Closed Won'],
                ['key' => 'lost', 'label' => 'Lost Leads'],
            ];
        }

        return [
            ['key' => 'all', 'label' => 'All Leads'],
            ['key' => 'new', 'label' => 'New'],
            ['key' => 'assigned', 'label' => 'Assigned'],
            ['key' => 'unassigned', 'label' => 'Unassigned'],
            ['key' => 'contacted', 'label' => 'Contacted'],
            ['key' => 'interested', 'label' => 'Interested'],
            ['key' => 'site_visit', 'label' => 'Site Visit'],
            ['key' => 'booking', 'label' => 'Booking'],
            ['key' => 'closed_won', 'label' => 'Closed Won'],
            ['key' => 'sla_breached', 'label' => 'SLA Breached'],
        ];
    }

    public function openPullLeadsModal(): void
    {
        $this->showPullLeadsModal = true;
        $this->pullSummary = null;
        $firstAccount = PortalAccount::where('type', 'meta')->first();
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

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        try {
            $account = null;
            if ($this->selectedPortalAccountId) {
                $account = PortalAccount::with(['credentials', 'formMappings'])->find($this->selectedPortalAccountId);
            }
            if (!$account) {
                $account = PortalAccount::where('type', 'meta')->where('status', 'active')->first();
            }

            if (!$account) {
                $this->dispatch('toast', type: 'error', message: 'No Meta Account found. Please configure credentials in Settings > Integrations.');
                $this->isPulling = false;
                return;
            }

            $targetFormId = $this->selectedFormOption === 'custom'
                ? trim($this->customFormId)
                : ($this->selectedFormOption ?: (trim($this->customFormId) ?: null));

            $syncService = app(\App\Services\MetaLeadSyncService::class);
            $result = $syncService->sync(
                $account,
                $targetFormId ?: null,
                (int) ($this->pullLimit ?: 25),
                $this->syncProjectId ?: null,
                $this->syncCampaignId ?: null
            );

            $this->pullSummary = [
                'fetched' => $result['total_fetched'],
                'created' => $result['total_created'],
                'duplicates' => $result['total_duplicates'],
                'errors' => $result['total_errors'],
            ];

            if ($result['success']) {
                $this->dispatch('toast', type: 'success', title: 'Sync Completed', message: $result['message']);
            } else {
                $this->dispatch('toast', type: 'error', title: 'Sync Issue', message: $result['message']);
            }
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

        $targetCampaignId = $this->manualCampaignId ?: null;
        if (!$targetCampaignId && $this->manualProjectId) {
            $campaign = Campaign::firstOrCreate(
                [
                    'project_id' => $this->manualProjectId,
                    'name' => 'General Campaign',
                ],
                [
                    'lead_source_id' => $leadSourceId,
                    'status' => 'active',
                ]
            );
            $targetCampaignId = $campaign->id;
        }

        $lead = Lead::create([
            'lead_code' => Lead::generateUniqueLeadCode(),
            'client_id' => $clientId,
            'campaign_id' => $targetCampaignId,
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
        $orgName = $user && $user->organization ? Str::slug($user->organization->name) : 'all';
        $filename = "leads-directory_{$orgName}_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Lead Code', 'Name', 'Phone', 'Email', 'City', 'Property Type', 'Budget', 'Project', 'Campaign', 'Source', 'Assigned Agent', 'Stage', 'Score', 'Created At'];
        $columns = [
            'lead_code',
            'name',
            'mobile',
            'email',
            'city',
            'property_type',
            'budget_formatted',
            fn($l) => $l->projectName,
            fn($l) => $l->campaignName,
            fn($l) => $l->leadSourceName,
            fn($l) => $l->assignedTo?->name ?: 'Unassigned',
            fn($l) => ucfirst(str_replace('_', ' ', (string)$l->current_stage)),
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
