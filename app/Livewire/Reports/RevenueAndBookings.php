<?php

// TODO: Move reporting queries to dedicated analytics database when scaling

namespace App\Livewire\Reports;

use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Client;
use App\Models\CreditTransaction;
use App\Models\Lead;
use App\Exports\BaseStyledExport;
use App\Models\Booking;
use App\Models\PaymentReceived;
use App\Support\NavigationConfig;

class RevenueAndBookings extends Component
{
    public function tableColumns(): array
    {
        return [
            ['key'=>'client_name','label'=>'Lead Owner','priority'=>1],
            ['key'=>'credits_spent','label'=>'Credits Consumed','priority'=>1,'formatter'=>fn($v)=>number_format((float)$v,2)],
            ['key'=>'bookings_closed','label'=>'Confirmed Bookings','priority'=>1],
            ['key'=>'booking_value','label'=>'Booked Amount','priority'=>1,'formatter'=>fn($v)=>'₹'.number_format((float)$v,2)],
            ['key'=>'revenue','label'=>'Payments Received','priority'=>1,'formatter'=>fn($v)=>'₹'.number_format((float)$v,2)],
            ['key'=>'outstanding','label'=>'Outstanding','priority'=>1,'formatter'=>fn($v)=>'₹'.number_format((float)$v,2)],
        ];
    }

    public function exportExcel()
    {
        $rows = collect($this->getClientRoiData());
        $headings=['Lead Owner','Credits Consumed','Confirmed Bookings','Booked Amount','Payments Received','Outstanding'];
        $columns=['client_name','credits_spent','bookings_closed','booking_value','revenue','outstanding'];

        $filename = 'revenue-and-bookings-roi-report_'.now()->format('Y-m-d').'.xlsx';
        $subtitle = 'Exported '.now()->format('d M Y, H:i T').' | Actual Bookings & Payments';

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return Excel::download(
            new BaseStyledExport(
                data: $rows,
                title: 'Revenue & Bookings Report',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                currencyColumns: ['booking_value','revenue','outstanding'],
                hasTotals: false
            ),
            $filename
        );
    }

    protected function getClientRoiData(): array
    {
        $user = auth()->user();
        $clientsQuery = Client::query()->whereIn('id',(Lead::usesSalesPanel() ? Lead::panelQuery() : Lead::query())->select('client_id'));

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
            $leadIds=(Lead::usesSalesPanel() ? Lead::panelQuery() : Lead::query())->where('client_id',$client->id)->select('id');
            $creditsUsed=CreditTransaction::withoutGlobalScopes()->whereIn('lead_id',clone $leadIds)->whereIn('transaction_type',['reserve','deduct','lead_consumption'])->sum('credit_used');
            $bookings=Booking::whereIn('lead_id',clone $leadIds)->where('status','confirmed')->get();
            $bookingsCount=$bookings->count();
            $bookedValue=(float)$bookings->sum('booking_amount');
            $revenue=(float)PaymentReceived::whereIn('booking_id',$bookings->pluck('id'))->where('status','completed')->sum('amount');
            $spentAmount=(float)$creditsUsed;
            $roiRatio=null;
            $data[] = [
                'client_name' => $client->name,
                'credits_spent' => $creditsUsed,
                'credits_spent_amount' => $spentAmount,
                'bookings_closed' => $bookingsCount,
                'revenue' => $revenue,
                'roi_ratio' => $roiRatio,
                'booking_value'=>$bookedValue,
                'outstanding'=>max(0,$bookedValue-$revenue),
            ];
        }


        return $data;

    }

    protected function getMonthlyRevenueChart(): array
    {
        $labels = [];
        $counts = [];
        $revenues = [];
        foreach (range(5, 0) as $offset) {
            $month = now()->subMonthsNoOverflow($offset);
            $labels[] = $month->format('M Y');
            $bookings = Booking::whereIn('lead_id',(Lead::usesSalesPanel() ? Lead::panelQuery() : Lead::query())->select('id'))->where('status','confirmed');
            $counts[] = (clone $bookings)->whereYear('booked_at', $month->year)->whereMonth('booked_at', $month->month)->count();
            $revenues[] = (float) PaymentReceived::whereIn('booking_id', $bookings->select('id'))->where('status', 'completed')->whereYear('paid_at', $month->year)->whereMonth('paid_at', $month->month)->sum('amount') / 10000000;
        }

        return ['labels' => $labels, 'bookings' => $counts, 'revenues' => $revenues];
    }

    public function render()
    {
        return view('livewire.reports.revenue-and-bookings', [
            'roiData' => $this->getClientRoiData(),
            'monthlyChart' => $this->getMonthlyRevenueChart(),
        ]);
    }
}
