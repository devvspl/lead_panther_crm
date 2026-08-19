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

    public function getPortalAccountNameAttribute(): string
    {
        return $this->portalAccount?->account_name ?: ($this->source_provider ?: 'System Default');
    }

    public function getStatusBadgeAttribute(): string
    {
        return $this->processed ? 'Processed' : ($this->error_message ? 'Failed' : 'Pending');
    }

    public function getPayloadPreviewAttribute(): string
    {
        return \Illuminate\Support\Str::limit(is_string($this->payload) ? $this->payload : json_encode($this->payload), 60);
    }
}
