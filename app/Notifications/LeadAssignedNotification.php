<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Channels\SmsChannel;
use App\Models\Lead;
use App\Models\NotificationTemplate;

class LeadAssignedNotification extends Notification
{
    use Queueable;

    public Lead $lead;

    public function __construct(Lead $lead)
    {
        $this->lead = $lead;
    }

    public function via($notifiable): array
    {
        return ['database', WhatsAppChannel::class, SmsChannel::class];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'lead_assigned',
            'title' => 'New Lead Assigned: ' . $this->lead->name,
            'message' => "Lead {$this->lead->lead_code} ({$this->lead->name}) has been assigned to you.",
            'link' => "/leads/kanban",
            'lead_id' => $this->lead->id,
        ];
    }

    public function toWhatsApp($notifiable): array
    {
        $template = NotificationTemplate::where('key', 'lead_assigned_whatsapp')->first();
        $msg = $template ? $template->render(['lead_name' => $this->lead->name, 'lead_code' => $this->lead->lead_code]) 
                         : "Hello! New Lead {$this->lead->lead_code} ({$this->lead->name}) assigned to you on Lead Panther CRM.";

        return [
            'phone' => $notifiable->phone ?? '9999999999',
            'message' => $msg,
        ];
    }

    public function toSms($notifiable): array
    {
        return [
            'phone' => $notifiable->phone ?? '9999999999',
            'message' => "Lead Panther CRM: New lead {$this->lead->name} assigned to you.",
        ];
    }
}
