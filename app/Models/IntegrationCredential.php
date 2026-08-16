<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationCredential extends Model
{
    protected $guarded = [];

    protected $casts = [
        'encrypted_value' => 'encrypted',
    ];

    public function portalAccount(): BelongsTo
    {
        return $this->belongsTo(PortalAccount::class);
    }
}
