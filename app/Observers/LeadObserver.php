<?php

namespace App\Observers;

class LeadObserver
{
    public function creating(\App\Models\Lead $lead): void
    {
        app(\App\Services\LeadDistributionService::class)->scheduleDelivery($lead);
    }
}
