<?php

namespace App\Livewire\Shared;

use Livewire\Component;

class NotificationBell extends Component
{
    public bool $isOpen = false;

    public function toggleDropdown(): void
    {
        $this->isOpen = !$this->isOpen;
    }

    public function markAllAsRead(): void
    {
        if (auth()->check()) {
            auth()->user()->unreadNotifications->markAsRead();
        }
    }

    public function markAsReadAndNavigate(string $notificationId, ?string $link): mixed
    {
        if (auth()->check()) {
            $notification = auth()->user()->notifications()->where('id', $notificationId)->first();
            if ($notification) {
                $notification->markAsRead();
            }
        }

        if ($link) {
            return redirect()->to($link);
        }

        return null;
    }

    public function render()
    {
        $unreadCount = auth()->check() ? auth()->user()->unreadNotifications->count() : 0;
        $notifications = auth()->check() ? auth()->user()->notifications()->take(6)->get() : collect();

        return view('livewire.shared.notification-bell', [
            'unreadCount' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }
}
