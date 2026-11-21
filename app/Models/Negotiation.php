<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Negotiation extends Model
{
    use \App\Traits\HidesMonetaryValues;

    use \App\Traits\HidesArchivedLeads;

    protected $guarded = [];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
