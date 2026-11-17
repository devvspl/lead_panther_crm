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
    public string $dateRange = 'all';
    public int $perPage = 20;
    #[\Livewire\Attributes\Locked]
    public ?string $selectedNotificationId = null;

    public function getIsSuperAdminProperty(): bool { return auth()->user()->hasAnyRole(['Super Admin', 'super-admin']); }

    public function notificationColumns(): array
    {
        $columns = [];
        foreach (['summary' => 'Type / Title & Message', 'recipient_info' => 'Recipient / Role', 'organization_info' => 'Organization',
            'related_record' => 'Related To', 'timestamp' => 'Timestamp', 'read_status' => 'Status', 'notice_actions' => 'Actions'] as $key => $label) {
            $columns[] = ['key' => $key, 'label' => $label, 'sortable' => false,
                'render' => fn ($notification) => view('components.ui.notification-table-cell', ['notification' => $notification, 'column' => $key])->render()];
        }
        return $columns;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['organizationId', 'recipientId', 'roleFilter', 'typeFilter', 'readFilter', 'dateRange', 'dateFrom', 'dateTo', 'search', 'perPage'])) $this->resetPage();
    }

    public function updatedOrganizationId(): void { $this->recipientId = null; $this->resetPage(); }

    public function updatedDateRange(): void
    {
        $now = now();
        $range = match ($this->dateRange) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last7' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'lastMonth' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom' => null,
            default => ['', ''],
        };
        if ($range !== null) {
            $this->dateFrom = $range[0] ? $range[0]->toDateString() : '';
            $this->dateTo = $range[1] ? $range[1]->toDateString() : '';
        }
        $this->resetPage();
    }

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
        $this->validate(['dateFrom' => 'nullable|date', 'dateTo' => 'nullable|date'.($this->dateFrom ? '|after_or_equal:dateFrom' : ''), 'perPage' => 'integer|in:10,20,50,100']);
        $this->resetPage();
    }
    public function resetFilters(): void { $this->reset(['search', 'organizationId', 'recipientId', 'roleFilter', 'typeFilter', 'readFilter', 'dateFrom', 'dateTo', 'dateRange']); $this->resetPage(); }
    public function inspect(string $id): void { $this->notificationQuery()->whereKey($id)->firstOrFail(); $this->selectedNotificationId = $id; $this->dispatch('open-modal', 'system-notification-details'); }
    public function closeInspection(): void { $this->selectedNotificationId = null; $this->dispatch('close-modal', 'system-notification-details'); }
    public function getSelectedNotificationProperty() { return $this->selectedNotificationId ? app(NotificationDeliveryService::class)->managedNotifications(auth()->user())->whereKey($this->selectedNotificationId)->first() : null; }


    public function getDeliveryFailureDetailsProperty(): ?array
    {
        $notice = $this->selectedNotification;
        if (!$notice || ($notice->data['type'] ?? null) !== 'delivery_failed' || !$this->isSuperAdmin) return null;
        $data = $notice->data;
        $id = $data['lead_id'] ?? (($data['entity_type'] ?? null) === 'lead' ? ($data['entity_id'] ?? null) : null);
        $code = $data['lead_code'] ?? null;
        // Older alerts linked to a campaign; resolve only their explicit CRM lead code.
        if (!$id && !$code && preg_match('/\bLP-\d{4}-\d+\b/', $data['message'] ?? '', $match)) $code = $match[0];
        $lead = $id ? \App\Models\Lead::withoutGlobalScopes()->find($id) : ($code ? \App\Models\Lead::withoutGlobalScopes()->where('lead_code', $code)->first() : null);
        if (!$lead) return null;
        $item = \App\Models\LeadDeliveryItem::where('lead_id', $lead->id)->first();
        $delivered = (bool) $lead->delivered_at && !$lead->delivery_pending;
        $owner = \App\Models\User::find($lead->client_id);
        return ['lead_code' => $lead->lead_code, 'delivered' => $delivered,
            'status' => $delivered ? 'Delivered' : ($item ? ucwords($item->status) : 'Not delivered — not enrolled in a client batch'),
            'reason' => $item?->failure_reason ?: ($lead->delivery_error ?: ($data['failure_reason'] ?? 'No detailed failure reason was stored.')),
            'last_failure_at' => $lead->delivery_failed_at?->format('d M Y H:i T'),
            'delivered_at' => $lead->delivered_at?->format('d M Y H:i T'),
            'wallet_balance' => \App\Models\CreditWallet::withoutGlobalScopes()->where('client_id', $lead->client_id)->value('balance'),
            'client_leads_url' => $owner?->organization_id ? route('admin.organizations.show', ['organization' => $owner->organization_id, 'tab' => 'leads', 'search' => $lead->lead_code]) : null];
    }

    public function exportExcel()
    {
        abort_unless($this->isSuperAdmin, 403);
        $this->applyFilters();
        $rows = $this->notificationQuery()->latest()->get();
        \App\Models\AuditLog::create(['user_id' => auth()->id(), 'action' => 'notification.exported', 'subject_type' => \App\Models\SystemNotification::class,
            'to_value' => ['record_count' => $rows->count(), 'format' => 'xlsx'], 'context' => ['category' => 'notification', 'status' => 'success', 'organization_filter' => $this->organizationId, 'recipient_filter' => $this->recipientId], 'created_at' => now()]);
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\BaseStyledExport(data: $rows, title: 'System Notifications', subtitle: 'Filtered database notifications',
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
            'recipients' => $admin ? \App\Models\User::when($this->organizationId, fn ($q) => $q->where('organization_id', $this->organizationId))->orderBy('name')->get() : collect(),
            'roles' => $admin ? \Spatie\Permission\Models\Role::orderBy('name')->pluck('name') : collect(),
            'types' => $admin ? \App\Models\SystemNotification::query()->select('data->type as notification_type')->distinct()->get()->pluck('notification_type')->filter()->sort()->values() : collect(),
        ])->layout('layouts.app');
    }
}
