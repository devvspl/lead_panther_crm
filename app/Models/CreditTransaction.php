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

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
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
     * Reserve & consume credits for a newly created lead with a pessimistic row lock.
     * Prevents duplicate deductions and strictly validates organization status and balance.
     */
    public static function reserveForLead(Lead $lead): bool
    {
        return DB::transaction(function () use ($lead) {
            // 1. Prevent duplicate deduction for the same lead
            $alreadyCharged = static::where('lead_id', $lead->id)
                ->whereIn('transaction_type', ['lead_consumption', 'deduct', 'reserve'])
                ->exists();

            if ($alreadyCharged) {
                return true;
            }

            // 2. Validate Client & Organization Status
            $client = $lead->client ?: ($lead->client_id ? Client::with('organization')->find($lead->client_id) : null);
            $organization = $client?->organization;

            if ($organization && !$organization->is_active) {
                $lead->update([
                    'current_stage' => 'pending_credit',
                    'status' => 'pending_credit',
                ]);

                NotificationLog::create([
                    'notifiable_type' => User::class,
                    'notifiable_id' => $lead->client_id,
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
            $wallet = CreditWallet::where('client_id', $lead->client_id)->lockForUpdate()->first();

            if (!$wallet) {
                $wallet = CreditWallet::create([
                    'client_id' => $lead->client_id,
                    'balance' => 0.00,
                    'opening_balance' => 0.00,
                ]);
            }

            // 4. Calculate effective per-lead pricing following the hierarchy
            $pricingService = app(\App\Services\LeadPricingService::class);
            $resolution = $pricingService->resolveForLead($lead);
            $cost = (float) $resolution['cost'];

            // 5. Check if wallet has sufficient available credits
            if ($wallet->balance >= $cost) {
                $before = (float) $wallet->balance;
                $after = max(0.00, $before - $cost);

                $wallet->update(['balance' => $after]);
                $lead->update(['lead_cost' => $cost]);

                $tx = static::create([
                    'client_id' => $lead->client_id,
                    'lead_id' => $lead->id,
                    'package_id' => null,
                    'credit_before' => $before,
                    'credit_used' => $cost,
                    'credit_after' => $after,
                    'transaction_type' => 'lead_consumption',
                    'description' => "Lead delivery charge for {$lead->lead_code} ({$lead->name})",
                    'created_by' => auth()->id() ?? $lead->client_id,
                    'created_at' => now(),
                ]);

                event(new CreditReserved($lead, $tx));
                return true;
            } else {
                // Insufficient credits: do NOT deduct credits, keep lead pending
                $lead->update([
                    'current_stage' => 'pending_credit',
                    'status' => 'pending_credit',
                ]);

                event(new CampaignPaused($lead, 'Credit balance insufficient'));

                // Warning notification for Builder/Developer
                NotificationLog::create([
                    'notifiable_type' => User::class,
                    'notifiable_id' => $lead->client_id,
                    'channel' => 'crm',
                    'status' => 'sent',
                    'payload' => json_encode([
                        'title' => 'Recharge required',
                        'message' => "Insufficient credits (Required: {$cost} Cr, Available: {$wallet->balance} Cr). Lead {$lead->lead_code} placed on pending_credit hold.",
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
        $isCredit = in_array($this->transaction_type, ['recharge', 'refund', 'opening_credit']) 
            || ($this->transaction_type === 'manual_adjustment' && $this->credit_after >= $this->credit_before);
        $prefix = $isCredit ? '+' : '-';
        return $prefix . number_format((float)$this->credit_used, 1) . ' Cr';
    }

    public function getTransactionTypeLabelAttribute(): string
    {
        return match ($this->transaction_type) {
            'opening_credit' => 'Opening Credit',
            'recharge' => 'Recharge',
            'lead_consumption', 'deduct', 'reserve' => 'Lead Consumption',
            'refund' => 'Refund',
            'manual_adjustment' => 'Manual Adjustment',
            default => ucwords(str_replace('_', ' ', $this->transaction_type)),
        };
    }

    public function getCreditAfterFormattedAttribute(): string
    {
        return number_format((float)$this->credit_after, 1) . ' Cr';
    }
}
