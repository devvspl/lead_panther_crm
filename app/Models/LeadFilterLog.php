<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFilterLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'campaign_id',
        'rule_id',
        'lead_identifier',
        'action', // 'skipped', 'included'
        'reason',
        'matched_conditions',
        'payload_snapshot',
        'created_at',
    ];

    protected $casts = [
        'matched_conditions' => 'array',
        'payload_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LeadFilterRule::class, 'rule_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
