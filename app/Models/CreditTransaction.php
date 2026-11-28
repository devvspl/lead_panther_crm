<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Events\CreditReserved;
use App\Events\CampaignPaused;
use App\Traits\ClientScoped;

class CreditTransaction extends Model
{
    use ClientScoped;

    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = ['legacy_credit_before', 'legacy_credit_used', 'legacy_credit_after'];
    protected $appends = ['quantity', 'action', 'remarks'];
    public function getQuantityAttribute(): ?int { return $this->is_legacy ? null : (int) $this->credit_used; }
    public function getActionAttribute(): string { return $this->transaction_type_label; }
    public function getRemarksAttribute(): ?string { return $this->description; }

    protected $casts = [
        'created_at' => 'datetime',
        'credit_before' => 'integer', 'credit_used' => 'integer', 'credit_after' => 'integer', 'is_legacy' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($transaction) {
            if (!$transaction->is_legacy) {
                foreach (['credit_before', 'credit_used', 'credit_after'] as $field) {
                    $transaction->{$field} = \App\Support\LeadCredits::quantity($transaction->getAttributes()[$field] ?? 0, $field);
                }
                if ($transaction->transaction_type === 'lead_consumption' && $transaction->lead_id) $transaction->delivery_charge_lead_id = $transaction->lead_id;
            }
        });
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class)->withoutGlobalScopes();
    }

    public function package()
    {
        return $this->belongsTo(CreditPackage::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Compatibility entry point for charging a successful delivery under a pessimistic row lock.
     * Prevents duplicate deductions and strictly validates organization status and balance.
     */
    public static function reserveForLead(Lead $lead, ?int $targetClientId = null, ?int $customCost = null): bool
    {
        return DB::transaction(function () use ($lead, $targetClientId, $customCost) {
            $lead = Lead::withoutGlobalScopes()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $clientId = $targetClientId ?: $lead->client_id;
            if (!$lead->delivered_at || $lead->delivery_pending || !$lead->credit_delivery_confirmed || in_array($lead->status, ['duplicate', 'rejected', 'invalid'])) return false;

            // 1. Prevent duplicate deduction for the same lead for this specific client
            $consumedCount = static::withoutGlobalScopes()->where('lead_id', $lead->id)
                ->where('client_id', $clientId)
                ->where('is_legacy', false)
                ->whereIn('transaction_type', ['lead_consumption', 'deduct', 'reserve'])
                ->count();

            $refundedCount = static::withoutGlobalScopes()->where('lead_id', $lead->id)
                ->where('client_id', $clientId)
                ->where('is_legacy', false)
                ->whereIn('transaction_type', ['lead_refund', 'refund'])
                ->count();

            if ($consumedCount > $refundedCount) {
                return true;
            }

            // 2. Validate Client & Organization Status
            $client = User::with('organization')->find($clientId);
            if (!$client || $client->status !== 'active') return false;
            $organization = $client?->organization;

            if ($organization && !$organization->is_active) {
                if ($clientId === $lead->client_id) {
                    $lead->update([
                        'current_stage' => 'pending_credit',
                        'status' => 'pending_credit',
                    ]);
                }

                NotificationLog::create([
                    'notifiable_type' => User::class,
                    'notifiable_id' => $clientId,
                    'channel' => 'crm',
                    'status' => 'sent',
                    'payload' => json_encode([
                        'title' => 'Organization Inactive',
                        'message' => "Lead {$lead->lead_code} held on pending_credit because organization '{$organization->name}' is suspended.",
                    ]),
                    'sent_at' => now(),
                ]);

                return false;
            }

            // 3. Acquire pessimistic row lock on the client's wallet
            $wallet = CreditWallet::withoutGlobalScopes()->where('client_id', $clientId)->lockForUpdate()->first();

            if (!$wallet) {
                $wallet = CreditWallet::create([
                    'client_id' => $clientId,
                    'balance' => 0.00,
                    'opening_balance' => 0.00,
                ]);
            }
            if (!$wallet->reconciled_at) return false;

            // 4. Calculate effective per-lead pricing
            if ($customCost !== null && $customCost >= 0) {
                $cost = (int) $customCost;
            } else {
                $pricingService = app(\App\Services\LeadPricingService::class);
                $project = $lead->project_id ? Project::withoutGlobalScopes()->find($lead->project_id) : null;
                $resolution = $pricingService->resolveCost($project, null, $organization);
                $cost = (int) $resolution['cost'];
            }

            // 5. Check if wallet has sufficient available credits
            if ($wallet->balance >= $cost) {
                $before = (int) $wallet->balance;
                $after = $before - $cost;

                $wallet->update(['balance' => $after]);
                if ($clientId === $lead->client_id) {
                    $lead->update(array_merge(['lead_cost' => $cost], $lead->current_stage === 'pending_credit' ? ['status' => 'new', 'current_stage' => 'new'] : []));
                }

                $tx = static::create([
                    'client_id' => $clientId,
                    'lead_id' => $lead->id,
                    'package_id' => null,
                    'credit_before' => $before,
                    'credit_used' => $cost,
                    'credit_after' => $after,
                    'transaction_type' => 'lead_consumption',
                    'description' => "Lead credits consumed for {$lead->lead_code} ({$lead->name})",
                    'created_by' => auth()->id() ?? $clientId,
                    'created_at' => $lead->delivered_at ?: $lead->created_at ?: now(),
                ]);

                event(new CreditReserved($lead, $tx));
                return true;
            } else {
                // Insufficient credits
                if ($clientId === $lead->client_id) {
                    $lead->update([
                        'current_stage' => 'pending_credit',
                        'status' => 'pending_credit',
                    ]);
                    event(new CampaignPaused($lead, 'Credit balance insufficient'));
                }

                // Warning notification for Client
                NotificationLog::create([
                    'notifiable_type' => User::class,
                    'notifiable_id' => $clientId,
                    'channel' => 'crm',
                    'status' => 'sent',
                    'payload' => json_encode([
                        'title' => 'Recharge required',
                        'message' => "Insufficient credits (Required: {$cost} Credits, Available: {$wallet->balance} Credits). Lead {$lead->lead_code} placed on pending_credit hold.",
                    ]),
                    'sent_at' => now(),
                ]);

                return false;
            }
        });
    }

    public function getClientNameAttribute(): string
    {
        return $this->client?->name ?: 'System';
    }

    public function getLeadSummaryAttribute(): string
    {
        return $this->lead ? $this->lead->lead_code . ' (' . $this->lead->name . ')' : '—';
    }

    public function getCreditUsedFormattedAttribute(): string
    {
        if ($this->is_legacy) return 'Legacy entry';
        $isCredit = $this->credit_after >= $this->credit_before;
        $prefix = $isCredit ? '+' : '-';
        return $prefix . number_format((int)$this->credit_used) . ' Credits';
    }

    public function getTransactionTypeLabelAttribute(): string
    {
        return match ($this->transaction_type) {
            'opening_credit' => 'Opening Credit',
            'recharge' => 'Credits Added',
            'lead_consumption', 'reserve' => 'Lead Delivery',
            'deduct' => 'Credits Deducted',
            'refund' => 'Credit Return',
            'reconciliation' => 'Reconciliation',
            'manual_adjustment' => 'Credit Adjustment',
            default => ucwords(str_replace('_', ' ', $this->transaction_type)),
        };
    }

    public function getCreditAfterFormattedAttribute(): string
    {
        return $this->is_legacy ? 'Legacy entry' : number_format((int)$this->credit_after) . ' Credits';
    }
}
