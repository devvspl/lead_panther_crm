<?php

namespace App\Listeners;

use App\Events\LeadStageChanged;
use App\Models\CreditTransaction;

class ReserveCreditOnLeadCreated
{
    public function handle(object $event): void
    {
        if (isset($event->lead)) {
            CreditTransaction::reserveForLead($event->lead);
        }
    }
}
