<?php

namespace App\Livewire\Credits;

use Livewire\Component;
use App\Models\CreditWallet as WalletModel;
use App\Models\CreditTransaction;
use App\Models\CreditPackage;
use App\Models\RechargeRequest;
use App\Models\Client;
use App\Livewire\Concerns\HasAdvancedTable;

class CreditWallet extends Component
{
    use HasAdvancedTable;

    public bool $showRechargeModal = false;
    public ?int $selectedPackageId = null;

    public string $filterType = '';
    public string $filterDateRange = '';
    public ?string $customFrom = null;
    public ?string $customTo = null;

    public function tableColumns(): array
    {
        return [
            ['key' => 'created_at', 'label' => 'Date & Time', 'type' => 'date', 'sortable' => true, 'priority' => 1],
            ['key' => 'transaction_type', 'label' => 'Type', 'type' => 'badge', 'badgeMap' => [
                'recharge' => 'bg-green-50 text-green-700 border border-green-200',
                'reserve' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'refund' => 'bg-purple-50 text-purple-700 border border-purple-200',
                'deduct' => 'bg-blue-50 text-blue-700 border border-blue-200',
            ], 'sortable' => true, 'priority' => 1],
            ['key' => 'lead', 'label' => 'Associated Lead', 'render' => fn($row) => $row->lead ? '<a href="' . route('leads.index') . '" class="font-semibold font-mono text-ink hover:underline">' . e($row->lead->lead_code) . '</a>' : '<span class="text-muted">—</span>', 'sortable' => false, 'priority' => 1],
            ['key' => 'credit_before', 'label' => 'Credit Before', 'formatter' => fn($v) => number_format((float)$v), 'class' => 'font-mono text-muted', 'sortable' => false, 'priority' => 2],
            ['key' => 'credit_used', 'label' => 'Used / Added', 'render' => function($row) {
                $isPlus = $row->transaction_type === 'recharge' || $row->transaction_type === 'refund';
                $sign = $isPlus ? '+' : '-';
                $color = $isPlus ? 'text-success' : 'text-danger';
                return '<span class="font-mono font-bold ' . $color . '">' . $sign . number_format((float)$row->credit_used) . '</span>';
            }, 'sortable' => false, 'priority' => 1],
            ['key' => 'credit_after', 'label' => 'Credit After', 'formatter' => fn($v) => number_format((float)$v), 'class' => 'font-mono font-bold text-ink', 'sortable' => false, 'priority' => 2],
        ];
    }

    public function mount(): void
    {
        $firstPkg = CreditPackage::first();
        if ($firstPkg) {
            $this->selectedPackageId = $firstPkg->id;
        }
    }

    public function openRechargeModal(): void
    {
        $this->showRechargeModal = true;
    }

    public function closeRechargeModal(): void
    {
        $this->showRechargeModal = false;
    }

    public function submitRechargeRequest(): void
    {
        $user = auth()->user();
        $client = Client::where('organization_id', $user?->organization_id)->first() ?? Client::first();

        if (!$client || !$this->selectedPackageId) {
            $this->dispatch('toast', type: 'error', message: 'Please select a valid package.');
            return;
        }

        $package = CreditPackage::find($this->selectedPackageId);
        if ($package) {
            RechargeRequest::create([
                'client_id' => $client->id,
                'package_id' => $package->id,
                'amount' => $package->price,
                'status' => 'pending',
                'requested_at' => now(),
            ]);

            $this->dispatch('toast', type: 'success', message: 'Recharge Request Sent! Status is now Pending Approval.');
            $this->closeRechargeModal();
        }
    }

