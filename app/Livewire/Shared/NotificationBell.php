<?php
namespace App\Livewire\Shared;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Services\NotificationDeliveryService;

class NotificationBell extends Component
{
    public bool $isOpen = false;
    public function toggleDropdown(): void { $this->isOpen = ! $this->isOpen; }

    #[On('notifications-refresh')]
    public function refreshNotifications(): void {}

    public function markAllAsRead(): void
    {
        app(NotificationDeliveryService::class)->inbox(auth()->user())->whereNull('read_at')->update(['read_at' => now()]);
        $this->notifyReadChange();
    }

    public function markAsRead(string $notificationId): void
    {
        app(NotificationDeliveryService::class)->inbox(auth()->user())->whereKey($notificationId)->firstOrFail()->markAsRead();
        $this->notifyReadChange();
    }

    public function markAsReadAndNavigate(string $notificationId): mixed
    {
        // Reuse the authenticated entity resolver even during a stale route-cache deployment.
        return app(\App\Http\Controllers\NotificationController::class)->open($notificationId);
    }

    protected function notifyReadChange(): void
    {
        $this->dispatch('notifications-refresh');
        try { event(new \App\Events\WebNotificationsChanged(auth()->id())); } catch (\Throwable $e) { report($e); }
    }

    public function render()
    {
        $query = app(NotificationDeliveryService::class)->inbox(auth()->user());
        return view('livewire.shared.notification-bell', ['unreadCount' => (clone $query)->whereNull('read_at')->count(), 'notifications' => $query->latest()->take(6)->get()]);
    }
}
