<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Support\Collection;

class DashboardReportExport implements WithMultipleSheets
{
    protected array $kpis;
    protected array $funnel;
    protected array $orgs;
    protected array $projects;
    protected Collection $leads;
    protected string $periodString;

    public function __construct(
        array $kpis = [],
        array $funnel = [],
        array $orgs = [],
        array $projects = [],
        ?Collection $leads = null,
        string $periodString = ''
    ) {
        $this->kpis = $kpis;
        $this->funnel = $funnel;
        $this->orgs = $orgs;
        $this->projects = $projects;
        $this->leads = $leads ?? collect();
        $this->periodString = $periodString ?: now()->format('d M Y, H:i T');
    }

    public function sheets(): array
    {
        $sheets = [];

        // Sheet 1: Executive KPI Summary
        $kpiRows = collect($this->kpis)->map(function ($kpi) {
            $isPos = $kpi['isPositive'] ?? true;
            return (object) [
                'metric' => $kpi['title'] ?? 'Metric',
                'current_value' => (string) ($kpi['value'] ?? '0'),
                'change_delta' => trim(($kpi['delta'] ?? '') . ' ' . ($kpi['period'] ?? '')),
                'status' => $isPos ? 'Active' : 'Under Review',
            ];
        });

        $sheets[] = new BaseStyledExport(
            data: $kpiRows,
            title: 'KPI Summary',
            subtitle: "Super Admin Command Centre | Key Performance Indicators | {$this->periodString}",
            headings: ['Metric Name', 'Current Value', 'Period Delta', 'Status'],
            columns: ['metric', 'current_value', 'change_delta', 'status'],
            statusColumns: ['status']
        );

        // Sheet 2: Lead Journey Lifecycle Funnel
        $funnelRows = collect($this->funnel)->map(function ($stg) {
            return (object) [
                'stage_number' => '#' . ($stg['index'] ?? 0),
                'stage_name' => $stg['name'] ?? '',
                'leads_count' => (int) ($stg['count'] ?? 0),
                'conversion_pct' => ($stg['conversion_pct'] ?? 0) . '%',
                'step_pct' => ($stg['step_pct'] ?? 0) . '%',
                'drop_offs' => (int) ($stg['drop_off'] ?? 0),
                'avg_duration' => $stg['avg_time'] ?? '—',
            ];
        });

        $sheets[] = new BaseStyledExport(
            data: $funnelRows,
            title: 'Lead Pipeline',
            subtitle: "13-Stage Lead Lifecycle Funnel Progression | {$this->periodString}",
            headings: ['Stage #', 'Stage Name', 'Leads Count', 'Total Conv %', 'Step Conv %', 'Drop-offs', 'Avg Duration'],
            columns: ['stage_number', 'stage_name', 'leads_count', 'conversion_pct', 'step_pct', 'drop_offs', 'avg_duration']
        );

        // Sheet 3: Organization Performance Breakdown
        $orgRows = collect($this->orgs)->map(function ($org) {
            return (object) [
                'organization' => $org['name'] ?? '',
                'status' => ucfirst($org['status'] ?? 'Active'),
                'total_leads' => (int) ($org['total_leads'] ?? 0),
                'assigned_leads' => (int) ($org['assigned_leads'] ?? 0),
                'unassigned_leads' => (int) ($org['unassigned_leads'] ?? 0),
                'converted_leads' => (int) ($org['converted_leads'] ?? 0),
                'conversion_rate' => ($org['conversion_rate'] ?? 0) . '%',
                'credits_consumed' => (int) ($org['credits_consumed'] ?? 0),
                'wallet_balance' => (float) ($org['wallet_balance'] ?? 0),
                'sla_breaches' => (int) ($org['sla_breaches'] ?? 0),
            ];
        });

        $sheets[] = new BaseStyledExport(
            data: $orgRows,
            title: 'Organizations',
            subtitle: "Organization Portfolios, Credit Consumption & SLA Compliance | {$this->periodString}",
            headings: ['Organization', 'Status', 'Total Leads', 'Assigned', 'Unassigned', 'Converted', 'Conv %', 'Credits Used', 'Wallet Balance', 'SLA Breaches'],
            columns: ['organization', 'status', 'total_leads', 'assigned_leads', 'unassigned_leads', 'converted_leads', 'conversion_rate', 'credits_consumed', 'wallet_balance', 'sla_breaches'],
            statusColumns: ['status']
        );

        // Sheet 4: Project Performance Telemetry
        $projectRows = collect($this->projects)->map(function ($proj) {
            return (object) [
                'project_name' => $proj['name'] ?? '',
                'client_name' => $proj['client_name'] ?? '—',
                'location' => $proj['location'] ?? '—',
                'total_leads' => (int) ($proj['total_leads'] ?? 0),
                'credits_charged' => (int) ($proj['total_credits_charged'] ?? 0),
                'conversion_rate' => ($proj['conversion_rate'] ?? 0) . '%',
            ];
        });

        $sheets[] = new BaseStyledExport(
            data: $projectRows,
            title: 'Projects',
            subtitle: "Project Performance, Client Mapping & Credit Charges | {$this->periodString}",
            headings: ['Project Name', 'Client Name', 'Location', 'Total Leads', 'Credits Charged', 'Conversion Rate'],
            columns: ['project_name', 'client_name', 'location', 'total_leads', 'credits_charged', 'conversion_rate']
        );

        // Sheet 5: Ingested Leads Sample Dataset
        if ($this->leads->isNotEmpty()) {
            $sheets[] = new BaseStyledExport(
                data: $this->leads,
                title: 'Recent Leads',
                subtitle: "Filtered Leads Dataset | {$this->periodString}",
                headings: ['Lead Code', 'Contact Name', 'Mobile', 'Project', 'Source', 'Stage', 'Assigned Agent', 'SLA Status', 'Ingestion Date'],
                columns: [
                    'lead_code',
                    'name',
                    'mobile',
                    fn($l) => $l->project?->name ?: ($l->client?->name ?: 'Direct'),
                    fn($l) => $l->leadSource?->name ?: 'Direct',
                    fn($l) => ucfirst(str_replace('_', ' ', $l->current_stage ?: $l->status)),
                    fn($l) => $l->assignedTo?->name ?: 'Unassigned',
                    fn($l) => $l->first_response_at ? 'Completed' : ($l->created_at && $l->created_at->diffInMinutes(now()) > 30 ? 'Breached' : 'Pending'),
                    fn($l) => $l->created_at ? $l->created_at->format('M d, Y H:i') : '—',
                ],
                statusColumns: ['SLA Status']
            );
        }

        return $sheets;
    }
}
