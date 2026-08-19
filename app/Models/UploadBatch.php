<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadBatch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'error_log' => 'array',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    public function getUploaderNameAttribute(): string
    {
        return $this->uploader?->name ?: 'System';
    }

    public function getProjectNameAttribute(): string
    {
        return $this->project?->name ?: 'N/A';
    }

    public function getStatusBadgeAttribute(): string
    {
        if ($this->total_rows === 0) return 'Empty';
        if ($this->failed_count > 0 && $this->imported_count > 0) return 'Partial';
        if ($this->failed_count > 0 && $this->imported_count === 0) return 'Failed';
        return 'Completed';
    }

    public function getSuccessProgressAttribute(): int
    {
        return $this->total_rows > 0 ? (int) round(($this->imported_count / $this->total_rows) * 100) : 0;
    }
}
