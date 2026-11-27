<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadDeliveryRevocation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'original_delivered_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class)->withoutGlobalScopes();
    }

    public function deliveryItem()
    {
        return $this->belongsTo(LeadDeliveryItem::class, 'delivery_item_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class)->withoutGlobalScopes();
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class)->withoutGlobalScopes();
    }

    public function revokedByUser()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
