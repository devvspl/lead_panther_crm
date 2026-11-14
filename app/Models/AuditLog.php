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
        'context' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($log) {
            // Historical data migrations create audited models before the enrichment migration.
            if (app()->runningInConsole() && ! \Illuminate\Support\Facades\Schema::hasColumn('audit_logs', 'context')) {
                unset($log->organization_id, $log->context);
                return;
            }
            $context = array_replace_recursive(\App\Support\AuditContext::current(), $log->context ?? []);
            $payload = \App\Support\AuditRedactor::payload($log->to_value);
            $context['category'] ??= str_starts_with($log->action, 'auth.') ? 'auth' : (str_contains($log->action, 'git') ? 'system' : 'business');
            if (! isset($context['status']) && is_array($payload)) {
                if (array_key_exists('successful', $payload)) $context['status'] = $payload['successful'] ? 'success' : 'failure';
                elseif (in_array($payload['status'] ?? null, ['success', 'logged_out', 'password_reset_success'])) $context['status'] = 'success';
                elseif (in_array($payload['status'] ?? null, ['failed_attempt', 'throttled_lockout'])) $context['status'] = 'failure';
            }
            $log->context = \App\Support\AuditRedactor::clean($context);
            if ($log->organization_id === null) $log->organization_id = $context['actor']['organization_id'] ?? $log->user?->organization_id;
        });
        static::updating(fn () => throw new \LogicException('Audit records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit records are append-only.'));
    }

    public function newEloquentBuilder($query) { return new \App\Models\Builders\AppendOnlyAuditBuilder($query); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }

    public function setFromValueAttribute($value): void { $this->attributes['from_value'] = $this->safePayload($value); }
    public function setToValueAttribute($value): void { $this->attributes['to_value'] = $this->safePayload($value); }
    public function getFromValueAttribute($value) { return $this->safePayload($value); }
    public function getToValueAttribute($value) { return $this->safePayload($value); }
    public function getContextAttribute($value): array { return (array) \App\Support\AuditRedactor::payload($value ?? []); }
    public function setUserAgentAttribute($value): void { $this->attributes['user_agent'] = \App\Support\AuditRedactor::clean($value); }
    public function getUserAgentAttribute($value) { return \App\Support\AuditRedactor::clean($value); }

    private function safePayload($value): ?string
    {
        if ($value === null) return null;
        $safe = \App\Support\AuditRedactor::payload($value);
        return is_array($safe) ? json_encode($safe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $safe;
    }

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
        return $this->ip_address ?: 'Unavailable';
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
        return $this->context['status'] ?? 'Unavailable';
    }
}
