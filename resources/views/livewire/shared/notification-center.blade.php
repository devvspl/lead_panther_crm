<div class="space-y-5" wire:poll.15s>
    <div class="flex flex-wrap justify-between items-center gap-3">
        <h1 class="text-xl font-bold text-ink">{{ $this->isSuperAdmin ? 'System Notifications' : 'Notifications' }}</h1>
        <div class="flex items-center gap-4 text-xs">
            @unless($this->isSuperAdmin)<label class="flex gap-2 items-center"><input type="checkbox" wire:model.live="unreadOnly">Unread only</label>@endunless
            <button wire:click="markAllAsRead" class="rounded-lg border border-border bg-surface text-ink px-3 py-2">{{ $this->isSuperAdmin ? 'Mark filtered as read' : 'Mark all as read' }}</button>
            @if($this->isSuperAdmin)<x-ui.export-button target="exportExcel" />@endif
        </div>
    </div>
    @if($this->isSuperAdmin)
        <p class="text-xs text-muted">All organizations and recipients. Timestamps and date filters use {{ config('app.timezone') }}. Read-all applies to the current filters.</p>
        <form wire:submit="applyFilters" class="bg-surface border border-border rounded-xl p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 items-end gap-4 text-xs">
            <div><x-input-label value="Organization" class="mb-1 !text-xs" /><x-ui.themed-select wire:model.live="organizationId" :options="['' => 'All organizations'] + $organizations->pluck('name', 'id')->all()" placeholder="All organizations" :searchable="true" /></div>
            <div><x-input-label value="User" class="mb-1 !text-xs" /><x-ui.themed-select wire:model.live="recipientId" :options="['' => 'All users'] + $recipients->pluck('name', 'id')->all()" placeholder="All users" :searchable="true" /></div>
            <div><x-input-label value="Role" class="mb-1 !text-xs" /><x-ui.themed-select wire:model.live="roleFilter" :options="['' => 'All roles'] + $roles->mapWithKeys(fn ($role) => [$role => $role])->all()" placeholder="All roles" :searchable="true" /></div>
            <div><x-input-label value="Notification Type" class="mb-1 !text-xs" /><x-ui.themed-select wire:model.live="typeFilter" :options="['' => 'All types'] + $types->mapWithKeys(fn ($type) => [$type => ucwords(str_replace('_', ' ', $type))])->all()" placeholder="All types" :searchable="true" /></div>
            <div><x-input-label value="Read Status" class="mb-1 !text-xs" /><x-ui.themed-select wire:model.live="readFilter" :options="['all' => 'Read and unread', 'unread' => 'Unread', 'read' => 'Read']" placeholder="Read and unread" /></div>
            <div class="min-w-0"><x-input-label value="Date Range" class="mb-1 !text-xs" /><x-ui.date-range-picker wire:model.live="dateRange" wire:custom-from="dateFrom" wire:custom-to="dateTo" class="w-full" placeholder="All Time" /></div>
            <div class="min-w-0"><x-input-label for="notification-search" value="Search" class="mb-1 !text-xs" /><x-text-input id="notification-search" wire:model.live.debounce.300ms="search" placeholder="Title, message, lead ID or code" class="block w-full h-8 px-2.5 !text-xs !bg-canvas !shadow-none font-semibold placeholder:font-normal" /></div>
            <div class="flex items-center gap-2 h-8" role="group" aria-label="Filter actions"><button type="submit" class="h-8 px-3.5 bg-primary text-white rounded-lg font-semibold whitespace-nowrap">Apply Filters</button><button type="button" wire:click="resetFilters" class="h-8 px-3.5 border border-border bg-surface rounded-lg font-semibold whitespace-nowrap">Reset</button></div>
            @error('dateTo')<span class="text-red-600">{{ $message }}</span>@enderror
            @error('dateFrom')<span class="text-red-600">{{ $message }}</span>@enderror
        </form>
    @endif
    @if($this->isSuperAdmin)
        <x-ui.advanced-table
            :columns="$this->notificationColumns()"
            :rows="$notifications"
            :showSearch="false"
            :showFilterDropdown="false"
            :showConfigurations="false"
            tableName="system-notifications"
            emptyTitle="No Notifications"
            emptyMessage="No notifications match the current filters."
        />
    @else
        <section class="bg-surface border border-border rounded-xl overflow-hidden">
            @forelse($notifications as $notification)
                <x-ui.notification-item :notification="$notification" />
            @empty
                <p class="p-8 text-center text-sm text-muted">No notifications to show. Updates will appear here when they happen.</p>
            @endforelse
            <div class="p-4">{{ $notifications->links() }}</div>
        </section>
    @endif
    <x-modal name="system-notification-details" :show="$selectedNotificationId !== null" maxWidth="3xl">
        @if($this->selectedNotification)
            @php $notice = $this->selectedNotification; @endphp
            <div class="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                <h2 class="font-bold text-lg text-ink">{{ \App\Support\ClientFacingText::label($notice->data['title'] ?? 'Notification Details') }}</h2>
                <p class="text-sm text-ink">{{ \App\Support\ClientFacingText::label($notice->data['message'] ?? '') }}</p>
                @if($failure = $this->deliveryFailureDetails)
                    <div class="rounded-lg border border-border bg-canvas p-4 space-y-2 text-xs text-ink">
                        <p class="font-semibold">Current delivery status: {{ $failure['status'] }}</p>
                        @if($failure['delivered'])<p>This is an earlier failure alert. The lead was delivered on {{ $failure['delivered_at'] }}.</p>@else<p>This notification records a failed attempt. Check the current status and last failure time below to see whether it still needs attention.</p>@endif
                        <p><strong>Last recorded reason:</strong> {{ $failure['reason'] }}</p>
                        @if($failure['last_failure_at'])<p>Last failure: {{ $failure['last_failure_at'] }}</p>@endif
                        @if($failure['wallet_balance'] !== null)<p>Available Lead Credits: {{ number_format((int) $failure['wallet_balance']) }} credits. This may have changed since the failed attempt.</p>@endif
                        @if($failure['client_leads_url'])<a href="{{ $failure['client_leads_url'] }}" class="font-semibold text-primary underline">Open Client Leads to push or retry</a>@endif
                    </div>
                @endif
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
