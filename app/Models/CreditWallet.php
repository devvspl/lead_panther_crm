<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditWallet extends Model
{
    protected $table = 'client_wallets';
    protected $guarded = [];
    protected $hidden = ['legacy_balance', 'legacy_opening_balance'];
    protected $casts = ['balance' => 'integer', 'opening_balance' => 'integer', 'reconciled_at' => 'datetime'];
    protected $appends = ['available_lead_credits', 'remaining_credits', 'used_credits', 'total_credits'];
    public function getAvailableLeadCreditsAttribute(): int { return (int) $this->balance; }
    public function getRemainingCreditsAttribute(): int { return (int) $this->balance; }

    protected static function booted(): void
    {
        static::creating(function ($wallet) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('client_wallets', 'reconciled_at')) $wallet->reconciled_at ??= now();
        });
        static::saving(function ($wallet) {
            foreach (['balance', 'opening_balance'] as $field) {
                if ($wallet->isDirty($field)) $wallet->{$field} = \App\Support\LeadCredits::quantity($wallet->getAttributes()[$field], $field);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function getUsedCreditsAttribute(): int
    {
        $query = CreditTransaction::withoutGlobalScopes()->where('client_id', $this->client_id)->where('is_legacy', false)->whereNotNull('lead_id');
        return max(0, (int) (clone $query)->whereIn('transaction_type', ['lead_consumption', 'reserve', 'deduct'])->sum('credit_used')
            - (int) (clone $query)->where('transaction_type', 'refund')->sum('credit_used'));
    }

    public function getTotalCreditsAttribute(): int { return (int) $this->balance + $this->used_credits; }
}
