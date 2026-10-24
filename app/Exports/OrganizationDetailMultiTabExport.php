<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Models\Organization;
use App\Models\User;
use App\Models\Client;
use App\Models\Lead;
use App\Models\CreditTransaction;
use App\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrganizationDetailMultiTabExport implements WithMultipleSheets
{
    protected Organization $organization;

    public function __construct(Organization $organization)
    {
        $this->organization = $organization;
    }

    public function sheets(): array
    {
        $sheets = [];
        $org = $this->organization;
        $exportedAt = now()->format('d M Y, H:i T');

        $clientIds = Client::where('organization_id', $org->id)->pluck('id')->toArray();
        $userIds = User::where('organization_id', $org->id)->pluck('id')->toArray();
        $walletBalance = DB::table('client_wallets')->whereIn('client_id', $clientIds)->sum('balance') ?? 0;
        $totalProjects = DB::table('projects')->whereIn('client_id', $clientIds)->count();
        $totalLeads = Lead::withoutGlobalScopes()
            ->where(function ($q) use ($clientIds, $userIds) {
                if (!empty($clientIds)) {
                    $q->whereIn('client_id', $clientIds);
                }
                if (!empty($userIds)) {
                    $q->orWhereIn('assigned_to', $userIds);
                }
            })
            ->count();

        // 1. Overview Sheet
        $overviewData = collect([
            (object)['property' => 'Organization ID', 'value' => '#ORG-' . str_pad($org->id, 4, '0', STR_PAD_LEFT)],
            (object)['property' => 'Organization Name', 'value' => $org->name],
            (object)['property' => 'Status', 'value' => ucfirst($org->status)],
            (object)['property' => 'Total Team Members', 'value' => (string)count($userIds)],
            (object)['property' => 'Total Registered Clients', 'value' => (string)count($clientIds)],
            (object)['property' => 'Total Projects', 'value' => (string)$totalProjects],
            (object)['property' => 'Total Leads (Org & Assigned)', 'value' => (string)$totalLeads],
            (object)['property' => 'Total Credit Wallet Balance', 'value' => number_format((float)$walletBalance, 2)],
            (object)['property' => 'Created At', 'value' => $org->created_at ? $org->created_at->format('M d, Y H:i') : '—'],
            (object)['property' => 'Last Updated', 'value' => $org->updated_at ? $org->updated_at->format('M d, Y H:i') : '—'],
        ]);

        $sheets[] = new BaseStyledExport(
            data: $overviewData,
            title: 'Overview & Profile',
            subtitle: "Organization Overview | {$org->name} | {$exportedAt}",
            headings: ['Property', 'Value'],
            columns: ['property', 'value']
        );

        // 2. Team Members Sheet
        $teamData = User::where('organization_id', $org->id)->with('roles')->orderBy('name')->get();
        $sheets[] = new BaseStyledExport(
            data: $teamData,
            title: 'Team Members',
            subtitle: "Assigned Team Members | {$org->name} | {$exportedAt}",
            headings: ['User ID', 'Name', 'Email', 'Role', 'Status', 'Joined Date'],
            columns: [
                'id',
                'name',
                'email',
                fn($u) => $u->roles->first()?->name ? ucwords(str_replace(['-', '_'], ' ', $u->roles->first()->name)) : 'No Role',
                'status',
                fn($u) => $u->created_at ? $u->created_at->format('M d, Y H:i') : '—',
            ],
            statusColumns: ['status']
        );

        // 3. Clients & Projects Sheet
        $clientsData = Client::where('organization_id', $org->id)->with(['projects', 'wallet'])->withCount(['projects', 'leads'])->orderBy('name')->get();
        $sheets[] = new BaseStyledExport(
            data: $clientsData,
            title: 'Clients & Projects',
            subtitle: "Registered Clients & Projects | {$org->name} | {$exportedAt}",
            headings: ['Client ID', 'Client Name', 'Billing Email', 'Status', 'Projects Count', 'Projects List', 'Wallet Balance', 'Created Date'],
            columns: [
                'id',
                'name',
                fn($c) => $c->billing_email ?: '—',
                'status',
                'projects_count',
                fn($c) => $c->projects->pluck('name')->implode(', ') ?: 'None',
                fn($c) => number_format((float)($c->wallet?->balance ?? 0), 2),
                fn($c) => $c->created_at ? $c->created_at->format('M d, Y H:i') : '—',
            ],
            statusColumns: ['status']
        );

        // 4. Directly Assigned Leads Sheet
        $leadsData = Lead::withoutGlobalScopes()
            ->where(function ($q) use ($clientIds, $userIds) {
                if (!empty($clientIds)) {
                    $q->whereIn('client_id', $clientIds);
                }
                if (!empty($userIds)) {
                    $q->orWhereIn('assigned_to', $userIds);
                }
            })
            ->with(['client', 'project', 'assignedTo'])
            ->latest('created_at')
            ->get();

        $sheets[] = new BaseStyledExport(
            data: $leadsData,
            title: 'Directly Assigned Leads',
            subtitle: "Directly Assigned & Managed Leads | {$org->name} | {$exportedAt}",
            headings: ['Lead Code', 'Lead Name', 'Mobile', 'Email', 'Project', 'Client', 'Directly Assigned To', 'Status', 'Booking Status', 'Created Date'],
            columns: [
                'lead_code',
                'name',
                'mobile',
                fn($l) => $l->email ?: '—',
                fn($l) => $l->project?->name ?: '—',
                fn($l) => $l->client?->name ?: '—',
                fn($l) => $l->assignedTo?->name ?: 'Unassigned',
                'status',
                'booking_status',
                fn($l) => $l->created_at ? $l->created_at->format('M d, Y H:i') : '—',
            ],
            statusColumns: ['status', 'booking_status']
        );

        // 5. Credits & Transactions Sheet
        $txData = CreditTransaction::withoutGlobalScopes()
            ->whereIn('client_id', $clientIds)
            ->with(['client', 'lead'])
            ->latest('created_at')
            ->get();

        $sheets[] = new BaseStyledExport(
            data: $txData,
            title: 'Credits & Wallet',
            subtitle: "Credits & Wallet Transactions | {$org->name} | {$exportedAt}",
            headings: ['Tx ID', 'Client Name', 'Type', 'Lead Reference', 'Credit Before', 'Credit In/Out', 'Credit After', 'Date & Time'],
            columns: [
                'id',
                fn($tx) => $tx->client?->name ?: '—',
                'transaction_type',
                fn($tx) => $tx->lead?->lead_code ?: 'Direct Adjustment',
                'credit_before',
                'credit_used',
                'credit_after',
                fn($tx) => $tx->created_at ? Carbon::parse($tx->created_at)->format('M d, Y H:i') : '—',
            ],
            statusColumns: ['transaction_type'],
            currencyColumns: ['credit_before', 'credit_used', 'credit_after'],
            hasTotals: true
        );

        // 6. Audit Logs Sheet
        $auditData = collect();
        try {
            $auditData = AuditLog::with('user')
                ->where(function ($q) use ($org, $userIds) {
                    $q->where(function ($sq) use ($org) {
                        $sq->where('subject_type', Organization::class)
                           ->where('subject_id', $org->id);
                    });
                    if (!empty($userIds)) {
                        $q->orWhereIn('user_id', $userIds);
                    }
                })
                ->latest('created_at')
                ->take(100)
                ->get();
        } catch (Throwable $e) {}

        $sheets[] = new BaseStyledExport(
            data: $auditData,
            title: 'Audit Logs',
            subtitle: "Activity & Audit History | {$org->name} | {$exportedAt}",
            headings: ['Log ID', 'Action', 'Target / Subject', 'Performed By', 'IP Address', 'Timestamp'],
            columns: [
                'id',
                'action',
                fn($a) => $a->subject_summary,
                fn($a) => $a->user_name,
                fn($a) => $a->formatted_ip,
                fn($a) => $a->created_at ? $a->created_at->format('M d, Y H:i') : '—',
            ],
            statusColumns: ['action']
        );

        return $sheets;
    }
}
