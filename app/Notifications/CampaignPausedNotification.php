<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Channels\SmsChannel;
use App\Models\Client;

class CampaignPausedNotification extends Notification
{
    use Queueable;

    public Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function via($notifiable): array
    {
        return ['database', WhatsAppChannel::class, SmsChannel::class];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'campaign_paused',
            'title' => 'Campaign Paused: Zero Credit Balance',
            'message' => "Campaigns for {$this->client->name} have been paused due to insufficient wallet credits.",
            'link' => "/credits",
            'client_id' => $this->client->id,
        ];
    }

    public function toWhatsApp($notifiable): array
    {
        return [
            'phone' => $notifiable->phone ?? '9999999999',
            'message' => "Alert: Lead Panther CRM campaigns for {$this->client->name} were PAUSED due to 0 credit balance. Please recharge now.",
        ];
    }

    public function toSms($notifiable): array
    {
        return [
            'phone' => $notifiable->phone ?? '9999999999',
            'message' => "Lead Panther CRM Alert: Campaigns paused for {$this->client->name} due to zero balance.",
        ];
    }
}
