<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Lead Panther CRM') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body 
        x-data="{ sidebarOpen: false }" 
        x-bind:class="{ 'overflow-hidden': sidebarOpen }"
        x-on:popstate.window="sidebarOpen = false"
        x-on:livewire:navigated.window="sidebarOpen = false"
        class="font-sans antialiased bg-canvas text-ink selection:bg-accent selection:text-white min-h-screen"
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
        <div class="min-h-screen flex flex-col flex-1 min-w-0 ml-0 md:ml-16 lg:ml-60 transition-all duration-200 ease-in-out">
            <!-- Topbar Component -->
            <x-ui.topbar />

            <!-- Main Canvas Content -->
            <main class="flex-1 p-4 sm:p-6 space-y-6">
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
