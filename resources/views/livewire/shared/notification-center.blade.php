<div class="space-y-5" wire:poll.15s>
    <div class="flex flex-wrap justify-between items-center gap-3">
        <h1 class="text-xl font-bold text-ink">{{ $this->isSuperAdmin ? 'System Notifications' : 'Notifications' }}</h1>
        <div class="flex items-center gap-4 text-xs">
            @unless($this->isSuperAdmin)<label class="flex gap-2 items-center"><input type="checkbox" wire:model.live="unreadOnly">Unread only</label>@endunless
            <button wire:click="markAllAsRead" class="rounded-lg border border-border bg-surface text-ink px-3 py-2">{{ $this->isSuperAdmin ? 'Mark filtered as read' : 'Mark all as read' }}</button>
            @if($this->isSuperAdmin)<button wire:click="exportExcel" class="rounded-lg border border-border bg-surface text-ink px-3 py-2">Export to Excel</button>@endif
        </div>
    </div>
    @if($this->isSuperAdmin)
        <p class="text-xs text-muted">All organizations and recipients. Timestamps and date filters use {{ config('app.timezone') }}. Read-all applies to the current filters.</p>
        <form wire:submit="applyFilters" class="bg-surface border border-border rounded-xl p-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            <label>Organization<select wire:model.live="organizationId" class="block w-full border border-border rounded-lg bg-canvas p-2 mt-1"><option value="">All organizations</option>@foreach($organizations as $org)<option value="{{ $org->id }}">{{ $org->name }}</option>@endforeach</select></label>
            <label>User<select wire:model.live="recipientId" class="block w-full border border-border rounded-lg bg-canvas p-2 mt-1"><option value="">All users</option>@foreach($recipients as $recipient)<option value="{{ $recipient->id }}">{{ $recipient->name }}</option>@endforeach</select></label>
            <label>Role<select wire:model.live="roleFilter" class="block w-full border border-border rounded-lg bg-canvas p-2 mt-1"><option value="">All roles</option>@foreach($roles as $role)<option value="{{ $role }}">{{ $role }}</option>@endforeach</select></label>
            <label>Notification Type<select wire:model.live="typeFilter" class="block w-full border border-border rounded-lg bg-canvas p-2 mt-1"><option value="">All types</option>@foreach($types as $type)<option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>@endforeach</select></label>
            <label>Read Status<select wire:model.live="readFilter" class="block w-full border border-border rounded-lg bg-canvas p-2 mt-1"><option value="all">Read and unread</option><option value="unread">Unread</option><option value="read">Read</option></select></label>
            <label>From<input type="date" wire:model.live="dateFrom" class="block w-full border border-border rounded-lg bg-canvas p-2 mt-1"></label>
            <label>To<input type="date" wire:model.live="dateTo" class="block w-full border border-border rounded-lg bg-canvas p-2 mt-1"></label>
            <label>Search<input wire:model.live.debounce.300ms="search" placeholder="Title, message, lead ID or code" class="block w-full border border-border rounded-lg bg-canvas p-2 mt-1"></label>
            <div class="flex gap-2 items-end"><button class="px-3 py-2 bg-primary text-white rounded-lg">Apply Filters</button><button type="button" wire:click="resetFilters" class="px-3 py-2 border border-border rounded-lg">Reset</button></div>
            @error('dateTo')<span class="text-red-600">{{ $message }}</span>@enderror
            @error('dateFrom')<span class="text-red-600">{{ $message }}</span>@enderror
        </form>
    @endif
    <section class="bg-surface border border-border rounded-xl overflow-hidden">
        @if($this->isSuperAdmin)
            <div class="overflow-x-auto"><table class="w-full text-xs text-left"><thead class="bg-canvas text-muted"><tr><th class="p-3">Type / Title & Message</th><th class="p-3">Recipient / Role</th><th class="p-3">Organization</th><th class="p-3">Related To</th><th class="p-3">Timestamp</th><th class="p-3">Status</th><th class="p-3">Actions</th></tr></thead><tbody>
            @forelse($notifications as $notification)
                <tr wire:key="system-notice-{{ $notification->id }}" class="border-t border-border {{ $notification->read_at ? '' : 'bg-primary/5' }}">
                    <td class="p-3 min-w-64"><p class="text-primary">{{ ucwords(str_replace('_', ' ', $notification->data['type'] ?? $notification->type)) }}</p><strong class="text-ink">{{ $notification->data['title'] ?? '' }}</strong><p class="text-muted mt-1">{{ $notification->data['message'] ?? '' }}</p></td>
                    <td class="p-3 text-ink">{{ $notification->recipient?->name ?? 'Recipient unavailable' }}<p class="text-muted">{{ $notification->recipient?->getRoleNames()->implode(', ') }}</p></td>
                    <td class="p-3 text-ink">{{ $notification->organization?->name ?? 'System / Unavailable' }}</td>
                    <td class="p-3"><button wire:click="markAsReadAndNavigate('{{ $notification->id }}')" class="text-primary">{{ ucfirst($notification->data['entity_type'] ?? 'Notification') }} {{ $notification->data['entity_id'] ?? '' }}</button></td>
                    <td class="p-3 whitespace-nowrap text-muted">{{ $notification->created_at->format('M d, Y H:i:s') }}</td><td class="p-3">{{ $notification->read_at ? 'Read' : 'Unread' }}</td>
                    <td class="p-3 whitespace-nowrap"><button wire:click="inspect('{{ $notification->id }}')" class="text-primary mr-2">Inspect</button>@unless($notification->read_at)<button wire:click="markAsRead('{{ $notification->id }}')" class="text-primary">Mark read</button>@endunless</td>
                </tr>
            @empty<tr><td colspan="7" class="p-8 text-center text-muted">No notifications match the current filters.</td></tr>@endforelse
            </tbody></table></div>
        @else
        @forelse($notifications as $notification)
            <x-ui.notification-item :notification="$notification" />
        @empty
            <p class="p-8 text-center text-sm text-muted">No notifications to show. Updates will appear here when they happen.</p>
        @endforelse
        @endif
        <div class="p-4">{{ $notifications->links() }}</div>
    </section>
    <x-modal name="system-notification-details" :show="$selectedNotificationId !== null" maxWidth="3xl">
        @if($this->selectedNotification)
            @php $notice = $this->selectedNotification; @endphp
            <div class="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                <h2 class="font-bold text-lg text-ink">{{ $notice->data['title'] ?? 'Notification Details' }}</h2>
                <p class="text-sm text-ink">{{ $notice->data['message'] ?? '' }}</p>
                <dl class="grid sm:grid-cols-2 gap-3 text-xs text-ink"><div>ID: {{ $notice->id }}</div><div>Status: {{ $notice->read_at ? 'Read' : 'Unread' }}</div><div>Created: {{ $notice->created_at->toDateTimeString() }}</div><div>Read: {{ $notice->read_at?->toDateTimeString() ?? 'Not yet read' }}</div>
                @if($this->isSuperAdmin)<div>Recipient: {{ $notice->recipient?->name }}</div><div>Organization: {{ $notice->organization?->name ?? 'Unavailable' }}</div>@endif</dl>
                <x-ui.audit-json :value="$notice->data" />
                <div class="flex gap-3 text-xs"><button wire:click="markAsReadAndNavigate('{{ $notice->id }}')" class="bg-primary text-white px-4 py-2 rounded-lg">Open Related Record</button>@unless($notice->read_at)<button wire:click="markAsRead('{{ $notice->id }}')" class="border border-border px-4 py-2 rounded-lg">Mark read</button>@endunless<button wire:click="closeInspection" class="border border-border px-4 py-2 rounded-lg">Close</button></div>
            </div>
        @endif
    </x-modal>
    <section class="bg-surface border border-border rounded-xl p-5 space-y-4">
        <h2 class="font-semibold text-ink">Notification preferences</h2>
        <p class="text-xs text-muted">Important delivery and replacement alerts remain enabled for responsible administrators.</p>
        <form wire:submit="savePreferences" class="space-y-4">
            <div class="grid sm:grid-cols-2 gap-3 text-sm text-ink">
                @foreach(['assignment' => 'Lead assignment and team activity', 'followup' => 'Follow-up reminders and scheduled visits', 'sla' => 'SLA alerts and first responses', 'replacement' => 'Replacement updates', 'delivery' => 'Campaign and delivery alerts'] as $category => $label)
                    <label class="flex gap-2 items-center"><input type="checkbox" wire:model="preferences.{{ $category }}">{{ $label }}</label>
                @endforeach
            </div>
            @error('preferences.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <button class="rounded-lg bg-primary text-white text-sm px-4 py-2" wire:loading.attr="disabled">Save Preferences</button>
        </form>
    </section>
</div>
