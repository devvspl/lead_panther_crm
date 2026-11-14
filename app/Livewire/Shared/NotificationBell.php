<?php
namespace App\Livewire\Shared;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Services\NotificationDeliveryService;

class NotificationBell extends Component
{
    public bool $isOpen = false;
    public function toggleDropdown(): void { $this->isOpen = ! $this->isOpen; }
    protected function notificationQuery() { return app(NotificationDeliveryService::class)->inbox(auth()->user()); }

    #[On('notifications-refresh')]
    public function refreshNotifications(): void {}

    public function markAllAsRead(): void
    {
        $query = $this->notificationQuery()->whereNull('read_at');
        $count = (clone $query)->count();
        $ids = (clone $query)->limit(100)->pluck('id')->all();
        $query->update(['read_at' => now()]);
        if ($count) \App\Models\AuditLog::create(['user_id' => auth()->id(), 'action' => 'notification.read_all', 'subject_type' => \Illuminate\Notifications\DatabaseNotification::class,
            'from_value' => ['read_at' => null], 'to_value' => ['read_at' => now()->toIso8601String(), 'count' => $count],
            'context' => ['notification_ids' => $ids, 'references_truncated' => $count > 100, 'category' => 'notification', 'status' => 'success'], 'created_at' => now()]);
        $this->notifyReadChange();
    }

    public function markAsRead(string $notificationId): void
    {
        $this->notificationQuery()->whereKey($notificationId)->firstOrFail()->markAsRead();
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
