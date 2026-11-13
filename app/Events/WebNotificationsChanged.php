<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class WebNotificationsChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(public int $userId) {}

    public function broadcastOn(): array { return [new PrivateChannel('App.Models.User.'.$this->userId)]; }
    public function broadcastAs(): string { return 'notifications.changed'; }
    public function broadcastWith(): array { return []; }
}
