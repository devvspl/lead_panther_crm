<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceived extends Model
{
    use \App\Traits\HidesMonetaryValues;

    use \App\Traits\HidesArchivedLeads;

    protected $table = 'payments';
    protected $guarded = [];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
