<?php

namespace App\Livewire\Credits;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\CreditWallet as WalletModel;
use App\Models\CreditTransaction;
use App\Models\CreditPackage;
use App\Models\RechargeRequest;
use App\Models\Client;

class CreditWallet extends Component
{
    use WithPagination;

    public bool $showRechargeModal = false;
    public ?int $selectedPackageId = null;

    public string $filterType = '';
    public string $filterDateRange = '';

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
            }
        }

        $transactions = $txQuery->latest('created_at')->paginate(10);
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
            }
        }

        $data = $txQuery->latest('created_at')->get();

        $clientName = $client ? \Illuminate\Support\Str::slug($client->name) : 'all';
        $filename = "credit-transactions_{$clientName}_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Date & Time', 'Transaction Type', 'Lead Code', 'Credit Before', 'Credit Used/Added', 'Credit After'];
        $columns = [
            fn($tx) => $tx->created_at ? $tx->created_at->format('M d, Y H:i') : '',
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
