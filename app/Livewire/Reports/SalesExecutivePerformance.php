<?php

// TODO: Move reporting queries to dedicated analytics database when scaling

namespace App\Livewire\Reports;

use App\Exports\BaseStyledExport;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\SiteVisit;
use App\Models\User;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

class SalesExecutivePerformance extends Component
{
    public string $sortField = 'bookings_closed';

    public string $sortDirection = 'desc';

    public function tableColumns(): array
    {
        return [
            ['key' => 'executive_name', 'label' => 'Executive Name', 'class' => 'font-bold text-ink', 'sortable' => true, 'priority' => 1],
            ['key' => 'assigned_leads', 'label' => 'Leads Assigned', 'class' => 'font-mono', 'formatter' => fn ($v) => number_format((float) $v), 'sortable' => true, 'priority' => 1],
            ['key' => 'sla_contacted_rate', 'label' => 'Contacted Within SLA (%)', 'suffix' => '%', 'class' => 'font-mono font-bold text-purple-700', 'sortable' => true, 'priority' => 1],
            ['key' => 'site_visits', 'label' => 'Site Visits Logged', 'class' => 'font-mono', 'formatter' => fn ($v) => number_format((float) $v), 'sortable' => true, 'priority' => 1],
            ['key' => 'bookings_closed', 'label' => 'Bookings Closed', 'class' => 'font-mono font-bold text-success', 'formatter' => fn ($v) => number_format((float) $v), 'sortable' => true, 'priority' => 1],
        ];
    }

    public function sortBy(string $field): void
    {
        abort_unless(in_array($field, ['executive_name', 'assigned_leads', 'sla_contacted_rate', 'site_visits', 'bookings_closed']), 422);
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }
    }

    public function exportExcel()
    {
        $rows = collect($this->getExecutiveData());
        $headings = ['Sales Executive', 'Leads Assigned', 'Contacted Within SLA (%)', 'Site Visits Logged', 'Bookings Closed'];
        $columns = ['executive_name', 'assigned_leads', fn ($r) => $r['sla_contacted_rate'].'%', 'site_visits', 'bookings_closed'];

        $filename = 'sales-executive-leaderboard_'.now()->format('Y-m-d').'.xlsx';
        $subtitle = 'Exported '.now()->format('d M Y, H:i T')." | Sorted By: {$this->sortField} ({$this->sortDirection})";

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return Excel::download(
            new BaseStyledExport(
                data: $rows,
                title: 'Sales Executive Performance Leaderboard',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                hasTotals: false
            ),
            $filename
        );
    }

    protected function getExecutiveData(): array
    {
        $user = auth()->user();
        $execsQuery = User::role('sales-executive');

        if ($user && $user->hasRole('Sales Executive')) {
            $execsQuery->where('id', $user->id);
        }

        if (Lead::usesSalesPanel() && ! $user->hasRole('Sales Executive')) {
            $execsQuery->where('organization_id', $user->organization_id)->whereNotNull('organization_id');
        }
        $execs = $execsQuery->get();
        $data = [];

        foreach ($execs as $exec) {
            $assignedCount = (Lead::usesSalesPanel() ? Lead::panelQuery() : Lead::query())->where('assigned_to', $exec->id)->count();
            $query = (Lead::usesSalesPanel() ? Lead::panelQuery() : Lead::query())->where('assigned_to', $exec->id);
            $assigned = (clone $query)->get();
            $met = $assigned->filter(fn ($lead) => $lead->first_response_at && $lead->created_at->diffInMinutes($lead->first_response_at) <= 15)->count();
            $slaRate = $assignedCount ? round($met / $assignedCount * 100, 1) : 0;
            $leadIds = (clone $query)->select('id');
            $siteVisits = SiteVisit::whereIn('lead_id', clone $leadIds)->where('attendance', 'attended')->count();
            $bookingsClosed = Booking::whereIn('lead_id', clone $leadIds)->where('status', 'confirmed')->count();
            $data[] = [
                'executive_name' => $exec->name,
                'assigned_leads' => $assignedCount,
                'sla_contacted_rate' => $slaRate,
                'site_visits' => $siteVisits,
                'bookings_closed' => $bookingsClosed,
            ];
        }

        // Sort data by active column
        usort($data, function ($a, $b) {
            $field = $this->sortField;
            if (! isset($a[$field])) {
                return 0;
            }
            $valA = $a[$field];
            $valB = $b[$field];
            if ($valA == $valB) {
                return 0;
            }
            if ($this->sortDirection === 'asc') {
                return ($valA < $valB) ? -1 : 1;
            } else {
                return ($valA > $valB) ? -1 : 1;
            }
        });

        return $data;

    }

    public function render()
    {
        return view('livewire.reports.sales-executive-performance', [
            'executives' => $this->getExecutiveData(),
        ]);
    }
}
