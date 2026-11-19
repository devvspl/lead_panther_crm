<?php

namespace App\Services;

use App\Models\{AuditLog, CreditTransaction, Lead, LeadDeliveryItem};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LeadArchiveService
{
    public static function authorize(): void
    {
        abort_unless(auth()->user()?->status === 'active' && auth()->user()->hasAnyRole(['Super Admin', 'super-admin']), 403);
    }

    public function archive(int $id, ?string $reason = null): void
    {
        self::authorize();
        Validator::make(['reason' => $reason], ['reason' => 'nullable|string|max:2000'])->validate();
        DB::transaction(function () use ($id, $reason) {
            $lead = Lead::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->firstOrFail();
            $values = ['archived_at' => now(), 'archived_by' => auth()->id(), 'archive_reason' => $reason];
            Lead::withoutGlobalScopes()->whereKey($id)->update($values);
            // Preserve transfer evidence but remove an archived lead from an in-flight batch.
            LeadDeliveryItem::where('lead_id', $id)->where('status', 'queued')->update(['status' => 'pending', 'run_id' => null]);
            $this->audit($lead, 'lead.archived', $reason, $values);
        }, 3);
    }

    public function restore(int $id, bool $reassign = false): void
    {
        self::authorize();
        DB::transaction(function () use ($id, $reassign) {
            $lead = Lead::withoutGlobalScopes()->onlyArchived()->whereKey($id)->lockForUpdate()->firstOrFail();
            Lead::withoutGlobalScopes()->withArchived()->whereKey($id)->update([
                'archived_at' => null, 'archived_by' => null, 'archive_reason' => null,
                'archive_assignment_hold' => !$reassign,
            ]);
            $this->audit($lead, 'lead.restored', $lead->archive_reason, ['automatic_assignment_requested' => $reassign]);
            // Query updates deliberately avoid creation/update observers and stage changes.
            if ($reassign) app(LeadDistributionService::class)->distribute(Lead::withoutGlobalScopes()->findOrFail($id));
        }, 3);
    }

    public static function assertPermanentDeletionAllowed(Lead $lead): void
    {
        self::authorize();
        abort_unless($lead->archived_at, 422, 'Archive the lead before permanently deleting it.');
        $bookings = \App\Models\Booking::withArchived()->where('lead_id', $lead->id)->select('id');
        if (CreditTransaction::where('lead_id', $lead->id)->exists() || LeadDeliveryItem::where('lead_id', $lead->id)->exists()
            || DB::table('payments')->whereIn('booking_id', $bookings)->exists()) {
            throw ValidationException::withMessages(['deletion' => 'Leads with credit transactions, payments or transfer history must be retained.']);
        }
    }

    public function permanentlyDelete(int $id, ?string $reason = null): void
    {
        self::authorize();
        Validator::make(['reason' => $reason], ['reason' => 'nullable|string|max:2000'])->validate();
        try {
            DB::transaction(function () use ($id, $reason) {
                $lead = Lead::withoutGlobalScopes()->onlyArchived()->whereKey($id)->lockForUpdate()->firstOrFail();
                self::assertPermanentDeletionAllowed($lead);
                $this->audit($lead, 'lead.permanently_deleted', $reason ?: $lead->archive_reason, ['lead_code' => $lead->lead_code]);
                // Existing FK cascades delete dependent activity; restrictive FKs roll everything back.
                Lead::withoutGlobalScopes()->withArchived()->whereKey($id)->delete();
            }, 3);
        } catch (\Illuminate\Database\QueryException $e) {
            if (in_array((string)$e->getCode(), ['23000','23503'])) {
                throw ValidationException::withMessages(['deletion'=>'Related records require this lead to be retained. No records were deleted.']);
            }
            throw $e;
        }
    }

    private function audit(Lead $lead, string $action, ?string $reason, array $values): void
    {
        AuditLog::create([
            'user_id' => auth()->id(), 'action' => $action, 'subject_type' => Lead::class,
            'subject_id' => $lead->id, 'organization_id' => $lead->client?->organization_id,
            'from_value' => json_encode(['status' => $lead->status, 'stage' => $lead->current_stage, 'assigned_to' => $lead->assigned_to]),
            'to_value' => json_encode(array_merge($values, ['reason' => $reason, 'lead_id' => $lead->id, 'roles' => auth()->user()->getRoleNames()->all()])),
            'created_at' => now(),
        ]);
    }
}
