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
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
    class="fixed left-0 top-0 h-screen w-64 md:w-16 lg:w-60 z-50 bg-surface border-r border-border flex flex-col justify-between p-4 md:px-2 lg:p-4 overflow-hidden shadow-sm transition-transform md:transition-all duration-200 ease-in-out"
>
    <!-- Logo + App Name Top (Fixed Header) -->
    <div class="flex-shrink-0 flex items-center space-x-3 px-3 md:px-0 lg:px-3 py-3 mb-2 md:justify-center lg:justify-start">
        <div class="h-9 w-9 rounded-xl bg-ink text-white flex items-center justify-center font-bold text-lg shadow-sm flex-shrink-0">
            LP
        </div>
        <span class="text-xl font-bold tracking-tight text-ink inline md:hidden lg:inline">Lead Panther</span>
    </div>

    <!-- Scrollable Nav Container -->
    <div class="flex-1 overflow-y-auto sidebar-scroll min-h-0 space-y-6 pr-1 py-1">
        <nav class="space-y-6">
            <!-- Primary Nav -->
            <div class="space-y-1">
                @foreach($primaryItems as $item)
                    <a href="{{ $item['url'] }}" wire:navigate.hover title="{{ $item['label'] }}"
                       data-nav-route="{{ $item['data_nav_route'] }}"
                       data-nav-exact="{{ $item['data_nav_exact'] }}"
                       @if(isset($item['data_nav_root'])) data-nav-root="true" @endif
                       class="flex items-center space-x-3 md:space-x-0 lg:space-x-3 px-3 md:px-0 lg:px-3 py-2 text-sm font-medium rounded-lg text-muted hover:text-ink hover:bg-canvas transition md:justify-center lg:justify-start">
                        {!! $item['icon'] !!}
                        <span class="inline md:hidden lg:inline">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>

            <!-- Secondary Nav (DATABASE) -->
            @if(!empty($databaseItems))
                <div>
                    <div class="px-3 mb-2 text-[10px] font-bold tracking-wider text-muted uppercase inline md:hidden lg:block">
                        Database
                    </div>
                    <hr class="hidden md:block lg:hidden border-border my-2" />
                    <div class="space-y-1">
                        @foreach($databaseItems as $item)
                            <a href="{{ $item['url'] }}" wire:navigate title="{{ $item['label'] }}"
                               data-nav-route="{{ $item['data_nav_route'] }}"
                               data-nav-exact="{{ $item['data_nav_exact'] }}"
                               class="flex items-center space-x-3 md:space-x-0 lg:space-x-3 px-3 md:px-0 lg:px-3 py-2 text-sm font-medium rounded-lg text-muted hover:text-ink hover:bg-canvas transition md:justify-center lg:justify-start">
                                {!! $item['icon'] !!}
                                <span class="inline md:hidden lg:inline">{{ $item['label'] }}</span>
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
            const rawPath = window.location.pathname.replace(/\/$/, '') || '/';
            
            document.querySelectorAll('[data-nav-route]').forEach(el => {
                const route = (el.dataset.navRoute || '').replace(/^\//, '').replace(/\/$/, '');
                const exact = el.dataset.navExact === 'true';
                const matchRoot = el.dataset.navRoot === 'true';
                const target = '/' + route;
                
                let isActive = false;
                
                if (matchRoot && (rawPath === '/' || rawPath === '/dashboard' || rawPath.endsWith('/dashboard'))) {
                    isActive = true;
                } else if (exact) {
                    isActive = (rawPath === target || rawPath === target + '/kanban');
                } else {
                    isActive = (rawPath === target || rawPath.startsWith(target + '/'));
                }
                
                if (isActive) {
                    el.classList.add('bg-ink', 'text-white');
                    el.classList.remove('text-muted', 'hover:bg-canvas', 'hover:text-ink');
                } else {
                    el.classList.remove('bg-ink', 'text-white');
                    el.classList.add('text-muted');
                }
            });
        }

        document.addEventListener('livewire:navigated', updateSidebarActiveNav);
        document.addEventListener('DOMContentLoaded', updateSidebarActiveNav);
        window.addEventListener('popstate', updateSidebarActiveNav);
        updateSidebarActiveNav();
    })();
</script>
@endpersist
