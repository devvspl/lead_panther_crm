<?php

namespace App\Listeners;

use App\Events\CreditReserved;
use App\Events\LeadAssigned;
use App\Models\DistributionRule;
use App\Models\LeadAssignment;

class AssignLeadAfterCreditReserved
{
    public function handle(CreditReserved $event): void
    {
        $lead = $event->lead;
        if (!$lead) return;

        $assignee = DistributionRule::pickAssignee($lead);

        if ($assignee) {
            $lead->update([
                'assigned_to' => $assignee->id,
                'current_stage' => 'assigned',
                'status' => 'assigned',
            ]);

            $assignerId = auth()->id() ?? \App\Models\User::where('id', 1)->value('id') ?? \App\Models\User::value('id') ?? $assignee->id;

            $assignment = LeadAssignment::create([
                'lead_id' => $lead->id,
                'assigned_to' => $assignee->id,
                'assigned_by' => $assignerId,
                'assigned_at' => now(),
            ]);

            event(new LeadAssigned($lead, $assignee, $assignment));
        }
    }
}
