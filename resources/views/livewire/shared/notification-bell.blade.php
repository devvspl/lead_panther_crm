<div class="relative" wire:poll.10s>
    <!-- Bell Icon Trigger -->
    <button 
        wire:click="toggleDropdown" 
        class="relative p-2 text-muted hover:text-ink rounded-lg hover:bg-canvas transition flex items-center justify-center focus:outline-none"
        title="Notifications"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>

        @if($unreadCount > 0)
            <span class="absolute top-1.5 right-1.5 w-4 h-4 bg-accent text-ink font-bold text-[9px] rounded-full flex items-center justify-center border border-surface shadow-sm animate-pulse">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <!-- Notification Dropdown Menu -->
    @if($isOpen)
        <div 
            class="absolute right-0 mt-2 w-80 sm:w-96 bg-surface rounded-card border border-border shadow-lg z-50 overflow-hidden divide-y divide-border"
        >
            <div class="p-3 bg-canvas flex items-center justify-between">
                <span class="text-xs font-bold text-ink">Notifications</span>
                @if($unreadCount > 0)
                    <button wire:click="markAllAsRead" class="text-[10px] text-accent-hover hover:underline font-semibold">
                        Mark all as read
                    </button>
                @endif
            </div>

            <div class="max-h-80 overflow-y-auto divide-y divide-border/50">
                @forelse($notifications as $n)
                    @php
                        $data = $n->data;
                        $isUnread = is_null($n->read_at);
                    @endphp
                    <div 
                        wire:click="markAsReadAndNavigate('{{ $n->id }}', '{{ $data['link'] ?? '#' }}')"
                        class="p-3 cursor-pointer hover:bg-canvas/60 transition flex items-start space-x-3 {{ $isUnread ? 'bg-amber-50/40' : '' }}"
                    >
                        <div class="mt-0.5 w-2 h-2 rounded-full flex-shrink-0 {{ $isUnread ? 'bg-accent' : 'bg-transparent' }}"></div>
                        <div class="flex-1 space-y-0.5">
                            <p class="text-xs font-bold text-ink flex items-center justify-between">
                                <span>{{ $data['title'] ?? 'Notification' }}</span>
                                <span class="text-[9px] font-normal text-muted">{{ $n->created_at->diffForHumans() }}</span>
                            </p>
                            <p class="text-[11px] text-muted line-clamp-2">{{ $data['message'] ?? '' }}</p>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-xs text-muted">
                        No notifications found.
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
