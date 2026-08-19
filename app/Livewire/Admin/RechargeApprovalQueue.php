<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Livewire\Concerns\HasAdvancedTable;
use App\Models\RechargeRequest;
use App\Models\CreditWallet;
use App\Models\CreditTransaction;
use App\Models\NotificationLog;
use App\Models\Client;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Throwable;

class RechargeApprovalQueue extends Component
{
    use HasAdvancedTable;

    public ?int $selectedRequestId = null;
    public string $referenceNote = '';
    public string $rejectionReason = '';

    public bool $showApproveModal = false;
    public bool $showRejectModal = false;

    public function mount(): void
    {
        $this->loadColumnPreferences();
    }

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'Req ID', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-ink'],
            ['key' => 'client_name', 'label' => 'Client / Organization', 'type' => 'text', 'priority' => 1, 'class' => 'font-bold text-ink'],
            ['key' => 'package_name', 'label' => 'Package', 'type' => 'text', 'priority' => 2],
            ['key' => 'amount_formatted', 'label' => 'Amount', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-ink'],
            ['key' => 'credits_formatted', 'label' => 'Credits', 'type' => 'text', 'priority' => 1, 'class' => 'font-mono text-primary font-bold'],
            ['key' => 'payment_reference', 'label' => 'Reference / UTR', 'type' => 'text', 'priority' => 2, 'class' => 'font-mono text-muted text-[11px]'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'sortable' => true, 'priority' => 1, 'badgeMap' => [
                'pending' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'approved' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'rejected' => 'bg-red-50 text-red-700 border border-red-200',
            ]],
            ['key' => 'created_at', 'label' => 'Requested', 'type' => 'date', 'sortable' => true, 'priority' => 2],
            ['key' => 'actions', 'label' => 'Actions', 'type' => 'recharge_actions', 'priority' => 1],
        ];
    }

    public function quickFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All Requests'],
            ['key' => 'pending', 'label' => 'Pending Approval'],
            ['key' => 'approved', 'label' => 'Approved'],
            ['key' => 'rejected', 'label' => 'Rejected'],
        ];
    }

    public function openApproveModal(int $id): void
    {
        $this->selectedRequestId = $id;
        $this->referenceNote = '';
        $this->showApproveModal = true;
    }

    public function openRejectModal(int $id): void
    {
        $this->selectedRequestId = $id;
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function approveRequest(?int $id = null): void
    {
        $targetId = $id ?? $this->selectedRequestId;

        if (!$targetId) {
            return;
        }

        try {
            DB::transaction(function () use ($targetId) {
                $req = RechargeRequest::with('package')->findOrFail($targetId);

                if ($req->status !== 'pending') {
                    throw new \Exception('Recharge request is no longer pending.');
                }

                // Pessimistic lock on client wallet
                $wallet = CreditWallet::where('client_id', $req->client_id)->lockForUpdate()->first();

                if (!$wallet) {
                    $wallet = CreditWallet::create([
                        'client_id' => $req->client_id,
                        'balance' => 0.00,
                    ]);
                }

                $before = (float) $wallet->balance;
                $creditToAdd = $req->package ? (float) $req->package->credit_count : ($req->amount / 10.0);
                $after = $before + $creditToAdd;

                $wallet->update(['balance' => $after]);

                // Create CreditTransaction row
                CreditTransaction::create([
                    'client_id' => $req->client_id,
                    'lead_id' => null,
                    'package_id' => $req->package_id,
                    'credit_before' => $before,
                    'credit_used' => $creditToAdd,
                    'credit_after' => $after,
                    'transaction_type' => 'recharge',
                    'created_at' => now(),
                ]);

                // Update recharge_requests row
                $req->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'reference_note' => $this->referenceNote ?: 'Manual approval via Admin Queue',
                ]);

                // Write to audit_logs
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'recharge.approved',
                    'subject_type' => RechargeRequest::class,
                    'subject_id' => $req->id,
                    'from_value' => 'pending',
                    'to_value' => 'approved',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                ]);

                // Dispatch Client Notification
                NotificationLog::create([
                    'notifiable_type' => Client::class,
                    'notifiable_id' => $req->client_id,
                    'channel' => 'crm',
                    'status' => 'sent',
                    'payload' => json_encode([
                        'title' => 'Recharge Approved 💳',
                        'message' => "Your recharge of {$creditToAdd} credits (₹{$req->amount}) has been approved.",
                    ]),
                    'sent_at' => now(),
                ]);
            });

            $this->reset(['showApproveModal', 'selectedRequestId', 'referenceNote']);
            $this->dispatch('toast', type: 'success', message: 'Recharge request approved and wallet balance credited successfully.');
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: "Approval error: {$e->getMessage()}");
        }
    }

    public function rejectRequest(?int $id = null): void
    {
        $targetId = $id ?? $this->selectedRequestId;

        if (!$targetId) {
            return;
        }

        try {
            DB::transaction(function () use ($targetId) {
                $req = RechargeRequest::findOrFail($targetId);

                if ($req->status !== 'pending') {
                    throw new \Exception('Recharge request is no longer pending.');
                }

                $req->update([
                    'status' => 'rejected',
                    'rejection_reason' => $this->rejectionReason ?: 'Request declined by administrator.',
                ]);

                // Write to audit_logs
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'recharge.rejected',
                    'subject_type' => RechargeRequest::class,
                    'subject_id' => $req->id,
                    'from_value' => 'pending',
                    'to_value' => 'rejected',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                ]);

                // Dispatch Client Notification
                NotificationLog::create([
                    'notifiable_type' => Client::class,
                    'notifiable_id' => $req->client_id,
                    'channel' => 'crm',
                    'status' => 'sent',
                    'payload' => json_encode([
                        'title' => 'Recharge Request Declined',
                        'message' => "Your recharge request was declined. Reason: {$this->rejectionReason}",
                    ]),
                    'sent_at' => now(),
                ]);
            });

            $this->reset(['showRejectModal', 'selectedRequestId', 'rejectionReason']);
            $this->dispatch('toast', type: 'success', message: 'Recharge request declined.');
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: "Rejection error: {$e->getMessage()}");
        }
    }

    protected function getFilteredQuery()
    {
        $query = RechargeRequest::with(['client', 'package']);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('payment_reference', 'like', '%' . $this->search . '%')
                  ->orWhere('amount', 'like', '%' . $this->search . '%')
                  ->orWhereHas('client', fn($cq) => $cq->where('name', 'like', '%' . $this->search . '%'));
            });
        }

        if ($this->statusFilter === 'pending') {
            $query->where('status', 'pending');
        } elseif ($this->statusFilter === 'approved') {
            $query->where('status', 'approved');
        } elseif ($this->statusFilter === 'rejected') {
            $query->where('status', 'rejected');
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
        $requests = $this->getFilteredQuery()->paginate($this->perPage);

        return view('livewire.admin.recharge-approval-queue', [
            'requests' => $requests,
        ])->layout('layouts.app');
    }
}
