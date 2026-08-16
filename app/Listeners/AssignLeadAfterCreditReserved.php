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

            $assignment = LeadAssignment::create([
                'lead_id' => $lead->id,
                'assigned_to' => $assignee->id,
                'assigned_by' => auth()->id() ?? 1,
                'assigned_at' => now(),
            ]);

            event(new LeadAssigned($lead, $assignee, $assignment));
        }
    }
}
