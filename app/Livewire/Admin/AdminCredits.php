<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Client;
use App\Models\AuditLog;

class AdminCredits extends Component
{
    use WithPagination;

    public bool $showAdjustModal = false;
    public ?int $selectedClientId = null;
    public string $adjustmentType = 'credit'; // credit or debit
    public float $adjustmentAmount = 100.00;
    public string $reason = '';

    public string $filterClient = '';
    public string $filterType = '';

    public function openAdjustModal(): void
    {
        $firstClient = Client::first();
        if ($firstClient) {
            $this->selectedClientId = $firstClient->id;
        }
        $this->showAdjustModal = true;
    }

    public function closeAdjustModal(): void
    {
        $this->showAdjustModal = false;
        $this->reason = '';
    }

    public function executeAdjustment(): void
    {
        $this->validate([
            'selectedClientId' => 'required|exists:clients,id',
            'adjustmentAmount' => 'required|numeric|min:1',
            'adjustmentType' => 'required|in:credit,debit',
            'reason' => 'required|string|min:5',
        ]);

        DB::transaction(function () {
            $wallet = CreditWallet::where('client_id', $this->selectedClientId)->lockForUpdate()->first();

            if (!$wallet) {
                $wallet = CreditWallet::create([
                    'client_id' => $this->selectedClientId,
                    'balance' => 0.00,
                ]);
            }

            $before = (float) $wallet->balance;
            if ($this->adjustmentType === 'credit') {
                $after = $before + $this->adjustmentAmount;
                $txType = 'recharge';
            } else {
                $after = max(0.00, $before - $this->adjustmentAmount);
                $txType = 'refund';
            }

            $wallet->update(['balance' => $after]);

            CreditTransaction::create([
                'client_id' => $this->selectedClientId,
                'lead_id' => null,
                'package_id' => null,
                'credit_before' => $before,
                'credit_used' => $this->adjustmentAmount,
                'credit_after' => $after,
                'transaction_type' => $txType,
                'created_at' => now(),
            ]);

            // Audit log record (required for manual overrides bypassing standard flow)
            AuditLog::create([
                'user_id' => auth()->id() ?? 1,
                'action' => 'credit.manual_adjustment',
                'subject_type' => CreditWallet::class,
                'subject_id' => $wallet->id,
                'from_value' => json_encode(['balance' => $before]),
                'to_value' => json_encode(['balance' => $after, 'reason' => $this->reason]),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'LeadPantherAdmin/1.0',
                'created_at' => now(),
            ]);
        });

        $this->dispatch('toast', type: 'success', message: 'Manual credit adjustment completed and logged to audit trail.');
        $this->closeAdjustModal();
    }

    public function render()
    {
        $query = CreditTransaction::with(['client', 'lead', 'package']);

        if ($this->filterClient) {
            $query->where('client_id', $this->filterClient);
        }

        if ($this->filterType) {
            $query->where('transaction_type', $this->filterType);
        }

        $transactions = $query->latest('created_at')->paginate(15);
        $clients = Client::all();

        return view('livewire.admin.admin-credits', [
            'transactions' => $transactions,
            'clients' => $clients,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $query = CreditTransaction::with(['client', 'lead', 'package']);

        if ($this->filterClient) {
            $query->where('client_id', $this->filterClient);
        }

        if ($this->filterType) {
            $query->where('transaction_type', $this->filterType);
        }

        $data = $query->latest('created_at')->get();
        $filename = "admin-credit-transactions_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Date & Time', 'Client', 'Transaction Type', 'Lead Code', 'Credit Before', 'Credit Used/Added', 'Credit After'];
        $columns = [
            fn($tx) => $tx->created_at ? $tx->created_at->format('M d, Y H:i') : '',
            fn($tx) => $tx->client?->name ?: 'System Client',
            fn($tx) => $tx->transaction_type,
            fn($tx) => $tx->lead?->lead_code ?: 'N/A',
            'credit_before',
            'credit_used',
            'credit_after',
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T') . ($this->filterType ? " | Type: {$this->filterType}" : '') . ($this->filterClient ? " | ClientID: {$this->filterClient}" : '');

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: $data,
                title: 'Admin Credit Transactions Directory',
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
