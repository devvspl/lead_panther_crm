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
    public ?int $customCredits = null;
    public string $rechargeNotes = '';

    public string $filterType = '';
    public string $filterDateRange = '';
    public ?string $customFrom = null;
    public ?string $customTo = null;

    private function billingClient(): ?Client
    {
        $user = auth()->user();
        if (!$user) return null;
        $own = Client::whereKey($user->id)->whereHas('wallet')->first();
        if ($own) return $own;
        if ($user->organization_id) {
            return Client::where('organization_id', $user->organization_id)->orderBy('id')->first() ?? Client::find($user->id);
        }
        return Client::find($user->id);
    }

    public function tableColumns(): array
    {
        return [
            ['key' => 'created_at', 'label' => 'Date & Time', 'type' => 'date', 'sortable' => true, 'priority' => 1, 'formatter' => fn($val) => $val ? \Carbon\Carbon::parse($val)->format('M d, Y H:i') : '-'],
            ['key' => 'transaction_type', 'label' => 'Type', 'sortable' => true, 'priority' => 1, 'render' => function($row) {
                $type = $row->transaction_type;
                $label = match($type) {
                    'deduct', 'reserve', 'lead_consumption' => 'Lead Consumption',
                    'recharge' => 'Recharge',
                    'refund' => 'Refund',
                    default => ucfirst(str_replace('_', ' ', (string) $type)),
                };
                $badgeClass = match($type) {
                    'deduct', 'reserve', 'lead_consumption' => 'bg-primary/10 text-primary border border-primary/20',
                    'recharge' => 'bg-emerald-50 text-emerald-700 border border-emerald-200/70',
                    'refund' => 'bg-blue-50 text-blue-700 border border-blue-200/70',
                    default => 'bg-canvas text-ink border border-border',
                };
                return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ' . $badgeClass . '">' . e($label) . '</span>';
            }],
            ['key' => 'lead', 'label' => 'Lead Code', 'render' => fn($row) => $row->lead ? '<a href="' . route('leads.index') . '" class="font-semibold font-mono text-xs text-ink hover:text-primary hover:underline transition">' . e($row->lead->lead_code) . '</a>' : '<span class="text-muted font-mono text-xs">—</span>', 'sortable' => false, 'priority' => 1],
            ['key' => 'credit_before', 'label' => 'Credit Before', 'formatter' => fn($v) => $v === null ? '-' : number_format((int)$v), 'class' => 'font-mono text-xs text-muted', 'sortable' => false, 'priority' => 2],
            ['key' => 'credit_used', 'label' => 'Change', 'render' => function($row) {
                if ($row->is_legacy) return '<span class="text-muted text-xs">Legacy</span>';
                $isPlus = $row->credit_after >= $row->credit_before;
                $sign = $isPlus ? '+' : '-';
                $badgeClass = $isPlus
                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/70'
                    : 'bg-rose-50 text-rose-600 border border-rose-200/70';
                return '<span class="inline-flex items-center px-2 py-0.5 rounded-full font-mono text-xs font-bold ' . $badgeClass . '">' . $sign . number_format((int)$row->credit_used) . '</span>';
            }, 'sortable' => true, 'priority' => 1],
            ['key' => 'credit_after', 'label' => 'Credit After', 'formatter' => fn($v) => $v === null ? '-' : number_format((int)$v), 'class' => 'font-mono text-xs font-bold text-ink', 'sortable' => false, 'priority' => 1],
        ];
    }

    public function mount(): void
    {
        // Standard packages selection commented out; credit quantity entered directly
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
        $client = $this->billingClient();

        if (!$client) {
            $this->dispatch('toast', type: 'error', message: 'Billing client not found.');
            return;
        }

        $amount = 0;
        $packageId = null;

        if ($this->customCredits && $this->customCredits > 0) {
            $amount = (int) $this->customCredits;
        } elseif ($this->selectedPackageId && ($package = CreditPackage::find($this->selectedPackageId))) {
            $amount = (int) $package->credit_count;
            $packageId = $package->id;
        } else {
            $this->dispatch('toast', type: 'error', message: 'Please select a package or specify a credit quantity.');
            return;
        }

        // Prevent duplicate requests within 2 minutes
        $existing = RechargeRequest::where('client_id', $client->id)
            ->where('status', 'pending')
            ->where('amount', $amount)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->first();

        if ($existing) {
            $this->dispatch('toast', type: 'warning', message: 'A pending request for this quantity was recently submitted. Please wait for approval.');
            $this->closeRechargeModal();
            return;
        }

        RechargeRequest::create([
            'client_id' => $client->id,
            'package_id' => $packageId,
            'amount' => $amount,
            'status' => 'pending',
            'reference_note' => $this->rechargeNotes ?: 'Credit request via Client Portal',
            'requested_at' => now(),
        ]);

        $this->dispatch('toast', type: 'success', message: 'Recharge Request Sent! Status is now Pending Approval.');
        $this->customCredits = null;
        $this->rechargeNotes = '';
        $this->closeRechargeModal();
    }

    public function render()
    {
        $user = auth()->user();
        $client = $this->billingClient();

        $wallet = $client ? WalletModel::firstOrCreate(['client_id' => $client->id], ['balance' => 0]) : null;

        $lastRecharge = $client ? CreditTransaction::where('client_id', $client->id)
            ->where('transaction_type', 'recharge')
            ->latest('created_at')
            ->first() : null;

        $txQuery = $client ? CreditTransaction::with(['lead', 'package'])->where('client_id', $client->id) : CreditTransaction::query()->whereRaw('1 = 0');

        if ($wallet && $wallet->reconciled_at) {
            $txQuery->where('is_legacy', false);
        }

        if (!empty($this->search)) {
            $term = trim($this->search);
            $cleanTerm = ltrim($term, '#');
            $txQuery->where(function ($q) use ($term, $cleanTerm) {
                if (is_numeric($cleanTerm)) {
                    $q->orWhere('id', (int) $cleanTerm);
                }
                $q->orWhere('transaction_type', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%")
                  ->orWhereHas('lead', function ($lq) use ($term) {
                      $lq->where('lead_code', 'like', "%{$term}%")
                         ->orWhere('name', 'like', "%{$term}%")
                         ->orWhere('phone', 'like', "%{$term}%");
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

        $sortField = in_array($this->sortField, ['created_at', 'transaction_type', 'credit_used', 'credit_before', 'credit_after', 'id']) ? $this->sortField : 'created_at';
        $sortDir = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $transactions = $txQuery->orderBy($sortField, $sortDir)->orderBy('id', $sortDir)->paginate($this->perPage);
        $packages = CreditPackage::all();

        $rechargeRequests = $client ? RechargeRequest::with('package')
            ->where('client_id', $client->id)
            ->latest('requested_at')
            ->take(5)
            ->get() : collect();

        $pricingResolution = app(\App\Services\LeadPricingService::class)->resolveCost(
            null,
            null,
            $client?->organization
        );

        return view('livewire.credits.credit-wallet', [
            'wallet' => $wallet,
            'lastRecharge' => $lastRecharge,
            'transactions' => $transactions,
            'packages' => $packages,
            'rechargeRequests' => $rechargeRequests,
            'client' => $client,
            'pricingResolution' => $pricingResolution,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $user = auth()->user();
        $client = $this->billingClient();

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

        $wallet = $client ? WalletModel::where('client_id', $client->id)->first() : null;
        if ($wallet && $wallet->reconciled_at) {
            $txQuery->where('is_legacy', false);
        }

        $data = $txQuery->orderBy('created_at', 'desc')->orderBy('id', 'desc')->get();

        $clientName = $client ? \Illuminate\Support\Str::slug($client->name) : 'all';
        $filename = "credit-transactions_{$clientName}_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Date & Time', 'Transaction Type', 'Lead Code', 'Credit Before', 'Credit Used/Added', 'Credit After'];
        $columns = [
            fn($tx) => $tx->created_at ? \Carbon\Carbon::parse($tx->created_at)->format('M d, Y H:i') : '',
            fn($tx) => \App\Support\ClientFacingText::label($tx->transaction_type),
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
                currencyColumns: [],
                hasTotals: true
            ),
            $filename
        );
    }
}
