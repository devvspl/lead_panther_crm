<?php

// TODO: Move reporting queries to dedicated analytics database when scaling

namespace App\Livewire\Reports;

use App\Exports\BaseStyledExport;
use App\Models\Client;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class SlaDashboard extends Component
{
    use WithPagination;

    public function tableColumns(): array
    {
        return [
            ['key' => 'lead_code', 'label' => 'Lead Code', 'class' => 'font-bold font-mono text-ink', 'sortable' => false, 'priority' => 1],
            ['key' => 'name', 'label' => 'Lead Name', 'class' => 'font-semibold text-ink', 'sortable' => false, 'priority' => 1],
            ['key' => 'assigned_name', 'label' => 'Assigned To', 'class' => 'text-muted', 'sortable' => false, 'priority' => 2],
            ['key' => 'created_at', 'label' => 'Assigned At', 'class' => 'text-muted font-mono text-[11px]', 'sortable' => false, 'priority' => 2],
            ['key' => 'minutes_over', 'label' => 'Minutes Over Target', 'prefix' => '+', 'suffix' => ' mins', 'class' => 'font-mono font-bold text-danger', 'sortable' => false, 'priority' => 1],
            ['key' => 'status_label', 'label' => 'Current SLA Status', 'type' => 'badge', 'badgeStyle' => fn ($val, $row) => (! empty($row['is_unresponded']) ? 'bg-red-100 text-red-800 animate-pulse border border-red-200' : 'bg-amber-50 text-amber-800 border border-amber-200'), 'sortable' => false, 'priority' => 1],
        ];
    }

    public function exportExcel()
    {
        $breached = collect($this->getBreachedLeadsData());
        $headings = ['Lead Code', 'Lead Name', 'Client Name', 'Assigned Executive', 'Assigned At', 'First Response At', 'Minutes Over SLA', 'SLA Status'];
        $columns = ['lead_code', 'name', 'client_name', 'assigned_name', 'created_at', 'first_response_at', 'minutes_over', 'status_label'];

        $filename = 'first-response-sla-report_'.now()->format('Y-m-d').'.xlsx';
        $subtitle = 'Exported '.now()->format('d M Y, H:i T').' | Breached & Late Response Audit';

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return Excel::download(
            new BaseStyledExport(
                data: $breached,
                title: 'First Response SLA Compliance & Breach Report',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                statusColumns: ['status_label'],
                hasTotals: false
            ),
            $filename
        );
    }

    protected function getSlaBuckets(): array
    {
        $user = auth()->user();
        $query = (Lead::usesSalesPanel() ? Lead::panelQuery() : Lead::query())->where('internal_processing_pending', false);

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

        $leads = $query->get();
        $excellent = 0;
        $acceptable = 0;
        $breached = 0;

        foreach ($leads as $lead) {
            match ($lead->sla_badge) { 'SLA Met' => $excellent++, 'SLA Breached' => $breached++, default => $acceptable++ };
        }

        return [
            'excellent' => $excellent,
            'acceptable' => $acceptable,
            'breached' => $breached,
        ];
    }

    protected function getBreachedLeadsData(): array
    {
        $user = auth()->user();
        $query = (Lead::usesSalesPanel() ? Lead::panelQuery() : Lead::query())->where('internal_processing_pending', false)->with(['client', 'assignedTo']);

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

        $leads = $query->get();
        $breachedList = [];

        foreach ($leads as $l) {
            if ($l->sla_badge === 'SLA Breached') {
                $minsOver = $l->executive_response_due_at->diffInMinutes($l->executive_first_response_at ?? now());
                $breachedList[] = [
                    'lead_code' => $l->lead_code,
                    'name' => $l->name,
                    'client_name' => $l->client?->name ?: 'Standard Client',
                    'assigned_name' => $l->assignedTo?->name ?: 'Unassigned',
                    'created_at' => $l->executive_assigned_at?->format('M d, H:i'),
                    'first_response_at' => $l->executive_first_response_at?->format('M d, H:i') ?? 'None',
                    'minutes_over' => round(max(0, $minsOver), 2),
                    'status_label' => 'SLA Breached',
                    'is_unresponded' => ! $l->executive_first_response_at,
                ];
            }
        }

        return $breachedList;
    }

    public function render()
    {
        $allBreached = collect($this->getBreachedLeadsData());
        $currentPage = LengthAwarePaginator::resolveCurrentPage('slaReportPage');
        $perPage = 15;
        $currentPageItems = $allBreached->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginatedBreached = new LengthAwarePaginator(
            $currentPageItems,
            $allBreached->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'slaReportPage']
        );

        return view('livewire.reports.sla-dashboard', [
            'buckets' => $this->getSlaBuckets(),
            'breachedLeads' => $paginatedBreached,
        ]);
    }
}
