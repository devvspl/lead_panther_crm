<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\RechargeRequest;
use App\Models\CreditWallet;
use App\Models\CreditTransaction;
use App\Models\NotificationLog;
use App\Models\Client;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * ============================================================================
 * PAYMENT GATEWAY WEBHOOK INTEGRATION STUB (TODO)
 * ============================================================================
 *
 * In production, when integrating a payment gateway like Razorpay or Stripe:
 * 1. The webhook controller (e.g., RazorpayWebhookController@handle) will receive
 *    the `payment.captured` event.
 * 2. It will look up the corresponding `recharge_requests` row by gateway order_id.
 * 3. It will invoke the exact same atomic transaction logic as `approveRequest()`
 *    below to credit the wallet, log the transaction, and notify the client.
 * ============================================================================
 */

class RechargeApprovalQueue extends Component
{
    use WithPagination;

    public ?int $selectedRequestId = null;
    public string $referenceNote = '';
    public string $rejectionReason = '';

    public bool $showApproveModal = false;
    public bool $showRejectModal = false;

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

    public function render()
    {
        $requests = RechargeRequest::with(['client', 'package'])
            ->latest('id')
            ->paginate(10);

        return view('livewire.admin.recharge-approval-queue', [
            'requests' => $requests,
        ])->layout('layouts.app');
    }
}
