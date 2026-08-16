<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use App\Contracts\SmsGateway;
use App\Models\NotificationLog;
use Throwable;

class SmsChannel
{
    protected SmsGateway $gateway;

    public function __construct(SmsGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function send($notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toSms')) {
            return;
        }

        $data = $notification->toSms($notifiable);
        $phone = $data['phone'] ?? $notifiable->phone ?? '9999999999';
        $message = $data['message'] ?? 'SMS Notification from Lead Panther CRM';

        try {
            $success = $this->gateway->sendSms($phone, $message, $data);
            $status = $success ? 'sent' : 'failed';

            NotificationLog::create([
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id ?? 1,
                'channel' => 'sms',
                'status' => $status,
                'payload' => json_encode(['phone' => $phone, 'message' => $message]),
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            NotificationLog::create([
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id ?? 1,
                'channel' => 'sms',
                'status' => 'failed',
                'payload' => json_encode(['phone' => $phone, 'message' => $message, 'error' => $e->getMessage()]),
                'sent_at' => now(),
            ]);
        }
    }
}
