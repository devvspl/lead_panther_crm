<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\ClientScoped;

class Project extends Model
{
    use \App\Traits\HidesMonetaryValues;

    use ClientScoped;

    use \App\Traits\HasLeadCreditCost;
    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function getProjectIdAttribute(): int
    {
        return (int) $this->id;
    }

    public function getProjectAttribute(): self
    {
        return $this;
    }
}
