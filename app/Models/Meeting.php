<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meeting extends Model
{
    use \App\Traits\HidesArchivedLeads;

    protected $guarded = [];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'participants' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
