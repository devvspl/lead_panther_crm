<div class="relative" wire:poll.15s x-data="notificationInbox(@js(auth()->id()))" @keydown.escape.window="$wire.isOpen = false" @click.outside="$wire.isOpen = false">
    <button type="button" wire:click="toggleDropdown" aria-label="Notifications" aria-expanded="{{ $isOpen ? 'true' : 'false' }}" class="relative p-2 text-muted hover:text-ink rounded-lg hover:bg-canvas">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3A6 6 0 006 11v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        @if($unreadCount)<span class="absolute -top-1 -right-1 min-w-4 px-1 rounded-full bg-primary text-white text-[10px] font-bold" aria-label="{{ $unreadCount }} unread notifications">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif
    </button>
    @if($isOpen)
        <section aria-label="Recent notifications" class="absolute right-0 mt-2 w-80 sm:w-96 max-w-[calc(100vw-2rem)] bg-surface rounded-xl border border-border shadow-xl z-50 overflow-hidden">
            <div class="p-3 flex justify-between items-center border-b border-border">
                <h2 class="font-bold text-sm text-ink">Notifications</h2>
                @if($unreadCount)<button type="button" wire:click="markAllAsRead" class="text-xs text-primary">Mark all as read</button>@endif
            </div>
            <div class="max-h-96 overflow-y-auto">
                @forelse($notifications as $notification)
                    <x-ui.notification-item :notification="$notification" />
                @empty
                    <p class="p-6 text-center text-xs text-muted">No notifications yet. Your updates will appear here.</p>
                @endforelse
            </div>
            @if(\Illuminate\Support\Facades\Route::has('notifications.index'))
                <a href="{{ route('notifications.index') }}" class="block text-center p-3 text-xs font-semibold text-primary border-t border-border">View All Notifications & Preferences</a>
            @endif
        </section>
    @endif
</div>
