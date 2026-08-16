<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight">
            {{ __('Analytics & Performance Reports') }}
        </h2>
    </x-slot>

    <div class="py-4 space-y-6">
        <livewire:reports.sla-dashboard />
        <livewire:reports.source-performance />
        <livewire:reports.revenue-chart />
    </div>
</x-app-layout>
