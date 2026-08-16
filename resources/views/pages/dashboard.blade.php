<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-ink tracking-tight">Dashboard Overview</h1>
                <p class="text-xs text-muted mt-1">Live lead intelligence, SLA monitoring, and sales performance analytics</p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center space-x-1.5 px-3 py-1 bg-surface border border-border rounded-pill text-xs font-semibold text-ink">
                    <span class="w-2 h-2 rounded-full bg-success animate-pulse"></span>
                    <span>Live 30s Polling</span>
                </span>
            </div>
        </div>
    </x-slot>

    <livewire:dashboard.dashboard-shell />
</x-app-layout>
