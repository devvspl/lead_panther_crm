<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleFirstResponseRecorded
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     */
    public function handle(\App\Events\FirstResponseRecorded $event): void
    {
        app(\App\Services\NotificationDeliveryService::class)->leadAlert($event->lead, 'first_response', 'First response recorded',
            "First response recorded for {$event->lead->lead_code}.", 'first-response:'.$event->lead->id, 'sla');
    }
}
