<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use App\Models\Lead;
use App\Models\CreditWallet;
use App\Models\Booking;
use App\Models\CreditTransaction;

class DashboardShell extends Component
{
    public function getNewLeadsTodayProperty(): int
    {
        return Lead::whereDate('created_at', today())->count();
    }

    public function getNewLeadsTodayDeltaProperty(): array
    {
        $current = $this->newLeadsToday;
        $lastMonthSameDay = Lead::whereDate('created_at', today()->subMonth())->count();

        if ($lastMonthSameDay === 0) {
            $pct = $current > 0 ? 100.0 : 0.0;
        } else {
            $pct = round((($current - $lastMonthSameDay) / $lastMonthSameDay) * 100, 1);
        }

        return [
            'delta' => ($pct >= 0 ? '+' : '') . $pct . '%',
            'isPositive' => $pct >= 0,
        ];
    }

    public function getActiveCreditsProperty(): int
    {
        return (int) CreditWallet::sum('balance');
    }

    public function getActiveCreditsDeltaProperty(): array
    {
        $rechargedThisMonth = (int) CreditTransaction::where('transaction_type', 'recharge')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('credit_used');
        $rechargedLastMonth = (int) CreditTransaction::where('transaction_type', 'recharge')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('credit_used');

        if ($rechargedLastMonth === 0) {
            $pct = $rechargedThisMonth > 0 ? 100.0 : 0.0;
        } else {
            $pct = round((($rechargedThisMonth - $rechargedLastMonth) / $rechargedLastMonth) * 100, 1);
        }

        return [
            'delta' => ($pct >= 0 ? '+' : '') . $pct . '%',
            'isPositive' => $pct >= 0,
        ];
    }

    public function getSlaBreachesTodayProperty(): int
    {
        return Lead::whereNull('first_response_at')
            ->where('created_at', '<=', now()->subMinutes(30))
            ->whereDate('created_at', today())
            ->count();
    }

    public function getSlaBreachesTodayDeltaProperty(): array
    {
        $current = $this->slaBreachesToday;
        $lastMonthSameDay = Lead::whereNull('first_response_at')
            ->where('created_at', '<=', today()->subMonth()->endOfDay()->subMinutes(30))
            ->whereDate('created_at', today()->subMonth())
            ->count();

        if ($lastMonthSameDay === 0) {
            $pct = $current > 0 ? 100.0 : 0.0;
        } else {
            $pct = round((($current - $lastMonthSameDay) / $lastMonthSameDay) * 100, 1);
        }

        // For SLA breaches, lower or zero breach rate is positive
        return [
            'delta' => ($pct >= 0 ? '+' : '') . $pct . '%',
            'isPositive' => $pct <= 0,
        ];
    }

    public function getBookingsThisMonthProperty(): int
    {
        return Booking::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    public function getBookingsThisMonthDeltaProperty(): array
    {
        $current = $this->bookingsThisMonth;
        $lastMonth = Booking::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        if ($lastMonth === 0) {
            $pct = $current > 0 ? 100.0 : 0.0;
        } else {
            $pct = round((($current - $lastMonth) / $lastMonth) * 100, 1);
        }

        return [
            'delta' => ($pct >= 0 ? '+' : '') . $pct . '%',
            'isPositive' => $pct >= 0,
        ];
    }

    public function render()
    {
        $newLeads = $this->newLeadsToday;
        $newLeadsDelta = $this->newLeadsTodayDelta;

        $activeCredits = $this->activeCredits;
        $activeCreditsDelta = $this->activeCreditsDelta;

        $slaBreaches = $this->slaBreachesToday;
        $slaBreachesDelta = $this->slaBreachesTodayDelta;

        $bookings = $this->bookingsThisMonth;
        $bookingsDelta = $this->bookingsThisMonthDelta;

        // Stat Cards Data (Distinct Metrics per Section 3.3)
        $stats = [
            [
                'title' => 'New Leads Today',
                'label' => 'New Leads Today',
                'value' => number_format($newLeads),
                'delta' => $newLeadsDelta['delta'],
                'isPositive' => $newLeadsDelta['isPositive'],
                'icon' => 'users',
            ],
            [
                'title' => 'Active Credits',
                'label' => 'Active Credits',
                'value' => number_format($activeCredits),
                'delta' => $activeCreditsDelta['delta'],
                'isPositive' => $activeCreditsDelta['isPositive'],
                'icon' => 'wallet',
            ],
            [
                'title' => 'SLA Breaches Today',
                'label' => 'SLA Breaches Today',
                'value' => number_format($slaBreaches),
                'delta' => $slaBreachesDelta['delta'],
                'isPositive' => $slaBreachesDelta['isPositive'],
                'icon' => 'alert-triangle',
            ],
            [
                'title' => 'Bookings This Month',
                'label' => 'Bookings This Month',
                'value' => number_format($bookings),
                'delta' => $bookingsDelta['delta'],
                'isPositive' => $bookingsDelta['isPositive'],
                'icon' => 'check-circle',
            ],
        ];

        // Next 5 Upcoming Follow-ups / Meetings / Site Visits
        $upcomingEvents = [
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

        // Last 10 Audit Log Entries in Plain English
        $auditLogs = [
            'Lead #4082 assigned to Sales Executive Vikram via Round-Robin rule.',
            'Credit Wallet recharged with 500 credits by Organization Admin.',
            'Site Visit logged for Rahul Sharma at Orchid Residency.',
            'Lead #4079 marked as Qualified by Priya Patel.',
            'SLA breach alert triggered for Lead #4071 (First response pending > 15 mins).',
            'Replacement Request #12 rounded for Invalid Phone Number - Refunded 1 Credit.',
            'Booking #88 confirmed with token deposit payment of ₹50,000.',
            'Lead #4065 assigned to Channel Partner Apex Real Estate.',
            'Source campaign Meta_Leads_Q3 integrated via API Webhook.',
            'Monthly SLA compliance report generated and exported to Excel.',
        ];

        // 6-Month Lead Volume & Revenue Trend Data for Chart.js
        $chartData = [
            'labels' => ['Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
            'leads' => [320, 410, 390, 520, 680, 840],
            'revenue' => [180, 240, 210, 310, 450, 580],
        ];

        return view('livewire.dashboard.dashboard-shell', [
            'stats' => $stats,
            'upcomingEvents' => $upcomingEvents,
            'auditLogs' => $auditLogs,
            'chartData' => $chartData,
        ]);
    }
}
