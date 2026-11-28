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

    public function boot(): void
    {
        abort_unless(auth()->user()?->status === 'active' && auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
    }

    public ?int $selectedRequestId = null;
    public string $referenceNote = '';
    public string $rejectionReason = '';
    public $approvalQuantity = null;

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
            ['key' => 'package_name', 'label' => 'Package / Type', 'type' => 'text', 'priority' => 2],
            ['key' => 'credits_formatted', 'label' => 'Requested Credits', 'type' => 'text', 'priority' => 1, 'class' => 'font-mono text-primary font-bold'],
            ['key' => 'current_balance', 'label' => 'Current Balance', 'type' => 'text', 'priority' => 1, 'sortable' => false, 'render' => fn($row) => '<span class="font-mono font-semibold text-ink">' . number_format((int) ($row->client?->wallet?->balance ?? 0)) . ' Credits</span>'],
            ['key' => 'payment_reference', 'label' => 'Reference / Notes', 'type' => 'text', 'priority' => 2, 'render' => fn($row) => '<span class="font-mono text-muted text-[11px]">' . e($row->reference_note ?: $row->payment_reference ?: '—') . '</span>'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'sortable' => true, 'priority' => 1, 'badgeMap' => [
                'pending' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'approved' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'rejected' => 'bg-red-50 text-red-700 border border-red-200',
            ]],
            ['key' => 'created_at', 'label' => 'Requested Date', 'type' => 'date', 'sortable' => true, 'priority' => 2],
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
        $this->approvalQuantity = RechargeRequest::findOrFail($id)->amount;
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
                $req = RechargeRequest::with(['client.wallet', 'package'])->lockForUpdate()->findOrFail($targetId);

                if ($req->status !== 'pending') {
                    throw new \Exception('Recharge request is no longer pending.');
                }

                $targetUser = \App\Models\User::findOrFail($req->client_id);
                // Ensure wallet exists and has reconciled_at set
                $wallet = CreditWallet::firstOrCreate(['client_id' => $targetUser->id], ['balance' => 0, 'opening_balance' => 0]);
                if (!$wallet->reconciled_at) {
                    $wallet->update(['reconciled_at' => now()]);
                }

                $creditToAdd = \App\Support\LeadCredits::quantity($this->approvalQuantity ?? $req->amount, 'quantity', 1);
                app(\App\Services\LeadCreditService::class)->adjust(
                    $targetUser, $creditToAdd, 'recharge',
                    $this->referenceNote ?: 'Lead credit request #'.$req->id.' approved', auth()->user());

                // Update recharge_requests row
                $req->update([
                    'status' => 'approved',
                    'approved_quantity' => $creditToAdd,
                    'approved_at' => now(),
                    'reference_note' => $this->referenceNote ?: 'Manual approval via Admin Queue',
                ]);

                // Write to audit_logs
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'organization_id' => $targetUser->organization_id,
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
                        'message' => "Your request for {$creditToAdd} lead credits has been approved.",
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

                $reason = $this->rejectionReason ?: 'Request declined by administrator.';

                $req->update([
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                ]);

                $targetUser = \App\Models\User::find($req->client_id);

                // Write to audit_logs
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'organization_id' => $targetUser?->organization_id,
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
                        'message' => "Your recharge request was declined. Reason: {$reason}",
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
        $query = RechargeRequest::with(['client.wallet', 'client.organization', 'package']);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('payment_reference', 'like', '%' . $this->search . '%')
                  ->orWhere('reference_note', 'like', '%' . $this->search . '%')
                  ->orWhere('amount', 'like', '%' . $this->search . '%')
                  ->orWhereHas('client', function ($cq) {
                      $cq->where('name', 'like', '%' . $this->search . '%')
                         ->orWhereHas('organization', fn($oq) => $oq->where('name', 'like', '%' . $this->search . '%'));
                  });
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
