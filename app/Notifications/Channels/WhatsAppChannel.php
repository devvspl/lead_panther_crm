<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use App\Contracts\WhatsAppGateway;
use App\Models\NotificationLog;
use Throwable;

class WhatsAppChannel
{
    protected WhatsAppGateway $gateway;

    public function __construct(WhatsAppGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function send($notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $data = $notification->toWhatsApp($notifiable);
        $phone = $data['phone'] ?? $notifiable->phone ?? '9999999999';
        $message = $data['message'] ?? 'Notification from Lead Panther CRM';

        try {
            $success = $this->gateway->sendMessage($phone, $message, $data);
            $status = $success ? 'sent' : 'failed';

            NotificationLog::create([
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id ?? 1,
                'channel' => 'whatsapp',
                'status' => $status,
                'payload' => json_encode(['phone' => $phone, 'message' => $message]),
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            NotificationLog::create([
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id ?? 1,
                'channel' => 'whatsapp',
                'status' => 'failed',
                'payload' => json_encode(['phone' => $phone, 'message' => $message, 'error' => $e->getMessage()]),
                'sent_at' => now(),
            ]);
        }
    }
}
