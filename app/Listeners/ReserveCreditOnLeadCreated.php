<?php

namespace App\Listeners;

use App\Events\LeadCreated;
use App\Models\CreditTransaction;

class ReserveCreditOnLeadCreated
{
    public function handle(LeadCreated $event): void
    {
        if (isset($event->lead)) {
            CreditTransaction::reserveForLead($event->lead);
        }
    }
}
