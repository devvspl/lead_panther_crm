<?php

namespace App\Listeners;

use App\Events\LeadCreated;
use App\Models\Lead;

class ReserveCreditOnLeadCreated
{
    public function handle(LeadCreated $event): void
    {
        if (isset($event->lead)) {
            if ($event->lead->delivery_pending) {
                app(\App\Services\LeadDistributionService::class)->releaseDelivery($event->lead->id);
                return;
            }
            // Campaign-less/manual leads use the same success/failure tracking path.
            if (! $event->lead->delivered_at) {
                // A repeated event may hold a stale model; never reset a successful delivery.
                Lead::withoutGlobalScopes()->whereKey($event->lead->id)->whereNull('delivered_at')
                    ->where('delivery_pending', false)->update(['delivery_pending' => true, 'delivery_at' => now()]);
                app(\App\Services\LeadDistributionService::class)->releaseDelivery($event->lead->id);
            }
        }
    }
}
