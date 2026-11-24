<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Lead Panther CRM') }}</title>

        <!-- PWA Web App Manifest & Meta Tags -->
        <link rel="manifest" href="{{ asset('manifest.json') }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Lead Panther">
        <meta name="application-name" content="Lead Panther">
        <meta name="theme-color" content="#0A0A0A">
        <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192x192.png') }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts & Styles -->
        <x-ui.vite-assets />
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-canvas text-ink selection:bg-accent selection:text-white">
        <div class="min-h-screen flex flex-col justify-center items-center py-12 px-4 sm:px-6 lg:px-8">
            {{ $slot }}
        </div>
        <script src="{{ asset('pwa-install.js') }}?v={{ filemtime(public_path('pwa-install.js')) }}" data-navigate-once></script>
        @livewireScripts
    </body>
</html>
