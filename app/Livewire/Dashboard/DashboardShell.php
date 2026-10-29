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
use App\Support\NavigationConfig;

class DashboardShell extends Component
{
    public ?string $role = null;

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
                    $q->where('assigned_to', $user->id)
                      ->orWhere('client_id', $user->id);
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
                'title' => 'Super Admin Master Telemetry',
                'subtitle' => 'Platform-wide lead flow, active organizations, credit supply & system telemetry',
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
                ['label' => 'My Leads', 'url' => route('account_manager.leads'), 'icon' => 'leads', 'primary' => true],
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
                [
                    'title' => 'Org Leads Today',
                    'label' => 'Org Leads Today',
                    'value' => number_format($leadsToday),
                    'delta' => 'Today',
                    'isPositive' => true,
                    'icon' => 'users',
                ],
                [
                    'title' => 'Active Credits',
                    'label' => 'Active Credits',
                    'value' => number_format($walletBalance),
                    'delta' => 'Live',
                    'isPositive' => $walletBalance > 0,
                    'icon' => 'wallet',
                ],
                [
                    'title' => 'Active Projects',
                    'label' => 'Active Projects',
                    'value' => number_format($projectsCount),
                    'delta' => 'Portfolio',
                    'isPositive' => true,
                    'icon' => 'chart',
                ],
                [
                    'title' => 'Closed Bookings',
                    'label' => 'Closed Bookings',
                    'value' => number_format($bookingsCount),
                    'delta' => 'This month',
                    'isPositive' => true,
                    'icon' => 'check-circle',
                ],
            ];
        }

        if ($this->role === 'broker') {
            $leadsToday = (clone $scopedQuery)->whereDate('created_at', today())->count();
            $walletBalance = (int) ($user?->wallet?->balance ?? 0);
            $siteVisits = (clone $scopedQuery)->where('current_stage', 'site_visit')->count();
            $closedDeals = (clone $scopedQuery)->whereIn('current_stage', ['booking', 'closed_won'])->count();

            return [
                [
                    'title' => 'My Buyer Leads Today',
                    'label' => 'My Buyer Leads Today',
                    'value' => number_format($leadsToday),
                    'delta' => 'Live',
                    'isPositive' => true,
                    'icon' => 'users',
                ],
                [
                    'title' => 'My Wallet Balance',
                    'label' => 'My Wallet Balance',
                    'value' => number_format($walletBalance),
                    'delta' => 'Credits',
                    'isPositive' => $walletBalance > 0,
                    'icon' => 'wallet',
                ],
                [
                    'title' => 'Site Visits Scheduled',
                    'label' => 'Site Visits Scheduled',
                    'value' => number_format($siteVisits),
                    'delta' => 'Active',
                    'isPositive' => true,
                    'icon' => 'chart',
                ],
                [
                    'title' => 'Closed Bookings',
                    'label' => 'Closed Bookings',
                    'value' => number_format($closedDeals),
                    'delta' => 'Pipeline',
                    'isPositive' => true,
                    'icon' => 'check-circle',
                ],
            ];
        }

        if ($this->role === 'partner') {
            $leadsToday = (clone $scopedQuery)->whereDate('created_at', today())->count();
            $agentsCount = $user?->organization_id ? User::where('organization_id', $user->organization_id)->count() : 1;
            $siteVisits = (clone $scopedQuery)->where('current_stage', 'site_visit')->count();
            $bookingsCount = (clone $scopedQuery)->whereIn('current_stage', ['booking', 'closed_won'])->count();

            return [
                [
                    'title' => 'Agency Leads Today',
                    'label' => 'Agency Leads Today',
                    'value' => number_format($leadsToday),
                    'delta' => 'Today',
                    'isPositive' => true,
                    'icon' => 'users',
                ],
                [
                    'title' => 'Active Agents',
                    'label' => 'Active Agents',
                    'value' => number_format($agentsCount),
                    'delta' => 'Team',
                    'isPositive' => true,
                    'icon' => 'chart',
                ],
                [
                    'title' => 'Site Visits Pipeline',
                    'label' => 'Site Visits Pipeline',
                    'value' => number_format($siteVisits),
                    'delta' => 'Pipeline',
                    'isPositive' => true,
                    'icon' => 'alert-triangle',
                ],
                [
                    'title' => 'Total Bookings',
                    'label' => 'Total Bookings',
                    'value' => number_format($bookingsCount),
                    'delta' => 'Deals',
                    'isPositive' => true,
                    'icon' => 'check-circle',
                ],
            ];
        }

        if ($this->role === 'sales') {
            $myLeadsToday = Lead::where('assigned_to', $user?->id)->whereDate('created_at', today())->count();
            $followupsPending = Lead::where('assigned_to', $user?->id)->whereIn('current_stage', ['new', 'contacted', 'in_progress'])->count();
            $slaCritical = Lead::where('assigned_to', $user?->id)->whereNull('first_response_at')->where('created_at', '<=', now()->subMinutes(15))->count();
            $myBookings = Lead::where('assigned_to', $user?->id)->whereIn('current_stage', ['site_visit', 'booking', 'closed_won'])->count();

            return [
                [
                    'title' => 'Assigned Leads Today',
                    'label' => 'Assigned Leads Today',
                    'value' => number_format($myLeadsToday),
                    'delta' => 'Assigned',
                    'isPositive' => true,
                    'icon' => 'users',
                ],
                [
                    'title' => 'Follow-ups Pending',
                    'label' => 'Follow-ups Pending',
                    'value' => number_format($followupsPending),
                    'delta' => 'Queue',
                    'isPositive' => $followupsPending === 0,
                    'icon' => 'chart',
                ],
                [
                    'title' => 'SLA Critical Leads',
                    'label' => 'SLA Critical Leads',
                    'value' => number_format($slaCritical),
                    'delta' => '< 15m timer',
                    'isPositive' => $slaCritical === 0,
                    'icon' => 'alert-triangle',
                ],
                [
                    'title' => 'My Conversions',
                    'label' => 'My Conversions',
                    'value' => number_format($myBookings),
                    'delta' => 'Deals',
                    'isPositive' => true,
                    'icon' => 'check-circle',
                ],
            ];
        }

        // Default: Super Admin
        $newLeads = Lead::whereDate('created_at', today())->count();
        $activeCredits = (int) CreditWallet::sum('balance');
        $activeOrgs = Organization::where('status', 'active')->count();
        $slaBreaches = Lead::whereNull('first_response_at')
            ->where('created_at', '<=', now()->subMinutes(30))
            ->whereDate('created_at', today())
            ->count();

        return [
            [
                'title' => 'Platform Leads Today',
                'label' => 'Platform Leads Today',
                'value' => number_format($newLeads),
                'delta' => 'Global',
                'isPositive' => true,
                'icon' => 'users',
            ],
            [
                'title' => 'System Credits Supply',
                'label' => 'System Credits Supply',
                'value' => number_format($activeCredits),
                'delta' => 'Circulation',
                'isPositive' => true,
                'icon' => 'wallet',
            ],
            [
                'title' => 'Active Organizations',
                'label' => 'Active Organizations',
                'value' => number_format($activeOrgs),
                'delta' => 'Clients',
                'isPositive' => true,
                'icon' => 'chart',
            ],
            [
                'title' => 'Platform SLA Breaches',
                'label' => 'Platform SLA Breaches',
                'value' => number_format($slaBreaches),
                'delta' => 'Today',
                'isPositive' => $slaBreaches === 0,
                'icon' => 'alert-triangle',
            ],
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

        return [
            [
                'title' => 'Site Visit with Vikram Shah',
                'project' => 'Orchid Residency',
                'type' => 'Site Visit',
                'due_time' => '11:30 AM Today',
                'status' => 'site_visit',
            ],
            [
                'title' => 'First Response Call - Ananya Roy',
                'project' => 'Skyline Towers',
                'type' => 'Followup',
                'due_time' => '02:00 PM Today',
                'status' => 'contacted',
            ],
            [
                'title' => 'Negotiation Meeting - Rajiv Malhotra',
                'project' => 'Green Acres Phase 2',
                'type' => 'Meeting',
                'due_time' => '04:15 PM Today',
                'status' => 'meeting',
            ],
            [
                'title' => 'Booking Form Signing - Neha Kapoor',
                'project' => 'Orchid Residency',
                'type' => 'Booking',
                'due_time' => '10:00 AM Tomorrow',
                'status' => 'booking',
            ],
            [
                'title' => 'Followup Call - Sandeep Kulkarni',
                'project' => 'Urban Horizon',
                'type' => 'Followup',
                'due_time' => '01:30 PM Tomorrow',
                'status' => 'in_progress',
            ],
        ];
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

        return [
            'Lead #4082 assigned to Sales Executive via Round-Robin rule.',
            'Credit Wallet recharged with credits by Organization Admin.',
            'Site Visit logged for Rahul Sharma at Orchid Residency.',
            'Lead #4079 marked as Qualified by Sales Team.',
            'SLA breach alert monitored for first response pending.',
            'Booking confirmed with token deposit payment recorded.',
            'Source campaign Meta_Leads integrated via API Webhook.',
            'Monthly compliance report generated and verified.',
        ];
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

        if (array_sum($leadCounts) === 0) {
            $leadCounts = [320, 410, 390, 520, 680, 840];
            $revenueCounts = [180, 240, 210, 310, 450, 580];
        } else {
            $revenueCounts = array_map(fn($c) => (int) round($c * 0.7), $leadCounts);
        }

        return [
            'labels' => $labels,
            'leads' => $leadCounts,
            'revenue' => $revenueCounts,
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.dashboard-shell', [
            'headerInfo' => $this->headerInfo,
            'quickActions' => $this->quickActions,
            'stats' => $this->roleStats,
            'upcomingEvents' => $this->upcomingEvents,
            'auditLogs' => $this->auditLogs,
            'chartData' => $this->chartData,
        ]);
    }
}
