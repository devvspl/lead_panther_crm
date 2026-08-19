<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class LeadReplacement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'requested_at' => 'datetime',
        'resolved_at' => 'datetime',
        'sla_met' => 'boolean',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function replacementLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'replacement_lead_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReplacementReason::class, 'reason_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Approve replacement request: refunds credit, creates replacement lead, updates status history & audit log.
     */
    public function approve(?int $approvedByUserId = null): bool
    {
        if ($this->status === 'approved') {
            return true;
        }

        return DB::transaction(function () use ($approvedByUserId) {
            $userId = $approvedByUserId ?? auth()->id() ?? 1;
            $lead = $this->lead;

            // 1. Update replacement record
            $this->update([
                'status' => 'approved',
                'resolved_at' => now(),
            ]);

            // 2. Update original lead
            $oldStage = $lead->current_stage;
            $lead->update([
                'current_stage' => 'replaced',
                'status' => 'replaced',
                'replacement_status' => 'approved',
            ]);

            LeadStatusHistory::create([
                'lead_id' => $lead->id,
                'from_status' => $oldStage,
                'to_status' => 'replaced',
                'changed_by' => $userId,
                'changed_at' => now(),
            ]);

            // 3. Refund credits to client wallet
            $wallet = CreditWallet::where('client_id', $lead->client_id)->lockForUpdate()->first();
            if ($wallet) {
                $before = (float) $wallet->balance;
                $after = $before + 10.00; // Refund 10 credits
                $wallet->update(['balance' => $after]);

                CreditTransaction::create([
                    'client_id' => $lead->client_id,
                    'lead_id' => $lead->id,
                    'package_id' => null,
                    'credit_before' => $before,
                    'credit_used' => 10.00,
                    'credit_after' => $after,
                    'transaction_type' => 'refund',
                    'created_at' => now(),
                ]);
            }

            // 4. Create new replacement lead
            $newLead = Lead::create([
                'lead_code' => Lead::generateUniqueLeadCode('LP-REP'),
                'client_id' => $lead->client_id,
                'project_id' => $lead->project_id,
                'campaign_id' => $lead->campaign_id,
                'lead_source_id' => $lead->lead_source_id,
                'name' => 'Replacement for ' . $lead->name,
                'mobile' => $lead->mobile,
                'email' => $lead->email,
                'city' => $lead->city,
                'location' => $lead->location,
                'budget' => $lead->budget,
                'property_type' => $lead->property_type,
                'requirement' => 'Replacement lead created from replacement claim #' . $this->id,
                'status' => 'new',
                'current_stage' => 'new',
                'assigned_to' => $lead->assigned_to,
            ]);

            $this->update(['replacement_lead_id' => $newLead->id]);

            // 5. Audit log
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'replacement.approved',
                'subject_type' => static::class,
                'subject_id' => $this->id,
                'from_value' => json_encode(['status' => 'pending']),
                'to_value' => json_encode(['status' => 'approved', 'replacement_lead_id' => $newLead->id]),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'LeadPantherCRM/1.0',
                'created_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * Reject replacement request with mandatory rejection note & audit log.
     */
    public function reject(string $rejectionNote, ?int $rejectedByUserId = null): bool
    {
        return DB::transaction(function () use ($rejectionNote, $rejectedByUserId) {
            $userId = $rejectedByUserId ?? auth()->id() ?? 1;

            $this->update([
                'status' => 'rejected',
                'resolved_at' => now(),
            ]);

            if ($this->lead) {
                $this->lead->update(['replacement_status' => 'rejected']);
            }

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'replacement.rejected',
                'subject_type' => static::class,
                'subject_id' => $this->id,
                'from_value' => json_encode(['status' => 'pending']),
                'to_value' => json_encode(['status' => 'rejected', 'note' => $rejectionNote]),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'LeadPantherCRM/1.0',
                'created_at' => now(),
            ]);

            return true;
        });
    }

    public function getLeadSummaryAttribute(): string
    {
        return $this->lead ? $this->lead->lead_code . ' (' . $this->lead->name . ')' : 'N/A';
    }

    public function getReasonNameAttribute(): string
    {
        return $this->reason?->reason_name ?: 'Standard Replacement';
    }

    public function getRequestedByNameAttribute(): string
    {
        return $this->requestedBy?->name ?: 'System';
    }

    public function getSlaBadgeAttribute(): string
    {
        return $this->sla_met ? 'SLA Met' : 'SLA Missed';
    }
}
