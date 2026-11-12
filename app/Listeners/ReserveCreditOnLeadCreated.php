<?php

namespace App\Listeners;

use App\Events\LeadCreated;
use App\Models\CreditTransaction;

class ReserveCreditOnLeadCreated
{
    public function handle(LeadCreated $event): void
    {
        if (isset($event->lead)) {
            if ($event->lead->delivery_pending) {
                app(\App\Services\LeadDistributionService::class)->releaseDelivery($event->lead->id);
                return;
            }
            CreditTransaction::reserveForLead($event->lead);
        }
    }
}
