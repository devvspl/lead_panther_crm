<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Livewire\Concerns\HasAdvancedTable;
use Illuminate\Support\Facades\DB;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Client;
use App\Models\AuditLog;

class AdminCredits extends Component
{
    use HasAdvancedTable;

    public bool $showAdjustModal = false;
    public ?int $selectedClientId = null;
    public string $adjustmentType = 'credit'; // credit or debit
    public float $adjustmentAmount = 100.00;
    public string $reason = '';

    public string $filterClient = '';

    public function mount(): void
    {
        $this->loadColumnPreferences();
    }

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'ID', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono text-muted text-[11px]'],
            ['key' => 'created_at', 'label' => 'Date & Time', 'type' => 'date', 'sortable' => true, 'priority' => 1, 'format' => 'M d, Y H:i'],
            ['key' => 'client_name', 'label' => 'Client / Organization', 'type' => 'text', 'priority' => 1, 'class' => 'font-bold text-ink'],
            ['key' => 'transaction_type', 'label' => 'Type', 'type' => 'badge', 'sortable' => true, 'priority' => 1, 'badgeMap' => [
                'recharge' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'refund' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'reserve' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'deduct' => 'bg-purple-50 text-purple-700 border border-purple-200',
            ]],
            ['key' => 'lead_summary', 'label' => 'Associated Lead', 'type' => 'text', 'priority' => 2, 'class' => 'font-mono text-muted'],
            ['key' => 'credit_used_formatted', 'label' => 'Credits In / Out', 'type' => 'text', 'priority' => 1, 'class' => 'font-mono font-bold text-ink'],
            ['key' => 'credit_after_formatted', 'label' => 'Balance After', 'type' => 'text', 'priority' => 2, 'class' => 'font-mono text-primary font-bold'],
        ];
    }

    public function quickFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All Transactions'],
            ['key' => 'recharge', 'label' => 'Recharges'],
            ['key' => 'reserve', 'label' => 'Lead Deductions'],
            ['key' => 'refund', 'label' => 'Refunds / Adjustments'],
        ];
    }

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

    protected function getFilteredQuery()
    {
        $query = CreditTransaction::with(['client', 'lead', 'package']);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('transaction_type', 'like', '%' . $this->search . '%')
                  ->orWhereHas('client', fn($cq) => $cq->where('name', 'like', '%' . $this->search . '%'))
                  ->orWhereHas('lead', fn($lq) => $lq->where('lead_code', 'like', '%' . $this->search . '%')->orWhere('name', 'like', '%' . $this->search . '%'));
            });
        }

        if ($this->filterClient) {
            $query->where('client_id', $this->filterClient);
        }

        if ($this->statusFilter === 'recharge') {
            $query->where('transaction_type', 'recharge');
        } elseif ($this->statusFilter === 'reserve') {
            $query->whereIn('transaction_type', ['reserve', 'deduct']);
        } elseif ($this->statusFilter === 'refund') {
            $query->where('transaction_type', 'refund');
        }

        if (!empty($this->sortField)) {
            $query->orderBy($this->sortField, $this->sortDirection);
        } else {
            $query->latest('id');
        }

        return $query;
    }

    public function render()
    {
        $transactions = $this->getFilteredQuery()->paginate($this->perPage);
        $clients = Client::all();

        return view('livewire.admin.admin-credits', [
            'transactions' => $transactions,
            'clients' => $clients,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $data = $this->getFilteredQuery()->get();
        $filename = "admin-credit-transactions_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Date & Time', 'Client', 'Transaction Type', 'Lead Code', 'Credit Before', 'Credit Used/Added', 'Credit After'];
        $columns = [
            fn($tx) => $tx->created_at ? \Carbon\Carbon::parse($tx->created_at)->format('M d, Y H:i') : '',
            fn($tx) => $tx->client?->name ?: 'System Client',
            fn($tx) => $tx->transaction_type,
            fn($tx) => $tx->lead?->lead_code ?: 'N/A',
            'credit_before',
            'credit_used',
            'credit_after',
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T') . ($this->statusFilter ? " | Type: {$this->statusFilter}" : '');

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
