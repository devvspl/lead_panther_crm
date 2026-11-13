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
            // pickAssignee delegates to the existing service, which already records assignment.
            $assignment = LeadAssignment::where('lead_id', $lead->id)->where('assigned_to', $assignee->id)->latest('id')->firstOrFail();

            event(new LeadAssigned($lead, $assignee, $assignment));
        }
    }
}
