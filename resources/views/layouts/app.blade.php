<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden max-w-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Lead Panther CRM') }}</title>

        <!-- Google Fonts for Theme Customization -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

        @php
            $currentTheme = \App\Support\ThemeService::getUserTheme();
        @endphp

        <style id="theme-dynamic-styles">
            :root {
                --theme-primary: {{ $currentTheme['theme_primary_color'] }};
                --theme-secondary: {{ $currentTheme['theme_secondary_color'] }};
                --theme-accent: {{ $currentTheme['theme_accent_color'] }};
                --theme-sidebar-bg: {{ $currentTheme['theme_sidebar_bg'] }};
                --theme-sidebar-text: {{ $currentTheme['theme_sidebar_text'] }};
                --theme-active-menu-color: {{ $currentTheme['theme_active_menu_color'] }};
                --theme-active-menu-bg: {{ $currentTheme['theme_active_menu_bg'] }};
                --theme-header-bg: {{ $currentTheme['theme_header_bg'] }};
                --theme-header-text: {{ $currentTheme['theme_header_text'] }};
                --theme-page-bg: {{ $currentTheme['theme_page_bg'] }};
                --theme-card-bg: {{ $currentTheme['theme_card_bg'] }};
                --theme-border-color: {{ $currentTheme['theme_border_color'] }};
                --theme-font-family: {{ $currentTheme['theme_font_family'] }};
                --theme-font-size: {{ $currentTheme['theme_font_size'] }};
                --theme-border-radius: {{ $currentTheme['theme_border_radius'] }};
            }

            body {
                background-color: var(--theme-page-bg) !important;
                font-family: var(--theme-font-family) !important;
                font-size: var(--theme-font-size) !important;
            }

            #sidebar-main {
                background-color: var(--theme-sidebar-bg) !important;
                border-color: var(--theme-border-color) !important;
            }

            #topbar-main {
                background-color: var(--theme-header-bg) !important;
                border-color: var(--theme-border-color) !important;
            }

            .rounded-card, .rounded-xl {
                border-radius: var(--theme-border-radius) !important;
            }
        </style>

        <style>
            [x-cloak] { display: none !important; }
            html, body {
                overflow-x: hidden !important;
                max-width: 100vw !important;
                width: 100% !important;
            }
            .no-scrollbar, .sidebar-scroll, #sidebar-main, #sidebar-main * {
                scrollbar-width: none !important;
                -ms-overflow-style: none !important;
            }
            .no-scrollbar::-webkit-scrollbar, .sidebar-scroll::-webkit-scrollbar, #sidebar-main::-webkit-scrollbar, #sidebar-main *::-webkit-scrollbar {
                display: none !important;
                width: 0 !important;
                height: 0 !important;
                background: transparent !important;
            }
        </style>

        <!-- Chart.js & Safe Chart Initialization Engine -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            window.__leadPantherCharts = window.__leadPantherCharts || new Map();

            window.initSafeChart = function(canvasOrId, config, callback) {
                if (typeof window.Chart === 'undefined') {
                    setTimeout(() => window.initSafeChart(canvasOrId, config, callback), 50);
                    return null;
                }

                const getCanvas = () => {
                    if (typeof canvasOrId === 'string') {
                        return document.getElementById(canvasOrId);
                    }
                    return canvasOrId;
                };

                const canvas = getCanvas();
                if (!canvas) {
                    return null;
                }

                if (!document.body.contains(canvas)) {
                    return null;
                }

                // If canvas or its parent is currently hidden (e.g. in inactive tab or modal)
                const isHidden = canvas.offsetParent === null && window.getComputedStyle(canvas).display === 'none';
                if (isHidden) {
                    if (window.IntersectionObserver) {
                        const observer = new IntersectionObserver((entries, obs) => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting && canvas.offsetParent !== null) {
                                    obs.disconnect();
                                    window.initSafeChart(canvas, config, callback);
                                }
                            });
                        });
                        observer.observe(canvas);
                    }
                    return null;
                }

                // Destroy any existing chart instance on this canvas
                try {
                    if (canvas.__chartInstance && typeof canvas.__chartInstance.destroy === 'function') {
                        canvas.__chartInstance.destroy();
                        canvas.__chartInstance = null;
                    }
                    if (typeof window.Chart.getChart === 'function') {
                        const existing = window.Chart.getChart(canvas);
                        if (existing && typeof existing.destroy === 'function') {
                            existing.destroy();
                        }
                    }
                } catch (e) {
                    console.warn('SafeChart cleanup error:', e);
                }

                let ctx = null;
                try {
                    ctx = canvas.getContext('2d');
                } catch (e) {
                    console.warn('SafeChart 2D context error:', e);
                    return null;
                }

                if (!ctx) {
                    return null;
                }

                try {
                    const chartInstance = new window.Chart(ctx, config);
                    canvas.__chartInstance = chartInstance;
                    if (typeof callback === 'function') {
                        callback(chartInstance);
                    }
                    return chartInstance;
                } catch (err) {
                    console.error('SafeChart initialization error:', err);
                    return null;
                }
            };

            // Global navigation and unmount cleanup
            document.addEventListener('livewire:navigating', () => {
                document.querySelectorAll('canvas').forEach(c => {
                    try {
                        if (c.__chartInstance) {
                            c.__chartInstance.destroy();
                            c.__chartInstance = null;
                        }
                        if (window.Chart && typeof window.Chart.getChart === 'function') {
                            const instance = window.Chart.getChart(c);
                            if (instance) instance.destroy();
                        }
                    } catch (e) {}
                });
            });
        </script>

        <!-- Scripts & Styles -->
        <x-ui.vite-assets />
        @livewireStyles
    </head>
    <body 
        x-data="{ 
            sidebarOpen: false,
            sidebarCollapsed: JSON.parse(localStorage.getItem('leadpanther_sidebar_collapsed') || 'false'),
            sidebarActiveStyle: localStorage.getItem('leadpanther_sidebar_active_style') || 'highlighted',
            sidebarTooltip: {
                show: false,
                text: '',
                top: 0
            },
            toggleSidebar() {
                this.sidebarCollapsed = !this.sidebarCollapsed;
                localStorage.setItem('leadpanther_sidebar_collapsed', JSON.stringify(this.sidebarCollapsed));
                this.hideSidebarTooltip();
            },
            setSidebarActiveStyle(style) {
                this.sidebarActiveStyle = style;
                localStorage.setItem('leadpanther_sidebar_active_style', style);
                window.dispatchEvent(new CustomEvent('sidebar-style-changed', { detail: style }));
            },
            showSidebarTooltip(e, text) {
                if (!this.sidebarCollapsed) return;
                const r = e.currentTarget.getBoundingClientRect();
                this.sidebarTooltip = {
                    show: true,
                    text: text,
                    top: Math.round(r.top + (r.height / 2))
                };
            },
            hideSidebarTooltip() {
                this.sidebarTooltip.show = false;
            }
        }" 
        x-bind:class="{ 'overflow-hidden': sidebarOpen }"
        x-on:popstate.window="sidebarOpen = false; hideSidebarTooltip()"
        x-on:livewire:navigated.window="sidebarOpen = false; hideSidebarTooltip()"
        class="font-sans antialiased bg-canvas text-ink selection:bg-accent selection:text-white min-h-screen overflow-x-hidden w-full max-w-full"
    >
        <!-- Mobile Sidebar Backdrop -->
        <div 
            x-show="sidebarOpen" 
            x-transition:enter="transition-opacity ease-linear duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="sidebarOpen = false" 
            class="fixed inset-0 bg-black/40 z-40 md:hidden" 
            style="display: none;"
        ></div>

        <!-- Sidebar Component -->
        <x-ui.sidebar />

        <!-- Global Floating Sidebar Tooltip in Collapsed Mode -->
        <div 
            x-cloak
            x-show="sidebarCollapsed && sidebarTooltip.show"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-x-1 scale-95"
            x-transition:enter-end="opacity-100 translate-x-0 scale-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-x-0 scale-100"
            x-transition:leave-end="opacity-0 translate-x-1 scale-95"
            :style="`top: ${sidebarTooltip.top}px; left: 72px;`"
            class="fixed -translate-y-1/2 z-[9999] pointer-events-none px-3 py-1.5 bg-ink text-white text-xs font-semibold rounded-lg shadow-xl border border-neutral-700/60 whitespace-nowrap flex items-center select-none"
            style="display: none;"
        >
            <!-- Tooltip Pointer Arrow -->
            <span class="absolute -left-1 top-1/2 -translate-y-1/2 w-2 h-2 bg-ink border-l border-b border-neutral-700/60 rotate-45"></span>
            <span class="relative z-10" x-text="sidebarTooltip.text"></span>
        </div>

        <!-- Main Content Wrapper -->
        <div 
            :class="{
                'md:ml-16': sidebarCollapsed,
                'md:ml-60': !sidebarCollapsed
            }"
            class="min-h-screen flex flex-col flex-1 min-w-0 ml-0 max-w-full transition-all duration-300 ease-in-out relative"
        >
            <!-- Topbar Component -->
            <x-ui.topbar />

            <!-- Main Canvas Content -->
            <main class="flex-1 p-4 sm:p-6 space-y-6 overflow-x-hidden min-w-0 max-w-full">
                @if (isset($header))
                    <header>
                        {{ $header }}
                    </header>
                @endif

                {{ $slot }}
            </main>
        </div>

        <!-- Global Toast Container -->
        <x-ui.toast />

        <!-- Theme Settings Offcanvas Customizer -->
        <x-ui.theme-customizer />

        <!-- Global Reusable Confirmation Modal -->
        <x-ui.confirmation-modal />

        @livewireScripts
    </body>
</html>
