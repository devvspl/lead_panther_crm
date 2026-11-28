<?php

namespace App\Services;

use App\Models\{AuditLog, CreditTransaction, CreditWallet, User};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

class LeadCreditService
{
    public function adjust(User $client, mixed $quantity, string $action, string $remarks, User $actor): CreditTransaction
    {
        abort_unless($actor->status === 'active' && $actor->hasAnyRole(['Super Admin', 'super-admin']), 403);
        $data = Validator::make(['quantity' => $quantity, 'action' => $action, 'remarks' => $remarks], [
            'quantity' => 'required|integer|min:1|max:1000000000',
            'action' => 'required|in:recharge,opening_credit,refund,manual_adjustment,deduct',
            'remarks' => 'required|string|max:1000',
        ])->validate();

        return DB::transaction(function () use ($client, $data, $actor) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $wallet = CreditWallet::firstOrCreate(['client_id' => $client->id], ['balance' => 0, 'opening_balance' => 0]);
            $wallet = CreditWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            if (!$wallet->reconciled_at) throw ValidationException::withMessages(['quantity' => 'Reconcile this client’s lead credits before adding or deducting credits.']);
            $quantity = (int) $data['quantity'];
            $before = (int) $wallet->balance;
            $deduct = $data['action'] === 'deduct';
            if ($deduct && $quantity > $before) throw ValidationException::withMessages(['quantity' => 'The client does not have enough lead credits.']);
            $after = $deduct ? $before - $quantity : $before + $quantity;
            $wallet->balance = $after;
            if ($data['action'] === 'opening_credit') $wallet->opening_balance = (int) $wallet->opening_balance + $quantity;
            $wallet->save();
            $transaction = CreditTransaction::create(['client_id' => $client->id, 'credit_before' => $before,
                'credit_used' => $quantity, 'credit_after' => $after, 'transaction_type' => $data['action'],
                'description' => $data['remarks'], 'created_by' => $actor->id, 'created_at' => now()]);
            AuditLog::create(['user_id' => $actor->id, 'organization_id' => $client->organization_id,
                'action' => 'lead_credits.'.$data['action'], 'subject_type' => CreditWallet::class, 'subject_id' => $wallet->id,
                'from_value' => ['remaining_credits' => $before], 'to_value' => ['remaining_credits' => $after, 'quantity' => $quantity, 'remarks' => $data['remarks']],
                'context' => ['category' => 'lead_credits', 'status' => 'success'], 'created_at' => now()]);
            return $transaction;
        }, 3);
    }

    public function reconcile(User $client, mixed $remaining, int $expected, string $reason, User $actor): CreditTransaction
    {
        abort_unless($actor->status === 'active' && $actor->hasAnyRole(['Super Admin', 'super-admin']), 403);
        $quantity = \App\Support\LeadCredits::quantity($remaining, 'remaining');
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:5|max:1000'])->validate();
        return DB::transaction(function () use ($client, $quantity, $expected, $reason, $actor) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $wallet = CreditWallet::firstOrCreate(['client_id' => $client->id], ['balance' => 0, 'opening_balance' => 0]);
            $wallet = CreditWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $before = (int) $wallet->balance;
            if ($before !== $expected) throw ValidationException::withMessages(['remaining' => 'Credits changed while you were reviewing. Reload the client before reconciling.']);
            $wallet->update(['balance' => $quantity, 'opening_balance' => $wallet->reconciled_at ? $wallet->opening_balance : $quantity,
                'reconciled_at' => now(), 'reconciled_by' => $actor->id]);
            $transaction = CreditTransaction::create(['client_id' => $client->id, 'transaction_type' => 'reconciliation',
                'credit_before' => $before, 'credit_used' => abs($quantity - $before), 'credit_after' => $quantity,
                'description' => $reason, 'created_by' => $actor->id, 'created_at' => now()]);
            AuditLog::create(['user_id' => $actor->id, 'organization_id' => $client->organization_id,
                'action' => 'lead_credits.reconciled', 'subject_type' => CreditWallet::class, 'subject_id' => $wallet->id,
                'from_value' => ['remaining_credits' => $before], 'to_value' => ['remaining_credits' => $quantity, 'reason' => $reason, 'transaction_id' => $transaction->id],
                'context' => ['category' => 'lead_credits', 'status' => 'success'], 'created_at' => now()]);
            return $transaction;
        }, 3);
    }

    public function markLegacyConsumption(CreditTransaction $legacy, mixed $quantity, string $reason, User $actor): CreditTransaction
    {
        abort_unless($actor->status === 'active' && $actor->hasAnyRole(['Super Admin', 'super-admin']), 403);
        return DB::transaction(function () use ($legacy, $quantity, $reason, $actor) {
            $legacy = CreditTransaction::withoutGlobalScopes()->whereKey($legacy->id)->lockForUpdate()->firstOrFail();
            if (!$legacy->is_legacy || !$legacy->lead_id || !in_array($legacy->transaction_type, ['lead_consumption', 'reserve', 'deduct'])) {
                throw ValidationException::withMessages(['legacyReason' => 'Select a legacy lead consumption entry.']);
            }
            $lead = \App\Models\Lead::withoutGlobalScopes()->whereKey($legacy->lead_id)->lockForUpdate()->firstOrFail();
            if ($lead->client_id !== $legacy->client_id || !$lead->delivered_at || $lead->delivery_pending) {
                throw ValidationException::withMessages(['legacyReason' => 'Only a successfully delivered lead for this client can be recorded.']);
            }
            if (CreditTransaction::withoutGlobalScopes()->where('lead_id', $lead->id)->where('is_legacy', false)->whereIn('transaction_type', ['lead_consumption', 'reserve', 'deduct'])->exists()) {
                throw ValidationException::withMessages(['legacyReason' => 'This lead already has credit consumption recorded.']);
            }
            Validator::make(['legacyReason' => $reason], ['legacyReason' => 'required|string|min:5|max:900'])->validate();
            $transaction = $this->adjust(User::findOrFail($legacy->client_id), $quantity, 'deduct', 'Legacy entry #'.$legacy->id.': '.$reason, $actor);
            $transaction->update(['transaction_type' => 'lead_consumption', 'lead_id' => $lead->id, 'delivery_charge_lead_id' => $lead->id]);
            AuditLog::create(['user_id' => $actor->id, 'organization_id' => $lead->organization_id,
                'action' => 'lead_credits.legacy_consumption', 'subject_type' => CreditTransaction::class, 'subject_id' => $transaction->id,
                'to_value' => ['legacy_transaction_id' => $legacy->id, 'lead_id' => $lead->id, 'quantity' => $transaction->credit_used, 'reason' => $reason],
                'context' => ['category' => 'lead_credits', 'status' => 'success'], 'created_at' => now()]);
            return $transaction;
        }, 3);
    }

    public function dispute(CreditTransaction $transaction, string $reason, User $actor): \App\Models\CreditDispute
    {
        abort_unless($actor->status === 'active' && $actor->hasAnyRole(['Super Admin', 'super-admin']), 403);
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:5|max:1000'])->validate();
        return DB::transaction(function () use ($transaction, $reason, $actor) {
            CreditTransaction::withoutGlobalScopes()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $dispute = \App\Models\CreditDispute::firstOrCreate(['credit_transaction_id' => $transaction->id, 'status' => 'open'], ['reason' => $reason, 'opened_by' => $actor->id]);
            if ($dispute->wasRecentlyCreated) $this->disputeAudit($dispute, 'opened', $actor);
            return $dispute;
        }, 3);
    }

    public function resolveDispute(int $id, string $resolution, mixed $quantity, string $action, User $actor): void
    {
        abort_unless($actor->status === 'active' && $actor->hasAnyRole(['Super Admin', 'super-admin']), 403);
        Validator::make(['resolution' => $resolution, 'quantity' => $quantity, 'action' => $action], [
            'resolution' => 'required|string|min:5|max:1000', 'quantity' => 'required|integer|min:0|max:1000000000', 'action' => 'required|in:recharge,deduct',
        ])->validate();
        DB::transaction(function () use ($id, $resolution, $quantity, $action, $actor) {
            $dispute = \App\Models\CreditDispute::lockForUpdate()->findOrFail($id);
            if ($dispute->status !== 'open') throw ValidationException::withMessages(['resolution' => 'This dispute has already been resolved.']);
            $adjustment = (int) $quantity > 0 ? $this->adjust(User::findOrFail($dispute->transaction->client_id), $quantity, $action, 'Dispute #'.$id.': '.$resolution, $actor) : null;
            $dispute->update(['status' => 'resolved', 'resolution' => $resolution, 'resolved_by' => $actor->id, 'resolved_at' => now(), 'adjustment_transaction_id' => $adjustment?->id]);
            $this->disputeAudit($dispute, 'resolved', $actor);
        }, 3);
    }

    /**
     * Audit all credit transactions chronologically, repair running before/after balances,
     * eliminate jumps or gaps, and synchronize wallet balance.
     */
    public function auditAndRebalanceLedger(User $client, User $actor): array
    {
        abort_unless($actor->status === 'active' && $actor->hasAnyRole(['Super Admin', 'super-admin']), 403);

        return DB::transaction(function () use ($client, $actor) {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $wallet = CreditWallet::firstOrCreate(['client_id' => $client->id], ['balance' => 0, 'opening_balance' => 0]);
            $wallet = CreditWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $txs = CreditTransaction::withoutGlobalScopes()
                ->where('client_id', $client->id)
                ->where('is_legacy', false)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $repairedCount = 0;
            $runningBalance = 0;

            foreach ($txs as $tx) {
                $expectedBefore = $runningBalance;
                $type = $tx->transaction_type;
                $used = (int) $tx->credit_used;

                if (in_array($type, ['recharge', 'opening_credit', 'refund', 'lead_refund'])) {
                    $runningBalance += $used;
                } elseif ($type === 'reconciliation') {
                    $runningBalance = (int) $tx->credit_after;
                } else {
                    $runningBalance = max(0, $runningBalance - $used);
                }
                $expectedAfter = $runningBalance;

                if ((int) $tx->credit_before !== $expectedBefore || (int) $tx->credit_after !== $expectedAfter) {
                    $tx->update([
                        'credit_before' => $expectedBefore,
                        'credit_after' => $expectedAfter,
                    ]);
                    $repairedCount++;
                }
            }

            $wallet->update([
                'balance' => $runningBalance,
                'reconciled_at' => now(),
                'reconciled_by' => $actor->id,
            ]);

            AuditLog::create([
                'user_id' => $actor->id,
                'organization_id' => $client->organization_id,
                'action' => 'lead_credits.rebalanced',
                'subject_type' => CreditWallet::class,
                'subject_id' => $wallet->id,
                'from_value' => ['repaired_count' => $repairedCount],
                'to_value' => ['balance' => $runningBalance, 'repaired_count' => $repairedCount],
                'context' => ['category' => 'lead_credits', 'status' => 'success'],
                'created_at' => now(),
            ]);

            return [
                'repaired_count' => $repairedCount,
                'final_balance' => $runningBalance,
                'total_transactions' => $txs->count(),
            ];
        }, 3);
    }

    private function disputeAudit(\App\Models\CreditDispute $dispute, string $action, User $actor): void
    {
        AuditLog::create(['user_id' => $actor->id, 'action' => 'lead_credits.dispute_'.$action, 'subject_type' => get_class($dispute), 'subject_id' => $dispute->id,
            'to_value' => $dispute->only(['credit_transaction_id', 'reason', 'status', 'resolution', 'adjustment_transaction_id']),
            'context' => ['category' => 'lead_credits', 'status' => 'success'], 'created_at' => now()]);
    }
}
