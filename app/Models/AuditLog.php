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

    public function getDetailsSummaryAttribute(): string
    {
        if (!empty($this->to_value)) {
            $data = is_array($this->to_value) ? $this->to_value : json_decode($this->to_value, true);
            if (is_array($data)) {
                $parts = [];
                if (!empty($data['branch'])) {
                    $parts[] = 'Branch: ' . $data['branch'];
                }
                if (!empty($data['commit_message'])) {
                    $parts[] = '"' . \Illuminate\Support\Str::limit($data['commit_message'], 40) . '"';
                }
                if (!empty($data['commit_after']) && $data['commit_after'] !== 'unknown') {
                    $parts[] = 'SHA: ' . substr($data['commit_after'], 0, 7);
                }
                if (!empty($data['target_commit'])) {
                    $parts[] = 'Target: ' . substr($data['target_commit'], 0, 7);
                }
                if (!empty($data['backup_branch'])) {
                    $parts[] = 'Backup: ' . $data['backup_branch'];
                }
                if (!empty($data['action'])) {
                    $parts[] = 'Task: ' . $data['action'];
                }
                if (!empty($parts)) {
                    return implode(' | ', $parts);
                }
            }
            return \Illuminate\Support\Str::limit((string) $this->to_value, 70);
        }

        return '—';
    }

    public function getStatusBadgeAttribute(): string
    {
        if (!empty($this->to_value)) {
            $data = is_array($this->to_value) ? $this->to_value : json_decode($this->to_value, true);
            if (is_array($data) && isset($data['successful'])) {
                return $data['successful'] ? 'Success' : 'Failed';
            }
        }
        return 'Success';
    }
}
