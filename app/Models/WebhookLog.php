<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    protected $guarded = [];

    public function portalAccount(): BelongsTo
    {
        return $this->belongsTo(PortalAccount::class);
    }
}
