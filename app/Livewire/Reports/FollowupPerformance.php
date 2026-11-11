<?php

// TODO: Move reporting queries to dedicated analytics database when scaling

namespace App\Livewire\Reports;

use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReportExport;
use App\Models\User;
use App\Models\LeadCommunication;
use App\Exports\BaseStyledExport;
use App\Models\Followup;
use App\Models\Lead;

class FollowupPerformance extends Component
{
    public function tableColumns(): array
    {
        return [
            ['key' => 'executive_name', 'label' => 'Sales Executive', 'class' => 'font-bold text-ink', 'sortable' => false, 'priority' => 1],
            ['key' => 'on_time', 'label' => 'Completed On Time', 'class' => 'font-mono font-bold text-success', 'formatter' => fn($v) => number_format((float)$v), 'sortable' => false, 'priority' => 1],
            ['key' => 'overdue', 'label' => 'Overdue / Delayed', 'class' => 'font-mono font-bold text-danger', 'formatter' => fn($v) => number_format((float)$v), 'sortable' => false, 'priority' => 1],
            ['key' => 'on_time_rate', 'label' => 'On-Time Execution Rate', 'suffix' => '%', 'class' => 'font-mono font-bold text-ink', 'sortable' => false, 'priority' => 1],
        ];
    }

    public function exportExcel()
    {
        $rows = collect($this->getFollowupData());
        $headings = ['Sales Executive', 'Completed On Time', 'Overdue / Delayed', 'On-Time Completion Rate (%)'];
        $columns = ['executive_name', 'on_time', 'overdue', fn($r) => $r['on_time_rate'] . '%'];

        $filename = 'followup-performance-report_'.now()->format('Y-m-d').'.xlsx';
        $subtitle = 'Exported '.now()->format('d M Y, H:i T').' | Followup Compliance Audit';

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return Excel::download(
            new BaseStyledExport(
                data: $rows,
                title: 'Followup Adherence & Performance Report',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                hasTotals: false
            ),
            $filename
        );
    }

    protected function getFollowupData(): array
    {
        $buildReport = function () {
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
                $leadIds=(Lead::usesSalesPanel() ? Lead::panelQuery() : Lead::query())->where('assigned_to',$exec->id)->select('id');
                $followups=Followup::whereIn('lead_id',$leadIds)->get();
                $onTime=0; $overdue=0;
                foreach($followups as $followup) {
                    if($followup->status==='completed') {
                        // Legacy rows have no completed_at column; updated_at is the recorded completion proxy.
                        $completedAt=$followup->updated_at;
                        $completedAt <= $followup->due_at ? $onTime++ : $overdue++;
                    } elseif($followup->status==='pending' && $followup->due_at < now()) $overdue++;
                }
                $rate=($onTime+$overdue)>0 ? round($onTime/($onTime+$overdue)*100,1) : 0;
                $data[] = [
                    'executive_name' => $exec->name,
                    'on_time' => $onTime,
                    'overdue' => $overdue,
                    'on_time_rate' => $rate,
                ];
            }


            return $data;
        };

        return $buildReport();
    }

    public function render()
    {
        return view('livewire.reports.followup-performance', [
            'followupData' => $this->getFollowupData(),
        ]);
    }
}
