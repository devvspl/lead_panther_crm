<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use App\Models\Lead;
use App\Models\CreditWallet;
use App\Models\Booking;
use App\Models\CreditTransaction;
use App\Models\Organization;
use App\Models\Project;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\LeadSource;
use App\Models\Campaign;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Models\LeadReplacement;
use App\Models\RechargeRequest;
use App\Models\Followup;
use App\Models\SiteVisit;
use App\Support\NavigationConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardShell extends Component
{
    public ?string $role = null;

    // Global Dashboard Filter Properties
    public string $dateRange = '30_days'; // 'today', 'yesterday', '7_days', '30_days', 'custom'
    public ?string $customFrom = null;
    public ?string $customTo = null;
    public ?int $filterOrganizationId = null;
    public ?int $filterTeamId = null;
    public ?int $filterProjectId = null;
    public ?int $filterLeadSourceId = null;
    public ?int $filterAssignedUserId = null;
    public ?string $filterStatus = null;
    public ?int $filterCampaignId = null;
    public ?string $filterTransactionType = null;

    // View Controls
    public string $trendInterval = 'daily'; // 'daily', 'weekly', 'monthly'
    public string $activeActivityTab = 'leads'; // 'leads', 'followups', 'sla', 'transactions', 'replacements', 'notifications'
    public string $activeKpiGroup = 'overview'; // 'overview', 'conversion', 'sla', 'credit'

    public function mount(?string $role = null): void
    {
        if ($role) {
            $this->role = $role;
            return;
        }

        $prefix = request()->segment(1);
        if (in_array($prefix, ['builder', 'broker', 'partner', 'sales', 'client', 'admin'])) {
            $this->role = $prefix;
            return;
        }

        $user = auth()->user();
        $this->role = NavigationConfig::getRoleKey($user);
    }

    public function applyFilters(): void
    {
        // Triggers reactive query re-evaluation in Livewire
        $this->dispatch('$refresh');
    }

    public function resetFilters(): void
    {
        $this->dateRange = '30_days';
        $this->customFrom = null;
        $this->customTo = null;
        $this->filterOrganizationId = null;
        $this->filterTeamId = null;
        $this->filterProjectId = null;
        $this->filterLeadSourceId = null;
        $this->filterAssignedUserId = null;
        $this->filterStatus = null;
        $this->filterCampaignId = null;
        $this->filterTransactionType = null;
    }

    public function setTrendInterval(string $interval): void
    {
        if (in_array($interval, ['daily', 'weekly', 'monthly'])) {
            $this->trendInterval = $interval;
        }
    }

    public function setActiveActivityTab(string $tab): void
    {
        $this->activeActivityTab = $tab;
    }

    public function setActiveKpiGroup(string $group): void
    {
        $this->activeKpiGroup = $group;
    }

    /**
     * Resolves current and comparison previous date boundaries.
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon, 4: string}
     */
    public function getDateBounds(): array
    {
        $range = strtolower($this->dateRange);

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
        } elseif ($range === 'custom' && $this->customFrom && $this->customTo) {
            $start = Carbon::parse($this->customFrom)->startOfDay();
            $end = Carbon::parse($this->customTo)->endOfDay();
            $days = max(1, (int) $start->diffInDays($end) + 1);
            $prevEnd = (clone $start)->subSecond();
            $prevStart = (clone $start)->subDays($days)->startOfDay();
            $periodLabel = "vs prior {$days}d";
        } else {
            // Default 30 days
            $start = Carbon::now()->subDays(29)->startOfDay();
            $end = Carbon::now()->endOfDay();
            $prevStart = Carbon::now()->subDays(59)->startOfDay();
            $prevEnd = Carbon::now()->subDays(30)->endOfDay();
            $periodLabel = 'vs prior 30 days';
        }

        return [$start, $end, $prevStart, $prevEnd, $periodLabel];
    }

    /**
     * Centralized query builder for leads respecting all global filters.
     */
    protected function getFilteredLeadsQuery(?Carbon $start = null, ?Carbon $end = null)
    {
        $query = Lead::withoutGlobalScopes();

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($this->filterOrganizationId) {
            $orgUserIds = User::where('organization_id', $this->filterOrganizationId)->pluck('id');
            $query->whereIn('client_id', $orgUserIds);
        }

        if ($this->filterProjectId) {
            $query->whereHas('campaign', fn($q) => $q->where('project_id', $this->filterProjectId));
        }

        if ($this->filterTeamId) {
            $teamUserIds = SalesTeamMember::where('sales_team_id', $this->filterTeamId)->pluck('user_id');
            $query->whereIn('assigned_to', $teamUserIds);
        }

        if ($this->filterLeadSourceId) {
            $query->where('lead_source_id', $this->filterLeadSourceId);
        }

        if ($this->filterAssignedUserId) {
            $query->where('assigned_to', $this->filterAssignedUserId);
        }

        if ($this->filterStatus) {
            if ($this->filterStatus === 'sla_breached') {
                $query->whereNull('first_response_at')->where('created_at', '<=', Carbon::now()->subMinutes(30));
            } elseif ($this->filterStatus === 'unassigned') {
                $query->whereNull('assigned_to');
            } elseif ($this->filterStatus === 'assigned') {
                $query->whereNotNull('assigned_to');
            } else {
                $query->where(function ($q) {
                    $q->where('status', $this->filterStatus)
                      ->orWhere('current_stage', $this->filterStatus);
                });
            }
        }

        if ($this->filterCampaignId) {
            $query->where('campaign_id', $this->filterCampaignId);
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
     * Computes the 17 Super Admin Main KPI cards with previous period comparisons.
     */
    public function getAdminKpisProperty(): array
    {
        [$start, $end, $prevStart, $prevEnd, $periodLabel] = $this->getDateBounds();

        // 1. Current period aggregated lead metrics
        $currStats = $this->getFilteredLeadsQuery($start, $end)
            ->selectRaw("
                COUNT(*) as total_leads,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as leads_today,
                COUNT(CASE WHEN status = 'new' OR current_stage = 'new' THEN 1 END) as new_leads,
                COUNT(CASE WHEN status = 'duplicate' OR current_stage = 'duplicate' THEN 1 END) as duplicate_leads,
                COUNT(CASE WHEN lead_score >= 60 OR current_stage IN ('qualified', 'interested', 'site_visit', 'negotiation', 'booking', 'closed_won') THEN 1 END) as qualified_leads,
                COUNT(CASE WHEN assigned_to IS NOT NULL THEN 1 END) as assigned_leads,
                COUNT(CASE WHEN assigned_to IS NULL THEN 1 END) as unassigned_leads,
                COUNT(CASE WHEN current_stage IN ('in_progress', 'contacted', 'followup') THEN 1 END) as in_progress_leads,
                COUNT(CASE WHEN current_stage IN ('booking', 'payment', 'closed_won') OR booking_status IN ('confirmed', 'booked') THEN 1 END) as converted_leads,
                COUNT(CASE WHEN current_stage = 'closed_won' OR status = 'closed_won' THEN 1 END) as closed_won_leads,
                COUNT(CASE WHEN current_stage IN ('lost', 'closed_lost', 'dead', 'rejected') OR status IN ('lost', 'closed_lost', 'rejected') THEN 1 END) as lost_leads,
                COUNT(CASE WHEN first_response_at IS NULL AND created_at <= ? THEN 1 END) as sla_breached_leads
            ", [Carbon::now()->subMinutes(30)])
            ->first();

        // 2. Previous period aggregated lead metrics
        $prevStats = $this->getFilteredLeadsQuery($prevStart, $prevEnd)
            ->selectRaw("
                COUNT(*) as total_leads,
                COUNT(CASE WHEN DATE(created_at) = DATE(?) THEN 1 END) as leads_today,
                COUNT(CASE WHEN status = 'new' OR current_stage = 'new' THEN 1 END) as new_leads,
                COUNT(CASE WHEN status = 'duplicate' OR current_stage = 'duplicate' THEN 1 END) as duplicate_leads,
                COUNT(CASE WHEN lead_score >= 60 OR current_stage IN ('qualified', 'interested', 'site_visit', 'negotiation', 'booking', 'closed_won') THEN 1 END) as qualified_leads,
                COUNT(CASE WHEN assigned_to IS NOT NULL THEN 1 END) as assigned_leads,
                COUNT(CASE WHEN assigned_to IS NULL THEN 1 END) as unassigned_leads,
                COUNT(CASE WHEN current_stage IN ('in_progress', 'contacted', 'followup') THEN 1 END) as in_progress_leads,
                COUNT(CASE WHEN current_stage IN ('booking', 'payment', 'closed_won') OR booking_status IN ('confirmed', 'booked') THEN 1 END) as converted_leads,
                COUNT(CASE WHEN current_stage = 'closed_won' OR status = 'closed_won' THEN 1 END) as closed_won_leads,
                COUNT(CASE WHEN current_stage IN ('lost', 'closed_lost', 'dead', 'rejected') OR status IN ('lost', 'closed_lost', 'rejected') THEN 1 END) as lost_leads,
                COUNT(CASE WHEN first_response_at IS NULL AND created_at <= ? THEN 1 END) as sla_breached_leads
            ", [$prevStart, Carbon::now()->subDays(max(1, $start->diffInDays($end) + 1))->subMinutes(30)])
            ->first();

        // 3. Replacements
        $currReplacements = LeadReplacement::whereBetween('created_at', [$start, $end])->count();
        $prevReplacements = LeadReplacement::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        // 4. Credit Engine Metrics
        $walletQuery = CreditWallet::query();
        if ($this->filterOrganizationId) {
            $orgUserIds = User::where('organization_id', $this->filterOrganizationId)->pluck('id');
            $walletQuery->whereIn('client_id', $orgUserIds);
        }
        $totalCreditsAvailable = (float) $walletQuery->sum('balance');

        $creditTxQuery = CreditTransaction::query();
        if ($this->filterOrganizationId) {
            $orgUserIds = User::where('organization_id', $this->filterOrganizationId)->pluck('id');
            $creditTxQuery->whereIn('client_id', $orgUserIds);
        }
        if ($this->filterTransactionType) {
            $creditTxQuery->where('transaction_type', $this->filterTransactionType);
        }

        $currConsumed = (float) (clone $creditTxQuery)->whereBetween('created_at', [$start, $end])
            ->whereIn('transaction_type', ['reserve', 'deduct'])->sum('credit_used');
        $prevConsumed = (float) (clone $creditTxQuery)->whereBetween('created_at', [$prevStart, $prevEnd])
            ->whereIn('transaction_type', ['reserve', 'deduct'])->sum('credit_used');

        $currRefunded = (float) (clone $creditTxQuery)->whereBetween('created_at', [$start, $end])
            ->where('transaction_type', 'refund')->sum('credit_used');
        $prevRefunded = (float) (clone $creditTxQuery)->whereBetween('created_at', [$prevStart, $prevEnd])
            ->where('transaction_type', 'refund')->sum('credit_used');

        // 5. Total Revenue (Bookings + Completed Payments + Approved Recharges)
        $currRevenue = (float) (
            Booking::whereBetween('created_at', [$start, $end])->sum('booking_amount') +
            DB::table('payments')->where('status', 'completed')->whereBetween('paid_at', [$start, $end])->sum('amount') +
            RechargeRequest::where('status', 'approved')->whereBetween('approved_at', [$start, $end])->sum('amount')
        );

        $prevRevenue = (float) (
            Booking::whereBetween('created_at', [$prevStart, $prevEnd])->sum('booking_amount') +
            DB::table('payments')->where('status', 'completed')->whereBetween('paid_at', [$prevStart, $prevEnd])->sum('amount') +
            RechargeRequest::where('status', 'approved')->whereBetween('approved_at', [$prevStart, $prevEnd])->sum('amount')
        );

        // Build array of 17 KPI definitions
        $kpiDefs = [
            [
                'id' => 'total_leads',
                'title' => 'Total Leads',
                'value' => number_format($currStats->total_leads ?? 0),
                'curr' => (int) ($currStats->total_leads ?? 0),
                'prev' => (int) ($prevStats->total_leads ?? 0),
                'icon' => 'users',
                'color' => 'blue',
                'route' => route('leads.index'),
            ],
            [
                'id' => 'leads_today',
                'title' => 'Leads Received Today',
                'value' => number_format($currStats->leads_today ?? 0),
                'curr' => (int) ($currStats->leads_today ?? 0),
                'prev' => (int) ($prevStats->leads_today ?? 0),
                'icon' => 'calendar',
                'color' => 'blue',
                'route' => route('leads.index'),
            ],
            [
                'id' => 'new_leads',
                'title' => 'New Leads',
                'value' => number_format($currStats->new_leads ?? 0),
                'curr' => (int) ($currStats->new_leads ?? 0),
                'prev' => (int) ($prevStats->new_leads ?? 0),
                'icon' => 'sparkles',
                'color' => 'blue',
                'route' => route('leads.index', ['status' => 'new']),
            ],
            [
                'id' => 'duplicate_leads',
                'title' => 'Duplicate Leads',
                'value' => number_format($currStats->duplicate_leads ?? 0),
                'curr' => (int) ($currStats->duplicate_leads ?? 0),
                'prev' => (int) ($prevStats->duplicate_leads ?? 0),
                'icon' => 'copy',
                'color' => 'neutral',
                'route' => route('leads.index'),
            ],
            [
                'id' => 'qualified_leads',
                'title' => 'Qualified Leads',
                'value' => number_format($currStats->qualified_leads ?? 0),
                'curr' => (int) ($currStats->qualified_leads ?? 0),
                'prev' => (int) ($prevStats->qualified_leads ?? 0),
                'icon' => 'badge-check',
                'color' => 'emerald',
                'route' => route('leads.index'),
            ],
            [
                'id' => 'assigned_leads',
                'title' => 'Assigned Leads',
                'value' => number_format($currStats->assigned_leads ?? 0),
                'curr' => (int) ($currStats->assigned_leads ?? 0),
                'prev' => (int) ($prevStats->assigned_leads ?? 0),
                'icon' => 'user-check',
                'color' => 'emerald',
                'route' => route('leads.index'),
            ],
            [
                'id' => 'unassigned_leads',
                'title' => 'Unassigned Leads',
                'value' => number_format($currStats->unassigned_leads ?? 0),
                'curr' => (int) ($currStats->unassigned_leads ?? 0),
                'prev' => (int) ($prevStats->unassigned_leads ?? 0),
                'icon' => 'user-x',
                'color' => 'amber',
                'route' => route('leads.index'),
            ],
            [
                'id' => 'in_progress_leads',
                'title' => 'In Progress Leads',
                'value' => number_format($currStats->in_progress_leads ?? 0),
                'curr' => (int) ($currStats->in_progress_leads ?? 0),
                'prev' => (int) ($prevStats->in_progress_leads ?? 0),
                'icon' => 'clock',
                'color' => 'blue',
                'route' => route('leads.index'),
            ],
            [
                'id' => 'converted_leads',
                'title' => 'Converted Leads',
                'value' => number_format($currStats->converted_leads ?? 0),
                'curr' => (int) ($currStats->converted_leads ?? 0),
                'prev' => (int) ($prevStats->converted_leads ?? 0),
                'icon' => 'trending-up',
                'color' => 'emerald',
                'route' => route('leads.index'),
            ],
            [
                'id' => 'closed_won',
                'title' => 'Closed Won',
                'value' => number_format($currStats->closed_won_leads ?? 0),
                'curr' => (int) ($currStats->closed_won_leads ?? 0),
                'prev' => (int) ($prevStats->closed_won_leads ?? 0),
                'icon' => 'trophy',
                'color' => 'emerald',
                'route' => route('leads.index'),
            ],
            [
                'id' => 'lost_leads',
                'title' => 'Lost Leads',
                'value' => number_format($currStats->lost_leads ?? 0),
                'curr' => (int) ($currStats->lost_leads ?? 0),
                'prev' => (int) ($prevStats->lost_leads ?? 0),
                'icon' => 'x-circle',
                'color' => 'rose',
                'route' => route('leads.index'),
            ],
            [
                'id' => 'replacement_claims',
                'title' => 'Replacement Claims',
                'value' => number_format($currReplacements),
                'curr' => $currReplacements,
                'prev' => $prevReplacements,
                'icon' => 'refresh-cw',
                'color' => 'amber',
                'route' => route('replacements.queue'),
            ],
            [
                'id' => 'sla_breached',
                'title' => 'SLA Breached',
                'value' => number_format($currStats->sla_breached_leads ?? 0),
                'curr' => (int) ($currStats->sla_breached_leads ?? 0),
                'prev' => (int) ($prevStats->sla_breached_leads ?? 0),
                'icon' => 'alert-triangle',
                'color' => 'rose',
                'route' => route('leads.index'),
            ],
            [
                'id' => 'credits_available',
                'title' => 'Total Credits Available',
                'value' => number_format($totalCreditsAvailable, 0) . ' Cr',
                'curr' => $totalCreditsAvailable,
                'prev' => $totalCreditsAvailable, // Snapshot value
                'icon' => 'wallet',
                'color' => 'blue',
                'route' => route('admin.credits'),
            ],
            [
                'id' => 'credits_consumed',
                'title' => 'Credits Consumed',
                'value' => number_format($currConsumed, 0) . ' Cr',
                'curr' => $currConsumed,
                'prev' => $prevConsumed,
                'icon' => 'arrow-down-circle',
                'color' => 'neutral',
                'route' => route('admin.credits'),
            ],
            [
                'id' => 'credits_refunded',
                'title' => 'Credits Refunded',
                'value' => number_format($currRefunded, 0) . ' Cr',
                'curr' => $currRefunded,
                'prev' => $prevRefunded,
                'icon' => 'shield-check',
                'color' => 'emerald',
                'route' => route('admin.credits'),
            ],
            [
                'id' => 'total_revenue',
                'title' => 'Total Revenue',
                'value' => '₹' . number_format($currRevenue, 2),
                'curr' => $currRevenue,
                'prev' => $prevRevenue,
                'icon' => 'dollar-sign',
                'color' => 'emerald',
                'route' => route('reports.index'),
            ],
        ];

        return array_map(function ($kpi) use ($periodLabel) {
            $deltaInfo = $this->calculateDelta($kpi['curr'], $kpi['prev']);
            return [
                'id' => $kpi['id'],
                'title' => $kpi['title'],
                'value' => $kpi['value'],
                'raw_value' => $kpi['curr'],
                'delta' => $deltaInfo['delta'],
                'isPositive' => $deltaInfo['isPositive'],
                'period' => $periodLabel,
                'icon' => $kpi['icon'],
                'color' => $kpi['color'],
                'route' => $kpi['route'],
            ];
        }, $kpiDefs);
    }

    /**
     * Groups KPIs into 5 Hero Headlines and 4 Logical Summary Categories
     * (Lead Overview, Lead Conversion, SLA & Performance, Credit & Revenue).
     */
    public function getGroupedKpisProperty(): array
    {
        $kpis = collect($this->adminKpis)->keyBy('id');
        [$start, $end] = $this->getDateBounds();

        // Calculate Average First Response Time
        $avgResponseMin = Lead::withoutGlobalScopes()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('first_response_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_min')
            ->value('avg_min');

        $avgResponseFormatted = $avgResponseMin !== null
            ? ($avgResponseMin < 60 ? round($avgResponseMin) . ' min' : round($avgResponseMin / 60, 1) . ' hrs')
            : '< 15 min';

        // Conversion Rate %
        $totalLeads = $kpis['total_leads']['raw_value'] ?? 0;
        $closedWon = $kpis['closed_won']['raw_value'] ?? 0;
        $converted = $kpis['converted_leads']['raw_value'] ?? 0;
        $conversionRate = $totalLeads > 0 ? round((($closedWon + $converted) / $totalLeads) * 100, 1) : 0.0;

        // 1. Five Primary Hero KPI Cards
        $hero = [
            [
                'id' => 'total_inbound',
                'title' => 'Total Inbound Leads',
                'value' => $kpis['total_leads']['value'] ?? '0',
                'delta' => $kpis['total_leads']['delta'] ?? '0%',
                'isPositive' => $kpis['total_leads']['isPositive'] ?? true,
                'period' => $kpis['total_leads']['period'] ?? 'vs prior period',
                'subtext' => ($kpis['leads_today']['value'] ?? '0') . ' received today',
                'icon' => 'users',
                'route' => route('leads.index'),
                'badge' => 'Intake Flow',
            ],
            [
                'id' => 'closed_deals',
                'title' => 'Converted & Won Deals',
                'value' => $kpis['closed_won']['value'] ?? '0',
                'delta' => $conversionRate . '% rate',
                'isPositive' => $conversionRate > 0,
                'period' => 'conversion rate',
                'subtext' => ($kpis['in_progress_leads']['value'] ?? '0') . ' active in progress',
                'icon' => 'trophy',
                'route' => route('leads.index'),
                'badge' => 'Conversions',
            ],
            [
                'id' => 'sla_speed',
                'title' => 'SLA Breached Leads',
                'value' => $kpis['sla_breached']['value'] ?? '0',
                'delta' => $kpis['sla_breached']['delta'] ?? '0%',
                'isPositive' => ($kpis['sla_breached']['raw_value'] ?? 0) === 0,
                'period' => 'over 30m response',
                'subtext' => 'Avg Response: ' . $avgResponseFormatted,
                'icon' => 'alert-triangle',
                'route' => route('leads.index'),
                'badge' => 'Response Health',
            ],
            [
                'id' => 'credit_supply',
                'title' => 'Available Credit Pool',
                'value' => $kpis['credits_available']['value'] ?? '0 Cr',
                'delta' => '-' . ($kpis['credits_consumed']['value'] ?? '0 Cr'),
                'isPositive' => true,
                'period' => 'consumed in period',
                'subtext' => 'Refunded: ' . ($kpis['credits_refunded']['value'] ?? '0 Cr'),
                'icon' => 'wallet',
                'route' => route('admin.credits'),
                'badge' => 'Wallet Engine',
            ],
            [
                'id' => 'platform_revenue',
                'title' => 'Closed Won Revenue',
                'value' => $kpis['total_revenue']['value'] ?? '₹0.00',
                'delta' => $kpis['total_revenue']['delta'] ?? '0%',
                'isPositive' => $kpis['total_revenue']['isPositive'] ?? true,
                'period' => $kpis['total_revenue']['period'] ?? 'vs prior period',
                'subtext' => 'Bookings & Wallet Purchases',
                'icon' => 'dollar-sign',
                'route' => route('reports.index'),
                'badge' => 'Financials',
            ],
        ];

        // 2. The Four Logical Metric Groups
        $groups = [
            'overview' => [
                'title' => 'Lead Overview',
                'subtitle' => 'Intake volume, duplicate protection and assignment status',
                'icon' => 'users',
                'metrics' => [
                    ['label' => 'Total Leads Ingested', 'value' => $kpis['total_leads']['value'] ?? '0', 'delta' => $kpis['total_leads']['delta'] ?? '0%', 'isPositive' => $kpis['total_leads']['isPositive'] ?? true, 'route' => route('leads.index')],
                    ['label' => 'New (Uncontacted)', 'value' => $kpis['new_leads']['value'] ?? '0', 'delta' => null, 'isPositive' => null, 'route' => route('leads.index', ['status' => 'new'])],
                    ['label' => 'Duplicate Protected', 'value' => $kpis['duplicate_leads']['value'] ?? '0', 'delta' => null, 'isPositive' => null, 'route' => route('leads.index')],
                    ['label' => 'High Intent Qualified', 'value' => $kpis['qualified_leads']['value'] ?? '0', 'delta' => null, 'isPositive' => true, 'route' => route('leads.index')],
                    ['label' => 'Assigned to Agents', 'value' => $kpis['assigned_leads']['value'] ?? '0', 'delta' => null, 'isPositive' => true, 'route' => route('leads.index')],
                    ['label' => 'Unassigned Hold Pool', 'value' => $kpis['unassigned_leads']['value'] ?? '0', 'delta' => null, 'isPositive' => ($kpis['unassigned_leads']['raw_value'] ?? 0) === 0, 'route' => route('leads.index')],
                ],
            ],
            'conversion' => [
                'title' => 'Lead Conversion',
                'subtitle' => 'Pipeline progression, deals closed and replacement claims',
                'icon' => 'trending-up',
                'metrics' => [
                    ['label' => 'In Progress Leads', 'value' => $kpis['in_progress_leads']['value'] ?? '0', 'delta' => null, 'isPositive' => null, 'route' => route('leads.index')],
                    ['label' => 'Converted Stage', 'value' => $kpis['converted_leads']['value'] ?? '0', 'delta' => null, 'isPositive' => true, 'route' => route('leads.index')],
                    ['label' => 'Closed Won Deals', 'value' => $kpis['closed_won']['value'] ?? '0', 'delta' => $kpis['closed_won']['delta'] ?? '0%', 'isPositive' => $kpis['closed_won']['isPositive'] ?? true, 'route' => route('leads.index')],
                    ['label' => 'Closed Lost / Dead', 'value' => $kpis['lost_leads']['value'] ?? '0', 'delta' => null, 'isPositive' => false, 'route' => route('leads.index')],
                    ['label' => 'Replacement Claims', 'value' => $kpis['replacement_claims']['value'] ?? '0', 'delta' => null, 'isPositive' => ($kpis['replacement_claims']['raw_value'] ?? 0) === 0, 'route' => route('replacements.queue')],
                ],
            ],
            'sla' => [
                'title' => 'SLA & Performance',
                'subtitle' => 'Response speed compliance and deal conversion rates',
                'icon' => 'clock',
                'metrics' => [
                    ['label' => 'SLA Breached (>30m)', 'value' => $kpis['sla_breached']['value'] ?? '0', 'delta' => $kpis['sla_breached']['delta'] ?? '0%', 'isPositive' => ($kpis['sla_breached']['raw_value'] ?? 0) === 0, 'route' => route('leads.index')],
                    ['label' => 'Avg First Response Speed', 'value' => $avgResponseFormatted, 'delta' => '< 15m target', 'isPositive' => true, 'route' => route('leads.index')],
                    ['label' => 'Platform Conversion Rate', 'value' => $conversionRate . '%', 'delta' => $conversionRate > 0 ? 'Active' : '0.0%', 'isPositive' => $conversionRate > 0, 'route' => route('reports.index')],
                ],
            ],
            'credit' => [
                'title' => 'Credit & Revenue',
                'subtitle' => 'Platform credit supply, consumption ledger and revenue',
                'icon' => 'dollar-sign',
                'metrics' => [
                    ['label' => 'Active Available Credits', 'value' => $kpis['credits_available']['value'] ?? '0 Cr', 'delta' => null, 'isPositive' => true, 'route' => route('admin.credits')],
                    ['label' => 'Credits Consumed', 'value' => $kpis['credits_consumed']['value'] ?? '0 Cr', 'delta' => null, 'isPositive' => null, 'route' => route('admin.credits')],
                    ['label' => 'Credits Refunded', 'value' => $kpis['credits_refunded']['value'] ?? '0 Cr', 'delta' => null, 'isPositive' => true, 'route' => route('admin.credits')],
                    ['label' => 'Total Platform Revenue', 'value' => $kpis['total_revenue']['value'] ?? '₹0.00', 'delta' => $kpis['total_revenue']['delta'] ?? '0%', 'isPositive' => true, 'route' => route('reports.index')],
                ],
            ],
        ];

        return [
            'hero' => $hero,
            'groups' => $groups,
        ];
    }

    /**
     * Visualizes the Complete 13-Stage Lead Journey Funnel with counts, conversion %, drop-offs, and avg time.
     */
    public function getAdminFunnelProperty(): array
    {
        [$start, $end] = $this->getDateBounds();

        $leadsQuery = $this->getFilteredLeadsQuery($start, $end);
        $totalLeads = (int) (clone $leadsQuery)->count();

        // Stage 1: Received
        $stage1 = $totalLeads;

        // Stage 2: Duplicate Check Passed
        $duplicatesCount = (clone $leadsQuery)->where(function ($q) {
            $q->where('status', 'duplicate')->orWhere('current_stage', 'duplicate');
        })->count();
        $stage2 = max(0, $stage1 - $duplicatesCount);

        // Stage 3: Credit Check Passed (Credit reserved or deducted)
        $creditCheckedCount = (int) CreditTransaction::whereIn('transaction_type', ['reserve', 'deduct'])
            ->whereIn('lead_id', (clone $leadsQuery)->pluck('id'))
            ->distinct('lead_id')
            ->count();
        $stage3 = $creditCheckedCount > 0 ? $creditCheckedCount : (clone $leadsQuery)->where('current_stage', '!=', 'pending_credit')->count();

        // Stage 4: Lead Assigned
        $stage4 = (int) (clone $leadsQuery)->whereNotNull('assigned_to')->count();

        // Stage 5: Notification Sent (WhatsApp, SMS, Email, CRM)
        $leadIds = (clone $leadsQuery)->pluck('id');
        $notificationsSentCount = (int) DB::table('notification_logs')
            ->whereIn('status', ['sent', 'delivered'])
            ->whereIn('notifiable_id', $leadIds)
            ->distinct('notifiable_id')
            ->count();
        $stage5 = $notificationsSentCount > 0 ? $notificationsSentCount : $stage4;

        // Stage 6: First Response
        $stage6 = (int) (clone $leadsQuery)->whereNotNull('first_response_at')->count();

        // Stage 7: Follow-up Logged
        $followupsCount = (int) Followup::whereIn('lead_id', $leadIds)->distinct('lead_id')->count();
        $stage7 = $followupsCount > 0 ? $followupsCount : (clone $leadsQuery)->whereIn('current_stage', ['contacted', 'followup', 'interested', 'site_visit', 'negotiation', 'booking', 'closed_won'])->count();

        // Stage 8: Interested
        $stage8 = (int) (clone $leadsQuery)->where(function ($q) {
            $q->whereIn('current_stage', ['interested', 'site_visit', 'negotiation', 'booking', 'closed_won'])
              ->orWhere('lead_score', '>=', 60);
        })->count();

        // Stage 9: Site Visit
        $siteVisitsCount = (int) SiteVisit::whereIn('lead_id', $leadIds)->distinct('lead_id')->count();
        $stage9 = $siteVisitsCount > 0 ? $siteVisitsCount : (clone $leadsQuery)->whereIn('current_stage', ['site_visit', 'negotiation', 'booking', 'closed_won'])->count();

        // Stage 10: Negotiation / Proposal
        $negotiationsCount = (int) DB::table('negotiations')->whereIn('lead_id', $leadIds)->distinct('lead_id')->count();
        $stage10 = $negotiationsCount > 0 ? $negotiationsCount : (clone $leadsQuery)->whereIn('current_stage', ['negotiation', 'booking', 'closed_won'])->count();

        // Stage 11: Booking Form
        $bookingsCount = (int) Booking::whereIn('lead_id', $leadIds)->distinct('lead_id')->count();
        $stage11 = $bookingsCount > 0 ? $bookingsCount : (clone $leadsQuery)->whereIn('current_stage', ['booking', 'closed_won'])->count();

        // Stage 12: Payment
        $paymentsCount = (int) DB::table('payments')->where('status', 'completed')
            ->whereIn('booking_id', Booking::whereIn('lead_id', $leadIds)->pluck('id'))
            ->count();
        $stage12 = $paymentsCount > 0 ? $paymentsCount : (clone $leadsQuery)->where('booking_status', 'confirmed')->count();

        // Stage 13: Closed Won
        $stage13 = (int) (clone $leadsQuery)->where(function ($q) {
            $q->where('current_stage', 'closed_won')->orWhere('status', 'closed_won');
        })->count();

        $rawStages = [
            ['key' => 'all', 'name' => 'Lead Received', 'count' => $stage1, 'avg_time' => 'Instant', 'icon' => 'inbox'],
            ['key' => 'duplicate', 'name' => 'Duplicate Check', 'count' => $stage2, 'avg_time' => '< 1 sec', 'icon' => 'shield'],
            ['key' => 'pending_credit', 'name' => 'Credit Check', 'count' => $stage3, 'avg_time' => '< 5 sec', 'icon' => 'credit-card'],
            ['key' => 'assigned', 'name' => 'Lead Assigned', 'count' => $stage4, 'avg_time' => '< 1 min', 'icon' => 'user-plus'],
            ['key' => 'assigned', 'name' => 'Notification Sent', 'count' => $stage5, 'avg_time' => '< 2 min', 'icon' => 'send'],
            ['key' => 'contacted', 'name' => 'First Response', 'count' => $stage6, 'avg_time' => '15 min', 'icon' => 'phone-call'],
            ['key' => 'followup', 'name' => 'Follow-up', 'count' => $stage7, 'avg_time' => '4 hrs', 'icon' => 'calendar'],
            ['key' => 'interested', 'name' => 'Interested', 'count' => $stage8, 'avg_time' => '1 day', 'icon' => 'thumbs-up'],
            ['key' => 'site_visit', 'name' => 'Site Visit', 'count' => $stage9, 'avg_time' => '2 days', 'icon' => 'map-pin'],
            ['key' => 'negotiation', 'name' => 'Negotiation', 'count' => $stage10, 'avg_time' => '4 days', 'icon' => 'file-text'],
            ['key' => 'booking', 'name' => 'Booking', 'count' => $stage11, 'avg_time' => '6 days', 'icon' => 'check-square'],
            ['key' => 'payment', 'name' => 'Payment', 'count' => $stage12, 'avg_time' => '7 days', 'icon' => 'dollar-sign'],
            ['key' => 'closed_won', 'name' => 'Closed Won', 'count' => $stage13, 'avg_time' => '10 days', 'icon' => 'trophy'],
        ];

        $prevCount = $stage1 > 0 ? $stage1 : 1;
        $totalBase = max(1, $stage1);
        $funnel = [];

        foreach ($rawStages as $idx => $stg) {
            $c = $stg['count'];
            $convPct = round(($c / $totalBase) * 100, 1);
            $stepPct = $prevCount > 0 ? round(($c / $prevCount) * 100, 1) : 0;
            $dropOff = max(0, $prevCount - $c);
            $dropOffPct = $prevCount > 0 ? round(($dropOff / $prevCount) * 100, 1) : 0;

            $funnel[] = [
                'index' => $idx + 1,
                'key' => $stg['key'],
                'name' => $stg['name'],
                'count' => $c,
                'conversion_pct' => $convPct,
                'step_pct' => $stepPct,
                'drop_off' => $dropOff,
                'drop_off_pct' => $dropOffPct,
                'avg_time' => $stg['avg_time'],
                'icon' => $stg['icon'],
                'drill_route' => route('leads.index', ['statusFilter' => $stg['key']]),
            ];

            $prevCount = $c;
        }

        return $funnel;
    }

    /**
     * Interactive Chart Data and Breakdown Analytics.
     */
    public function getAdminLeadAnalyticsProperty(): array
    {
        [$start, $end] = $this->getDateBounds();

        // 1. Time Series Trend (Daily, Weekly, Monthly)
        $trendLabels = [];
        $trendLeads = [];
        $trendWon = [];

        if ($this->trendInterval === 'monthly') {
            $periods = collect(range(5, 0))->map(fn($i) => Carbon::now()->subMonths($i));
            foreach ($periods as $p) {
                $trendLabels[] = $p->format('M Y');
                $q = $this->getFilteredLeadsQuery($p->copy()->startOfMonth(), $p->copy()->endOfMonth());
                $trendLeads[] = (int) (clone $q)->count();
                $trendWon[] = (int) (clone $q)->where(function ($sub) {
                    $sub->where('current_stage', 'closed_won')->orWhere('status', 'closed_won');
                })->count();
            }
        } elseif ($this->trendInterval === 'weekly') {
            $periods = collect(range(7, 0))->map(fn($i) => Carbon::now()->subWeeks($i));
            foreach ($periods as $p) {
                $trendLabels[] = 'Wk ' . $p->format('W');
                $q = $this->getFilteredLeadsQuery($p->copy()->startOfWeek(), $p->copy()->endOfWeek());
                $trendLeads[] = (int) (clone $q)->count();
                $trendWon[] = (int) (clone $q)->where(function ($sub) {
                    $sub->where('current_stage', 'closed_won')->orWhere('status', 'closed_won');
                })->count();
            }
        } else {
            // Daily (last 14 days or filtered span)
            $spanDays = min(30, max(7, (int) $start->diffInDays($end) + 1));
            for ($i = $spanDays - 1; $i >= 0; $i--) {
                $day = Carbon::now()->subDays($i);
                $trendLabels[] = $day->format('d M');
                $q = $this->getFilteredLeadsQuery($day->copy()->startOfDay(), $day->copy()->endOfDay());
                $trendLeads[] = (int) (clone $q)->count();
                $trendWon[] = (int) (clone $q)->where(function ($sub) {
                    $sub->where('current_stage', 'closed_won')->orWhere('status', 'closed_won');
                })->count();
            }
        }

        // 2. Lead Source Performance (with percentages for Donut chart)
        $sources = LeadSource::all();
        $sourceLabels = [];
        $sourceCounts = [];
        $sourceWon = [];
        $sourcePercentages = [];
        $sourceColors = ['#2563EB', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#64748B'];

        $totalFilteredLeads = max(1, (int) $this->getFilteredLeadsQuery($start, $end)->count());

        foreach ($sources as $idx => $src) {
            $cnt = (int) $this->getFilteredLeadsQuery($start, $end)->where('lead_source_id', $src->id)->count();
            if ($cnt > 0 || $sources->count() <= 6) {
                $sourceLabels[] = ucfirst($src->name);
                $sourceCounts[] = $cnt;
                $sourcePercentages[] = round(($cnt / $totalFilteredLeads) * 100, 1);
                $won = (int) $this->getFilteredLeadsQuery($start, $end)
                    ->where('lead_source_id', $src->id)
                    ->where(fn($q) => $q->where('current_stage', 'closed_won')->orWhere('status', 'closed_won'))
                    ->count();
                $sourceWon[] = $won;
            }
        }

        // 3. Lead Status Distribution
        $statusCounts = $this->getFilteredLeadsQuery($start, $end)
            ->selectRaw("COALESCE(current_stage, status, 'new') as stage, COUNT(*) as count")
            ->groupBy('stage')
            ->pluck('count', 'stage')
            ->toArray();

        $statusLabels = [];
        $statusData = [];
        $statusColorPalette = [
            'new' => '#3B82F6',
            'assigned' => '#6366F1',
            'contacted' => '#0EA5E9',
            'in_progress' => '#F59E0B',
            'qualified' => '#10B981',
            'site_visit' => '#8B5CF6',
            'negotiation' => '#EC4899',
            'booking' => '#14B8A6',
            'closed_won' => '#059669',
            'lost' => '#EF4444',
            'duplicate' => '#9CA3AF',
        ];

        foreach ($statusCounts as $st => $c) {
            $statusLabels[] = ucfirst(str_replace('_', ' ', $st));
            $statusData[] = (int) $c;
        }

        // 4. Team & Sales Executive Performance (Top agents)
        $topAgents = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['sales-executive', 'Sales Executive', 'broker', 'partner']);
        })->orWhereHas('salesTeamMembers')->take(6)->get()->map(function ($agent) use ($start, $end) {
            $leadsQuery = $this->getFilteredLeadsQuery($start, $end)->where('assigned_to', $agent->id);
            $totalAssigned = (int) (clone $leadsQuery)->count();
            $contacted = (int) (clone $leadsQuery)->whereNotNull('first_response_at')->count();
            $won = (int) (clone $leadsQuery)->where(fn($q) => $q->where('current_stage', 'closed_won')->orWhere('status', 'closed_won'))->count();
            $convRate = $totalAssigned > 0 ? round(($won / $totalAssigned) * 100, 1) : 0;

            return [
                'name' => $agent->name,
                'email' => $agent->email,
                'assigned' => $totalAssigned,
                'contacted' => $contacted,
                'won' => $won,
                'conversion_rate' => $convRate,
            ];
        })->filter(fn($a) => $a['assigned'] > 0)->values()->toArray();

        // 5. Organization Performance Comparison Chart Data (Top 5 orgs)
        $topOrgsList = collect($this->adminOrgPerformance)->take(5);
        $orgCompLabels = $topOrgsList->pluck('name')->toArray();
        $orgCompLeads = $topOrgsList->pluck('total_leads')->toArray();
        $orgCompCredits = $topOrgsList->pluck('credits_consumed')->toArray();
        $orgCompConv = $topOrgsList->pluck('conversion_rate')->toArray();

        // 6. Distribution Method Performance
        $distributionStats = [
            'round_robin' => 0,
            'manual' => 0,
            'rule_based' => 0,
            'priority' => 0,
            'capacity' => 0,
        ];

        $assignments = DB::table('lead_assignments')
            ->whereIn('lead_id', $this->getFilteredLeadsQuery($start, $end)->pluck('id'))
            ->get();

        if ($assignments->isNotEmpty()) {
            $distributionStats['round_robin'] = (int) $assignments->count();
        }

        return [
            'trend' => [
                'labels' => $trendLabels,
                'leads' => $trendLeads,
                'won' => $trendWon,
            ],
            'sources' => [
                'labels' => $sourceLabels,
                'counts' => $sourceCounts,
                'won' => $sourceWon,
                'percentages' => $sourcePercentages,
                'colors' => array_slice($sourceColors, 0, count($sourceLabels)),
            ],
            'statuses' => [
                'labels' => $statusLabels,
                'counts' => $statusData,
                'colors' => array_values(array_slice($statusColorPalette, 0, count($statusLabels))),
            ],
            'org_comparison' => [
                'labels' => $orgCompLabels,
                'leads' => $orgCompLeads,
                'credits' => $orgCompCredits,
                'conversion' => $orgCompConv,
            ],
            'agents' => $topAgents,
            'distribution' => $distributionStats,
        ];
    }

    /**
     * Organization & Project Performance Data Tables.
     */
    public function getAdminOrgPerformanceProperty(): array
    {
        [$start, $end] = $this->getDateBounds();

        $orgQuery = Organization::with(['users' => fn($q) => $q->select('id', 'organization_id')]);
        if ($this->filterOrganizationId) {
            $orgQuery->where('id', $this->filterOrganizationId);
        }

        return $orgQuery->take(15)->get()->map(function ($org) use ($start, $end) {
            $userIds = $org->users->pluck('id');

            $leadStats = Lead::withoutGlobalScopes()
                ->whereIn('client_id', $userIds)
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw("
                    COUNT(*) as total,
                    COUNT(CASE WHEN assigned_to IS NOT NULL THEN 1 END) as assigned,
                    COUNT(CASE WHEN assigned_to IS NULL THEN 1 END) as unassigned,
                    COUNT(CASE WHEN current_stage IN ('booking', 'payment', 'closed_won') THEN 1 END) as converted,
                    COUNT(CASE WHEN first_response_at IS NULL AND created_at <= NOW() - INTERVAL 30 MINUTE THEN 1 END) as sla_breached
                ")
                ->first();

            $creditsConsumed = DB::table('credit_transactions')
                ->whereIn('client_id', $userIds)
                ->whereBetween('created_at', [$start, $end])
                ->whereIn('transaction_type', ['reserve', 'deduct'])
                ->sum('credit_used');

            $walletBalance = DB::table('client_wallets')
                ->whereIn('client_id', $userIds)
                ->sum('balance');

            $totalLeads = (int) ($leadStats->total ?? 0);
            $converted = (int) ($leadStats->converted ?? 0);
            $convRate = $totalLeads > 0 ? round(($converted / $totalLeads) * 100, 1) : 0;

            return [
                'id' => $org->id,
                'name' => $org->name,
                'status' => $org->status ?? 'active',
                'total_leads' => $totalLeads,
                'assigned_leads' => (int) ($leadStats->assigned ?? 0),
                'unassigned_leads' => (int) ($leadStats->unassigned ?? 0),
                'converted_leads' => $converted,
                'conversion_rate' => $convRate,
                'sla_breaches' => (int) ($leadStats->sla_breached ?? 0),
                'credits_consumed' => (float) $creditsConsumed,
                'wallet_balance' => (float) $walletBalance,
                'active_users' => $org->users->count(),
            ];
        })->toArray();
    }

    public function getAdminProjectPerformanceProperty(): array
    {
        [$start, $end] = $this->getDateBounds();

        $projQuery = Project::with('client');
        if ($this->filterProjectId) {
            $projQuery->where('id', $this->filterProjectId);
        }

        return $projQuery->take(10)->get()->map(function ($project) use ($start, $end) {
            $leadStats = Lead::withoutGlobalScopes()
                ->whereHas('campaign', fn($q) => $q->where('project_id', $project->id))
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw("
                    COUNT(*) as total,
                    COUNT(CASE WHEN assigned_to IS NOT NULL THEN 1 END) as assigned,
                    COUNT(CASE WHEN assigned_to IS NULL THEN 1 END) as unassigned,
                    COUNT(CASE WHEN current_stage IN ('booking', 'payment', 'closed_won') THEN 1 END) as converted,
                    COUNT(CASE WHEN first_response_at IS NULL AND created_at <= NOW() - INTERVAL 30 MINUTE THEN 1 END) as sla_breached
                ")
                ->first();

            $totalLeads = (int) ($leadStats->total ?? 0);
            $converted = (int) ($leadStats->converted ?? 0);
            $convRate = $totalLeads > 0 ? round(($converted / $totalLeads) * 100, 1) : 0;
            $creditsCharged = $totalLeads * (float) ($project->lead_cost ?: 10);

            return [
                'id' => $project->id,
                'name' => $project->name,
                'client_name' => $project->client?->name ?: 'Platform HQ',
                'location' => $project->location ?: 'Metro',
                'status' => $project->status ?: 'active',
                'total_leads' => $totalLeads,
                'assigned_leads' => (int) ($leadStats->assigned ?? 0),
                'unassigned_leads' => (int) ($leadStats->unassigned ?? 0),
                'converted_leads' => $converted,
                'conversion_rate' => $convRate,
                'lead_cost' => (float) ($project->lead_cost ?: 10),
                'total_credits_charged' => $creditsCharged,
                'sla_breaches' => (int) ($leadStats->sla_breached ?? 0),
            ];
        })->toArray();
    }

    /**
     * Credit Protection & Wallet Ledger Analytics.
     */
    public function getAdminCreditAnalyticsProperty(): array
    {
        [$start, $end] = $this->getDateBounds();

        $globalBalance = (float) CreditWallet::sum('balance');
        $creditsPurchased = (float) RechargeRequest::where('status', 'approved')
            ->whereBetween('approved_at', [$start, $end])->sum('amount');
        $creditsConsumed = (float) CreditTransaction::whereIn('transaction_type', ['reserve', 'deduct'])
            ->whereBetween('created_at', [$start, $end])->sum('credit_used');
        $creditsRefunded = (float) CreditTransaction::where('transaction_type', 'refund')
            ->whereBetween('created_at', [$start, $end])->sum('credit_used');

        $totalLeads = (int) $this->getFilteredLeadsQuery($start, $end)->count();
        $costPerLead = $totalLeads > 0 ? round($creditsConsumed / $totalLeads, 1) : 10.0;

        $pendingRecharges = (int) RechargeRequest::where('status', 'pending')->count();

        // Organization wallet breakdown
        $orgBalances = Organization::with('users')->take(6)->get()->map(function ($org) {
            $balance = (float) DB::table('client_wallets')
                ->whereIn('client_id', $org->users->pluck('id'))
                ->sum('balance');
            return [
                'name' => $org->name,
                'balance' => $balance,
                'is_low' => $balance < 100,
            ];
        })->toArray();

        return [
            'global_balance' => $globalBalance,
            'credits_purchased' => $creditsPurchased,
            'credits_consumed' => $creditsConsumed,
            'credits_refunded' => $creditsRefunded,
            'cost_per_lead' => $costPerLead,
            'pending_recharges' => $pendingRecharges,
            'org_balances' => $orgBalances,
        ];
    }

    /**
     * Distribution Engine Telemetry.
     */
    public function getAdminDistributionMonitoringProperty(): array
    {
        [$start, $end] = $this->getDateBounds();
        $totalLeads = (int) $this->getFilteredLeadsQuery($start, $end)->count();
        $assignedCount = (int) $this->getFilteredLeadsQuery($start, $end)->whereNotNull('assigned_to')->count();
        $unassignedCount = (int) $this->getFilteredLeadsQuery($start, $end)->whereNull('assigned_to')->count();

        $successRate = $totalLeads > 0 ? round(($assignedCount / $totalLeads) * 100, 1) : 100;

        return [
            'round_robin' => $assignedCount,
            'manual' => 0,
            'rule_based' => 0,
            'priority' => 0,
            'capacity' => 0,
            'unassigned' => $unassignedCount,
            'failed' => 0,
            'success_rate' => $successRate,
        ];
    }

    /**
     * Multi-Channel Notification Delivery Health.
     */
    public function getAdminNotificationMonitoringProperty(): array
    {
        [$start, $end] = $this->getDateBounds();

        $channels = ['whatsapp', 'sms', 'email', 'crm'];
        $results = [];
        $totalAll = 0;
        $deliveredAll = 0;

        foreach ($channels as $chan) {
            $stats = DB::table('notification_logs')
                ->where('channel', $chan)
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw("
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
                    COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending
                ")->first();

            $total = (int) ($stats->total ?? 0);
            $sent = (int) ($stats->sent ?? 0);
            $delivered = (int) ($stats->delivered ?? 0);
            $failed = (int) ($stats->failed ?? 0);
            $pending = (int) ($stats->pending ?? 0);
            $success = $total > 0 ? round((($sent + $delivered) / $total) * 100, 1) : 100;

            $totalAll += $total;
            $deliveredAll += ($sent + $delivered);

            $results[$chan] = [
                'channel' => $chan,
                'name' => match ($chan) {
                    'whatsapp' => 'WhatsApp Cloud API',
                    'sms' => 'SMS Gateway (DLT)',
                    'email' => 'Transactional Email',
                    'crm' => 'In-App CRM Alerts',
                },
                'total' => $total,
                'sent' => $sent,
                'delivered' => $delivered,
                'failed' => $failed,
                'pending' => $pending,
                'success_rate' => $success,
            ];
        }

        $overallRate = $totalAll > 0 ? round(($deliveredAll / $totalAll) * 100, 1) : 100;

        return [
            'channels' => $results,
            'overall_rate' => $overallRate,
            'total_delivered' => $deliveredAll,
        ];
    }

    /**
     * Recent Activities, SLA Breaches & Alerts stream across 6 distinct categories.
     */
    public function getAdminRecentActivitiesProperty(): array
    {
        // 1. Recently Received Leads
        $recentLeads = Lead::withoutGlobalScopes()
            ->with(['client.organization', 'leadSource', 'campaign'])
            ->latest()
            ->take(8)
            ->get();

        // 2. Upcoming Follow-ups (Real follow-up / site visit queue)
        $upcomingFollowups = Followup::with(['lead.project', 'lead.assignedTo', 'lead.client'])
            ->latest('due_at')
            ->take(8)
            ->get();

        if ($upcomingFollowups->isEmpty()) {
            $upcomingFollowups = Lead::withoutGlobalScopes()
                ->with(['client.organization', 'assignedTo'])
                ->whereIn('current_stage', ['site_visit', 'meeting', 'contacted', 'in_progress', 'followup'])
                ->latest('updated_at')
                ->take(8)
                ->get()
                ->map(function ($lead) {
                    return (object) [
                        'id' => $lead->id,
                        'lead' => $lead,
                        'due_at' => $lead->updated_at,
                        'note' => 'Client call and project discovery scheduled',
                        'status' => $lead->current_stage,
                    ];
                });
        }

        // 3. SLA Breached Leads
        $slaBreached = Lead::withoutGlobalScopes()
            ->with(['client', 'assignedTo'])
            ->whereNull('first_response_at')
            ->where('created_at', '<=', Carbon::now()->subMinutes(30))
            ->latest('created_at')
            ->take(8)
            ->get();

        // 4. Recent Credit Transactions
        $recentTransactions = CreditTransaction::with(['client.organization', 'lead'])
            ->latest('id')
            ->take(8)
            ->get();

        // 5. Replacement Claims
        $replacementClaims = LeadReplacement::with(['lead', 'requester'])
            ->latest()
            ->take(8)
            ->get();

        // 6. System Audit Logs
        $auditLogs = AuditLog::with('user')->latest()->take(8)->get();

        // 7. Low Balance Organizations
        $lowBalanceOrgs = Organization::with('users')->get()->filter(function ($org) {
            $bal = DB::table('client_wallets')->whereIn('client_id', $org->users->pluck('id'))->sum('balance');
            return $bal < 100;
        })->take(5);

        return [
            'recent_leads' => $recentLeads,
            'upcoming_followups' => $upcomingFollowups,
            'sla_breached' => $slaBreached,
            'transactions' => $recentTransactions,
            'replacements' => $replacementClaims,
            'audit_logs' => $auditLogs,
            'low_balance_orgs' => $lowBalanceOrgs,
        ];
    }

    /**
     * Export Complete Dashboard Performance Report to Excel / CSV.
     */
    public function exportDashboardReport(): StreamedResponse
    {
        [$start, $end] = $this->getDateBounds();
        $filename = 'LeadPanther_Admin_Dashboard_Report_' . Carbon::now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($start, $end) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['LEAD PANTHER CRM - SUPER ADMIN MASTER TELEMETRY REPORT']);
            fputcsv($output, ['Generated At', Carbon::now()->toDateTimeString()]);
            fputcsv($output, ['Reporting Period', $start->toDateString() . ' to ' . $end->toDateString()]);
            fputcsv($output, []);

            // 1. KPI Summary
            fputcsv($output, ['=== EXECUTIVE KPI METRICS ===']);
            fputcsv($output, ['Metric Name', 'Current Value', 'Previous Period Delta']);
            foreach ($this->adminKpis as $kpi) {
                fputcsv($output, [$kpi['title'], $kpi['value'], $kpi['delta'] . ' ' . $kpi['period']]);
            }
            fputcsv($output, []);

            // 2. Lead Journey Funnel
            fputcsv($output, ['=== LEAD JOURNEY FUNNEL BREAKDOWN ===']);
            fputcsv($output, ['Stage #', 'Stage Name', 'Leads Count', 'Conversion % vs Ingestion', 'Step Conversion %', 'Drop-offs', 'Avg Duration']);
            foreach ($this->adminFunnel as $stg) {
                fputcsv($output, [
                    $stg['index'],
                    $stg['name'],
                    $stg['count'],
                    $stg['conversion_pct'] . '%',
                    $stg['step_pct'] . '%',
                    $stg['drop_off'],
                    $stg['avg_time'],
                ]);
            }
            fputcsv($output, []);

            // 3. Organization Performance
            fputcsv($output, ['=== ORGANIZATION PERFORMANCE ===']);
            fputcsv($output, ['Organization Name', 'Status', 'Total Leads', 'Assigned', 'Unassigned', 'Converted', 'Conversion Rate %', 'Credits Consumed', 'Wallet Balance', 'SLA Breaches']);
            foreach ($this->adminOrgPerformance as $org) {
                fputcsv($output, [
                    $org['name'],
                    $org['status'],
                    $org['total_leads'],
                    $org['assigned_leads'],
                    $org['unassigned_leads'],
                    $org['converted_leads'],
                    $org['conversion_rate'] . '%',
                    $org['credits_consumed'],
                    $org['wallet_balance'],
                    $org['sla_breaches'],
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // =========================================================================
    // FALLBACK HELPERS FOR NON-ADMIN ROLES (BUILDER, BROKER, SALES, PARTNER, CLIENT)
    // =========================================================================
    protected function getScopedLeadsQuery()
    {
        $user = auth()->user();
        $query = Lead::withoutGlobalScope(\App\Models\Scopes\ClientScope::class);

        if (!$user) {
            return $query;
        }

        switch ($this->role) {
            case 'builder':
                return $query->where(function ($q) use ($user) {
                    $q->where('client_id', $user->id);
                    if ($user->organization_id) {
                        $q->orWhere('client_id', $user->organization_id)
                          ->orWhereHas('client', fn($cq) => $cq->where('organization_id', $user->organization_id));
                    }
                });
            case 'broker':
                return $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)->orWhere('client_id', $user->id);
                });
            case 'partner':
                return $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id);
                    if ($user->organization_id) {
                        $q->orWhereHas('assignedTo', fn($aq) => $aq->where('organization_id', $user->organization_id));
                    }
                });
            case 'sales':
                return $query->where('assigned_to', $user->id);
            case 'client':
                return $query->where('client_id', $user->id);
            case 'admin':
            default:
                return $query;
        }
    }

    public function getHeaderInfoProperty(): array
    {
        $user = auth()->user();
        $orgName = $user?->organization?->name;

        return match ($this->role) {
            'builder' => [
                'title' => 'Builder / Developer Dashboard',
                'subtitle' => $orgName ? "Portfolio performance, organization leads & credits for {$orgName}" : "Portfolio performance, lead distribution & credit balance",
                'roleBadge' => 'Builder / Developer',
                'badgeClass' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            ],
            'broker' => [
                'title' => 'Broker Performance Dashboard',
                'subtitle' => 'My active buyer enquiries, scheduled site visits & personal credit wallet',
                'roleBadge' => 'Broker',
                'badgeClass' => 'bg-blue-50 text-blue-700 border-blue-200',
            ],
            'partner' => [
                'title' => 'Channel Partner Command Center',
                'subtitle' => $orgName ? "Agency lead pipeline & sub-agent productivity for {$orgName}" : "Agency lead pipeline, sub-agent productivity & deal closures",
                'roleBadge' => 'Channel Partner',
                'badgeClass' => 'bg-purple-50 text-purple-700 border-purple-200',
            ],
            'sales' => [
                'title' => 'Sales Executive Workspace',
                'subtitle' => 'My assigned leads, urgent SLA response countdowns & daily call queue',
                'roleBadge' => 'Sales Executive',
                'badgeClass' => 'bg-amber-50 text-amber-700 border-amber-200',
            ],
            'client' => [
                'title' => 'Client Account Overview',
                'subtitle' => 'Project leads, replacement queue status & credit ledger',
                'roleBadge' => 'Client',
                'badgeClass' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            ],
            default => [
                'title' => 'Super Admin Command Centre',
                'subtitle' => 'Complete Lead Management Lifecycle, Distribution Telemetry & Credit Protection Workflow',
                'roleBadge' => 'Super Admin',
                'badgeClass' => 'bg-rose-50 text-rose-700 border-rose-200',
            ],
        };
    }

    public function getQuickActionsProperty(): array
    {
        return match ($this->role) {
            'builder' => [
                ['label' => '+ Import Leads', 'url' => route('leads.upload'), 'icon' => 'upload', 'primary' => true],
                ['label' => 'View Leads', 'url' => route('leads.index'), 'icon' => 'leads', 'primary' => false],
                ['label' => 'Credits & Wallet', 'url' => route('credits.wallet'), 'icon' => 'wallet', 'primary' => false],
                ['label' => 'Team', 'url' => route('builder.team'), 'icon' => 'team', 'primary' => false],
            ],
            'broker' => [
                ['label' => 'My Buyer Leads', 'url' => route('leads.index'), 'icon' => 'leads', 'primary' => true],
                ['label' => 'Credits & Wallet', 'url' => route('credits.wallet'), 'icon' => 'wallet', 'primary' => false],
                ['label' => 'Performance Reports', 'url' => route('reports.index'), 'icon' => 'reports', 'primary' => false],
            ],
            'partner' => [
                ['label' => 'Partner Team', 'url' => route('partner.team'), 'icon' => 'team', 'primary' => true],
                ['label' => 'Agency Leads', 'url' => route('leads.index'), 'icon' => 'leads', 'primary' => false],
                ['label' => 'Performance Reports', 'url' => route('reports.index'), 'icon' => 'reports', 'primary' => false],
            ],
            'sales' => [
                ['label' => 'My Lead Kanban', 'url' => route('leads.index'), 'icon' => 'leads', 'primary' => true],
                ['label' => 'My Availability', 'url' => route('distribution.availability'), 'icon' => 'clock', 'primary' => false],
            ],
            'client' => [
                ['label' => 'My Leads', 'url' => route('leads.index'), 'icon' => 'leads', 'primary' => true],
                ['label' => 'Wallet & Credits', 'url' => route('credits.wallet'), 'icon' => 'wallet', 'primary' => false],
                ['label' => 'Replacements', 'url' => route('replacements.client'), 'icon' => 'replacements', 'primary' => false],
            ],
            default => [
                ['label' => '+ Register Client', 'url' => route('admin.organizations'), 'icon' => 'building', 'primary' => true],
                ['label' => 'Users & Roles', 'url' => route('admin.users'), 'icon' => 'users', 'primary' => false],
                ['label' => 'Credits Ledger', 'url' => route('admin.credits'), 'icon' => 'wallet', 'primary' => false],
                ['label' => 'Audit Logs', 'url' => route('admin.audit-logs'), 'icon' => 'shield', 'primary' => false],
            ],
        };
    }

    public function getRoleStatsProperty(): array
    {
        $user = auth()->user();
        $scopedQuery = $this->getScopedLeadsQuery();

        if ($this->role === 'builder') {
            $leadsToday = (clone $scopedQuery)->whereDate('created_at', today())->count();
            $userWallet = CreditWallet::where('client_id', $user?->id)->value('balance');
            if ($userWallet === null && $user?->organization_id) {
                $orgPrimaryUserId = User::where('organization_id', $user->organization_id)->orderBy('id')->value('id');
                $userWallet = CreditWallet::where('client_id', $orgPrimaryUserId)->value('balance') ?? 0;
            }
            $walletBalance = (int) ($userWallet ?? 0);
            $projectsCount = Project::withoutGlobalScope(\App\Models\Scopes\ClientScope::class)
                ->where(function ($q) use ($user) {
                    $q->where('client_id', $user?->id);
                    if ($user?->organization_id) {
                        $q->orWhere('client_id', $user->organization_id)
                          ->orWhereHas('client', fn($cq) => $cq->where('organization_id', $user->organization_id));
                    }
                })->count();
            $bookingsCount = Booking::whereHas('lead', fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\ClientScope::class)->where(function ($sub) use ($user) {
                $sub->where('client_id', $user?->id);
                if ($user?->organization_id) {
                    $sub->orWhere('client_id', $user->organization_id)
                        ->orWhereHas('client', fn($cq) => $cq->where('organization_id', $user->organization_id));
                }
            }))->count();

            return [
                ['title' => 'Org Leads Today', 'label' => 'Org Leads Today', 'value' => number_format($leadsToday), 'delta' => 'Today', 'isPositive' => true, 'icon' => 'users'],
                ['title' => 'Active Credits', 'label' => 'Active Credits', 'value' => number_format($walletBalance), 'delta' => 'Live', 'isPositive' => $walletBalance > 0, 'icon' => 'wallet'],
                ['title' => 'Active Projects', 'label' => 'Active Projects', 'value' => number_format($projectsCount), 'delta' => 'Portfolio', 'isPositive' => true, 'icon' => 'chart'],
                ['title' => 'Closed Bookings', 'label' => 'Closed Bookings', 'value' => number_format($bookingsCount), 'delta' => 'This month', 'isPositive' => true, 'icon' => 'check-circle'],
            ];
        }

        if ($this->role === 'broker') {
            $leadsToday = (clone $scopedQuery)->whereDate('created_at', today())->count();
            $walletBalance = (int) ($user?->wallet?->balance ?? 0);
            $siteVisits = (clone $scopedQuery)->where('current_stage', 'site_visit')->count();
            $closedDeals = (clone $scopedQuery)->whereIn('current_stage', ['booking', 'closed_won'])->count();

            return [
                ['title' => 'My Buyer Leads Today', 'label' => 'My Buyer Leads Today', 'value' => number_format($leadsToday), 'delta' => 'Live', 'isPositive' => true, 'icon' => 'users'],
                ['title' => 'My Wallet Balance', 'label' => 'My Wallet Balance', 'value' => number_format($walletBalance), 'delta' => 'Credits', 'isPositive' => $walletBalance > 0, 'icon' => 'wallet'],
                ['title' => 'Site Visits Scheduled', 'label' => 'Site Visits Scheduled', 'value' => number_format($siteVisits), 'delta' => 'Active', 'isPositive' => true, 'icon' => 'chart'],
                ['title' => 'Closed Bookings', 'label' => 'Closed Bookings', 'value' => number_format($closedDeals), 'delta' => 'Pipeline', 'isPositive' => true, 'icon' => 'check-circle'],
            ];
        }

        if ($this->role === 'partner') {
            $leadsToday = (clone $scopedQuery)->whereDate('created_at', today())->count();
            $agentsCount = $user?->organization_id ? User::where('organization_id', $user->organization_id)->count() : 1;
            $siteVisits = (clone $scopedQuery)->where('current_stage', 'site_visit')->count();
            $bookingsCount = (clone $scopedQuery)->whereIn('current_stage', ['booking', 'closed_won'])->count();

            return [
                ['title' => 'Agency Leads Today', 'label' => 'Agency Leads Today', 'value' => number_format($leadsToday), 'delta' => 'Today', 'isPositive' => true, 'icon' => 'users'],
                ['title' => 'Active Agents', 'label' => 'Active Agents', 'value' => number_format($agentsCount), 'delta' => 'Team', 'isPositive' => true, 'icon' => 'chart'],
                ['title' => 'Site Visits Pipeline', 'label' => 'Site Visits Pipeline', 'value' => number_format($siteVisits), 'delta' => 'Pipeline', 'isPositive' => true, 'icon' => 'alert-triangle'],
                ['title' => 'Total Bookings', 'label' => 'Total Bookings', 'value' => number_format($bookingsCount), 'delta' => 'Deals', 'isPositive' => true, 'icon' => 'check-circle'],
            ];
        }

        if ($this->role === 'sales') {
            $myLeadsToday = Lead::where('assigned_to', $user?->id)->whereDate('created_at', today())->count();
            $followupsPending = Lead::where('assigned_to', $user?->id)->whereIn('current_stage', ['new', 'contacted', 'in_progress'])->count();
            $slaCritical = Lead::where('assigned_to', $user?->id)->whereNull('first_response_at')->where('created_at', '<=', now()->subMinutes(15))->count();
            $myBookings = Lead::where('assigned_to', $user?->id)->whereIn('current_stage', ['site_visit', 'booking', 'closed_won'])->count();

            return [
                ['title' => 'Assigned Leads Today', 'label' => 'Assigned Leads Today', 'value' => number_format($myLeadsToday), 'delta' => 'Assigned', 'isPositive' => true, 'icon' => 'users'],
                ['title' => 'Follow-ups Pending', 'label' => 'Follow-ups Pending', 'value' => number_format($followupsPending), 'delta' => 'Queue', 'isPositive' => $followupsPending === 0, 'icon' => 'chart'],
                ['title' => 'SLA Critical Leads', 'label' => 'SLA Critical Leads', 'value' => number_format($slaCritical), 'delta' => '< 15m timer', 'isPositive' => $slaCritical === 0, 'icon' => 'alert-triangle'],
                ['title' => 'My Conversions', 'label' => 'My Conversions', 'value' => number_format($myBookings), 'delta' => 'Deals', 'isPositive' => true, 'icon' => 'check-circle'],
            ];
        }

        // Super admin legacy 4-card fallback
        $newLeads = Lead::whereDate('created_at', today())->count();
        $activeCredits = (int) CreditWallet::sum('balance');
        $activeOrgs = Organization::where('status', 'active')->count();
        $slaBreaches = Lead::whereNull('first_response_at')
            ->where('created_at', '<=', now()->subMinutes(30))
            ->whereDate('created_at', today())
            ->count();

        return [
            ['title' => 'Platform Leads Today', 'label' => 'Platform Leads Today', 'value' => number_format($newLeads), 'delta' => 'Global', 'isPositive' => true, 'icon' => 'users'],
            ['title' => 'System Credits Supply', 'label' => 'System Credits Supply', 'value' => number_format($activeCredits), 'delta' => 'Circulation', 'isPositive' => true, 'icon' => 'wallet'],
            ['title' => 'Active Organizations', 'label' => 'Active Organizations', 'value' => number_format($activeOrgs), 'delta' => 'Clients', 'isPositive' => true, 'icon' => 'chart'],
            ['title' => 'Platform SLA Breaches', 'label' => 'Platform SLA Breaches', 'value' => number_format($slaBreaches), 'delta' => 'Today', 'isPositive' => $slaBreaches === 0, 'icon' => 'alert-triangle'],
        ];
    }

    public function getUpcomingEventsProperty(): array
    {
        $realLeads = $this->getScopedLeadsQuery()
            ->with(['project'])
            ->whereIn('current_stage', ['site_visit', 'meeting', 'contacted', 'new', 'in_progress'])
            ->latest('updated_at')
            ->take(5)
            ->get();

        if ($realLeads->isNotEmpty()) {
            return $realLeads->map(function ($lead) {
                return [
                    'title' => "Follow-up: {$lead->name}",
                    'project' => $lead->project?->name ?: 'General Project',
                    'type' => ucfirst(str_replace('_', ' ', $lead->current_stage)),
                    'due_time' => $lead->updated_at ? $lead->updated_at->diffForHumans() : 'Today',
                    'status' => $lead->current_stage ?: 'new',
                ];
            })->toArray();
        }

        return [];
    }

    public function getAuditLogsProperty(): array
    {
        if ($this->role === 'admin') {
            $logs = AuditLog::with('user')->latest()->take(8)->get();
            if ($logs->isNotEmpty()) {
                return $logs->map(function ($log) {
                    $userName = $log->user?->name ?: 'System';
                    $action = ucfirst(str_replace('_', ' ', $log->action));
                    return "{$userName} executed {$action} on {$log->subject_type} #{$log->subject_id}";
                })->toArray();
            }
        }

        $recentLeads = $this->getScopedLeadsQuery()->with(['project', 'assignedTo'])->latest()->take(8)->get();
        if ($recentLeads->isNotEmpty()) {
            return $recentLeads->map(function ($lead) {
                $assigned = $lead->assignedTo?->name ?: 'Unassigned';
                $stage = ucfirst(str_replace('_', ' ', $lead->current_stage));
                $projectName = $lead->project?->name ?: 'Project';
                return "Lead #{$lead->lead_code} ({$lead->name}) in {$projectName} • Stage: {$stage} • Assigned to {$assigned}";
            })->toArray();
        }

        return [];
    }

    public function getChartDataProperty(): array
    {
        $months = collect(range(5, 0))->map(fn($i) => now()->subMonths($i));
        $labels = $months->map(fn($m) => $m->format('M'))->toArray();

        $leadCounts = $months->map(function ($m) {
            return (clone $this->getScopedLeadsQuery())
                ->whereMonth('created_at', $m->month)
                ->whereYear('created_at', $m->year)
                ->count();
        })->toArray();

        $revenueCounts = array_map(fn($c) => (int) round($c * 0.7), $leadCounts);

        return [
            'labels' => $labels,
            'leads' => $leadCounts,
            'revenue' => $revenueCounts,
        ];
    }

    public function render()
    {
        $sourceOptions = LeadSource::pluck('name', 'id')->map(fn($name) => ucfirst($name))->toArray();

        return view('livewire.dashboard.dashboard-shell', [
            'headerInfo' => $this->headerInfo,
            'quickActions' => $this->quickActions,
            'stats' => $this->roleStats,
            'upcomingEvents' => $this->upcomingEvents,
            'auditLogs' => $this->auditLogs,
            'chartData' => $this->chartData,

            // Super Admin Command Centre Data
            'adminKpis' => $this->role === 'admin' ? $this->adminKpis : [],
            'groupedKpis' => $this->role === 'admin' ? $this->groupedKpis : [],
            'adminFunnel' => $this->role === 'admin' ? $this->adminFunnel : [],
            'adminLeadAnalytics' => $this->role === 'admin' ? $this->adminLeadAnalytics : [],
            'adminOrgPerformance' => $this->role === 'admin' ? $this->adminOrgPerformance : [],
            'adminProjectPerformance' => $this->role === 'admin' ? $this->adminProjectPerformance : [],
            'adminCreditAnalytics' => $this->role === 'admin' ? $this->adminCreditAnalytics : [],
            'adminDistributionMonitoring' => $this->role === 'admin' ? $this->adminDistributionMonitoring : [],
            'adminNotificationMonitoring' => $this->role === 'admin' ? $this->adminNotificationMonitoring : [],
            'adminRecentActivities' => $this->role === 'admin' ? $this->adminRecentActivities : [],
            'sourceOptions' => $sourceOptions,
        ]);
    }
}
