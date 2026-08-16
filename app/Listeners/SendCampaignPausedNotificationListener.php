<?php

namespace App\Listeners;

use App\Events\CampaignPaused;
use App\Notifications\CampaignPausedNotification;
use App\Models\User;

class SendCampaignPausedNotificationListener
{
    public function handle(CampaignPaused $event): void
    {
        $client = $event->client;
        if (!$client) return;

        $clientUser = User::where('organization_id', $client->organization_id)->first() ?? User::first();
        if ($clientUser) {
            $clientUser->notify(new CampaignPausedNotification($client));
        }
    }
}
