<?php

// TODO: Move reporting queries to dedicated analytics database when scaling

namespace App\Livewire\Reports;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Lead;
use App\Models\Client;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Exports\BaseStyledExport;

class SourcePerformance extends Component
{
    use WithPagination;

    public int $days = 30;

    public function tableColumns(): array
    {
        return [
            ['key' => 'source_name', 'label' => 'Lead Source', 'class' => 'font-bold text-ink', 'sortable' => false, 'priority' => 1],
            ['key' => 'total_leads', 'label' => 'Total Leads', 'class' => 'font-mono font-semibold', 'formatter' => fn($v) => number_format((float)$v), 'sortable' => false, 'priority' => 1],
            ['key' => 'cost_per_lead', 'label' => 'Average Lead Cost (Credits)', 'prefix' => '', 'formatter' => fn ($v) => $v === null ? '—' : number_format((float)$v,2), 'class' => 'font-mono text-muted', 'sortable' => false, 'priority' => 2],
            ['key' => 'closed_won', 'label' => 'Closed Won', 'class' => 'font-mono text-success font-bold', 'formatter' => fn($v) => number_format((float)$v), 'sortable' => false, 'priority' => 1],
            ['key' => 'conversion_rate', 'label' => 'Conversion Rate', 'suffix' => '%', 'class' => 'font-mono font-bold text-ink', 'sortable' => false, 'priority' => 1],
        ];
    }

    public function updatingDays(): void
    {
        $this->resetPage('sourceReportPage');
    }

    public function exportExcel()
    {
        $rows = collect($this->getReportData());
        $headings = ['Lead Source', 'Total Leads', 'Average Lead Cost (Credits)', 'Closed Won', 'Conversion Rate (%)'];
        $columns = ['source_name', 'total_leads', 'cost_per_lead', 'closed_won', fn($r) => $r['conversion_rate'] . '%'];

        $filename = 'source-performance-report_'.now()->format('Y-m-d').'.xlsx';
        $subtitle = 'Exported '.now()->format('d M Y, H:i T')." | Timeframe: Last {$this->days} Days";

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return Excel::download(
            new BaseStyledExport(
                data: $rows,
                title: 'Lead Source Performance Report',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                hasTotals: false
            ),
            $filename
        );
    }

    protected function getReportData(): array
    {
        $this->validate(['days'=>'required|integer|in:7,30,90']);
        $user = auth()->user();
        $query = (Lead::usesSalesPanel() ? Lead::panelQuery() : Lead::query())->where('created_at', '>=', now()->subDays($this->days));

        if ($user && ! Lead::usesSalesPanel()) {
            if ($user->hasRole('Client')) {
                $client = Client::where('organization_id', $user->organization_id)->first();
                if ($client) {
                    $query->where('client_id', $client->id);
                }
            } elseif ($user->hasRole('Sales Executive') || $user->hasRole('Executive')) {
                $query->where('assigned_to', $user->id);
            }
        }

        $results = $query->select(
            'lead_source_id',
            DB::raw('count(*) as total_leads'),
            DB::raw('AVG(lead_cost) as actual_cost'),
            DB::raw("sum(case when LOWER(REPLACE(current_stage, ' ', '_')) IN ('closed_won','won') or status = 'closed_won' then 1 else 0 end) as closed_won")
        )->groupBy('lead_source_id')->with('leadSource')->get();

        $data = [];
        foreach ($results as $row) {
            $sourceName = $row->leadSource?->name ?: 'Direct Ingestion';
            $total = $row->total_leads ?: 1;
            $won = $row->closed_won ?: 0;
            $costPerLead = $row->actual_cost !== null ? round((float)$row->actual_cost,2) : null; // Stored credit charge; unknown costs remain empty.
            $conversionRate = round(($won / $total) * 100, 1);

            $data[] = [
                'source_name' => ucfirst($sourceName),
                'total_leads' => $total,
                'cost_per_lead' => $costPerLead,
                'closed_won' => $won,
                'conversion_rate' => $conversionRate,
            ];
        }


        return $data;

    }

    public function render()
    {
        $allData = collect($this->getReportData());
        $currentPage = LengthAwarePaginator::resolveCurrentPage('sourceReportPage');
        $perPage = 15;
        $currentPageItems = $allData->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginatedData = new LengthAwarePaginator(
            $currentPageItems,
            $allData->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName'=>'sourceReportPage']
        );

        return view('livewire.reports.source-performance', [
            'reportData' => $paginatedData,
            'chartReportData' => $allData->toArray(),
        ]);
    }
}
