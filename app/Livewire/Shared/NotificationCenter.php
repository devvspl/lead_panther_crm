<?php
namespace App\Livewire\Shared;

use Livewire\WithPagination;
use App\Services\NotificationDeliveryService;

class NotificationCenter extends NotificationBell
{
    use WithPagination;
    public array $preferences = [];
    public bool $unreadOnly = false;
    public string $search = '';
    public ?int $organizationId = null;
    public ?int $recipientId = null;
    public string $roleFilter = '';
    public string $typeFilter = '';
    public string $readFilter = 'all';
    public string $dateFrom = '';
    public string $dateTo = '';
    public int $perPage = 20;
    #[\Livewire\Attributes\Locked]
    public ?string $selectedNotificationId = null;

    public function getIsSuperAdminProperty(): bool { return auth()->user()->hasAnyRole(['Super Admin', 'super-admin']); }

    protected function notificationQuery()
    {
        $query = app(NotificationDeliveryService::class)->managedNotifications(auth()->user());
        if (! $this->isSuperAdmin) return $query->when($this->unreadOnly, fn ($q) => $q->whereNull('read_at'));
        if ($this->organizationId) $query->where('organization_id', $this->organizationId);
        if ($this->recipientId) $query->where('notifiable_id', $this->recipientId);
        if ($this->roleFilter) $query->whereIn('notifiable_id', \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', $this->roleFilter))->select('id'));
        if ($this->typeFilter) $query->where('data->type', $this->typeFilter);
        if ($this->readFilter === 'unread') $query->whereNull('read_at');
        if ($this->readFilter === 'read') $query->whereNotNull('read_at');
        if ($this->dateFrom && strtotime($this->dateFrom)) $query->where('created_at', '>=', $this->dateFrom.' 00:00:00');
        if ($this->dateTo && strtotime($this->dateTo)) $query->where('created_at', '<=', $this->dateTo.' 23:59:59');
        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('data->title', 'like', $term)->orWhere('data->message', 'like', $term)->orWhere('data->lead_id', 'like', $term)
                    ->orWhere(function ($related) use ($term) { $related->where('data->entity_type', 'lead')->whereIn('data->entity_id', \App\Models\Lead::withoutGlobalScopes()->where('lead_code', 'like', $term)->orWhere('id', $this->search)->select('id')); });
            });
        }
        return $query;
    }

    public function applyFilters(): void
    {
        $this->validate(['dateFrom' => 'nullable|date', 'dateTo' => 'nullable|date|after_or_equal:dateFrom', 'perPage' => 'integer|in:10,20,50,100']);
        $this->resetPage();
    }
    public function resetFilters(): void { $this->reset(['search', 'organizationId', 'recipientId', 'roleFilter', 'typeFilter', 'readFilter', 'dateFrom', 'dateTo']); $this->resetPage(); }
    public function inspect(string $id): void { $this->notificationQuery()->whereKey($id)->firstOrFail(); $this->selectedNotificationId = $id; $this->dispatch('open-modal', 'system-notification-details'); }
    public function closeInspection(): void { $this->selectedNotificationId = null; $this->dispatch('close-modal', 'system-notification-details'); }
    public function getSelectedNotificationProperty() { return $this->selectedNotificationId ? app(NotificationDeliveryService::class)->managedNotifications(auth()->user())->whereKey($this->selectedNotificationId)->first() : null; }

    public function exportExcel()
    {
        abort_unless($this->isSuperAdmin, 403);
        $this->applyFilters();
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\BaseStyledExport(data: $this->notificationQuery()->latest()->get(), title: 'System Notifications', subtitle: 'Filtered database notifications',
            headings: ['ID', 'Recipient', 'Organization', 'Type', 'Title', 'Message', 'Related Entity', 'Related ID', 'Status', 'Created At'],
            columns: ['id', fn ($n) => $n->recipient?->name, fn ($n) => $n->organization?->name, fn ($n) => $n->data['type'] ?? '', fn ($n) => $n->data['title'] ?? '', fn ($n) => $n->data['message'] ?? '', fn ($n) => $n->data['entity_type'] ?? '', fn ($n) => $n->data['entity_id'] ?? '', fn ($n) => $n->read_at ? 'Read' : 'Unread', fn ($n) => $n->created_at->toDateTimeString()], statusColumns: []), 'system-notifications.xlsx');
    }

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
        $query = $this->notificationQuery();
        $admin = $this->isSuperAdmin;
        return view('livewire.shared.notification-center', ['notifications' => $query->latest()->paginate(in_array($this->perPage, [10,20,50,100]) ? $this->perPage : 20),
            'organizations' => $admin ? \App\Models\Organization::orderBy('name')->get() : collect(),
            'recipients' => $admin ? \App\Models\User::orderBy('name')->get() : collect(),
            'roles' => $admin ? \Spatie\Permission\Models\Role::orderBy('name')->pluck('name') : collect(),
            'types' => $admin ? \App\Models\SystemNotification::query()->select('data->type as notification_type')->distinct()->get()->pluck('notification_type')->filter()->sort()->values() : collect(),
        ])->layout('layouts.app');
    }
}
