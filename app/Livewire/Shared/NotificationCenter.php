<?php
namespace App\Livewire\Shared;

use Livewire\WithPagination;
use App\Services\NotificationDeliveryService;

class NotificationCenter extends NotificationBell
{
    use WithPagination;
    public array $preferences = [];
    public bool $unreadOnly = false;

    public function mount(): void
    {
        foreach (NotificationDeliveryService::WEB_CATEGORIES as $category) $this->preferences[$category] = (bool) (auth()->user()->notification_preferences[$category] ?? true);
    }

    public function updatedUnreadOnly(): void { $this->resetPage(); }

    public function savePreferences(): void
    {
        $rules = [];
        foreach (NotificationDeliveryService::WEB_CATEGORIES as $category) $rules['preferences.'.$category] = 'required|boolean';
        $this->validate($rules);
        auth()->user()->update(['notification_preferences' => array_intersect_key($this->preferences, array_flip(NotificationDeliveryService::WEB_CATEGORIES))]);
        $this->dispatch('toast', type: 'success', message: 'Notification preferences saved.');
    }

    public function render()
    {
        $query = app(NotificationDeliveryService::class)->inbox(auth()->user());
        return view('livewire.shared.notification-center', ['notifications' => $query->when($this->unreadOnly, fn ($q) => $q->whereNull('read_at'))->latest()->paginate(20)])->layout('layouts.app');
    }
}
