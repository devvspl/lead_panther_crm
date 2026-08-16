<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>404 Page Not Found - {{ config('app.name', 'Lead Panther CRM') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-canvas text-ink font-sans antialiased min-h-screen flex items-center justify-center p-4">
        @php
            $dashboardUrl = route('dashboard');
            if (auth()->check()) {
                $user = auth()->user();
                if ($user->hasRole('Super Admin')) {
                    $dashboardUrl = route('admin.dashboard');
                } elseif ($user->hasRole('Builder')) {
                    $dashboardUrl = route('builder.dashboard');
                } elseif ($user->hasRole('Channel Partner')) {
                    $dashboardUrl = route('partner.dashboard');
                } elseif ($user->hasRole('Sales Executive')) {
                    $dashboardUrl = route('sales.dashboard');
                }
            }
        @endphp

        <div class="max-w-md w-full bg-surface border border-border rounded-card p-8 shadow-xl text-center space-y-5">
            <div class="inline-flex items-center justify-center h-16 w-16 rounded-full bg-blue-50 text-blue-600 border border-blue-200 mx-auto">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>

            <div>
                <span class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-pill bg-blue-50 text-blue-700 border border-blue-200">HTTP 404</span>
                <h1 class="text-xl font-bold tracking-tight text-ink mt-2">Page Not Found</h1>
                <p class="text-xs text-muted mt-1">The requested page or record could not be found. It may have been moved or deleted.</p>
            </div>

            <div class="pt-2">
                <a href="{{ $dashboardUrl }}" class="inline-flex items-center px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary-hover transition shadow-sm">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
    </body>
</html>
