<?php

namespace App\Observers;

class LeadObserver
{
    public function created(\App\Models\Lead $lead): void
    {
        if ($lead->delivery_plan_id) app(\App\Services\LeadDistributionService::class)->captureClientDeliveryLead($lead);
        if ($lead->internal_processing_pending) app(\App\Services\LeadDistributionService::class)->startInternalProcessing($lead);
    }
    public function creating(\App\Models\Lead $lead): void
    {
        app(\App\Services\LeadDistributionService::class)->scheduleDelivery($lead);
    }
}
