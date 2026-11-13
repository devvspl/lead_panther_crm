<?php

namespace App\Listeners;

use App\Events\CampaignPaused;
use App\Notifications\CampaignPausedNotification;
use App\Models\User;

class SendCampaignPausedNotificationListener
{
    public function handle(CampaignPaused $event): void
    {
        app(\App\Services\NotificationDeliveryService::class)->leadAlert($event->lead, 'campaign_paused', 'Campaign delivery paused',
            $event->reason, 'campaign-credit-paused:'.$event->lead->campaign_id.':'.now()->toDateString(), 'delivery', true);
    }
}
