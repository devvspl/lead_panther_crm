<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Channels\SmsChannel;
use App\Models\Followup;

class FollowupDueNotification extends Notification
{
    use Queueable;

    public Followup $followup;

    public function __construct(Followup $followup)
    {
        $this->followup = $followup;
    }

    public function via($notifiable): array
    {
        return ['database', WhatsAppChannel::class, SmsChannel::class];
    }

    public function toArray($notifiable): array
    {
        $leadName = $this->followup->lead?->name ?? 'Lead';
        return [
            'type' => 'followup_due',
            'title' => 'Follow-up Due: ' . $leadName,
            'message' => "Scheduled follow-up for {$leadName} is now due.",
            'link' => "/leads/kanban",
            'followup_id' => $this->followup->id,
        ];
    }

    public function toWhatsApp($notifiable): array
    {
        $leadName = $this->followup->lead?->name ?? 'Lead';
        return [
            'phone' => $notifiable->phone ?? '9999999999',
            'message' => "Reminder: Follow-up with {$leadName} is due now on Lead Panther CRM.",
        ];
    }

    public function toSms($notifiable): array
    {
        $leadName = $this->followup->lead?->name ?? 'Lead';
        return [
            'phone' => $notifiable->phone ?? '9999999999',
            'message' => "Lead Panther CRM: Follow-up due for {$leadName}.",
        ];
    }
}
