<div class="space-y-5" wire:poll.15s>
    <div class="flex flex-wrap justify-between items-center gap-3">
        <h1 class="text-xl font-bold text-ink">Notifications</h1>
        <div class="flex items-center gap-4 text-xs">
            <label class="flex gap-2 items-center"><input type="checkbox" wire:model.live="unreadOnly">Unread only</label>
            <button wire:click="markAllAsRead" class="rounded-lg border border-border bg-surface text-ink px-3 py-2">Mark all as read</button>
        </div>
    </div>
    <section class="bg-surface border border-border rounded-xl overflow-hidden">
        @forelse($notifications as $notification)
            <x-ui.notification-item :notification="$notification" />
        @empty
            <p class="p-8 text-center text-sm text-muted">No notifications to show. Updates will appear here when they happen.</p>
        @endforelse
        <div class="p-4">{{ $notifications->links() }}</div>
    </section>
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
