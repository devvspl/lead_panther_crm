<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class WebNotification extends Notification
{
    public function __construct(public array $payload) {}
    public function via($notifiable): array { return ['database']; }
    public function toArray($notifiable): array { return $this->payload; }
}
