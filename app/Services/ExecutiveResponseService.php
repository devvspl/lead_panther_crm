<?php
namespace App\Services;

use App\Models\{Lead, LeadAssignment, User, Organization};
use Illuminate\Support\Facades\DB;

class ExecutiveResponseService
{
    public function assigned(LeadAssignment $assignment): void
    {
        $lead = Lead::withoutGlobalScopes()->find($assignment->lead_id);
        if (!$lead || $lead->assigned_to != $assignment->assigned_to || !$assignment->assigned_at) return;
        $minutes = Organization::find(User::find($lead->client_id)?->organization_id)?->executive_response_minutes ?? 15;
        $start = \Carbon\Carbon::parse($assignment->assigned_at);
        $due = $start->copy()->addMinutes($minutes);
        DB::table('lead_assignments')->where('id', $assignment->id)->update(['response_due_at' => $due]);
        Lead::withoutGlobalScopes()->whereKey($lead->id)->where('assigned_to', $assignment->assigned_to)
            ->update(['executive_assigned_at' => $start, 'executive_response_due_at' => $due, 'executive_first_response_at' => null]);
    }

    public function record(int $leadId, ?int $actorId, $at): void
    {
        if (!$actorId) return;
        DB::transaction(function () use ($leadId, $actorId, $at) {
            $lead = Lead::withoutGlobalScopes()->whereKey($leadId)->lockForUpdate()->first();
            if (!$lead || $lead->assigned_to != $actorId || $lead->delivery_pending || $lead->internal_processing_pending || !$lead->executive_assigned_at || $lead->executive_first_response_at) return;
            $at = \Carbon\Carbon::parse($at);
            if ($at->lt($lead->executive_assigned_at) || $at->isFuture()) return;
            Lead::withoutGlobalScopes()->whereKey($leadId)->update(['executive_first_response_at' => $at]);
            LeadAssignment::where('lead_id', $leadId)->where('assigned_to', $actorId)->whereNull('unassigned_at')->whereNull('first_response_at')->update(['first_response_at' => $at]);
        });
    }
}
