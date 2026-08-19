<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RechargeRequest extends Model
{
    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(CreditPackage::class);
    }

    public function getClientNameAttribute(): string
    {
        return $this->client?->name ?: 'N/A';
    }

    public function getAmountFormattedAttribute(): string
    {
        return '₹' . number_format((float)$this->amount, 2);
    }

    public function getCreditsFormattedAttribute(): string
    {
        return ($this->package ? number_format($this->package->credit_count) : number_format($this->amount / 10)) . ' Credits';
    }

    public function getPackageNameAttribute(): string
    {
        return $this->package?->name ?: 'Custom Recharge';
    }

    public function getRequestedAtFormattedAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('M d, Y H:i') : '';
    }
}
