<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RechargeRequest extends Model
{
    protected $guarded = [];
    protected $hidden = ['legacy_amount'];
    protected $casts = ['amount' => 'integer', 'approved_quantity' => 'integer'];
    protected $appends = ['credits_requested', 'credits_approved'];
    public function getCreditsRequestedAttribute(): ?int { return $this->amount; }
    public function getCreditsApprovedAttribute(): ?int { return $this->approved_quantity; }

    protected static function booted(): void
    {
        static::saving(function ($request) {
            foreach (['amount', 'approved_quantity'] as $field) {
                if ($request->isDirty($field) && $request->getAttributes()[$field] !== null) $request->{$field} = \App\Support\LeadCredits::quantity($request->getAttributes()[$field], $field, 1);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CreditPackage::class);
    }

    public function getClientNameAttribute(): string
    {
        $orgName = $this->client?->organization?->name;
        $name = $this->client?->name ?: 'N/A';
        if ($orgName && $orgName !== $name) {
            return $name . ' (' . $orgName . ')';
        }
        return $name;
    }

    public function getCurrentBalanceAttribute(): int
    {
        return (int) ($this->client?->wallet?->balance ?? 0);
    }

    public function getAmountFormattedAttribute(): string
    {
        $quantity = $this->approved_quantity ?? $this->amount;
        return $quantity === null ? 'Review quantity' : number_format((int) $quantity).' Credits';
    }

    public function getCreditsFormattedAttribute(): string
    {
        return $this->amount_formatted;
    }

    public function getPackageNameAttribute(): string
    {
        return $this->package?->name ?: 'Custom Request';
    }

    public function getRequestedAtFormattedAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('M d, Y H:i') : '';
    }
}
