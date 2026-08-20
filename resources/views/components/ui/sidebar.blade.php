@php
    $user = auth()->user();
    $navSections = \App\Support\NavigationConfig::getNavItemsForUser($user);
    $primaryItems = $navSections['primary'] ?? [];
    $databaseItems = $navSections['database'] ?? [];
@endphp

@persist('sidebar')
<aside 
    id="sidebar-main"
    wire:persist="sidebar"
    :class="{
        'translate-x-0': sidebarOpen,
        '-translate-x-full md:translate-x-0': !sidebarOpen,
        'w-64 md:w-16 lg:w-16 p-2.5': sidebarCollapsed,
        'w-64 md:w-60 lg:w-60 p-4': !sidebarCollapsed
    }"
    class="fixed left-0 top-0 h-screen z-50 bg-surface border-r border-border flex flex-col justify-between overflow-x-hidden overflow-y-hidden shadow-sm transition-all duration-300 ease-in-out select-none no-scrollbar"
>
    <!-- Logo + App Name Top (Header) -->
    <div 
        :class="sidebarCollapsed ? 'justify-center px-0' : 'justify-start px-2'"
        class="flex-shrink-0 flex items-center py-2 mb-2 min-h-[44px] transition-all duration-300"
    >
        <!-- Logo + App Name -->
        <div class="flex items-center space-x-3 overflow-hidden">
            <div 
                x-on:click="if (sidebarCollapsed) toggleSidebar()"
                :class="sidebarCollapsed ? 'cursor-pointer hover:scale-105 transition-transform' : ''"
                :title="sidebarCollapsed ? 'Click to expand sidebar' : 'Lead Panther CRM'"
                class="h-9 w-9 rounded-xl bg-ink text-white flex items-center justify-center font-bold text-sm shadow-sm flex-shrink-0 select-none"
            >
                LP
            </div>
            <span 
                x-show="!sidebarCollapsed"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-x-2"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 -translate-x-2"
                class="text-lg font-bold tracking-tight text-ink whitespace-nowrap overflow-hidden select-none"
            >
                Lead Panther
            </span>
        </div>
    </div>

    <!-- Scrollable Nav Container -->
    <div class="flex-1 overflow-y-auto overflow-x-hidden sidebar-scroll no-scrollbar min-h-0 space-y-4 py-1">
        <nav class="space-y-4">
            <!-- Primary Nav -->
            <div class="space-y-1">
                @foreach($primaryItems as $item)
                    <a href="{{ $item['url'] }}" wire:navigate.hover
                       data-nav-route="{{ $item['data_nav_route'] }}"
                       data-nav-exact="{{ $item['data_nav_exact'] }}"
                       @if(isset($item['data_nav_root'])) data-nav-root="true" @endif
                       :class="sidebarCollapsed ? 'justify-center px-0 w-10 h-10 mx-auto' : 'px-3 py-2 space-x-3 w-full'"
                       class="group relative flex items-center text-sm font-medium rounded-lg text-muted hover:text-ink hover:bg-canvas transition"
                       title="{{ $item['label'] }}"
                    >
                        <div class="flex-shrink-0 flex items-center justify-center">
                            {!! $item['icon'] !!}
                        </div>
                        <span 
                            x-show="!sidebarCollapsed"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-x-2"
                            x-transition:enter-end="opacity-100 translate-x-0"
                            class="whitespace-nowrap overflow-hidden truncate"
                        >
                            {{ $item['label'] }}
                        </span>

                        <!-- Floating Tooltip in Collapsed Mode -->
                        <div 
                            x-show="sidebarCollapsed"
                            class="fixed left-16 ml-3 px-2.5 py-1.5 bg-ink text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap pointer-events-none opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150 z-50 flex items-center border border-neutral-700"
                            style="display: none;"
                        >
                            <span class="absolute -left-1 top-1/2 -translate-y-1/2 w-2 h-2 bg-ink rotate-45 border-l border-b border-neutral-700"></span>
                            <span>{{ $item['label'] }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            <!-- Secondary Nav (DATABASE) -->
            @if(!empty($databaseItems))
                <div class="pt-1">
                    <div 
                        x-show="!sidebarCollapsed"
                        class="px-3 mb-2 text-[10px] font-bold tracking-wider text-muted uppercase"
                    >
                        Database
                    </div>
                    <div 
                        x-show="sidebarCollapsed"
                        class="my-2 border-t border-border mx-2"
                    ></div>
                    <div class="space-y-1">
                        @foreach($databaseItems as $item)
                            <a href="{{ $item['url'] }}" wire:navigate
                               data-nav-route="{{ $item['data_nav_route'] }}"
                               data-nav-exact="{{ $item['data_nav_exact'] }}"
                               :class="sidebarCollapsed ? 'justify-center px-0 w-10 h-10 mx-auto' : 'px-3 py-2 space-x-3 w-full'"
                               class="group relative flex items-center text-sm font-medium rounded-lg text-muted hover:text-ink hover:bg-canvas transition"
                               title="{{ $item['label'] }}"
                            >
                                <div class="flex-shrink-0 flex items-center justify-center">
                                    {!! $item['icon'] !!}
                                </div>
                                <span 
                                    x-show="!sidebarCollapsed"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 -translate-x-2"
                                    x-transition:enter-end="opacity-100 translate-x-0"
                                    class="whitespace-nowrap overflow-hidden truncate"
                                >
                                    {{ $item['label'] }}
                                </span>

                                <!-- Floating Tooltip in Collapsed Mode -->
                                <div 
                                    x-show="sidebarCollapsed"
                                    class="fixed left-16 ml-3 px-2.5 py-1.5 bg-ink text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap pointer-events-none opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150 z-50 flex items-center border border-neutral-700"
                                    style="display: none;"
                                >
                                    <span class="absolute -left-1 top-1/2 -translate-y-1/2 w-2 h-2 bg-ink rotate-45 border-l border-b border-neutral-700"></span>
                                    <span>{{ $item['label'] }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </nav>
    </div>
</aside>
<script>
    (function() {
        function updateSidebarActiveNav() {
            const rawPath = (window.location.pathname.replace(/\/$/, '') || '/').toLowerCase();
            const navElements = Array.from(document.querySelectorAll('[data-nav-route]'));
            const activeStyle = localStorage.getItem('leadpanther_sidebar_active_style') || 'highlighted';
            
            let bestElement = null;
            let bestMatchLength = -1;
            
            navElements.forEach(el => {
                const route = (el.dataset.navRoute || '').replace(/^\//, '').replace(/\/$/, '').toLowerCase();
                const matchRoot = el.dataset.navRoot === 'true';
                const target = '/' + route;
                
                let isMatch = false;
                let matchLength = 0;
                
                if (matchRoot && (rawPath === '/' || rawPath === '/dashboard' || rawPath.endsWith('/dashboard'))) {
                    isMatch = true;
                    matchLength = 100;
                } else if (rawPath === target) {
                    isMatch = true;
                    matchLength = target.length + 1000;
                } else if (target !== '/' && (rawPath.startsWith(target + '/') || rawPath.startsWith(target + '-'))) {
                    isMatch = true;
                    matchLength = target.length;
                }
                
                if (isMatch && matchLength > bestMatchLength) {
                    bestMatchLength = matchLength;
                    bestElement = el;
                }
            });
            
            const allStyletypes = [
                'bg-ink', 'text-white', 
                'bg-canvas', 'text-ink', 'font-semibold', 'font-bold', 'font-medium',
                'border', 'border-border/60', 'shadow-2xs', 'bg-transparent'
            ];
            
            navElements.forEach(el => {
                el.classList.remove(...allStyletypes);
                el.style.color = '';
                el.style.backgroundColor = '';
                el.style.borderColor = '';

                if (el === bestElement) {
                    el.classList.remove('text-muted');
                    if (activeStyle === 'text-only' || activeStyle === 'text_only') {
                        // Option 1: Text Only - Solid active color text & icon, transparent background
                        el.classList.add('font-bold', 'bg-transparent');
                        el.style.color = 'var(--theme-active-menu-color, #0A0A0A)';
                        el.style.backgroundColor = 'transparent';
                    } else {
                        // Option 2: Highlighted Background - Custom active background with custom active color
                        el.classList.add('font-semibold', 'border', 'shadow-2xs');
                        el.style.color = 'var(--theme-active-menu-color, #0A0A0A)';
                        el.style.backgroundColor = 'var(--theme-active-menu-bg, #F5F5F5)';
                        el.style.borderColor = 'var(--theme-border-color, #E5E7EB)';
                    }
                } else {
                    // Inactive items: custom sidebar text color with hover
                    el.classList.add('font-medium', 'hover:bg-canvas');
                    el.style.color = 'var(--theme-sidebar-text, #6B7280)';
                }
            });
        }

        document.addEventListener('livewire:navigated', updateSidebarActiveNav);
        document.addEventListener('DOMContentLoaded', updateSidebarActiveNav);
        window.addEventListener('popstate', updateSidebarActiveNav);
        window.addEventListener('sidebar-style-changed', updateSidebarActiveNav);
        updateSidebarActiveNav();
    })();
</script>
@endpersist
