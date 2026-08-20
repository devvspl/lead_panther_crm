<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden max-w-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Lead Panther CRM') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Chart.js & Flatpickr -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

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

        <!-- Scripts & Styles -->
        <x-ui.vite-assets />
        @livewireStyles
    </head>
    <body 
        x-data="{ 
            sidebarOpen: false,
            sidebarCollapsed: JSON.parse(localStorage.getItem('leadpanther_sidebar_collapsed') || 'false'),
            toggleSidebar() {
                this.sidebarCollapsed = !this.sidebarCollapsed;
                localStorage.setItem('leadpanther_sidebar_collapsed', JSON.stringify(this.sidebarCollapsed));
            }
        }" 
        x-bind:class="{ 'overflow-hidden': sidebarOpen }"
        x-on:popstate.window="sidebarOpen = false"
        x-on:livewire:navigated.window="sidebarOpen = false"
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

        @livewireScripts
    </body>
</html>
