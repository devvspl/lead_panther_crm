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

        <!-- Scripts & Styles -->
        @if(file_exists(public_path('build/manifest.json')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = {
                    theme: {
                        extend: {
                            colors: {
                                canvas: '#F9FAFB',
                                surface: '#FFFFFF',
                                ink: '#111827',
                                muted: '#6B7280',
                                border: '#E5E7EB',
                                accent: '#111827',
                                primary: '#4F46E5',
                            },
                            borderRadius: {
                                'card': '0.75rem',
                                'pill': '9999px',
                            }
                        }
                    }
                }
            </script>
        @endif
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-canvas text-ink selection:bg-accent selection:text-white">
        <div class="min-h-screen flex flex-col justify-center items-center py-12 px-4 sm:px-6 lg:px-8">
            {{ $slot }}
        </div>
        @livewireScripts
    </body>
</html>
