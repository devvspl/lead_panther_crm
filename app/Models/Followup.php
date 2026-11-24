<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Followup extends Model
{
    use \App\Traits\HidesArchivedLeads;

    protected $guarded = [];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'escalated_at' => 'datetime',
        'escalation_level' => 'integer',
    ];

    public const TYPE_CALL = 'call';
    public const TYPE_WHATSAPP = 'whatsapp';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_SITE_VISIT = 'site_visit';
    public const TYPE_OTHER = 'other';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public static function typeLabels(): array
    {
        return [
            self::TYPE_CALL => 'Phone Call',
            self::TYPE_WHATSAPP => 'WhatsApp Message',
            self::TYPE_MEETING => 'Face-to-Face Meeting',
            self::TYPE_SITE_VISIT => 'Site Visit',
            self::TYPE_OTHER => 'Other Task',
        ];
    }

    public static function outcomeOptions(): array
    {
        return [
            'connected' => 'Connected / Discussed',
            'callback_requested' => 'Customer Asked to Callback',
            'busy' => 'Busy / Not Answering',
            'interested' => 'Positive / Highly Interested',
            'meeting_agreed' => 'Agreed for Meeting',
            'site_visit_agreed' => 'Agreed for Site Visit',
            'budget_issue' => 'Budget / Pricing Mismatch',
            'location_issue' => 'Location / Unit Preference Mismatch',
            'not_interested' => 'Not Interested',
            'other' => 'Other Outcome',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('status')->orWhere('status', self::STATUS_PENDING);
        });
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->pending()->where('due_at', '<', now());
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->pending()->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->pending()->where('due_at', '>', now());
    }

    public function scopeEscalated(Builder $query): Builder
    {
        return $query->where('escalation_level', '>', 0);
    }

    public function getUrgencyAttribute(): string
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return 'completed';
        }
        if ($this->status === self::STATUS_CANCELLED) {
            return 'cancelled';
        }
        if ($this->due_at && $this->due_at->isPast()) {
            return 'overdue';
        }
        if ($this->due_at && $this->due_at->isToday()) {
            return 'due_today';
        }

        return 'upcoming';
    }

    public function getTypeLabelAttribute(): string
    {
        return static::typeLabels()[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type ?? 'call'));
    }

    public function getOutcomeLabelAttribute(): ?string
    {
        if (! $this->outcome) {
            return null;
        }

        return static::outcomeOptions()[$this->outcome] ?? ucfirst(str_replace('_', ' ', $this->outcome));
    }
}
