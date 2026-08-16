<?php

namespace App\Listeners;

use App\Events\LeadAssigned;
use App\Notifications\LeadAssignedNotification;

class SendLeadAssignedNotifications
{
    public function handle(LeadAssigned $event): void
    {
        if ($event->user) {
            $event->user->notify(new LeadAssignedNotification($event->lead));
        }
    }
}
