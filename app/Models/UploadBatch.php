<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadBatch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'uploaded_by' => 'integer',
        'project_id' => 'integer',
        'campaign_id' => 'integer',
        'lead_source_id' => 'integer',
        'total_rows' => 'integer',
        'imported_count' => 'integer',
        'skipped_count' => 'integer',
        'failed_count' => 'integer',
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

    public function hasOriginalFile(): bool
    {
        if (empty($this->file_path) || str_contains($this->file_path, '..')) {
            return false;
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->exists($this->file_path);
    }

    public function getOriginalFileAvailableAttribute(): bool
    {
        return $this->hasOriginalFile();
    }

    public function getDownloadFilename(): string
    {
        $name = $this->original_filename ?: $this->filename;
        $safe = str_replace(['"', "'", "\r", "\n", "/", "\\"], '', basename($name));
        if (!empty($safe)) {
            return $safe;
        }

        $ext = !empty($this->file_path) ? pathinfo($this->file_path, PATHINFO_EXTENSION) : 'xlsx';
        return "batch_{$this->id}_import.{$ext}";
    }
}
