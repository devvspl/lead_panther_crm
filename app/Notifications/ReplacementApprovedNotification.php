<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Channels\SmsChannel;
use App\Models\LeadReplacement;

class ReplacementApprovedNotification extends Notification
{
    use Queueable;

    public LeadReplacement $replacement;

    public function __construct(LeadReplacement $replacement)
    {
        $this->replacement = $replacement;
    }

    public function via($notifiable): array
    {
        return ['database', WhatsAppChannel::class, SmsChannel::class];
    }

    public function toArray($notifiable): array
    {
        $leadName = $this->replacement->originalLead?->name ?? 'Lead';
        return [
            'type' => 'replacement_approved',
            'title' => 'Replacement Approved',
            'message' => "Replacement request for {$leadName} has been approved.",
            'link' => "/replacements",
            'replacement_id' => $this->replacement->id,
        ];
    }

    public function toWhatsApp($notifiable): array
    {
        $leadName = $this->replacement->originalLead?->name ?? 'Lead';
        return [
            'phone' => $notifiable->phone ?? '9999999999',
            'message' => "Your replacement request for {$leadName} was approved on Lead Panther CRM.",
        ];
    }

    public function toSms($notifiable): array
    {
        $leadName = $this->replacement->originalLead?->name ?? 'Lead';
        return [
            'phone' => $notifiable->phone ?? '9999999999',
            'message' => "Lead Panther CRM: Replacement for {$leadName} approved.",
        ];
    }
}
