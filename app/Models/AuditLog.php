<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUserNameAttribute(): string
    {
        return $this->user?->name ?: 'System / Anonymous';
    }

    public function getSubjectSummaryAttribute(): string
    {
        return $this->subject_type ? class_basename($this->subject_type) . ' #' . $this->subject_id : '—';
    }

    public function getChangesSummaryAttribute(): string
    {
        if ($this->to_value) {
            return \Illuminate\Support\Str::limit($this->to_value, 50);
        }
        if ($this->from_value) {
            return \Illuminate\Support\Str::limit($this->from_value, 50);
        }
        return '—';
    }

    public function getFormattedIpAttribute(): string
    {
        return $this->ip_address ?: '127.0.0.1';
    }
}
