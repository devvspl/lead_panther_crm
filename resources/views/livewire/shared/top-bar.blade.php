<header class="h-16 bg-surface border-b border-border px-6 flex items-center justify-between flex-shrink-0">
    <!-- Search Bar with ⌘F hint -->
    <div class="w-72 relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <input type="text" placeholder="Search leads, projects..." class="w-full pl-9 pr-12 py-1.5 bg-canvas text-ink text-sm rounded-lg border-0 focus:ring-2 focus:ring-ink focus:bg-surface placeholder:text-muted transition">
        <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none">
            <kbd class="px-1.5 py-0.5 text-[10px] font-semibold text-muted bg-surface border border-border rounded shadow-2xs">⌘F</kbd>
        </div>
    </div>

    <!-- Right-aligned Links & Profile Dropdown -->
    <div class="flex items-center space-x-6">
        <!-- Help Center Link -->
        <a href="#" class="text-xs font-medium text-muted hover:text-ink transition flex items-center space-x-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Help Center</span>
        </a>

        <!-- User Avatar & Dropdown -->
        <x-ui.dropdown align="right" width="48">
            <x-slot name="trigger">
                <button class="flex items-center space-x-3 text-left focus:outline-none">
                    <div class="h-8 w-8 rounded-full bg-accent text-white flex items-center justify-center text-xs font-bold ring-2 ring-border">
                        {{ strtoupper(substr(auth()->user()->name ?? 'User', 0, 2)) }}
                    </div>
                    <span class="text-sm font-medium text-ink flex items-center space-x-1">
                        <span>{{ auth()->user()->name ?? 'Account User' }}</span>
                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </button>
            </x-slot>

            <x-slot name="content">
                <div class="px-4 py-2 border-b border-border">
                    <div class="text-xs font-semibold text-ink">{{ auth()->user()->name ?? 'User' }}</div>
                    <div class="text-xs text-muted truncate">{{ auth()->user()->email ?? 'user@leadpanther.com' }}</div>
                </div>
                <a href="{{ route('profile') }}" class="block px-4 py-2 text-xs text-ink hover:bg-canvas">Profile Settings</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 text-xs text-danger hover:bg-canvas">
                        Log out
                    </button>
                </form>
            </x-slot>
        </x-ui.dropdown>
    </div>
</header>
