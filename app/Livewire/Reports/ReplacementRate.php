<?php

// TODO: Move reporting queries to dedicated analytics database when scaling

namespace App\Livewire\Reports;

use App\Exports\BaseStyledExport;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadReplacement;
use App\Support\NavigationConfig;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

class ReplacementRate extends Component
{
    public function tableColumns(): array
    {
        return [
            ['key' => 'client_name', 'label' => 'Client Organization', 'class' => 'font-bold text-ink', 'sortable' => false, 'priority' => 1],
            ['key' => 'total_leads', 'label' => 'Total Ingested Leads', 'class' => 'font-mono', 'formatter' => fn ($v) => number_format((float) $v), 'sortable' => false, 'priority' => 1],
            ['key' => 'total_claims', 'label' => 'Replacement Claims', 'class' => 'font-mono', 'formatter' => fn ($v) => number_format((float) $v), 'sortable' => false, 'priority' => 2],
            ['key' => 'approved_claims', 'label' => 'Approved Replacements', 'class' => 'font-mono font-bold text-purple-700', 'formatter' => fn ($v) => number_format((float) $v), 'sortable' => false, 'priority' => 1],
            ['key' => 'replacement_rate', 'label' => 'Effective Replacement Rate', 'suffix' => '%', 'class' => 'font-mono font-bold text-ink', 'sortable' => false, 'priority' => 1],
        ];
    }

    public function exportExcel()
    {
        $rows = collect($this->getReplacementRatesData());
        $headings = ['Client Name', 'Total Leads Assigned', 'Total Claims Submitted', 'Approved Claims', 'Replacement Rate (%)'];
        $columns = ['client_name', 'total_leads', 'total_claims', 'approved_claims', fn ($r) => $r['replacement_rate'].'%'];

        $filename = 'replacement-rate-report_'.now()->format('Y-m-d').'.xlsx';
        $subtitle = 'Exported '.now()->format('d M Y, H:i T').' | Client Replacement Quality Analysis';

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return Excel::download(
            new BaseStyledExport(
                data: $rows,
                title: 'Client Lead Replacement Rate & Quality Report',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                hasTotals: false
            ),
            $filename
        );
    }

    protected function getReplacementRatesData(): array
    {
        $user = auth()->user();
        $clientsQuery = Client::query()->whereIn('id', (Lead::usesSalesPanel() ? Lead::panelQuery() : Lead::query())->select('client_id'));

        if (Lead::usesSalesPanel()) {
            if (NavigationConfig::getRoleKey($user) === 'sales-executive') {
                $clientsQuery->whereIn('id', Lead::panelQuery()->select('client_id'));
            } else {
                $clientsQuery->where('organization_id', $user->organization_id)->whereNotNull('organization_id');
            }
        }
        if (! Lead::usesSalesPanel() && $user && $user->hasRole('Client')) {
            $clientsQuery->where('organization_id', $user->organization_id);
        }

        $clients = $clientsQuery->get();
        $data = [];

        foreach ($clients as $client) {
            $totalLeads = (Lead::usesSalesPanel() ? Lead::panelQuery() : Lead::query())->where('client_id', $client->id)->count();
            $claims = LeadReplacement::whereHas('lead', function ($q) use ($client) {
                $q->withoutGlobalScopes()->where('client_id', $client->id);
                if (Lead::usesSalesPanel()) {
                    $q->whereIn('id', Lead::panelQuery()->select('id'));
                }
            })->get();

            $totalClaims = $claims->count();
            $approvedClaims = $claims->where('status', 'approved')->count();
            $rate = $totalLeads > 0 ? round(($approvedClaims / $totalLeads) * 100, 1) : 0.0;

            $data[] = [
                'client_name' => $client->name,
                'total_leads' => $totalLeads,
                'total_claims' => $totalClaims,
                'approved_claims' => $approvedClaims,
                'replacement_rate' => $rate,
            ];
        }

        return $data;

    }

    protected function getMonthlyTrend(): array
    {
        $labels = [];
        $rates = [];
        foreach (range(5, 0) as $offset) {
            $month = now()->subMonthsNoOverflow($offset);
            $labels[] = $month->format('M Y');
            $leads = (Lead::usesSalesPanel() ? Lead::panelQuery() : Lead::query())->whereYear('created_at', $month->year)->whereMonth('created_at', $month->month);
            $total = (clone $leads)->count();
            $approved = LeadReplacement::whereIn('lead_id', $leads->select('id'))->where('status', 'approved')->count();
            $rates[] = $total ? round($approved / $total * 100, 1) : 0;
        }

        return ['labels' => $labels, 'rates' => $rates];
    }

    public function render()
    {
        return view('livewire.reports.replacement-rate', [
            'ratesData' => $this->getReplacementRatesData(),
            'monthlyTrend' => $this->getMonthlyTrend(),
        ]);
    }
}
