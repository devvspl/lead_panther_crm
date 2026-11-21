<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditPackage extends Model
{
    protected $guarded = [];
    protected $hidden = ['legacy_price'];
    protected $casts = ['credit_count' => 'integer'];
    protected static function booted(): void
    {
        static::saving(function ($package) {
            if ($package->isDirty('credit_count')) $package->credit_count = \App\Support\LeadCredits::quantity($package->getAttributes()['credit_count'], 'credit_count', 1);
        });
    }
    public function setPriceAttribute($value): void { $this->attributes['legacy_price'] = $value; }

}