    public function render()
    {
        $user = auth()->user();
        $client = Client::where('organization_id', $user?->organization_id)->first() ?? Client::first();

        $wallet = $client ? WalletModel::firstOrCreate(['client_id' => $client->id], ['balance' => 5000.00]) : null;

        $lastRecharge = $client ? CreditTransaction::where('client_id', $client->id)
            ->where('transaction_type', 'recharge')
            ->latest('created_at')
            ->first() : null;

        $txQuery = $client ? CreditTransaction::with(['lead', 'package'])->where('client_id', $client->id) : CreditTransaction::query()->whereRaw('1 = 0');

        if (!empty($this->search)) {
            $txQuery->where(function ($q) {
                $q->where('transaction_type', 'like', "%{$this->search}%")
                  ->orWhereHas('lead', function ($lq) {
                      $lq->where('lead_code', 'like', "%{$this->search}%")
                         ->orWhere('name', 'like', "%{$this->search}%");
                  });
            });
        }

        if ($this->filterType) {
            $txQuery->where('transaction_type', $this->filterType);
        }

        if ($this->filterDateRange) {
            if ($this->filterDateRange === 'today') {
                $txQuery->whereDate('created_at', now());
            } elseif ($this->filterDateRange === 'week') {
                $txQuery->where('created_at', '>=', now()->subDays(7));
            } elseif ($this->filterDateRange === 'month') {
                $txQuery->where('created_at', '>=', now()->subDays(30));
            } elseif ($this->filterDateRange === 'custom' && $this->customFrom && $this->customTo) {
                $txQuery->whereBetween('created_at', [$this->customFrom . ' 00:00:00', $this->customTo . ' 23:59:59']);
            }
        }

        $sortField = in_array($this->sortField, ['created_at', 'transaction_type', 'id']) ? $this->sortField : 'created_at';
        $sortDir = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $transactions = $txQuery->orderBy($sortField, $sortDir)->paginate($this->perPage);
        $packages = CreditPackage::all();

        $rechargeRequests = $client ? RechargeRequest::with('package')
            ->where('client_id', $client->id)
            ->latest('requested_at')
            ->take(5)
            ->get() : collect();

        return view('livewire.credits.credit-wallet', [
            'wallet' => $wallet,
            'lastRecharge' => $lastRecharge,
            'transactions' => $transactions,
            'packages' => $packages,
            'rechargeRequests' => $rechargeRequests,
            'client' => $client,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $user = auth()->user();
        $client = Client::where('organization_id', $user?->organization_id)->first() ?? Client::first();

        $txQuery = $client ? CreditTransaction::with(['lead', 'package'])->where('client_id', $client->id) : CreditTransaction::query()->whereRaw('1 = 0');

        if ($this->filterType) {
            $txQuery->where('transaction_type', $this->filterType);
        }

        if ($this->filterDateRange) {
            if ($this->filterDateRange === 'today') {
                $txQuery->whereDate('created_at', now());
            } elseif ($this->filterDateRange === 'week') {
                $txQuery->where('created_at', '>=', now()->subDays(7));
            } elseif ($this->filterDateRange === 'month') {
                $txQuery->where('created_at', '>=', now()->subDays(30));
            } elseif ($this->filterDateRange === 'custom' && $this->customFrom && $this->customTo) {
                $txQuery->whereBetween('created_at', [$this->customFrom . ' 00:00:00', $this->customTo . ' 23:59:59']);
            }
        }

        $data = $txQuery->latest('created_at')->get();

        $clientName = $client ? \Illuminate\Support\Str::slug($client->name) : 'all';
        $filename = "credit-transactions_{$clientName}_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Date & Time', 'Transaction Type', 'Lead Code', 'Credit Before', 'Credit Used/Added', 'Credit After'];
        $columns = [
            fn($tx) => $tx->created_at ? \Carbon\Carbon::parse($tx->created_at)->format('M d, Y H:i') : '',
            fn($tx) => $tx->transaction_type,
            fn($tx) => $tx->lead?->lead_code ?: 'N/A',
            'credit_before',
            'credit_used',
            'credit_after',
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T') . ($this->filterType ? " | Type: {$this->filterType}" : '') . ($this->filterDateRange ? " | DateRange: {$this->filterDateRange}" : '');

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: $data,
                title: 'Credit Transaction History',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                statusColumns: ['transaction_type'],
                currencyColumns: ['credit_before', 'credit_used', 'credit_after'],
                hasTotals: true
            ),
            $filename
        );
    }
}
