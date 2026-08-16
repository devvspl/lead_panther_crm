<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortalAccount extends Model
{
    protected $guarded = [];

    public function credentials(): HasMany
    {
        return $this->hasMany(IntegrationCredential::class);
    }
}
