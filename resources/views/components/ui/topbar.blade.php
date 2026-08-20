@persist('topbar')
<header id="topbar-main" wire:persist="topbar" class="sticky top-0 z-40 h-16 bg-surface border-b border-border px-4 sm:px-6 flex items-center justify-between flex-shrink-0 w-full">
    <!-- Left Section: Mobile toggle, Desktop toggle & Search -->
    <div class="flex items-center space-x-2 sm:space-x-3 min-w-0 flex-1 mr-3 sm:mr-6">
        <!-- Hamburger Icon Button (Mobile Only: md:hidden) -->
        <button 
            x-on:click="sidebarOpen = !sidebarOpen" 
            type="button" 
            class="md:hidden p-2 -ml-1 rounded-lg text-muted hover:text-ink hover:bg-canvas focus:outline-none flex-shrink-0" 
            aria-label="Toggle mobile sidebar"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        <!-- Desktop Sidebar Toggle Button (md:flex) -->
        <button 
            x-on:click="toggleSidebar()" 
            type="button" 
            class="hidden md:flex items-center justify-center p-2 -ml-1 rounded-lg text-muted hover:text-ink hover:bg-canvas focus:outline-none flex-shrink-0 transition cursor-pointer" 
            :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            aria-label="Toggle desktop sidebar"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
            </svg>
        </button>

        @if(session()->has('impersonator_id'))
            <div class="flex items-center space-x-2 bg-purple-50 text-purple-700 px-3 py-1 rounded-pill border border-purple-200 text-xs font-bold truncate">
                <span>Impersonating {{ auth()->user()->name }}</span>
                <a href="{{ route('impersonate.stop') }}" class="underline hover:text-purple-900 font-extrabold ml-1">Stop</a>
            </div>
        @else
            <!-- Global Cross-Entity Search Component -->
            <livewire:shared.global-search />
        @endif
    </div>

    <!-- Right Section: Help, Notifications, and User Profile -->
    <div class="flex items-center space-x-3 sm:space-x-4 flex-shrink-0">
        <!-- Help Center Link -->
        <a href="#" class="text-xs font-medium text-muted hover:text-ink transition hidden md:flex items-center space-x-1.5 px-2 py-1.5 rounded-lg hover:bg-canvas">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Help Center</span>
        </a>

        <!-- Notification Bell with Count Badge -->
        <livewire:shared.notification-bell />

        <!-- User Avatar & Dropdown -->
        <x-ui.dropdown align="right" width="56">
            <x-slot name="trigger">
                <button class="flex items-center space-x-2 text-left focus:outline-none p-1 rounded-lg hover:bg-canvas transition cursor-pointer">
                    <div class="h-8 w-8 rounded-full bg-accent text-white flex items-center justify-center text-xs font-bold ring-2 ring-border flex-shrink-0">
                        {{ strtoupper(substr(auth()->user()->name ?? 'User', 0, 2)) }}
                    </div>
                    <span class="text-xs font-semibold text-ink hidden sm:flex items-center space-x-1">
                        <span class="max-w-[120px] truncate">{{ auth()->user()->name ?? 'Account User' }}</span>
                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </button>
            </x-slot>

            <x-slot name="content">
                <div class="px-4 py-2.5 border-b border-border bg-canvas/40">
                    <div class="text-xs font-bold text-ink truncate">{{ auth()->user()->name ?? 'User' }}</div>
                    <div class="text-[11px] text-muted truncate">{{ auth()->user()->email ?? 'user@leadpanther.com' }}</div>
                </div>
                
                <div class="py-1">
                    <a href="{{ route('profile') }}" wire:navigate class="flex items-center px-4 py-2 text-xs font-medium text-ink hover:bg-canvas transition">
                        Profile Settings
                    </a>
                    <a href="{{ route('settings.organization') }}" wire:navigate class="flex items-center px-4 py-2 text-xs font-medium text-ink hover:bg-canvas transition">
                        Settings
                    </a>
                </div>

                <div class="border-t border-border my-1"></div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center px-4 py-2 text-xs font-medium text-danger hover:bg-canvas transition cursor-pointer">
                        Log out
                    </button>
                </form>
            </x-slot>
        </x-ui.dropdown>
    </div>
</header>
@endpersist
