<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignment extends Model
{
    use \App\Traits\HidesArchivedLeads;

    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['assigned_at' => 'datetime', 'response_due_at' => 'datetime', 'first_response_at' => 'datetime'];

    protected static function booted(): void
    {
        static::created(fn ($assignment) => app(\App\Services\ExecutiveResponseService::class)->assigned($assignment));
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
